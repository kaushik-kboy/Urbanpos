<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['Active', 'Inactive', 'Yet to Active'])->default('Yet to Active');
            $table->string('product_type')->default('TruePOS');
            $table->integer('inv_seq_no')->default(1);
            $table->string('device_id')->nullable();
            $table->boolean('online_sales_allowed')->default(false);
            $table->string('register_prefix')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
