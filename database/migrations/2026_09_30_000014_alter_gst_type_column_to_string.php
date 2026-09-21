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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('gst_type', 100)->default('Regular')->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('gst_type', 100)->default('Un Register')->change();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('gst_type', 100)->default('Regular')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('gst_type', 50)->default('Regular')->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('gst_type', 50)->default('Un Register')->change();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('gst_type', 50)->default('Regular')->change();
        });
    }
};
