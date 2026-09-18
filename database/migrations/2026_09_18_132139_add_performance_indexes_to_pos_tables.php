<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Index Migration — UrbanPOS Scale Readiness
 *
 * Adds missing composite and single-column indexes to critical POS tables
 * based on real query patterns:
 *   - Branch + date range filters (daily/monthly reports)
 *   - Status filters (Posted/Draft/Cancelled)
 *   - Item-wise sales history
 *   - Stock ledger time-range queries
 *   - Customer billing history
 *
 * All indexes use IF NOT EXISTS logic (via hasIndex check) to be safe on re-run.
 * Safe for both MySQL (production) and SQLite (testing).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // sales_bills — highest traffic table
        // ============================================================
        Schema::table('sales_bills', function (Blueprint $table) {
            // Bill date range queries (daily/weekly/monthly sales reports)
            if (!$this->hasIndex('sales_bills', 'idx_sb_bill_date')) {
                $table->index('bill_date', 'idx_sb_bill_date');
            }
            // Status filter (Posted bills listing)
            if (!$this->hasIndex('sales_bills', 'idx_sb_status')) {
                $table->index('status', 'idx_sb_status');
            }
            // Branch + date composite (branch-wise daily report — most common)
            if (!$this->hasIndex('sales_bills', 'idx_sb_branch_date')) {
                $table->index(['branch_id', 'bill_date'], 'idx_sb_branch_date');
            }
            // Branch + status composite (e.g. list all Posted bills for branch)
            if (!$this->hasIndex('sales_bills', 'idx_sb_branch_status')) {
                $table->index(['branch_id', 'status'], 'idx_sb_branch_status');
            }
            // Customer + date (customer billing history)
            if (!$this->hasIndex('sales_bills', 'idx_sb_customer_date')) {
                $table->index(['customer_id', 'bill_date'], 'idx_sb_customer_date');
            }
            // Created at (latest bills, dashboard queries)
            if (!$this->hasIndex('sales_bills', 'idx_sb_created_at')) {
                $table->index('created_at', 'idx_sb_created_at');
            }
        });

        // ============================================================
        // sales_bill_items — joins on item_id for item-wise reports
        // ============================================================
        Schema::table('sales_bill_items', function (Blueprint $table) {
            // Item-wise sales quantity/amount aggregation
            if (!$this->hasIndex('sales_bill_items', 'idx_sbi_item_bill')) {
                $table->index(['item_id', 'sales_bill_id'], 'idx_sbi_item_bill');
            }
        });

        // ============================================================
        // stock_ledger — can grow to millions of rows
        // ============================================================
        Schema::table('stock_ledger', function (Blueprint $table) {
            // Date range queries on stock movement
            if (!$this->hasIndex('stock_ledger', 'idx_sl_created_at')) {
                $table->index('created_at', 'idx_sl_created_at');
            }
            // Movement type filter (SALE, PURCHASE_RECEIPT, TRANSFER_IN etc.)
            if (!$this->hasIndex('stock_ledger', 'idx_sl_movement_type')) {
                $table->index('movement_type', 'idx_sl_movement_type');
            }
            // Item + date range (item stock history report)
            if (!$this->hasIndex('stock_ledger', 'idx_sl_item_date')) {
                $table->index(['item_id', 'created_at'], 'idx_sl_item_date');
            }
            // Branch + date range (branch stock movement report)
            if (!$this->hasIndex('stock_ledger', 'idx_sl_branch_date')) {
                $table->index(['branch_id', 'created_at'], 'idx_sl_branch_date');
            }
        });

        // ============================================================
        // purchase_bills — vendor reports and GRN tracking
        // ============================================================
        if (Schema::hasTable('purchase_bills')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                if (!$this->hasIndex('purchase_bills', 'idx_pb_bill_date')) {
                    $table->index('bill_date', 'idx_pb_bill_date');
                }
                if (Schema::hasColumn('purchase_bills', 'branch_id') &&
                    !$this->hasIndex('purchase_bills', 'idx_pb_branch_date')) {
                    $table->index(['branch_id', 'bill_date'], 'idx_pb_branch_date');
                }
                if (Schema::hasColumn('purchase_bills', 'status') &&
                    !$this->hasIndex('purchase_bills', 'idx_pb_status')) {
                    $table->index('status', 'idx_pb_status');
                }
                if (!$this->hasIndex('purchase_bills', 'idx_pb_created_at')) {
                    $table->index('created_at', 'idx_pb_created_at');
                }
            });
        }

        // ============================================================
        // customers — search and loyalty queries
        // ============================================================
        Schema::table('customers', function (Blueprint $table) {
            // Customer name search (LIKE queries)
            if (!$this->hasIndex('customers', 'idx_cust_name')) {
                $table->index('name', 'idx_cust_name');
            }
            // Phone lookup (most common customer search)
            if (Schema::hasColumn('customers', 'phone') &&
                !$this->hasIndex('customers', 'idx_cust_phone')) {
                $table->index('phone', 'idx_cust_phone');
            }
        });

        // ============================================================
        // items — search performance
        // ============================================================
        Schema::table('items', function (Blueprint $table) {
            // Active/inactive item filter
            if (Schema::hasColumn('items', 'is_active') &&
                !$this->hasIndex('items', 'idx_items_active')) {
                $table->index('is_active', 'idx_items_active');
            }
            // Item name search
            if (!$this->hasIndex('items', 'idx_items_name')) {
                $table->index('name', 'idx_items_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            foreach (['idx_sb_bill_date','idx_sb_status','idx_sb_branch_date','idx_sb_branch_status','idx_sb_customer_date','idx_sb_created_at'] as $idx) {
                if ($this->hasIndex('sales_bills', $idx)) $table->dropIndex($idx);
            }
        });

        Schema::table('sales_bill_items', function (Blueprint $table) {
            if ($this->hasIndex('sales_bill_items', 'idx_sbi_item_bill')) $table->dropIndex('idx_sbi_item_bill');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            foreach (['idx_sl_created_at','idx_sl_movement_type','idx_sl_item_date','idx_sl_branch_date'] as $idx) {
                if ($this->hasIndex('stock_ledger', $idx)) $table->dropIndex($idx);
            }
        });

        if (Schema::hasTable('purchase_bills')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                foreach (['idx_pb_bill_date','idx_pb_branch_date','idx_pb_status','idx_pb_created_at'] as $idx) {
                    if ($this->hasIndex('purchase_bills', $idx)) $table->dropIndex($idx);
                }
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach (['idx_cust_name','idx_cust_phone'] as $idx) {
                if ($this->hasIndex('customers', $idx)) $table->dropIndex($idx);
            }
        });

        Schema::table('items', function (Blueprint $table) {
            foreach (['idx_items_active','idx_items_name'] as $idx) {
                if ($this->hasIndex('items', $idx)) $table->dropIndex($idx);
            }
        });
    }

    /**
     * Check if an index exists on a table (cross-DB compatible).
     */
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
