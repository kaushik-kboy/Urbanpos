<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_transfer_items') && !Schema::hasColumn('stock_transfer_items', 'batch_no')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }

        if (Schema::hasTable('damage_stock_items') && !Schema::hasColumn('damage_stock_items', 'batch_no')) {
            Schema::table('damage_stock_items', function (Blueprint $table) {
                $table->string('batch_no', 100)->nullable()->after('item_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfer_items') && Schema::hasColumn('stock_transfer_items', 'batch_no')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }

        if (Schema::hasTable('damage_stock_items') && Schema::hasColumn('damage_stock_items', 'batch_no')) {
            Schema::table('damage_stock_items', function (Blueprint $table) {
                $table->dropColumn('batch_no');
            });
        }
    }
};
