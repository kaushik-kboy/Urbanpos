<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stock_updates')) {
            Schema::table('stock_updates', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_updates', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('stock_updates', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('created_by')->index();
                }
            });
        }

        if (Schema::hasTable('damage_stocks')) {
            Schema::table('damage_stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('damage_stocks', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('damage_stocks', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('created_by')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_updates')) {
            Schema::table('stock_updates', function (Blueprint $table) {
                if (Schema::hasColumn('stock_updates', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
                if (Schema::hasColumn('stock_updates', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('damage_stocks')) {
            Schema::table('damage_stocks', function (Blueprint $table) {
                if (Schema::hasColumn('damage_stocks', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
                if (Schema::hasColumn('damage_stocks', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }
    }
};
