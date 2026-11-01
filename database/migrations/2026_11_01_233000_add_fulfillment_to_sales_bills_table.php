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
        if (Schema::hasTable('sales_bills') && ! Schema::hasColumn('sales_bills', 'fulfillment')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->string('fulfillment', 50)->nullable()->after('delivery_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_bills') && Schema::hasColumn('sales_bills', 'fulfillment')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropColumn('fulfillment');
            });
        }
    }
};
