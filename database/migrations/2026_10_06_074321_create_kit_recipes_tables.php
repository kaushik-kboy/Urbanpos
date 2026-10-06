<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kit_recipes')) {
            Schema::create('kit_recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kit_item_id')->constrained('items')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('kit_item_id');
            });
        }

        if (!Schema::hasTable('kit_recipe_items')) {
            Schema::create('kit_recipe_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kit_recipe_id')->constrained('kit_recipes')->cascadeOnDelete();
                $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
                $table->decimal('qty_per_kit', 10, 4)->default(1.0000);
                $table->string('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kit_recipe_items');
        Schema::dropIfExists('kit_recipes');
    }
};
