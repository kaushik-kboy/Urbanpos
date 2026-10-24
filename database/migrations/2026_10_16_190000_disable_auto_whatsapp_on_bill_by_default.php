<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('whats_app_settings') && Schema::hasColumn('whats_app_settings', 'auto_send_on_bill')) {
            // Disable auto_send_on_bill for all existing settings rows across all branches/environments
            // WhatsApp should only be dispatched when explicitly clicked/requested by user
            DB::table('whats_app_settings')->update(['auto_send_on_bill' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
