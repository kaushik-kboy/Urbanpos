<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_form_preferences')) {
            Schema::create('user_form_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('form_key', 100);
                $table->json('preferences');
                $table->timestamps();

                $table->unique(['user_id', 'form_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_form_preferences');
    }
};
