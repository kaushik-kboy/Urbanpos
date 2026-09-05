<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('currency')->default('INR');
            $table->enum('purchase_type', ['Local', 'Interstate', 'Import'])->default('Local');
            $table->enum('purchase_mode', ['Credit', 'Cash', 'Consignment'])->default('Credit');
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->integer('credit_days')->default(0);
            $table->boolean('status')->default(true);
            $table->enum('gst_type', ['Regular', 'Composite', 'Un Register'])->default('Regular');
            $table->enum('mail_type', ['None', 'Inline HTML', 'CSV', 'SAP', 'EDI'])->default('None');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('aadhar_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('gst_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
