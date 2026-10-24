<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales_bills', 'purchase_type')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->string('purchase_type', 50)->default('Local')->after('sales_type');
            });
        }

        // Backfill: existing sales bills that were Local or Interstate get preserved in purchase_type
        DB::table('sales_bills')
            ->whereIn('sales_type', ['Local', 'Interstate'])
            ->update([
                'purchase_type' => DB::raw('sales_type'),
                'sales_type' => 'Retail',
            ]);

        DB::table('sales_bills')
            ->whereNull('purchase_type')
            ->update(['purchase_type' => 'Local']);

        if (Schema::hasTable('form_field_validations')) {
            DB::table('form_field_validations')->updateOrInsert(
                ['module_key' => 'sales_bills', 'field_name' => 'purchase_type'],
                [
                    'field_label' => 'Purchase Type',
                    'field_type' => 'select',
                    'is_required' => false,
                    'is_readonly' => true,
                    'block_future_date' => false,
                    'is_unique' => false,
                    'custom_error_message' => null,
                    'sort_order' => 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_bills', 'purchase_type')) {
            Schema::table('sales_bills', function (Blueprint $table) {
                $table->dropColumn('purchase_type');
            });
        }
    }
};
