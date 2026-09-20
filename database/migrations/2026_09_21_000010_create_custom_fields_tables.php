<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->index(); // 'Customer', 'Item'
            $table->string('field_name', 100);
            $table->string('field_key', 100);
            $table->string('field_type', 30)->default('text'); // text, number, date, select, textarea, checkbox
            $table->json('options')->nullable(); // For select type options
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['module', 'field_key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->string('entity_type', 100)->index(); // App\Models\Customer, App\Models\Item
            $table->unsignedBigInteger('entity_id')->index();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_definition_id', 'entity_type', 'entity_id'], 'cfv_def_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
