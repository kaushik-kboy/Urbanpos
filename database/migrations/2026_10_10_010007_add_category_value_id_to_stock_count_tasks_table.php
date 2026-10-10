<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: adds a nullable category_value_id to stock_count_tasks.
 *
 * Why: the original stock_count_tasks.category_id column (2026_10_10_010002) points at
 * item_categories, but in this POS item_categories rows are category HEADS
 * ("DEPARTMENT", "CATEGORY", "Brands" — see ImportItemMaster), not the categories a
 * product actually belongs to. Products are categorised via
 * items.category_value_id -> item_category_values.id, and every existing POS screen
 * that filters products "by category" (Master\ItemController@index, BarcodeController,
 * PriceFixingController, ReportController, ...) filters on items.category_value_id.
 * Scoping a count task "by category" therefore needs item_category_values, so it can
 * actually resolve to a product list. category_id is kept untouched (nullable, still
 * accepted by the API) for backward compatibility — nothing is dropped or altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_tasks', function (Blueprint $table) {
            $table->foreignId('category_value_id')
                ->nullable()
                ->after('category_id')
                ->constrained('item_category_values')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_value_id');
        });
    }
};
