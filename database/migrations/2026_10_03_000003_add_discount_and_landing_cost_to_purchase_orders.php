<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_items') && !Schema::hasColumn('purchase_order_items', 'effective_cost')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('effective_cost', 12, 4)->default(0)->after('cost_price');
            });
        }

        if (Schema::hasTable('purchase_orders') && !Schema::hasColumn('purchase_orders', 'scheme_item_disc_percent')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('scheme_item_disc_percent', 5, 2)->default(0)->after('scheme_item_disc_amt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('purchase_order_items', 'effective_cost')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('effective_cost');
            });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'scheme_item_disc_percent')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('scheme_item_disc_percent');
            });
        }
    }
};
