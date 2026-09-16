<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('based_on')->default('Bill Amount');
            $table->unsignedInteger('min_points_redeem')->default(50);
            $table->decimal('amount_per_point', 8, 2)->default(1.00);
            $table->decimal('points_per_hundred', 8, 2)->default(1.00);
            $table->boolean('roundoff')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->decimal('min_bill_amount', 12, 2)->default(0.00);
            $table->decimal('max_bill_amount', 12, 2)->nullable();
            $table->decimal('points_earned', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('customer_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sales_bill_id')->nullable()->constrained('sales_bills')->nullOnDelete();
            $table->enum('type', ['Earned', 'Redeemed', 'Adjustment_Add', 'Adjustment_Deduct', 'Reversal']);
            $table->decimal('points', 10, 2);
            $table->decimal('amount_value', 10, 2)->default(0.00);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_points');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('loyalty_programs');
    }
};
