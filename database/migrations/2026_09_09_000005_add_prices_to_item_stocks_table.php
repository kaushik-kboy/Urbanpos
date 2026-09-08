<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0)->after('quantity');
            $table->decimal('landing_cost', 12, 2)->default(0)->after('cost_price');
            $table->decimal('sell_price', 12, 2)->default(0)->after('landing_cost');
            $table->decimal('mrp', 12, 2)->default(0)->after('sell_price');
        });
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'landing_cost', 'sell_price', 'mrp']);
        });
    }
};
