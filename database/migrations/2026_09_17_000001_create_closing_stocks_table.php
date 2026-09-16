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
        Schema::create('closing_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('store_id', 50)->nullable()->index();
            $table->string('store_name')->nullable()->index();
            $table->foreignId('branch_id')->nullable()->index();
            $table->string('item_code', 100)->nullable()->index();
            $table->foreignId('item_id')->nullable()->index();
            $table->string('item_name')->nullable()->index();
            $table->string('isbn', 100)->nullable()->index(); // Barcode
            $table->string('item_alias')->nullable();
            $table->string('cat1_code', 50)->nullable();
            $table->string('cat1_name')->nullable()->index();
            $table->string('cat2_code', 50)->nullable();
            $table->string('cat2_name')->nullable()->index();
            $table->string('cat3_code', 50)->nullable();
            $table->string('cat3_name')->nullable()->index();
            $table->string('brand_code', 50)->nullable();
            $table->string('brand_name')->nullable()->index();
            $table->string('batch_no', 100)->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->string('hsn_code', 50)->nullable()->index();
            $table->string('status', 50)->default('Active')->index();
            $table->decimal('net_cost', 12, 2)->default(0);
            $table->decimal('closing_stock', 12, 3)->default(0)->index();
            $table->decimal('closing_stock_amount', 14, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->string('old_batch_no', 100)->nullable();
            $table->date('old_expiry_date')->nullable();
            $table->date('old_mfr_date')->nullable();
            $table->date('as_on_date')->nullable()->index();
            $table->timestamps();

            // Composite indexes for fast report filtering
            $table->index(['branch_id', 'closing_stock']);
            $table->index(['store_id', 'closing_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closing_stocks');
    }
};
