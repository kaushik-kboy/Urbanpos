<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `customer_pets` MODIFY COLUMN `birth_date` VARCHAR(30) NULL");
            // Normalize existing YYYY-MM-DD values to DD-MM-YYYY
            try {
                DB::statement("UPDATE `customer_pets` SET `birth_date` = DATE_FORMAT(STR_TO_DATE(`birth_date`, '%Y-%m-%d'), '%d-%m-%Y') WHERE `birth_date` REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            } catch (\Throwable) {
                // Ignore if any records cannot be transformed
            }
        } else {
            Schema::table('customer_pets', function (Blueprint $table) {
                $table->string('birth_date', 30)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `customer_pets` MODIFY COLUMN `birth_date` DATE NULL");
        } else {
            Schema::table('customer_pets', function (Blueprint $table) {
                $table->date('birth_date')->nullable()->change();
            });
        }
    }
};
