<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['name', 'state_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
