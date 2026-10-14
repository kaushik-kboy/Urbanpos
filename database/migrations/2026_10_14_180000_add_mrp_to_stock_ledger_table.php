<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_ledger') && !Schema::hasColumn('stock_ledger', 'mrp')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->decimal('mrp', 14, 2)->nullable()->after('exp_date')->index();
            });
        }

        // Backfill MRP on stock_ledger from referenced document lines
        // 1. Sales
        if (Schema::hasTable('sales_bill_items')) {
            $salesLedgers = DB::table('stock_ledger')
                ->where('reference_type', 'App\\Models\\SalesBill')
                ->whereNull('mrp')
                ->get();
            foreach ($salesLedgers as $sl) {
                $q = DB::table('sales_bill_items')
                    ->where('sales_bill_id', $sl->reference_id)
                    ->where('item_id', $sl->item_id);
                if ($sl->exp_date) {
                    $q->whereDate('exp_date', $sl->exp_date);
                }
                $mrp = $q->value('mrp');
                if ($mrp > 0) {
                    DB::table('stock_ledger')->where('id', $sl->id)->update(['mrp' => $mrp]);
                }
            }
        }

        // 2. Transfers
        if (Schema::hasTable('stock_transfer_items')) {
            $stLedgers = DB::table('stock_ledger')
                ->where('reference_type', 'App\\Models\\StockTransfer')
                ->whereNull('mrp')
                ->get();
            foreach ($stLedgers as $sl) {
                $q = DB::table('stock_transfer_items')
                    ->where('stock_transfer_id', $sl->reference_id)
                    ->where('item_id', $sl->item_id);
                if ($sl->exp_date) {
                    $q->whereDate('exp_date', $sl->exp_date);
                }
                $mrp = $q->value('mrp');
                if ($mrp > 0) {
                    DB::table('stock_ledger')->where('id', $sl->id)->update(['mrp' => $mrp]);
                }
            }
        }

        // 3. Purchase Invoices
        if (Schema::hasTable('purchase_invoice_items')) {
            $piLedgers = DB::table('stock_ledger')
                ->where('reference_type', 'App\\Models\\PurchaseInvoice')
                ->whereNull('mrp')
                ->get();
            foreach ($piLedgers as $sl) {
                $q = DB::table('purchase_invoice_items')
                    ->where('purchase_invoice_id', $sl->reference_id)
                    ->where('item_id', $sl->item_id);
                if ($sl->exp_date) {
                    $q->whereDate('exp_date', $sl->exp_date);
                }
                $mrp = $q->value('mrp');
                if ($mrp > 0) {
                    DB::table('stock_ledger')->where('id', $sl->id)->update(['mrp' => $mrp]);
                }
            }
        }

        // 4. Fallback from items
        try {
            $itemsWithMrp = DB::table('items')->whereNotNull('mrp')->where('mrp', '>', 0)->pluck('mrp', 'id');
            foreach ($itemsWithMrp as $itemId => $mrp) {
                DB::table('stock_ledger')->where('item_id', $itemId)->whereNull('mrp')->update(['mrp' => $mrp]);
            }
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_ledger') && Schema::hasColumn('stock_ledger', 'mrp')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->dropColumn('mrp');
            });
        }
    }
};
