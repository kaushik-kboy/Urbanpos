<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate-index cleanup, found during the QA index audit (docs/QA-CLOSURE-FINAL-PASS.md).
 *
 * Three separate migrations, each added independently over time, ended up creating
 * byte-identical indexes on `sales_bills`:
 *   - idx_sb_bill_date              (bill_date)               duplicates sales_bills_bill_date_index
 *   - idx_sb_branch_date            (branch_id, bill_date)    duplicates sales_bills_branch_id_bill_date_index
 *   - idx_sb_customer_date          (customer_id, bill_date)  duplicates sales_bills_customer_id_bill_date_index
 *
 * This migration drops only the `idx_sb_*` copies (added in
 * 2026_09_18_132139_add_performance_indexes_to_pos_tables.php) and keeps the
 * Laravel-default-named ones, which is the exact opposite table's set of names
 * already relied on elsewhere in this migration history. `idx_sb_status`,
 * `idx_sb_branch_status` and `idx_sb_created_at` (from the same original
 * migration) are NOT duplicates of anything and are left untouched.
 *
 * `sales_bill_items.idx_sbi_item_id` was audited too and found to be a left-prefix
 * of `idx_sbi_item_bill` (item_id vs item_id+sales_bill_id) — redundant by the
 * strict definition, but deliberately NOT dropped here: it's cheaper for
 * item_id-only lookups than the composite, and the task instructions were
 * explicit not to remove it without a separately measured reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            foreach (['idx_sb_bill_date', 'idx_sb_branch_date', 'idx_sb_customer_date'] as $idx) {
                if ($this->hasIndex('sales_bills', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            if (! $this->hasIndex('sales_bills', 'idx_sb_bill_date')) {
                $table->index('bill_date', 'idx_sb_bill_date');
            }
            if (! $this->hasIndex('sales_bills', 'idx_sb_branch_date')) {
                $table->index(['branch_id', 'bill_date'], 'idx_sb_branch_date');
            }
            if (! $this->hasIndex('sales_bills', 'idx_sb_customer_date')) {
                $table->index(['customer_id', 'bill_date'], 'idx_sb_customer_date');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $conn = \Illuminate\Support\Facades\DB::getDriverName();
            if ($conn === 'sqlite') {
                $indexes = \Illuminate\Support\Facades\DB::select("PRAGMA index_list(`{$table}`)");
                return collect($indexes)->contains('name', $indexName);
            }
            $indexes = \Illuminate\Support\Facades\DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$indexName]
            );
            return count($indexes) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
