<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->date('exp_date')->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('sell_price', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('gst_tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_bill_items');
    }
};
