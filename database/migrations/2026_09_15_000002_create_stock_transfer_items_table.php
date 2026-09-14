<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->date('exp_date')->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('received_qty', 14, 3)->nullable();
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('taxable_value', 12, 2)->default(0);
            $table->decimal('gst_tax_amount', 12, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
