<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('ean_upc_code')->nullable()->unique();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('product_type', ['Standard', 'Serialized', 'Service Component', 'Gift Voucher'])->default('Standard');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('landing_cost', 12, 2)->default(0);
            $table->decimal('sell_price', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->boolean('store_pickup')->default(false);
            $table->boolean('tax_inclusive')->default(false);
            $table->enum('batch_expiry_details', ['Not Required', 'Optional', 'Mandatory', 'Days', 'Month'])->default('Not Required');
            $table->integer('shelf_life_days')->nullable();
            $table->integer('minimum_shelf_life_days')->nullable();
            $table->boolean('allow_negative_stock')->default(false);
            $table->foreignId('department_value_id')->nullable()->constrained('item_category_values')->nullOnDelete();
            $table->foreignId('category_value_id')->nullable()->constrained('item_category_values')->nullOnDelete();
            $table->foreignId('brand_value_id')->nullable()->constrained('item_category_values')->nullOnDelete();
            $table->foreignId('gst_tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hsn_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
