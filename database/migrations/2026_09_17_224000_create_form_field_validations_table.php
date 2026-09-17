<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_field_validations', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 100)->index();
            $table->string('field_name', 100);
            $table->string('field_label', 150);
            $table->string('field_type', 50)->default('text'); // text, number, date, datetime, select
            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('block_future_date')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->text('custom_error_message')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module_key', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_validations');
    }
};
