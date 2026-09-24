<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\DynamicValidationService;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_field_validations')
            ->where('module_key', 'suppliers')
            ->where('field_name', 'gst_no')
            ->update(['is_unique' => 1]);

        app(DynamicValidationService::class)->clearCache('suppliers');
    }

    public function down(): void
    {
        DB::table('form_field_validations')
            ->where('module_key', 'suppliers')
            ->where('field_name', 'gst_no')
            ->update(['is_unique' => 0]);

        app(DynamicValidationService::class)->clearCache('suppliers');
    }
};
