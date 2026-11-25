<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Non-invasive, fully nullable columns for Automobile Workshop & Accessories.
     */
    public function up(): void
    {
        // 1. Sales Bills - Vehicle and Service Details
        Schema::table('sales_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_bills', 'vehicle_no')) {
                $table->string('vehicle_no', 30)->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('sales_bills', 'vehicle_model')) {
                $table->string('vehicle_model', 100)->nullable()->after('vehicle_no');
            }
            if (!Schema::hasColumn('sales_bills', 'odometer_km')) {
                $table->unsignedInteger('odometer_km')->nullable()->after('vehicle_model');
            }
            if (!Schema::hasColumn('sales_bills', 'mechanic_name')) {
                $table->string('mechanic_name', 100)->nullable()->after('odometer_km');
            }
            if (!Schema::hasColumn('sales_bills', 'service_charge')) {
                $table->decimal('service_charge', 10, 2)->default(0.00)->after('total');
            }
        });

        // 2. Items - Spare Part & Compatibility
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'part_number')) {
                $table->string('part_number', 100)->nullable()->after('item_code');
            }
            if (!Schema::hasColumn('items', 'car_compatibility')) {
                $table->string('car_compatibility', 255)->nullable()->after('part_number');
            }
            if (!Schema::hasColumn('items', 'warranty_months')) {
                $table->unsignedInteger('warranty_months')->default(0)->after('car_compatibility');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $cols = ['vehicle_no', 'vehicle_model', 'odometer_km', 'mechanic_name', 'service_charge'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sales_bills', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $cols = ['part_number', 'car_compatibility', 'warranty_months'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
