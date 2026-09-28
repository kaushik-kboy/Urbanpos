<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Priority 5 (performance at 20 lakh rows). Every index here was added only after EXPLAIN + timing on the
 * QA dataset (urban_pos_qa) showed a scan/filesort it removes; see docs/PERFORMANCE-PRIORITY5.md.
 */
return new class extends Migration
{
    /** [table, index name, columns] */
    private array $indexes = [
        // Reorder report: WHERE branch_id = ? AND quantity <= ? ORDER BY quantity — range scan, no filesort (count 79->4 ms, page 89->1.3 ms).
        ['item_stocks', 'idx_is_branch_qty', ['branch_id', 'quantity']],
        // Billwise / return-summary filters: SELECT DISTINCT invoice_type became a loose index scan (350 -> 0.4 ms).
        ['sales_bills', 'idx_sb_invoice_type', ['invoice_type']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $name, $columns]) {
            if (! $this->hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $name, $columns]) {
            if (! $this->hasIndex($table, $name)) {
                continue;
            }

            // MySQL silently drops the implicit single-column index that was backing item_stocks' branch_id
            // foreign key once idx_is_branch_qty (branch_id, quantity) made it redundant (verified on a fresh
            // migrate). Dropping idx_is_branch_qty would then leave that FK without any covering index and
            // fail with error 1553, so a plain index on the FK column is restored first wherever this index's
            // leftmost column has a foreign key and no other index still covers it.
            $leftmost = $columns[0];
            if ($this->isSoleFkIndex($table, $name, $leftmost)) {
                $restoreName = "{$table}_{$leftmost}_foreign";
                if (! $this->hasIndex($table, $restoreName)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index([$leftmost], $restoreName));
                }
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }

    /**
     * True if $column has a foreign key on $table and $indexName is the only index whose leftmost column is $column.
     */
    private function isSoleFkIndex(string $table, string $indexName, string $column): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false; // sqlite doesn't enforce this the same way; nothing to restore.
        }

        $hasFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if (! $hasFk) {
            return false;
        }

        $otherLeftmostIndexes = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('SEQ_IN_INDEX', 1)
            ->where('COLUMN_NAME', $column)
            ->where('INDEX_NAME', '!=', $indexName)
            ->exists();

        return ! $otherLeftmostIndexes;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list(`{$table}`)"))->contains('name', $indexName);
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
