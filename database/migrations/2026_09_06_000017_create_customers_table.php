<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('title', ['Mr', 'Ms', 'Mrs', 'M/s', 'Dr'])->nullable();
            $table->string('name');
            $table->foreignId('customer_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_code')->nullable();
            $table->enum('sales_type', ['Local', 'Interstate'])->default('Local');
            $table->enum('payment_mode', ['Cash Only', 'No Credit', 'Credit Only', 'Both Cash and Credit', 'Cash on Delivery'])->default('Cash Only');
            $table->decimal('credit_limit', 12, 2)->default(1000000);
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->decimal('monthly_credit_balance', 12, 2)->default(0);
            $table->integer('credit_days')->default(1000);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->string('sales_formula')->nullable();
            $table->enum('gst_type', ['Regular', 'Composite', 'Un Register'])->default('Un Register');
            $table->boolean('sms_consent')->default(false);

            // Contact details
            $table->string('address1')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('std_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('remarks')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('aadhar_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('mobile')->nullable();

            // Others
            $table->enum('gender', ['Male', 'Female'])->nullable();
            $table->string('exempted_reason')->nullable();
            $table->enum('customer_type', ['RETAIL INVOICE', 'TAX INVOICE', 'EXEMPTED', 'E-COMMERCE'])->default('RETAIL INVOICE');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
