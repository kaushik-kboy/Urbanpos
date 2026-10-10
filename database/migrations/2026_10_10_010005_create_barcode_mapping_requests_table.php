<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_mapping_requests', function (Blueprint $table) {
            $table->id();
            $table->string('scanned_barcode');
            $table->foreignId('suggested_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('scanned_barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_mapping_requests');
    }
};
