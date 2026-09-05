<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('damage_number')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->date('entry_date');
            $table->enum('wastage_type', ['Wastage', 'Damage', 'Theft'])->default('Damage');
            $table->decimal('total_qty', 12, 3)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_stocks');
    }
};
