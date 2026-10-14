<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_transfer_items') && ! Schema::hasColumn('stock_transfer_items', 'sell_price')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('sell_price', 12, 2)->nullable()->after('unit_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfer_items') && Schema::hasColumn('stock_transfer_items', 'sell_price')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->dropColumn('sell_price');
            });
        }
    }
};
