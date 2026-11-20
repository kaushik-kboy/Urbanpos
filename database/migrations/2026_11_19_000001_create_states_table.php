<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('states')) {
            Schema::create('states', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code', 10)->nullable();
                $table->string('country', 100)->default('India');
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
