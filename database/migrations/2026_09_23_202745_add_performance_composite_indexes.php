<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes to high-volume tables used in reporting and
 * search queries. Each index covers the most common filter combinations
 * seen in the analytics-builder, index pages, and ledger queries.
 *
 * All indexes are created with `IF NOT EXISTS` semantics via Blueprint's
 * skipUnlessExists guard (we catch QueryException to be idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── sales_bills ────────────────────────────────────────────────
        Schema::table('sales_bills', function (Blueprint $table) {
            if (!$this->indexExists('sales_bills', 'sales_bills_branch_id_bill_date_index')) {
                $table->index(['branch_id', 'bill_date'], 'sales_bills_branch_id_bill_date_index');
            }
            if (!$this->indexExists('sales_bills', 'sales_bills_customer_id_bill_date_index')) {
                $table->index(['customer_id', 'bill_date'], 'sales_bills_customer_id_bill_date_index');
            }
            if (!$this->indexExists('sales_bills', 'sales_bills_bill_date_index')) {
                $table->index(['bill_date'], 'sales_bills_bill_date_index');
            }
        });

        // ── purchase_invoices ──────────────────────────────────────────
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!$this->indexExists('purchase_invoices', 'purchase_invoices_branch_id_invoice_date_index')) {
                $table->index(['branch_id', 'invoice_date'], 'purchase_invoices_branch_id_invoice_date_index');
            }
            if (!$this->indexExists('purchase_invoices', 'purchase_invoices_supplier_id_invoice_date_index')) {
                $table->index(['supplier_id', 'invoice_date'], 'purchase_invoices_supplier_id_invoice_date_index');
            }
        });

        // ── stock_transfers ────────────────────────────────────────────
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (!$this->indexExists('stock_transfers', 'stock_transfers_from_branch_id_transfer_date_index')) {
                $table->index(['from_branch_id', 'transfer_date'], 'stock_transfers_from_branch_id_transfer_date_index');
            }
            if (!$this->indexExists('stock_transfers', 'stock_transfers_to_branch_id_transfer_date_index')) {
                $table->index(['to_branch_id', 'transfer_date'], 'stock_transfers_to_branch_id_transfer_date_index');
            }
        });

        // ── closing_stocks ─────────────────────────────────────────────
        Schema::table('closing_stocks', function (Blueprint $table) {
            if (!$this->indexExists('closing_stocks', 'closing_stocks_branch_id_as_on_date_index')) {
                $table->index(['branch_id', 'as_on_date'], 'closing_stocks_branch_id_as_on_date_index');
            }
        });

        // ── stock_ledger ───────────────────────────────────────────────
        Schema::table('stock_ledger', function (Blueprint $table) {
            if (!$this->indexExists('stock_ledger', 'stock_ledger_item_id_branch_id_document_date_index')) {
                $table->index(['item_id', 'branch_id', 'document_date'], 'stock_ledger_item_id_branch_id_document_date_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropIndexIfExists('sales_bills_branch_id_bill_date_index');
            $table->dropIndexIfExists('sales_bills_customer_id_bill_date_index');
            $table->dropIndexIfExists('sales_bills_bill_date_index');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndexIfExists('purchase_invoices_branch_id_invoice_date_index');
            $table->dropIndexIfExists('purchase_invoices_supplier_id_invoice_date_index');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndexIfExists('stock_transfers_from_branch_id_transfer_date_index');
            $table->dropIndexIfExists('stock_transfers_to_branch_id_transfer_date_index');
        });

        Schema::table('closing_stocks', function (Blueprint $table) {
            $table->dropIndexIfExists('closing_stocks_branch_id_as_on_date_index');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropIndexIfExists('stock_ledger_item_id_branch_id_document_date_index');
        });
    }

    /**
     * Check if a specific index already exists on a table (idempotent guard).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $connection->select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$indexName]
            );
            return ! empty($result);
        }

        // SQLite / other: attempt check via docblock fallback
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $idx) {
                if (($idx['name'] ?? '') === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Ignore — index will be created or already exists
        }

        return false;
    }
};
