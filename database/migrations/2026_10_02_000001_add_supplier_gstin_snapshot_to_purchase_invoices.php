<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GST Purchase Summary invoice-wise export audit finding
 * (docs/GST-PURCHASE-SUMMARY-EXPORT.md): the "GST No." column has to read the
 * supplier's GSTIN, but purchase_invoices had no posting-time snapshot column
 * — only the live `suppliers.gst_no` was available. If a supplier's GSTIN is
 * corrected after an invoice is posted, a past invoice would silently show
 * today's value instead of what was true when it was posted.
 *
 * This mirrors the exact same fix already applied on the sales side (see
 * 2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents.php) —
 * same nullable-snapshot pattern, populated going forward by
 * PurchaseInvoiceController::store(). Historical rows (posted before this
 * migration) are intentionally left NULL for the same reason as the sales
 * version: there is no reliable source to backfill a past GSTIN, and guessing
 * it from the current supplier record would silently reintroduce the exact
 * bug this column exists to fix. The export falls back to live
 * suppliers.gst_no ONLY for these NULL/pre-migration rows, documented as a
 * limitation, not hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('purchase_invoices', 'supplier_gstin')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('supplier_gstin', 15)->nullable()->after('supplier_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasColumn('purchase_invoices', 'supplier_gstin')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropColumn('supplier_gstin');
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
            // "SHOW COLUMNS FROM x LIKE ?" does not support a bound placeholder
            // (MySQL/PDO error 1064) — a WHERE clause does. See the sales-side
            // migration's own comment for how this was found.
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
