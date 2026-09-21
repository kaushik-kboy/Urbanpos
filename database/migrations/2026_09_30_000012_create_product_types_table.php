<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Seed standard product types
        $defaultProductTypes = [
            ['name' => 'Standard', 'code' => 'STD', 'description' => 'Standard retail item with inventory tracking', 'status' => true],
            ['name' => 'Serialized', 'code' => 'SER', 'description' => 'Serialized item tracked by IMEI or serial number', 'status' => true],
            ['name' => 'Service Component', 'code' => 'SRV', 'description' => 'Service or non-inventory billable labor', 'status' => true],
            ['name' => 'Gift Voucher', 'code' => 'GFT', 'description' => 'Prepaid gift voucher or coupon', 'status' => true],
        ];

        foreach ($defaultProductTypes as $type) {
            DB::table('product_types')->insertOrIgnore(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_types');
    }
};
