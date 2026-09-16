<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sales Quotations
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->enum('sales_type', ['Local', 'Interstate'])->default('Local');
            $table->decimal('item_disc_amount', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->enum('status', ['Draft', 'Sent', 'Accepted', 'Converted', 'Cancelled'])->default('Draft');
            $table->foreignId('converted_sales_bill_id')->nullable()->constrained('sales_bills')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('qty', 12, 3);
            $table->decimal('sell_price', 12, 2);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('gst_tax_amount', 12, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        // 2. Sales Orders
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->enum('sales_type', ['Local', 'Interstate'])->default('Local');
            $table->decimal('item_disc_amount', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('advance_amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->enum('status', ['Open', 'Partially Fulfilled', 'Converted', 'Cancelled'])->default('Open');
            $table->foreignId('converted_sales_bill_id')->nullable()->constrained('sales_bills')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('qty', 12, 3);
            $table->decimal('sell_price', 12, 2);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('gst_tax_amount', 12, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_quotation_items');
        Schema::dropIfExists('sales_quotations');
    }
};
