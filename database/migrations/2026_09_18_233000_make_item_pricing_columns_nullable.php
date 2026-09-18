<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE items MODIFY cost_price DECIMAL(12,2) NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY landing_cost DECIMAL(12,2) NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY sell_price DECIMAL(12,2) NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY mrp DECIMAL(12,2) NULL DEFAULT 0.00");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE items MODIFY cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY landing_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY sell_price DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE items MODIFY mrp DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        }
    }
};
