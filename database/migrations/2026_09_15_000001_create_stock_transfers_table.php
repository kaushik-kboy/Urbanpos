<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->date('transfer_date');
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->enum('status', ['Dispatched', 'Received', 'Cancelled'])->default('Dispatched');
            $table->string('posting_key')->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->decimal('total_qty', 14, 3)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
