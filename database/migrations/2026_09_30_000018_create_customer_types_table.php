<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_types')) {
            Schema::create('customer_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable();
                $table->string('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });

            // Seed default customer types
            $defaultCustomerTypes = [
                ['name' => 'RETAIL INVOICE', 'code' => 'RETAIL', 'description' => 'Retail Customer (B2C)', 'status' => true],
                ['name' => 'TAX INVOICE', 'code' => 'TAX', 'description' => 'Tax Invoice Customer (B2B)', 'status' => true],
                ['name' => 'EXEMPTED', 'code' => 'EXM', 'description' => 'Tax Exempted Customer', 'status' => true],
                ['name' => 'E-COMMERCE', 'code' => 'ECOM', 'description' => 'E-Commerce / Online Portal Customer', 'status' => true],
            ];

            foreach ($defaultCustomerTypes as $type) {
                DB::table('customer_types')->insertOrIgnore(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_types');
    }
};
