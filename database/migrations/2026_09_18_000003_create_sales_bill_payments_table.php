<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_type_id')->constrained();
            $table->foreignId('tender_type_value_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_bill_payments');
    }
};
