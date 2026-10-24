<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create pos_settings table for Admin POS configurations (e.g. lock option)
        if (!Schema::hasTable('pos_settings')) {
            Schema::create('pos_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });

            DB::table('pos_settings')->insert([
                [
                    'key' => 'pos_lock_enabled',
                    'value' => '1',
                    'description' => 'Enable 4-digit PIN lock screen and inactivity lock on POS terminals',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // 2. Add bill_discount to sales_bills if not exists and modify sales_type to string
        if (Schema::hasTable('sales_bills')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_bills', 'bill_discount')) {
                    $table->decimal('bill_discount', 12, 2)->default(0)->after('item_disc_amount');
                }
            });

            // Modify sales_type column from ENUM to VARCHAR(100)
            try {
                DB::statement("ALTER TABLE sales_bills MODIFY COLUMN sales_type VARCHAR(100) NOT NULL DEFAULT 'Local'");
            } catch (\Throwable $e) {
                // In case doctrine dbal is used or already string
            }
        }

        // Modify sales_quotations, sales_orders, sales_returns sales_type to VARCHAR(100) for consistency
        foreach (['sales_quotations', 'sales_orders', 'sales_returns'] as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::statement("ALTER TABLE {$table} MODIFY COLUMN sales_type VARCHAR(100) NOT NULL DEFAULT 'Local'");
                } catch (\Throwable $e) {
                }
            }
        }

        // 3. Update sales_types table to support branch_id
        if (Schema::hasTable('sales_types')) {
            Schema::table('sales_types', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_types', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
                }
            });

            // Drop unique constraint on 'name' if present, to allow duplicate names across different branches
            try {
                Schema::table('sales_types', function (Blueprint $table) {
                    $table->dropUnique('sales_types_name_unique');
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE sales_types DROP INDEX sales_types_name_unique");
                } catch (\Throwable $e2) {
                }
            }
        }

        // 4. Ensure Motera and Satellite branches exist
        $motera = DB::table('branches')->where('name', 'like', '%motera%')->first();
        if (!$motera) {
            $moteraId = DB::table('branches')->insertGetId([
                'name' => 'Urban Pets - Motera',
                'erp_code' => 'MOTERA',
                'state' => 'Gujarat',
                'city' => 'Ahmedabad',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $moteraId = $motera->id;
        }

        $satellite = DB::table('branches')->where('name', 'like', '%satellite%')->first();
        if (!$satellite) {
            $satelliteId = DB::table('branches')->insertGetId([
                'name' => 'Urban Pets - Satellite',
                'erp_code' => 'SAT',
                'state' => 'Gujarat',
                'city' => 'Ahmedabad',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $satelliteId = $satellite->id;
        }

        // 5. Seed branch-wise sales types
        // Motera branch: retail, home, spa
        // Satellite branch: b2b, ipc, home, retail
        $moteraTypes = [
            ['name' => 'Retail', 'code' => 'RETAIL', 'description' => 'Retail Sales (Motera)'],
            ['name' => 'Home', 'code' => 'HOME', 'description' => 'Home Delivery / Sales (Motera)'],
            ['name' => 'Spa', 'code' => 'SPA', 'description' => 'Spa & Grooming Sales (Motera)'],
        ];

        foreach ($moteraTypes as $t) {
            $exists = DB::table('sales_types')
                ->where('branch_id', $moteraId)
                ->where('name', $t['name'])
                ->exists();
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
            }
        }

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
                ->exists();
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
            }
        }

        // 6. Ensure FormFieldValidation for sales_type is compulsory
        if (Schema::hasTable('form_field_validations')) {
            DB::table('form_field_validations')
                ->where('module_key', 'sales_bills')
                ->where('field_name', 'sales_type')
                ->update([
                    'field_label' => 'Sales Bill Type',
                    'is_required' => 1,
                    'custom_error_message' => 'Sales Bill Type is required.',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_settings');
        if (Schema::hasColumn('sales_bills', 'bill_discount')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropColumn('bill_discount');
            });
        }
        if (Schema::hasColumn('sales_types', 'branch_id')) {
            Schema::table('sales_types', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
