<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoice_items') && !Schema::hasColumn('purchase_invoice_items', 'batch_no')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }

        if (Schema::hasTable('stock_ledger') && !Schema::hasColumn('stock_ledger', 'batch_no')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('branch_id')->index();
            });
        }

        if (Schema::hasTable('stock_update_items')) {
            Schema::table('stock_update_items', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_update_items', 'batch_no')) {
                    $table->string('batch_no', 100)->nullable()->after('item_id')->index();
                }
                if (!Schema::hasColumn('stock_update_items', 'cost_price')) {
                    $table->decimal('cost_price', 12, 2)->default(0)->after('delta_qty');
                }
            });
        }

        if (Schema::hasTable('sales_bill_items') && !Schema::hasColumn('sales_bill_items', 'batch_no')) {
            Schema::table('sales_bill_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }

        if (Schema::hasTable('purchase_return_items') && !Schema::hasColumn('purchase_return_items', 'batch_no')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }

        if (Schema::hasTable('sales_return_items') && !Schema::hasColumn('sales_return_items', 'batch_no')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }

        if (Schema::hasTable('opening_stock_items') && !Schema::hasColumn('opening_stock_items', 'batch_no')) {
            Schema::table('opening_stock_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoice_items') && Schema::hasColumn('purchase_invoice_items', 'batch_no')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('stock_ledger') && Schema::hasColumn('stock_ledger', 'batch_no')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('stock_update_items')) {
            Schema::table('stock_update_items', function (Blueprint $table) {
                if (Schema::hasColumn('stock_update_items', 'batch_no')) {
                    $table->dropColumn('batch_no');
                }
                if (Schema::hasColumn('stock_update_items', 'cost_price')) {
                    $table->dropColumn('cost_price');
                }
            });
        }

        if (Schema::hasTable('sales_bill_items') && Schema::hasColumn('sales_bill_items', 'batch_no')) {
            Schema::table('sales_bill_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('purchase_return_items') && Schema::hasColumn('purchase_return_items', 'batch_no')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('sales_return_items') && Schema::hasColumn('sales_return_items', 'batch_no')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('opening_stock_items') && Schema::hasColumn('opening_stock_items', 'batch_no')) {
            Schema::table('opening_stock_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }
    }
};
