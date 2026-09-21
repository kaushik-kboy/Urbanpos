<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemStock;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemPriceChangeController extends Controller
{
    use HasPerPage;

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $items = Item::with('brand')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('ean_upc_code', 'like', "%{$q}%")
                      ->orWhere('alias', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'ean_upc_code', 'brand_id', 'sell_price', 'mrp']);

        return response()->json($items->map(function ($item) {
            $extra = [];
            if ($item->ean_upc_code) $extra[] = "Barcode: {$item->ean_upc_code}";
            if ($item->brand) $extra[] = "Brand: {$item->brand->name}";
            $extraStr = !empty($extra) ? ' (' . implode(', ', $extra) . ')' : '';
            return [
                'id' => $item->id,
                'text' => $item->name . $extraStr,
                'name' => $item->name,
                'barcode' => $item->ean_upc_code,
                'sell_price' => $item->sell_price,
                'mrp' => $item->mrp,
            ];
        }));
    }

    public function index(Request $request)
    {
        $itemId = $request->input('item_id');
        $search = $request->input('search');

        if ($itemId) {
            $selectedItem = Item::with(['stocks.branch', 'brand', 'categoryValue'])->find($itemId);
        } elseif ($search) {
            $selectedItem = Item::with(['stocks.branch', 'brand', 'categoryValue'])
                ->where('name', 'like', "%{$search}%")
                ->orWhere('ean_upc_code', 'like', "%{$search}%")
                ->orWhere('alias', 'like', "%{$search}%")
                ->first();
        } else {
            // Default to Item 10 (Pedigree Puppy Gravy) or first item
            $selectedItem = Item::with(['stocks.branch', 'brand', 'categoryValue'])->find(10)
                ?? Item::with(['stocks.branch', 'brand', 'categoryValue'])->first();
        }

        // Active operational branches (excluding GLOBAL distribution hub)
        $branches = Branch::where('name', '!=', 'GLOBAL')
            ->where('status', true)
            ->orderBy('id')
            ->get();

        if ($branches->isEmpty()) {
            $branches = Branch::where('status', true)->orderBy('id')->get();
        }

        $stocksByBranch = $selectedItem ? $selectedItem->stocks->keyBy('branch_id') : collect();

        // Paginated items list for quick browsing and picking
        $query = Item::with(['brand', 'stocks']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ean_upc_code', 'like', "%{$search}%")
                  ->orWhere('alias', 'like', "%{$search}%");
            });
        }

        if ($brandId = $request->input('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        $items = $query->orderBy('name')->paginate($this->perPage());
        $brands = Brand::orderBy('name')->pluck('name', 'id');

        return view('master.item-price-change.index', compact(
            'selectedItem',
            'branches',
            'stocksByBranch',
            'items',
            'brands'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'prices' => ['required', 'array'],
            'prices.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.landing_cost' => ['nullable', 'numeric', 'min:0'],
            'prices.*.sell_price' => ['required', 'numeric', 'min:0'],
            'prices.*.mrp' => ['required', 'numeric', 'min:0'],
        ]);

        $prices = $request->input('prices', []);

        // Validate sell_price <= mrp for each branch
        foreach ($prices as $branchId => $priceData) {
            $sp = (float)($priceData['sell_price'] ?? 0);
            $mrp = (float)($priceData['mrp'] ?? 0);
            if ($sp > $mrp) {
                return back()->withErrors([
                    'prices' => "Selling price ({$sp}) cannot exceed Maximum Retail Price MRP ({$mrp}). MRP must be greater than or equal to Selling Price."
                ])->withInput();
            }
        }

        $item = Item::findOrFail($request->input('item_id'));

        // All branches (and the item's own default price fields) must land together —
        // a failure partway through must not leave some branches repriced and others not.
        DB::transaction(function () use ($item, $prices) {
            foreach ($prices as $branchId => $priceData) {
                $newValues = [
                    'cost_price' => $priceData['cost_price'] ?? 0,
                    'landing_cost' => $priceData['landing_cost'] ?? 0,
                    'sell_price' => $priceData['sell_price'] ?? 0,
                    'mrp' => $priceData['mrp'] ?? 0,
                ];

                $existing = ItemStock::where('item_id', $item->id)->where('branch_id', $branchId)->first();
                $oldValues = $existing?->only(array_keys($newValues));

                $stock = ItemStock::updateOrCreate(
                    ['item_id' => $item->id, 'branch_id' => $branchId],
                    $newValues
                );

                // Cost/price override is a spec-named auditable action — log per branch since
                // each branch's ItemStock row is the actual value that changed.
                $this->auditLogger->log('update', $stock, $oldValues, $newValues);
            }

            // Update default price fields on items table from first branch
            $firstBranch = reset($prices);
            if ($firstBranch) {
                $item->update([
                    'cost_price' => $firstBranch['cost_price'] ?? $item->cost_price,
                    'landing_cost' => $firstBranch['landing_cost'] ?? $item->landing_cost,
                    'sell_price' => $firstBranch['sell_price'] ?? $item->sell_price,
                    'mrp' => $firstBranch['mrp'] ?? $item->mrp,
                ]);
            }
        });

        return redirect()->route('master.item-price-change.index', array_merge($request->query(), ['item_id' => $item->id]))
            ->with('status', "Branch prices updated successfully for \"{$item->name}\"!");
    }
}
