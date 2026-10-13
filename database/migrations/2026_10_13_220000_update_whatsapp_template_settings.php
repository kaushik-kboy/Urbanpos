<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear unapproved template_name so direct messages with invoice link are dispatched reliably
        DB::table('whats_app_settings')
            ->where('template_name', 'urban_tax_invoice')
            ->update(['template_name' => null]);
    }

    public function down(): void
    {
    }
};
