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
        Schema::create('whats_app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_url', 255)->default('https://chatonclick.com');
            $table->string('app_key', 255)->nullable();
            $table->string('auth_key', 255)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('template_lang', 10)->default('en');
            $table->string('header_title', 100)->default('URBAN PETS');
            $table->string('footer_message', 255)->default('Have an Awesome Day!');
            $table->string('support_phone', 30)->nullable();
            $table->boolean('auto_send_on_bill')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_settings');
    }
};
