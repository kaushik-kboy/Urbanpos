<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->date('bill_date');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->enum('invoice_type', ['Retail Invoice', 'Tax Invoice', 'Exempted'])->default('Retail Invoice');
            $table->string('delivery_type')->default('Delivered');
            $table->time('delivery_time')->nullable();
            $table->enum('sales_type', ['Local', 'Interstate'])->default('Local');
            $table->string('payment_type')->default('None');
            $table->decimal('item_disc_amount', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->decimal('total_extra_cess', 12, 2)->default(0);
            $table->decimal('gst_calamity_cess', 12, 2)->default(0);
            $table->decimal('total_qty', 12, 3)->default(0);
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_bills');
    }
};
