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

class ChangeSellingController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $brandId = $request->input('brand_id');
        $catValueId = $request->input('category_value_id');
        $search = $request->input('search');

        $branches = Branch::where('name', '!=', 'GLOBAL')->where('status', true)->pluck('name', 'id');
        if ($branches->isEmpty()) {
            $branches = Branch::where('status', true)->pluck('name', 'id');
        }

        $brands = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'like', '%CAT%'))
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

        $items = $query->orderBy('name')->paginate($this->perPage());

        return view('inventory.change-selling.index', compact(
            'branches',
            'branchId',
            'brands',
            'brandId',
            'categories',
            'catValueId',
            'items',
            'search'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'prices' => ['required', 'array'],
            'prices.*.sell_price' => ['required', 'numeric', 'min:0'],
            'prices.*.mrp' => ['required', 'numeric', 'min:0'],
        ]);

        $branchId = (int) $request->input('branch_id');
        $prices = $request->input('prices');
        $updatedCount = 0;

        DB::transaction(function () use ($branchId, $prices, &$updatedCount) {
            foreach ($prices as $itemId => $data) {
                $sellPrice = (float) $data['sell_price'];
                $mrp = (float) $data['mrp'];

                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $itemId, 'branch_id' => $branchId],
                    ['quantity' => 0]
                );

                $stock->update([
                    'sell_price' => $sellPrice,
                    'mrp' => $mrp,
                ]);

                // Also update item master if missing
                Item::where('id', $itemId)
                    ->where(fn ($q) => $q->whereNull('sell_price')->orWhere('sell_price', 0))
                    ->update(['sell_price' => $sellPrice, 'mrp' => $mrp]);

                $updatedCount++;
            }
        });

        return back()->with('status', "Successfully updated selling prices and MRPs for {$updatedCount} items.");
    }
}
