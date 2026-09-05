<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('ledger_group', [
                'Sundry Debtors', 'Sundry Creditors', 'Cash in Hand', 'Bank Account',
                'Sales Account', 'Purchase Account', 'Duties & Taxes', 'Indirect Income',
                'Indirect Expense', 'Capital Account', 'Fixed Assets', 'Current Liabilities',
            ]);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->enum('opening_balance_type', ['Debit', 'Credit'])->default('Debit');
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
