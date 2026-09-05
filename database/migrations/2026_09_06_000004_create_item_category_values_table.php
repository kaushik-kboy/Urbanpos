<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_category_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('show_in_webstore')->default(false);
            $table->boolean('status')->default(true);
            $table->boolean('sellquick_applicable')->default(false);
            $table->integer('allowed_qty_ml')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category_values');
    }
};
