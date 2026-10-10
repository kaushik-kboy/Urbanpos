<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: adds a nullable brand_id to stock_count_tasks so a Manager/Owner can scope
 * a task by brand in addition to (or instead of) category_id. See
 * URBANPETS_DATABASE_AND_WORKFLOW.md §8 for the category-vs-brand design decision
 * (both are optional and independent, not mutually exclusive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_tasks', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
