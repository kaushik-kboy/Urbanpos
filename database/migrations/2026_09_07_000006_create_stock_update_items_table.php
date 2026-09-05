<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_update_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->date('exp_date')->nullable();
            $table->decimal('physical_qty', 12, 3);
            $table->decimal('system_qty_at_entry', 12, 3)->default(0);
            $table->decimal('delta_qty', 12, 3)->default(0);
            $table->decimal('sell_price', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_update_items');
    }
};
