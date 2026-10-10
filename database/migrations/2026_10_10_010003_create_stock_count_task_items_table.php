<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('stock_count_tasks')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->string('batch_no')->nullable();
            $table->timestamps();

            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_task_items');
    }
};
