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
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%"));
        }

        $items = $query->orderBy('name')->paginate($this->perPage());

        $batchService = app(\App\Services\Inventory\BatchStockService::class);
        foreach ($items as $item) {
            $rawBatches = $batchService->getItemBatches($item->id, $branchId);
            $posBatches = $rawBatches->filter(fn ($b) => (float)($b['remaining_qty'] ?? 0) > 0.0001)->values();
            $item->display_batches = $posBatches->isNotEmpty() ? $posBatches : $rawBatches->values();
        }

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
            'prices.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.mrp' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = (int) $request->input('branch_id');
        $prices = $request->input('prices');
        $updatedCount = 0;

        DB::transaction(function () use ($branchId, $prices, &$updatedCount) {
            foreach ($prices as $key => $data) {
                // Support both legacy test format prices[itemId] and batch format prices[rowKey]
                $itemId = isset($data['item_id']) ? (int) $data['item_id'] : (int) $key;
                if ($itemId <= 0) {
                    continue;
                }

                $sellPrice = isset($data['sell_price']) && $data['sell_price'] !== '' ? (float) $data['sell_price'] : null;
                $mrp = isset($data['mrp']) && $data['mrp'] !== '' ? (float) $data['mrp'] : null;

                if ($sellPrice === null && $mrp === null) {
                    continue;
                }

                // If sell price exceeds MRP, clamp sell price to MRP
                if ($mrp !== null && $mrp > 0 && $sellPrice !== null && $sellPrice > $mrp) {
                    $sellPrice = $mrp;
                }

                $rawBatchNo = isset($data['raw_batch_no']) ? trim((string) $data['raw_batch_no']) : (isset($data['batch_no']) ? trim((string) $data['batch_no']) : null);
                $expDate = !empty($data['exp_date']) ? trim((string) $data['exp_date']) : null;
                $oldMrp = isset($data['old_mrp']) && $data['old_mrp'] !== '' ? (float) $data['old_mrp'] : null;

                $hasSpecificBatch = !empty($rawBatchNo) && !in_array($rawBatchNo, ['DEFAULT', 'Initial Stock', 'Opening Stock']) && !str_starts_with($rawBatchNo, 'Lot #');

                // 1. Update purchase_invoice_items for this branch & item
                $piQuery = DB::table('purchase_invoice_items as pii')
                    ->join('purchase_invoices as pi', 'pii.purchase_invoice_id', '=', 'pi.id')
                    ->where('pi.branch_id', $branchId)
                    ->where('pii.item_id', $itemId);

                if ($hasSpecificBatch) {
                    $piQuery->where('pii.batch_no', $rawBatchNo);
                } elseif (!empty($expDate)) {
                    $piQuery->whereDate('pii.exp_date', $expDate);
                }
                if ($oldMrp !== null && $oldMrp > 0) {
                    $piQuery->where(function ($q) use ($oldMrp) {
                        $q->whereBetween('pii.mrp', [$oldMrp - 0.05, $oldMrp + 0.05])
                          ->orWhereNull('pii.mrp');
                    });
                }
                $piItemIds = $piQuery->pluck('pii.id');

                if ($piItemIds->isNotEmpty()) {
                    $piiUpdate = [];
                    if ($mrp !== null) $piiUpdate['mrp'] = $mrp;
                    if ($sellPrice !== null) $piiUpdate['sell_price'] = $sellPrice;
                    DB::table('purchase_invoice_items')->whereIn('id', $piItemIds)->update($piiUpdate);
                }

                // 2. Update closing_stocks (imported TruePOS batches)
                $csQuery = DB::table('closing_stocks')
                    ->where('branch_id', $branchId)
                    ->where('item_id', $itemId);

                if ($hasSpecificBatch) {
                    $csQuery->where('batch_no', $rawBatchNo);
                } elseif (!empty($expDate)) {
                    $csQuery->whereDate('expiry_date', $expDate);
                }
                if ($oldMrp !== null && $oldMrp > 0) {
                    $csQuery->whereBetween('mrp', [$oldMrp - 0.05, $oldMrp + 0.05]);
                }
                if ($mrp !== null) {
                    $csQuery->update(['mrp' => $mrp]);
                }

                // 3. Update stock_ledger for this item & branch so POS batch resolution stays in sync
                if (\Illuminate\Support\Facades\Schema::hasTable('stock_ledger') && \Illuminate\Support\Facades\Schema::hasColumn('stock_ledger', 'mrp')) {
                    $slQuery = DB::table('stock_ledger')
                        ->where('branch_id', $branchId)
                        ->where('item_id', $itemId);

                    if ($hasSpecificBatch) {
                        $slQuery->where('batch_no', $rawBatchNo);
                    } elseif (!empty($expDate)) {
                        $slQuery->whereDate('exp_date', $expDate);
                    }
                    if ($oldMrp !== null && $oldMrp > 0) {
                        $slQuery->whereBetween('mrp', [$oldMrp - 0.05, $oldMrp + 0.05]);
                    }
                    if ($mrp !== null) {
                        $slQuery->update(['mrp' => $mrp]);
                    }
                }

                // 4. Update opening_stock_items
                $osiQuery = DB::table('opening_stock_items as osi')
                    ->join('opening_stocks as os', 'osi.opening_stock_id', '=', 'os.id')
                    ->where('os.branch_id', $branchId)
                    ->where('osi.item_id', $itemId);

                if ($hasSpecificBatch) {
                    $osiQuery->where('osi.batch_no', $rawBatchNo);
                } elseif (!empty($expDate)) {
                    $osiQuery->whereDate('osi.exp_date', $expDate);
                }
                if ($oldMrp !== null && $oldMrp > 0) {
                    $osiQuery->whereBetween('osi.mrp', [$oldMrp - 0.05, $oldMrp + 0.05]);
                }
                $osiIds = $osiQuery->pluck('osi.id');
                if ($osiIds->isNotEmpty()) {
                    $osiUpdate = [];
                    if ($mrp !== null) $osiUpdate['mrp'] = $mrp;
                    if ($sellPrice !== null) $osiUpdate['sell_price'] = $sellPrice;
                    DB::table('opening_stock_items')->whereIn('id', $osiIds)->update($osiUpdate);
                }

                // 5. Update branch item_stocks table
                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $itemId, 'branch_id' => $branchId],
                    ['quantity' => 0]
                );
                $stockUpdate = [];
                if ($sellPrice !== null) $stockUpdate['sell_price'] = $sellPrice;
                if ($mrp !== null) $stockUpdate['mrp'] = $mrp;
                if (!empty($stockUpdate)) {
                    $stock->update($stockUpdate);
                }

                // 6. Update master Item defaults if missing or zero
                $itemMasterUpdate = [];
                if ($sellPrice !== null) $itemMasterUpdate['sell_price'] = $sellPrice;
                if ($mrp !== null) $itemMasterUpdate['mrp'] = $mrp;
                if (!empty($itemMasterUpdate)) {
                    Item::where('id', $itemId)
                        ->where(fn ($q) => $q->whereNull('sell_price')->orWhere('sell_price', '<=', 0))
                        ->update($itemMasterUpdate);
                }

                $updatedCount++;
            }
        });

        return back()->with('status', "Successfully updated selling prices and MRPs for {$updatedCount} records.");
    }
}
