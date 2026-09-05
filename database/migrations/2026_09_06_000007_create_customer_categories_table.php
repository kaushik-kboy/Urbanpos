<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('app_access')->default(false);
            $table->boolean('enable_loyalty')->default(false);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->enum('business_type', ['ALL', 'COCO', 'FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'])->default('ALL');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_categories');
    }
};
