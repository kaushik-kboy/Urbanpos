<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('opening_cash', 12, 2);
            $table->timestamp('opened_at');
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_sessions');
    }
};
