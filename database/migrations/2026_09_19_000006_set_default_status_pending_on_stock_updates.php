<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_updates') && Schema::hasColumn('stock_updates', 'status')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE stock_updates MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_updates') && Schema::hasColumn('stock_updates', 'status')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE stock_updates MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Approved'");
            }
        }
    }
};
