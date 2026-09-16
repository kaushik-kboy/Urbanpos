<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'status')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->boolean('status')->default(true)->after('branch_id');
            });
        }

        if (Schema::hasTable('uoms') && ! Schema::hasColumn('uoms', 'status')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->boolean('status')->default(true)->after('alias');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'status')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('uoms') && Schema::hasColumn('uoms', 'status')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
