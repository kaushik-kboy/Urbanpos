<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\DynamicValidationService;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_field_validations')
            ->where('module_key', 'items')
            ->where('field_name', 'item_code')
            ->update(['is_required' => false]);

        try {
            app(DynamicValidationService::class)->clearCache('items');
        } catch (\Throwable $e) {
            // cache clear fail-safe
        }
    }

    public function down(): void
    {
    }
};
