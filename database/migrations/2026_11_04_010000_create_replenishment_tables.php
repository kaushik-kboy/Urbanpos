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
        // 1. Sales Movement Rules (Highest, Fast, Normal, Slow, Dead)
        Schema::create('replenishment_sales_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 50)->unique();
            $table->decimal('min_daily_avg', 8, 2);
            $table->decimal('max_daily_avg', 8, 2)->nullable();
            $table->unsignedInteger('target_cover_days');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Stock Condition Rules (Critical, Low, Healthy, Overstock)
        Schema::create('replenishment_stock_rules', function (Blueprint $table) {
            $table->id();
            $table->string('stock_type', 50)->unique();
            $table->unsignedInteger('min_cover_days');
            $table->unsignedInteger('max_cover_days')->nullable();
            $table->string('color_code', 20)->default('secondary');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Pre-calculated Item Metrics Cache Table
        Schema::create('replenishment_item_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('other_branch_stock', 14, 3)->default(0);
            $table->decimal('sales_30d', 14, 3)->default(0);
            $table->decimal('daily_avg_sales', 14, 3)->default(0);
            $table->decimal('cover_days', 10, 1)->default(0);
            $table->unsignedInteger('target_cover_days')->default(0);
            $table->unsignedInteger('suggested_qty')->default(0);
            $table->string('sales_type', 50)->default('Dead Stock');
            $table->string('stock_status', 50)->default('Healthy');
            $table->foreignId('primary_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'branch_id']);
            $table->index(['branch_id', 'stock_status']);
            $table->index(['branch_id', 'sales_type']);
            $table->index('primary_supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replenishment_item_metrics');
        Schema::dropIfExists('replenishment_stock_rules');
        Schema::dropIfExists('replenishment_sales_rules');
    }
};
