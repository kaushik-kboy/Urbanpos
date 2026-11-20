<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_transfer_items') && !Schema::hasColumn('stock_transfer_items', 'requested_qty')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('requested_qty', 14, 3)->nullable()->after('qty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfer_items') && Schema::hasColumn('stock_transfer_items', 'requested_qty')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->dropColumn('requested_qty');
            });
        }
    }
};
