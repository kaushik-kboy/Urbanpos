<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_field_validations')
            ->where('field_name', 'gstin')
            ->whereIn('module_key', ['suppliers', 'customers', 'branches'])
            ->update(['field_name' => 'gst_no']);
    }

    public function down(): void
    {
        DB::table('form_field_validations')
            ->where('field_name', 'gst_no')
            ->whereIn('module_key', ['suppliers', 'customers', 'branches'])
            ->update(['field_name' => 'gstin']);
    }
};
