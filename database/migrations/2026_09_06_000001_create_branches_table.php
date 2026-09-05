<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('language')->default('ENGLISH');
            $table->string('area_code')->nullable();
            $table->string('circle_code')->nullable();
            $table->enum('business_type', ['COCO', 'FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'])->default('COCO');
            $table->boolean('webstore')->default(false);
            $table->string('erp_code')->nullable();
            $table->string('country_code')->default('1');
            $table->string('license_id')->nullable();
            $table->string('cst')->nullable();
            $table->string('website_link')->nullable();
            $table->string('social_media_link')->nullable();
            $table->boolean('enable_thirdparty_loyalty')->default(false);
            $table->string('gst_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->enum('gst_type', ['Regular', 'Composite', 'Un Register'])->default('Regular');
            $table->enum('gst_filing', ['Monthly', 'Quarterly'])->default('Monthly');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
