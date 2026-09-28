<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSTR-1 audit finding (docs/GSTR1-AUDIT.md): B2B/B2C classification currently
 * reads the customer's CURRENT gst_no live at report time, not what was true
 * when the document was actually posted. If a customer's GSTIN is added,
 * removed, or corrected after the fact, historical GSTR-1 for a past period
 * would silently reclassify old documents based on today's status.
 *
 * This adds a nullable posting-time snapshot column, following the same
 * denormalized-snapshot pattern this app already uses elsewhere on
 * `sales_bills` (e.g. the eway-bill/e-invoice fields). It is populated going
 * forward by SalesBillController::store() / SalesReturnController::store().
 *
 * Historical rows (posted before this migration) are intentionally left NULL
 * — there is no reliable, provably-authoritative source to backfill what a
 * customer's GSTIN actually was at some past posting time, and guessing it
 * from the CURRENT customer record would silently reintroduce the exact bug
 * this column exists to fix. The GSTR-1 report falls back to the live
 * customers.gst_no ONLY for these NULL/pre-migration rows, with that fallback
 * explicitly documented as a limitation, not hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('sales_bills', 'customer_gstin')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->string('customer_gstin', 15)->nullable()->after('customer_id');
            });
        }
        if (! $this->hasColumn('sales_returns', 'customer_gstin')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->string('customer_gstin', 15)->nullable()->after('customer_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasColumn('sales_bills', 'customer_gstin')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropColumn('customer_gstin');
            });
        }
        if ($this->hasColumn('sales_returns', 'customer_gstin')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->dropColumn('customer_gstin');
            });
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $conn = \Illuminate\Support\Facades\DB::getDriverName();
            if ($conn === 'sqlite') {
                $cols = \Illuminate\Support\Facades\DB::select("PRAGMA table_info(`{$table}`)");
                return collect($cols)->contains('name', $column);
            }
            // Note: "SHOW COLUMNS FROM x LIKE ?" does NOT work with a bound
            // placeholder (MySQL/PDO error 1064) — confirmed while testing this
            // migration's rollback, which silently no-op'd because of it. A
            // WHERE clause works correctly with a placeholder; LIKE directly
            // after SHOW COLUMNS does not.
            $cols = \Illuminate\Support\Facades\DB::select(
                "SHOW COLUMNS FROM `{$table}` WHERE Field = ?",
                [$column]
            );
            return count($cols) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
