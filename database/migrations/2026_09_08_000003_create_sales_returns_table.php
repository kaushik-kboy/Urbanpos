<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->date('return_date');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sales_bill_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('return_mode', ['RRN', 'Credit Note', 'Cash', 'Wallet', 'Card'])->default('Cash');
            $table->enum('sales_type', ['Local', 'Interstate'])->default('Local');
            $table->decimal('item_disc_amount', 12, 2)->default(0);
            $table->decimal('disc_percent', 5, 2)->default(0);
            $table->decimal('disc_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->decimal('total_extra_cess', 12, 2)->default(0);
            $table->decimal('gst_calamity_cess', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
