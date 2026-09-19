<?php

use App\Models\FormFieldValidation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        FormFieldValidation::where('module_key', 'items')
            ->whereIn('field_name', ['category_id', 'uom_id'])
            ->delete();

        app(\App\Services\DynamicValidationService::class)->clearCache();
    }

    public function down(): void
    {
        // No down needed as these fields never existed on items table or item master form
    }
};
