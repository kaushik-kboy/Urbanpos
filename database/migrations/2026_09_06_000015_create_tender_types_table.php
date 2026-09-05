<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->enum('type', ['Cash', 'Card', 'Coupon', 'Wallet', 'Credit', 'Finance'])->default('Cash');
            $table->string('mode')->default('Manual');
            $table->boolean('service_applicable')->default(false);
            $table->boolean('mandate_refno')->default(false);
            $table->decimal('service_charge_perc', 5, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_types');
    }
};
