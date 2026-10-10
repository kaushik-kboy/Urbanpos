<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_observations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['stock_count', 'expiry_check']);
            $table->foreignId('task_id')->nullable()->constrained('stock_count_tasks')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('batch_no')->nullable();
            $table->date('exp_date_observed')->nullable();
            $table->decimal('physical_qty', 14, 3)->nullable();
            $table->text('remarks')->nullable();
            $table->string('photo_path')->nullable();
            $table->uuid('idempotency_key');
            $table->decimal('system_qty_snapshot', 14, 3);
            $table->enum('status', ['pending', 'approved', 'rejected', 'recount_requested'])->default('pending');
            $table->foreignId('parent_observation_id')->nullable()->constrained('inventory_observations')->nullOnDelete();
            $table->decimal('approved_qty', 14, 3)->nullable();
            $table->decimal('actual_adjustment_qty', 14, 3)->nullable();
            $table->foreignId('stock_ledger_id')->nullable()->constrained('stock_ledger')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index(['branch_id', 'status']);
            $table->index(['item_id', 'branch_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_observations');
    }
};
