<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business rule (2026-10-10 client decision): a task may be scoped to MULTIPLE
 * categories and/or MULTIPLE brands at once, but only when explicitly selected —
 * the single `category_value_id` / `brand_id` columns on stock_count_tasks can only
 * ever hold one value each. These pivot tables are the authoritative multi-value
 * scope; the singular columns are kept (unchanged) for backward compatibility and are
 * mirrored to hold the single value when exactly one category/brand is selected, so
 * every existing query/test that filters by them keeps working unchanged. See
 * URBANPETS_DATABASE_AND_WORKFLOW.md §8 item 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: a prior attempt on real MySQL can leave this table created but without
        // its unique index, because the default auto-generated index name exceeds
        // MySQL's 64-char identifier limit (fine on SQLite, which has no such limit —
        // that's why this passed in the test suite but failed against the real dev DB).
        // Explicit short index names below fix it going forward; this guard makes the
        // migration safely re-runnable against that partial state without a manual drop.
        if (!Schema::hasTable('stock_count_task_category_values')) {
            Schema::create('stock_count_task_category_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('stock_count_tasks')->cascadeOnDelete();
                $table->foreignId('category_value_id')->constrained('item_category_values')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['task_id', 'category_value_id'], 'sctcv_task_category_unique');
            });
        } elseif (!$this->hasIndex('stock_count_task_category_values', 'sctcv_task_category_unique')) {
            Schema::table('stock_count_task_category_values', function (Blueprint $table) {
                $table->unique(['task_id', 'category_value_id'], 'sctcv_task_category_unique');
            });
        }

        if (!Schema::hasTable('stock_count_task_brands')) {
            Schema::create('stock_count_task_brands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('stock_count_tasks')->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['task_id', 'brand_id'], 'sctb_task_brand_unique');
            });
        } elseif (!$this->hasIndex('stock_count_task_brands', 'sctb_task_brand_unique')) {
            Schema::table('stock_count_task_brands', function (Blueprint $table) {
                $table->unique(['task_id', 'brand_id'], 'sctb_task_brand_unique');
            });
        }

        // Backfill: every existing task with a singular category_value_id/brand_id gets
        // exactly one pivot row, so index()/scope-check reads from the pivot tables are
        // consistent with pre-existing data (additive, no existing column changed/dropped).
        DB::table('stock_count_tasks')->whereNotNull('category_value_id')->get(['id', 'category_value_id'])
            ->each(function ($row) {
                DB::table('stock_count_task_category_values')->insertOrIgnore([
                    'task_id' => $row->id,
                    'category_value_id' => $row->category_value_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('stock_count_tasks')->whereNotNull('brand_id')->get(['id', 'brand_id'])
            ->each(function ($row) {
                DB::table('stock_count_task_brands')->insertOrIgnore([
                    'task_id' => $row->id,
                    'brand_id' => $row->brand_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_task_brands');
        Schema::dropIfExists('stock_count_task_category_values');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$indexName]) !== [];
    }
};
