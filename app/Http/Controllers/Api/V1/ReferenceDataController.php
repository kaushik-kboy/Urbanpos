<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use Illuminate\Http\Request;

/**
 * Read-only product taxonomy lists for the mobile app's task-assignment filters
 * (master prompt §9: "assign tasks using ... Category or brand").
 *
 * Mirrors exactly how the existing web POS builds its "Category" and "Brand" filter
 * dropdowns (App\Http\Controllers\Master\ItemController@index):
 *  - Brand    = brands table (items.brand_id)
 *  - Category = item_category_values whose parent item_categories HEAD is named
 *               CATEGORY (or a known alias) — items.category_value_id
 * Unlike the web helper, this never creates a missing head row (GET must not write).
 */
class ReferenceDataController extends Controller
{
    /** Same aliases Master\ItemController::index passes to categoryValues(). */
    private const CATEGORY_HEAD_ALIASES = ['CATEGORY', 'Category', 'category', 'Categories', 'CAT'];

    public function categories(Request $request)
    {
        $headIds = ItemCategory::query()
            ->where(function ($q) {
                foreach (self::CATEGORY_HEAD_ALIASES as $alias) {
                    $q->orWhere('name', 'like', $alias);
                }
            })
            ->pluck('id');

        $values = $headIds->isEmpty()
            ? collect()
            : ItemCategoryValue::query()
                ->whereIn('item_category_id', $headIds)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json(['data' => $values->values()]);
    }

    public function brands(Request $request)
    {
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $brands]);
    }
}
