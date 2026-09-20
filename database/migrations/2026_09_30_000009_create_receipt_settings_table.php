<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();

            // Store Header & Branding
            $table->string('store_name')->default('URBAN PETS');
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('logo_width')->default(120);
            $table->boolean('show_logo')->default(false);

            // Store Contact & Address
            $table->text('header_address')->nullable();
            $table->string('phone')->nullable()->default('7383056626');
            $table->string('phone_alt')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin')->nullable();

            // Feature Toggles
            $table->boolean('show_customer_pet_name')->default(true);
            $table->boolean('show_hsn_code')->default(true);
            $table->boolean('show_tax_breakup')->default(true);
            $table->boolean('show_discount')->default(true);
            $table->boolean('show_upi_qr')->default(true);
            $table->string('upi_id')->nullable()->default('7383056626@okbizaxis');
            $table->string('upi_payee_name')->nullable()->default('Urban Pets');
            $table->boolean('show_barcode')->default(true);

            // Paper & Layout
            $table->string('paper_size')->default('80mm'); // '80mm', '58mm', 'a4', 'a5'
            $table->string('font_size')->default('normal'); // 'small', 'normal', 'large'

            // Footer Policy & Terms
            $table->text('footer_policy')->nullable();
            $table->text('footer_note')->nullable();
            $table->text('custom_css')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};
