<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_bills') && Schema::hasColumn('sales_bills', 'bill_date')) {
            DB::statement('ALTER TABLE sales_bills MODIFY bill_date DATETIME NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_bills') && Schema::hasColumn('sales_bills', 'bill_date')) {
            DB::statement('ALTER TABLE sales_bills MODIFY bill_date DATE NOT NULL');
        }
    }
};
