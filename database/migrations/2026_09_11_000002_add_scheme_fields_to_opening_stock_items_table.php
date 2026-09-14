<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_stock_items', function (Blueprint $table) {
            $table->decimal('scheme_disc_percent', 5, 2)->default(0)->after('disc_amount');
            $table->decimal('scheme_amount', 12, 2)->default(0)->after('scheme_disc_percent');
            $table->decimal('scheme_others', 12, 2)->default(0)->after('scheme_amount');
        });
    }

    public function down(): void
    {
        Schema::table('opening_stock_items', function (Blueprint $table) {
            $table->dropColumn(['scheme_disc_percent', 'scheme_amount', 'scheme_others']);
        });
    }
};
