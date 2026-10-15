<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Inventory\BatchStockService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(private BatchStockService $batchStockService)
    {
    }

    /**
     * Paginated product search by item_code / name / ean_upc_code.
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Same product filters the web item master uses (Master\ItemController@index).
            'category_value_id' => ['nullable', 'integer', 'exists:item_category_values,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 20);

        $query = Item::query()->where('status', true);

        if ($categoryValueId = $request->query('category_value_id')) {
            $query->where('category_value_id', $categoryValueId);
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        if ($q !== '') {
            if (Item::where('item_code', $q)->exists()) {
                $query->where('item_code', $q);
            } elseif (Item::where('ean_upc_code', $q)->exists()) {
                $query->where('ean_upc_code', $q);
            } else {
                $query->where(function ($sub) use ($q) {
                    $sub->where('item_code', 'like', "{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('ean_upc_code', 'like', "{$q}%");
                });
            }
        }

        $items = $query->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Item $item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'ean_upc_code' => $item->ean_upc_code,
                'mrp' => $item->mrp,
                'brand_id' => $item->brand_id,
                'category_value_id' => $item->category_value_id,
            ]);

        return response()->json($items);
    }

    /**
     * Lookup by the item's single EAN/UPC barcode.
     */
    public function byBarcode(Request $request, string $code)
    {
        $item = Item::where('ean_upc_code', $code)
            ->orWhere('item_code', $code)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'No item found for this barcode.'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'ean_upc_code' => $item->ean_upc_code,
                'mrp' => $item->mrp,
                'batch_expiry_details' => $item->batch_expiry_details,
                'shelf_life_days' => $item->shelf_life_days,
            ],
        ]);
    }

    /**
     * Batch/expiry reconstruction for an item in a branch, via the read-only
     * BatchStockService — the same source used by the manager's batch view.
     *
     * Blind counting (master prompt §6): a Staff-only user (not also Manager/Owner)
     * must NEVER receive the system quantity in the response body — the 'remaining_qty'
     * key (current system stock for the batch) is stripped entirely, not merely hidden
     * client-side. Manager/Owner responses keep it.
     */
    public function batches(Request $request, Item $item)
    {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchId = (int) $request->query('branch_id');

        $batches = $this->batchStockService->getItemBatches($item->id, $branchId);

        $user = $request->user();
        $isBlindStaff = $user->hasRole('Staff') && ! $user->hasAnyRole(['Manager', 'Owner']);

        $data = $batches->map(function (array $batch) use ($isBlindStaff) {
            if ($isBlindStaff) {
                unset($batch['remaining_qty']);
            }

            return $batch;
        })->values();

        return response()->json(['data' => $data]);
    }
}
