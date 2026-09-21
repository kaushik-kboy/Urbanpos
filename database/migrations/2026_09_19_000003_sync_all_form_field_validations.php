<?php

use App\Models\FormFieldValidation;
use Database\Seeders\FormFieldValidationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $all = FormFieldValidationSeeder::getFields();
        $hasSection = \Illuminate\Support\Facades\Schema::hasColumn('form_field_validations', 'section');

        foreach ($all as $fieldData) {
            if (! $hasSection) {
                unset($fieldData['section']);
            }
            FormFieldValidation::firstOrCreate(
                [
                    'module_key' => $fieldData['module_key'],
                    'field_name' => $fieldData['field_name'],
                ],
                $fieldData
            );
        }

        // Clean obsolete fields if any (e.g. driver_name in sales_delivery_notes which was never a column)
        FormFieldValidation::where('module_key', 'sales_delivery_notes')->where('field_name', 'driver_name')->delete();
    }

    public function down(): void
    {
        // No-op to preserve configuration
    }
};
