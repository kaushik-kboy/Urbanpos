<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->enum('settlement_type', ['Customer', 'Supplier']);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->date('settlement_date');
            $table->decimal('total_amount', 14, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('payment_mode')->default('Cash');
            $table->foreignId('bank_ledger_id')->constrained('ledgers');
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->enum('status', ['Active', 'Cancelled'])->default('Active');
            $table->datetime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bill_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_settlement_id')->constrained('bill_settlements')->cascadeOnDelete();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->decimal('bill_amount', 14, 2);
            $table->decimal('settled_amount', 14, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_settlement_items');
        Schema::dropIfExists('bill_settlements');
    }
};
