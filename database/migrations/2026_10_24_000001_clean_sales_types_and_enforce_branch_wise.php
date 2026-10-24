<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Delete legacy generic 'Local' and 'Interstate' sales_types
        // These are Purchase / Tax territory types, NOT branch sales bill types.
        if (Schema::hasTable('sales_types')) {
            DB::table('sales_types')
                ->whereIn('name', ['Local', 'Interstate'])
                ->whereNull('branch_id')
                ->delete();

            // Find Motera & Satellite branches
            $moteraId = DB::table('branches')
                ->where('name', 'like', '%Motera%')
                ->orWhere('erp_code', '02')
                ->value('id') ?? 3;

            $satelliteId = DB::table('branches')
                ->where('name', 'like', '%Satellite%')
                ->orWhere('erp_code', '01')
                ->value('id') ?? 2;

            // Motera branch sales bill types: Retail, Home, Spa
            $moteraTypes = [
                ['name' => 'Retail', 'code' => 'RETAIL', 'description' => 'Retail Sales (Motera)'],
                ['name' => 'Home', 'code' => 'HOME', 'description' => 'Home Delivery / Sales (Motera)'],
                ['name' => 'Spa', 'code' => 'SPA', 'description' => 'Spa & Grooming Sales (Motera)'],
            ];

            foreach ($moteraTypes as $t) {
                $exists = DB::table('sales_types')
                    ->where('branch_id', $moteraId)
                    ->where('name', $t['name'])
                    ->first();
                if (!$exists) {
                    DB::table('sales_types')->insert([
                        'branch_id' => $moteraId,
                        'name' => $t['name'],
                        'code' => $t['code'],
                        'description' => $t['description'],
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('sales_types')->where('id', $exists->id)->update([
                        'status' => 1,
                        'code' => $t['code'],
                        'description' => $t['description'],
                        'updated_at' => now(),
                    ]);
                }
            }

            // Satellite branch sales bill types: B2B, IPC, Home, Retail
            $satelliteTypes = [
                ['name' => 'B2B', 'code' => 'B2B', 'description' => 'Business to Business (Satellite)'],
                ['name' => 'IPC', 'code' => 'IPC', 'description' => 'Inter-Pet Club / Special (Satellite)'],
                ['name' => 'Home', 'code' => 'HOME', 'description' => 'Home Delivery / Sales (Satellite)'],
                ['name' => 'Retail', 'code' => 'RETAIL', 'description' => 'Retail Sales (Satellite)'],
            ];

            foreach ($satelliteTypes as $t) {
                $exists = DB::table('sales_types')
                    ->where('branch_id', $satelliteId)
                    ->where('name', $t['name'])
                    ->first();
                if (!$exists) {
                    DB::table('sales_types')->insert([
                        'branch_id' => $satelliteId,
                        'name' => $t['name'],
                        'code' => $t['code'],
                        'description' => $t['description'],
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('sales_types')->where('id', $exists->id)->update([
                        'status' => 1,
                        'code' => $t['code'],
                        'description' => $t['description'],
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 2. Ensure FormFieldValidation has 'sales_type' as compulsory for 'sales_bills'
        if (Schema::hasTable('form_field_validations')) {
            $existing = DB::table('form_field_validations')
                ->where('module_key', 'sales_bills')
                ->where('field_name', 'sales_type')
                ->first();

            if ($existing) {
                DB::table('form_field_validations')
                    ->where('id', $existing->id)
                    ->update([
                        'field_label' => 'Sales Bill Type',
                        'is_required' => 1,
                        'custom_error_message' => 'Sales Bill Type is required.',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('form_field_validations')->insert([
                    'module_key' => 'sales_bills',
                    'field_name' => 'sales_type',
                    'field_label' => 'Sales Bill Type',
                    'section' => 'Header Details',
                    'field_type' => 'select',
                    'is_required' => 1,
                    'is_readonly' => 0,
                    'block_future_date' => 0,
                    'is_unique' => 0,
                    'custom_error_message' => 'Sales Bill Type is required.',
                    'sort_order' => 9,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No destructive reverse
    }
};
