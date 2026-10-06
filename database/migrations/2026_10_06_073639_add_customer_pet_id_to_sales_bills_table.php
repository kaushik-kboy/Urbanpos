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
        Schema::table('sales_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_bills', 'customer_pet_id')) {
                $table->foreignId('customer_pet_id')->nullable()->after('customer_id')->constrained('customer_pets')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_pet_id');
        });
    }
};
