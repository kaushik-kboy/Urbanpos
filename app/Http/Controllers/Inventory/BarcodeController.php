<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * Barcode printing index — search items and build a print queue.
     */
    public function index(Request $request)
    {
        $search          = $request->input('search');
        $branchId        = $request->input('branch_id');
        $brandId         = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');

        $items = collect();

        if ($search || $brandId || $categoryValueId) {
            $query = Item::with(['brand', 'categoryValue'])
                ->where('status', true)
                ->when($search, fn ($q) => $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('item_code', 'like', "%{$search}%")
                       ->orWhere('ean_upc_code', 'like', "%{$search}%")
                       ->orWhere('alias', 'like', "%{$search}%");
                }))
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->when($categoryValueId, fn ($q) => $q->where('category_value_id', $categoryValueId))
                ->orderBy('name')
                ->limit(100);

            $items = $query->get()->map(function ($item) use ($branchId) {
                $stock = $branchId
                    ? $item->stocks()->where('branch_id', $branchId)->first()
                    : $item->stocks()->first();

                return (object) [
                    'id'           => $item->id,
                    'item_code'    => $item->item_code,
                    'ean_upc_code' => $item->ean_upc_code,
                    'name'         => $item->name,
                    'brand_name'   => $item->brand?->name,
                    'sell_price'   => $item->sell_price,
                    'mrp'          => $item->mrp,
                    'stock_qty'    => $stock?->quantity ?? 0,
                    'barcode'      => $item->ean_upc_code ?: $item->item_code,
                ];
            });
        }

        $branches   = Branch::orderBy('name')->pluck('name', 'id');
        $brands     = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))
                        ->orderBy('name')->pluck('name', 'id');

        return view('inventory.barcode.index', compact(
            'items', 'search', 'branchId', 'brandId', 'categoryValueId',
            'branches', 'brands', 'categories'
        ));
    }

    /**
     * Render a print-ready barcode label sheet.
     * Accepts: items[] = [ { id, qty } ]
     */
    public function print(Request $request)
    {
        $itemIds = collect($request->input('items', []))->keyBy('id');

        if ($itemIds->isEmpty()) {
            return redirect()->route('inventory.barcode.index')->with('error', 'No items selected for printing.');
        }

        $items = Item::whereIn('id', $itemIds->keys())
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($itemIds) {
                $entry = $itemIds->get($item->id);
                $qty = max(1, (int) ($entry['qty'] ?? 1));
                return (object) [
                    'id'           => $item->id,
                    'item_code'    => $item->item_code,
                    'name'         => $item->name,
                    'sell_price'   => $item->sell_price,
                    'mrp'          => $item->mrp,
                    'barcode'      => $item->ean_upc_code ?: $item->item_code,
                    'qty'          => $qty,
                ];
            });

        // Expand: repeat each item entry $qty times
        $labels = $items->flatMap(function ($item) {
            return collect(range(1, $item->qty))->map(fn () => $item);
        });

        return view('inventory.barcode.print', compact('labels'));
    }
}
