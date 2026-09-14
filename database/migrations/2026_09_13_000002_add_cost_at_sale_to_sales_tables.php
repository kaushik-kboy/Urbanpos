<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_bill_items', function (Blueprint $table) {
            $table->decimal('cost_at_sale', 12, 2)->nullable()->after('sell_price');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('cost_at_sale', 12, 2)->nullable()->after('sell_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales_bill_items', function (Blueprint $table) {
            $table->dropColumn('cost_at_sale');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn('cost_at_sale');
        });
    }
};
