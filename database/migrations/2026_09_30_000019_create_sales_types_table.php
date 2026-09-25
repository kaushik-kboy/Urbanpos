<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_types')) {
            Schema::create('sales_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable();
                $table->string('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });

            // Seed default sales types
            $defaultSalesTypes = [
                ['name' => 'Local', 'code' => 'LOC', 'description' => 'Within State Sale (CGST + SGST)', 'status' => true],
                ['name' => 'Interstate', 'code' => 'INT', 'description' => 'Inter-State Sale (IGST)', 'status' => true],
            ];

            foreach ($defaultSalesTypes as $type) {
                DB::table('sales_types')->insertOrIgnore(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_types');
    }
};
