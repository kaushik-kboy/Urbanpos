<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('function_key_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('action_key', 100)->unique();
            $table->string('action_title', 150);
            $table->string('shortcut_combination', 50); // e.g. Alt+P, Alt+S, F2, F6
            $table->string('scope', 50)->default('global'); // global, sales_bill, purchase_invoice, all_forms
            $table->string('target_url', 255)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('function_key_mappings');
    }
};
