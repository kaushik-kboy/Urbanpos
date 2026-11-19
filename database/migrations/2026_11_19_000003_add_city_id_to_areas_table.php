<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            if (! Schema::hasColumn('areas', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('name')->constrained('cities')->nullOnDelete();
            }
            if (! Schema::hasColumn('areas', 'status')) {
                $table->boolean('status')->default(true)->after('branch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            if (Schema::hasColumn('areas', 'city_id')) {
                $table->dropForeign(['city_id']);
                $table->dropColumn('city_id');
            }
        });
    }
};
