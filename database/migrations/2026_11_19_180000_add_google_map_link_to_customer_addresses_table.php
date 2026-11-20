<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_addresses') && ! Schema::hasColumn('customer_addresses', 'google_map_link')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->text('google_map_link')->nullable()->after('landmark');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_addresses') && Schema::hasColumn('customer_addresses', 'google_map_link')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('google_map_link');
            });
        }
    }
};
