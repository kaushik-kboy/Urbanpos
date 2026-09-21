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
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'upi_id')) {
                $table->string('upi_id', 100)->nullable()->after('email');
            }
            if (! Schema::hasColumn('branches', 'upi_payee_name')) {
                $table->string('upi_payee_name', 150)->nullable()->after('upi_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'upi_payee_name')) {
                $table->dropColumn('upi_payee_name');
            }
            if (Schema::hasColumn('branches', 'upi_id')) {
                $table->dropColumn('upi_id');
            }
        });
    }
};
