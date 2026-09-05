<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_updates', function (Blueprint $table) {
            $table->id();
            $table->string('update_number')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->date('entry_date');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_updates');
    }
};
