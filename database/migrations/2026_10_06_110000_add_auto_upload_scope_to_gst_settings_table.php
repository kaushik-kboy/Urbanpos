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
        Schema::table('gst_settings', function (Blueprint $table) {
            $table->string('auto_upload_scope', 30)->default('both')->after('auto_upload_threshold');
            $table->string('company_name', 150)->nullable()->default('URBANPETS SERVICES PRIVATE LIMITED')->after('gstin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gst_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_upload_scope', 'company_name']);
        });
    }
};
