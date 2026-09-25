<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // items: composite (status, name) — filters by active items then sorts by name
        if (! $this->hasIndex('items', 'idx_items_status_name')) {
            Schema::table('items', function (Blueprint $table) {
                $table->index(['status', 'name'], 'idx_items_status_name');
            });
        }

        // purchase_invoice_items: (item_id, exp_date) — used in batch lookup subquery in lookupItem
        if (! $this->hasIndex('purchase_invoice_items', 'idx_pii_item_expdate')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->index(['item_id', 'exp_date'], 'idx_pii_item_expdate');
            });
        }

        // customers: (mobile) — used in customerSearch LIKE + (status, name)
        if (! $this->hasIndex('customers', 'idx_cust_mobile')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['mobile'], 'idx_cust_mobile');
            });
        }

        if (! $this->hasIndex('customers', 'idx_cust_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['customer_code'], 'idx_cust_code');
            });
        }

        // customers: (status, name) — used in all customer dropdown queries
        if (! $this->hasIndex('customers', 'idx_cust_status_name')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['status', 'name'], 'idx_cust_status_name');
            });
        }

        // stock_ledger: (item_id, branch_id) — used in batch reconciliation in lookupItem
        if (! $this->hasIndex('stock_ledger', 'idx_sl_item_branch')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->index(['item_id', 'branch_id'], 'idx_sl_item_branch');
            });
        }

        // sales_bill_items: (item_id) standalone for return quantity checks
        if (! $this->hasIndex('sales_bill_items', 'idx_sbi_item_id')) {
            Schema::table('sales_bill_items', function (Blueprint $table) {
                $table->index(['item_id'], 'idx_sbi_item_id');
            });
        }

        // sales_return_items: (item_id) for returnable qty lookups
        if (! $this->hasIndex('sales_return_items', 'idx_sri_item_bill')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->index(['item_id', 'sales_return_id'], 'idx_sri_item_bill');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_status_name');
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropIndex('idx_pii_item_expdate');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_cust_mobile');
            $table->dropIndex('idx_cust_code');
            $table->dropIndex('idx_cust_status_name');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_sl_item_branch');
        });

        Schema::table('sales_bill_items', function (Blueprint $table) {
            $table->dropIndex('idx_sbi_item_id');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropIndex('idx_sri_item_bill');
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
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?
                 LIMIT 1",
                [$table, $indexName]
            );
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
