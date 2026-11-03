<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('replenishment_item_metrics', function (Blueprint $table) {
            $table->index(['branch_id', 'suggested_qty', 'cover_days'], 'idx_replenish_sort');
            $table->index(['branch_id', 'primary_supplier_id'], 'idx_branch_supplier');
            $table->index(['branch_id', 'stock_status', 'suggested_qty'], 'idx_branch_status_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('replenishment_item_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_replenish_sort');
            $table->dropIndex('idx_branch_supplier');
            $table->dropIndex('idx_branch_status_qty');
        });
    }
};
