<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('exp_date')->nullable();
            $table->enum('movement_type', [
                'OPENING', 'PURCHASE_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_RETURN',
                'TRANSFER_OUT', 'TRANSFER_TRANSIT', 'TRANSFER_IN', 'DAMAGE', 'EXPIRY',
                'SHORTAGE', 'EXCESS', 'CORRECTION', 'REPACK', 'KIT_ASSEMBLY', 'KIT_DISASSEMBLY',
                'SALES_DELIVERY',
            ]);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty_in', 14, 3)->default(0);
            $table->decimal('qty_out', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('value_in', 14, 2)->default(0);
            $table->decimal('value_out', 14, 2)->default(0);
            $table->decimal('running_balance_qty', 14, 3)->default(0);
            $table->decimal('running_balance_value', 14, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason_code')->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('stock_ledger')->nullOnDelete();
            $table->date('document_date');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['item_id', 'branch_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};
