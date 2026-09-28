<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\OpeningStockItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockLedger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchStockService
{
    /**
     * Resolves all batches for an item in a branch, preserving:
     * - Batch No
     * - Expiry Date
     * - Purchase Price / Cost
     * - MRP
     * - Sales Price
     * - Purchased Quantity
     * - Remaining Stock Quantity
     *
     * @return Collection<int, array>
     */
    public function getItemBatches(int $itemId, int $branchId): Collection
    {
        $item = Item::find($itemId);
        if (!$item) {
            return collect();
        }

        // 1. Query tracked batch stock from StockLedger for this branch & item
        $ledgerRows = StockLedger::where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->selectRaw('batch_no, DATE(exp_date) as exp_date_val, SUM(qty_in) - SUM(qty_out) as remaining_qty, SUM(qty_in) as total_in')
            ->groupBy('batch_no', DB::raw('DATE(exp_date)'))
            ->get();

        $ledgerBatchMap = [];
        foreach ($ledgerRows as $lr) {
            $bNo = $lr->batch_no ? trim($lr->batch_no) : '';
            $expStr = $lr->exp_date_val ? trim((string) $lr->exp_date_val) : '';
            $key = $bNo !== '' ? $bNo : ($expStr !== '' ? $expStr : 'DEFAULT');
            $ledgerBatchMap[$key] = [
                'remaining' => (float) $lr->remaining_qty,
                'total_in' => (float) $lr->total_in,
                'exp_date' => $expStr,
                'batch_no' => $bNo,
            ];
        }

        // 2. Fetch purchase batches from Purchase Invoices in this branch
        $piItems = PurchaseInvoiceItem::with('purchaseInvoice')
            ->where('item_id', $itemId)
            ->whereHas('purchaseInvoice', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id', 'asc')
            ->get();

        if ($piItems->isEmpty()) {
            $piItems = PurchaseInvoiceItem::with('purchaseInvoice')
                ->where('item_id', $itemId)
                ->orderBy('id', 'asc')
                ->get();
        }

        // 3. Fetch from Opening Stock
        $osItems = OpeningStockItem::with('openingStock')
            ->where('item_id', $itemId)
            ->whereHas('openingStock', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id', 'asc')
            ->get();

        if ($osItems->isEmpty()) {
            $osItems = OpeningStockItem::with('openingStock')
                ->where('item_id', $itemId)
                ->orderBy('id', 'asc')
                ->get();
        }

        $allPurchases = $piItems->concat($osItems);

        $batches = [];

        foreach ($allPurchases as $p) {
            $bNo = !empty($p->batch_no) ? trim($p->batch_no) : '';
            $exp = null;
            if (!empty($p->exp_date)) {
                try {
                    $exp = Carbon::parse($p->exp_date)->format('Y-m-d');
                } catch (\Throwable) {
                    $exp = substr((string) $p->exp_date, 0, 10);
                }
            }

            // Key on Batch No if present; otherwise Expiry Date if present; else DEFAULT
            $key = $bNo !== '' ? $bNo : ($exp ?: 'DEFAULT');

            $cost = (float) (($p->cost_price ?? 0) > 0 ? $p->cost_price : ($item->cost_price ?? 0));
            $sell = (float) (($p->sell_price ?? 0) > 0 ? $p->sell_price : ($item->sell_price ?? 0));
            $mrp  = (float) (($p->mrp ?? 0) > 0 ? $p->mrp : ($item->mrp ?? 0));
            $inQty = (float) ($p->qty ?? 0) + (float) ($p->free_qty ?? 0);

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'item_id' => $itemId,
                    'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                    'item_name' => $item->name,
                    'batch_no' => $bNo,
                    'exp_date' => $exp,
                    'cost_price' => $cost,
                    'sell_price' => $sell,
                    'mrp' => $mrp,
                    'purchased_qty' => $inQty,
                    'remaining_qty' => 0.0,
                ];
            } else {
                $batches[$key]['purchased_qty'] += $inQty;
                // Preserve batch attributes
                if ($cost > 0) $batches[$key]['cost_price'] = $cost;
                if ($sell > 0) $batches[$key]['sell_price'] = $sell;
                if ($mrp > 0) $batches[$key]['mrp'] = $mrp;
                if ($exp && !$batches[$key]['exp_date']) $batches[$key]['exp_date'] = $exp;
            }
        }

        // Also add any batches present in StockLedger not in purchases
        foreach ($ledgerBatchMap as $key => $lData) {
            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'item_id' => $itemId,
                    'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                    'item_name' => $item->name,
                    'batch_no' => $lData['batch_no'] ?: ($key !== 'DEFAULT' ? $key : ''),
                    'exp_date' => $lData['exp_date'] ?: null,
                    'cost_price' => (float) ($item->cost_price ?? 0),
                    'sell_price' => (float) ($item->sell_price ?? 0),
                    'mrp' => (float) ($item->mrp ?? 0),
                    'purchased_qty' => $lData['total_in'],
                    'remaining_qty' => 0.0,
                ];
            }
        }

        // Apply remaining quantities from ledger
        foreach ($batches as $key => &$bData) {
            if (isset($ledgerBatchMap[$key])) {
                $bData['remaining_qty'] = (float) $ledgerBatchMap[$key]['remaining'];
            } elseif ($bData['batch_no'] && isset($ledgerBatchMap[$bData['batch_no']])) {
                $bData['remaining_qty'] = (float) $ledgerBatchMap[$bData['batch_no']]['remaining'];
            } elseif ($bData['exp_date'] && isset($ledgerBatchMap[$bData['exp_date']])) {
                $bData['remaining_qty'] = (float) $ledgerBatchMap[$bData['exp_date']]['remaining'];
            } else {
                $bData['remaining_qty'] = $bData['purchased_qty'];
            }
        }
        unset($bData);

        return collect(array_values($batches));
    }

    /**
     * Resolves a single batch's inventory attributes and remaining quantity.
     */
    public function getBatchStock(int $itemId, int $branchId, string $batchNo): ?array
    {
        $batches = $this->getItemBatches($itemId, $branchId);

        return $batches->first(function ($b) use ($batchNo) {
            return strcasecmp(trim((string)$b['batch_no']), trim($batchNo)) === 0;
        });
    }
}
