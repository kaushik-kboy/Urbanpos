<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_return_items')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_return_items', 'sales_bill_id')) {
                    $table->foreignId('sales_bill_id')->nullable()->after('item_id')->constrained('sales_bills')->nullOnDelete();
                }
                if (!Schema::hasColumn('sales_return_items', 'sales_bill_item_id')) {
                    $table->foreignId('sales_bill_item_id')->nullable()->after('sales_bill_id')->constrained('sales_bill_items')->nullOnDelete();
                }
                $table->index(['sales_bill_id', 'item_id'], 'idx_sri_bill_item');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_return_items')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_return_items', 'sales_bill_item_id')) {
                    $table->dropForeign(['sales_bill_item_id']);
                    $table->dropColumn('sales_bill_item_id');
                }
                if (Schema::hasColumn('sales_return_items', 'sales_bill_id')) {
                    $table->dropIndex('idx_sri_bill_item');
                    $table->dropForeign(['sales_bill_id']);
                    $table->dropColumn('sales_bill_id');
                }
            });
        }
    }
};
