<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('till_session_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['In', 'Out']);
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_cash_movements');
    }
};
