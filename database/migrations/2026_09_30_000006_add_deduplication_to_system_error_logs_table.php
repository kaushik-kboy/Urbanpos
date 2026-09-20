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
        Schema::table('system_error_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('system_error_logs', 'error_hash')) {
                $table->char('error_hash', 32)->nullable()->index()->after('message');
            }
            if (!Schema::hasColumn('system_error_logs', 'occurrence_count')) {
                $table->unsignedInteger('occurrence_count')->default(1)->after('error_hash');
            }
            if (!Schema::hasColumn('system_error_logs', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('occurrence_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_error_logs', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('system_error_logs', 'error_hash')) {
                $columnsToDrop[] = 'error_hash';
            }
            if (Schema::hasColumn('system_error_logs', 'occurrence_count')) {
                $columnsToDrop[] = 'occurrence_count';
            }
            if (Schema::hasColumn('system_error_logs', 'last_seen_at')) {
                $columnsToDrop[] = 'last_seen_at';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
