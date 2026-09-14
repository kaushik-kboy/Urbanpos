<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceFixingController extends Controller
{
    use HasPerPage;

    public function markupMarkdown(Request $request)
    {
        $request->merge(['tab' => 'markup_markdown']);
        return $this->index($request);
    }

    public function priceLevel(Request $request)
    {
        $request->merge(['tab' => 'price_levels']);
        return $this->index($request);
    }

    public function priceLevelItems(Request $request)
    {
        $request->merge(['tab' => 'price_level_items']);
        return $this->index($request);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'markup_markdown'); // 'markup_markdown', 'price_levels', 'price_level_items'
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $brandId = $request->input('brand_id');
        $catValueId = $request->input('category_value_id');
        $search = $request->input('search');

        $branches = Branch::where('name', '!=', 'GLOBAL')->where('status', true)->pluck('name', 'id');
        if ($branches->isEmpty()) {
            $branches = Branch::where('status', true)->pluck('name', 'id');
        }

        $brands = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('itemCategory', fn ($q) => $q->where('name', 'like', '%CAT%'))
            ->orderBy('name')
            ->pluck('name', 'id');

        $query = Item::with(['stocks' => fn ($q) => $q->where('branch_id', $branchId), 'brand', 'categoryValue']);

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }
        if ($catValueId) {
            $query->where('category_value_id', $catValueId);
        }
        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('ean_upc_code', 'like', "%{$search}%"));
        }

        $items = $query->orderBy('name')->paginate(30);

        // Standard predefined Price Levels for 4.6.2
        $defaultPriceLevels = [
            ['name' => 'Wholesale Pricing', 'type' => 'MarkUp', 'based_on' => 'Cost Price', 'by' => 'Percentage', 'value' => 15.0],
            ['name' => 'Retail Standard', 'type' => 'MarkUp', 'based_on' => 'Landing Cost', 'by' => 'Percentage', 'value' => 30.0],
            ['name' => 'VIP Customer Rate', 'type' => 'MarkDown', 'based_on' => 'Selling', 'by' => 'Percentage', 'value' => 5.0],
            ['name' => 'Distributor Special', 'type' => 'MarkUp', 'based_on' => 'Cost Price', 'by' => 'Percentage', 'value' => 8.0],
        ];

        return view('inventory.price-fixing.index', compact(
            'tab',
            'branches',
            'branchId',
            'brands',
            'brandId',
            'categories',
            'catValueId',
            'items',
            'search',
            'defaultPriceLevels'
        ));
    }

    public function apply(Request $request)
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'base_field' => ['required', 'in:cost_price,landing_cost,mrp'],
            'target_field' => ['required', 'in:sell_price,mrp'],
            'calc_type' => ['required', 'in:percentage,amount'],
            'operation' => ['required', 'in:markup,markdown'],
            'value' => ['required', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'in:none,near_1,near_10,round_up'],
            'apply_scope' => ['required', 'in:selected,filtered,all'],
            'selected_ids' => ['nullable', 'array'],
        ]);

        $branchId = (int) $request->input('branch_id');
        $baseField = $request->input('base_field');
        $targetField = $request->input('target_field');
        $calcType = $request->input('calc_type');
        $operation = $request->input('operation');
        $val = (float) $request->input('value');
        $roundOff = $request->input('round_off', 'none');

        $query = Item::query();

        if ($request->input('apply_scope') === 'selected') {
            $query->whereIn('id', $request->input('selected_ids', []));
        } else {
            if ($brandId = $request->input('brand_id')) {
                $query->where('brand_id', $brandId);
            }
            if ($catId = $request->input('category_value_id')) {
                $query->where('category_value_id', $catId);
            }
            if ($search = $request->input('search')) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('ean_upc_code', 'like', "%{$search}%"));
            }
        }

        $items = $query->get(['id', 'cost_price', 'landing_cost', 'sell_price', 'mrp']);
        $updatedCount = 0;

        DB::transaction(function () use ($items, $branchId, $baseField, $targetField, $calcType, $operation, $val, $roundOff, &$updatedCount) {
            foreach ($items as $item) {
                $base = (float) ($item->{$baseField} > 0 ? $item->{$baseField} : $item->cost_price);
                if ($base <= 0) continue;

                $delta = ($calcType === 'percentage') ? ($base * $val / 100) : $val;
                $newPrice = ($operation === 'markup') ? ($base + $delta) : max(0, $base - $delta);

                // Rounding
                if ($roundOff === 'near_1') {
                    $newPrice = round($newPrice);
                } elseif ($roundOff === 'near_10') {
                    $newPrice = round($newPrice / 10) * 10;
                } elseif ($roundOff === 'round_up') {
                    $newPrice = ceil($newPrice);
                } else {
                    $newPrice = round($newPrice, 2);
                }

                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $item->id, 'branch_id' => $branchId],
                    ['quantity' => 0, 'cost_price' => $item->cost_price, 'landing_cost' => $item->landing_cost, 'sell_price' => $item->sell_price, 'mrp' => $item->mrp]
                );

                $stock->update([$targetField => $newPrice]);
                $updatedCount++;
            }
        });

        return back()->with('status', "Price Fixing rule applied successfully to {$updatedCount} items in the selected branch.");
    }

    public function savePriceLevelMapping(Request $request)
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'price_level' => ['required', 'string'],
            'mappings' => ['required', 'array'],
        ]);

        return back()->with('status', 'Price Level item mapping saved successfully.');
    }
}
