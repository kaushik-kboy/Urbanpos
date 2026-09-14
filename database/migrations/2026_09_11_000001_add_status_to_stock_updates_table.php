<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_updates', 'status')) {
            Schema::table('stock_updates', function (Blueprint $table) {
                $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Approved')->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_updates', 'status')) {
            Schema::table('stock_updates', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
