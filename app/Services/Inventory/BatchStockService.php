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

        // 2. Fetch purchase batches strictly from Purchase Invoices in this branch
        $piItems = PurchaseInvoiceItem::with('purchaseInvoice')
            ->where('item_id', $itemId)
            ->whereHas('purchaseInvoice', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id', 'asc')
            ->get();

        // 3. Fetch from Opening Stock strictly in this branch
        $osItems = OpeningStockItem::with('openingStock')
            ->where('item_id', $itemId)
            ->whereHas('openingStock', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id', 'asc')
            ->get();

        $allPurchases = $piItems->concat($osItems);

        $batches = [];
        $unassignedKeys = [];

        foreach ($allPurchases as $p) {
            $rawBNo = !empty($p->batch_no) ? trim((string)$p->batch_no) : '';
            $exp = null;
            if (!empty($p->exp_date)) {
                try {
                    $exp = Carbon::parse($p->exp_date)->format('Y-m-d');
                } catch (\Throwable) {
                    $exp = substr((string) $p->exp_date, 0, 10);
                }
            }

            $cost = (float) (($p->cost_price ?? 0) > 0 ? $p->cost_price : ($item->cost_price ?? 0));
            $sell = (float) (($p->sell_price ?? 0) > 0 ? $p->sell_price : ($item->sell_price ?? 0));
            $mrp  = (float) (($p->mrp ?? 0) > 0 ? $p->mrp : ($item->mrp ?? 0));
            $inQty = (float) ($p->qty ?? 0) + (float) ($p->free_qty ?? 0);

            if ($rawBNo !== '') {
                $displayBatchNo = $rawBNo;
                // Merge only if same batch_no, same expiry, and same prices
                $key = 'B_' . $rawBNo . '|' . round($cost, 2) . '|' . round($sell, 2) . '|' . round($mrp, 2) . '|' . ($exp ?: '');
            } else {
                // When batch_no is omitted, assign a recognizable purchase/lot label and preserve distinct prices
                if ($p instanceof PurchaseInvoiceItem && $p->purchaseInvoice) {
                    $invNum = $p->purchaseInvoice->supplier_inv_no ?: $p->purchaseInvoice->invoice_number;
                    $invDate = $p->purchaseInvoice->invoice_date ? Carbon::parse($p->purchaseInvoice->invoice_date)->format('d/m/Y') : null;
                    $displayBatchNo = $invNum ? ($invNum . ($invDate ? " ({$invDate})" : '')) : ($invDate ? "Lot {$invDate}" : ('Lot #' . $p->id));
                } elseif ($p instanceof OpeningStockItem && $p->openingStock) {
                    $osDate = $p->openingStock->opening_date ? Carbon::parse($p->openingStock->opening_date)->format('d/m/Y') : null;
                    $displayBatchNo = $osDate ? "Opening ({$osDate})" : 'Opening Stock';
                } else {
                    $displayBatchNo = 'Lot #' . $p->id;
                }
                $key = 'PI_' . $p->id . '|' . round($cost, 2) . '|' . round($sell, 2) . '|' . round($mrp, 2) . '|' . ($exp ?: '');
                $unassignedKeys[$key] = true;
            }

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'item_id' => $itemId,
                    'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                    'item_name' => $item->name,
                    'batch_no' => $displayBatchNo,
                    'raw_batch_no' => $rawBNo,
                    'exp_date' => $exp,
                    'cost_price' => $cost,
                    'sell_price' => $sell,
                    'mrp' => $mrp,
                    'purchased_qty' => $inQty,
                    'remaining_qty' => 0.0,
                ];
            } else {
                $batches[$key]['purchased_qty'] += $inQty;
                if ($exp && !$batches[$key]['exp_date']) $batches[$key]['exp_date'] = $exp;
            }
        }

        // Also add any batches present in StockLedger not in purchases
        foreach ($ledgerBatchMap as $key => $lData) {
            $lBNo = $lData['batch_no'] ?: ($key !== 'DEFAULT' ? $key : '');
            if ($lBNo !== '') {
                $foundExisting = false;
                foreach ($batches as $b) {
                    if (strcasecmp((string)$b['batch_no'], $lBNo) === 0 || strcasecmp((string)($b['raw_batch_no'] ?? ''), $lBNo) === 0) {
                        $foundExisting = true;
                        break;
                    }
                }
                if (!$foundExisting) {
                    $batches['L_' . $lBNo] = [
                        'item_id' => $itemId,
                        'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                        'item_name' => $item->name,
                        'batch_no' => $lBNo,
                        'raw_batch_no' => $lBNo,
                        'exp_date' => $lData['exp_date'] ?: null,
                        'cost_price' => (float) ($item->cost_price ?? 0),
                        'sell_price' => (float) ($item->sell_price ?? 0),
                        'mrp' => (float) ($item->mrp ?? 0),
                        'purchased_qty' => $lData['total_in'],
                        'remaining_qty' => (float) $lData['remaining'],
                    ];
                }
            }
        }

        // Apply remaining quantities from ledger:
        // 1. For batches with specific tracked raw_batch_no in StockLedger:
        foreach ($batches as $key => &$bData) {
            $raw = $bData['raw_batch_no'] ?? '';
            $bNo = $bData['batch_no'];
            if ($raw !== '' && isset($ledgerBatchMap[$raw])) {
                $bData['remaining_qty'] = (float) $ledgerBatchMap[$raw]['remaining'];
            } elseif ($bNo !== '' && isset($ledgerBatchMap[$bNo])) {
                $bData['remaining_qty'] = (float) $ledgerBatchMap[$bNo]['remaining'];
            } elseif (!isset($unassignedKeys[$key])) {
                $bData['remaining_qty'] = $bData['purchased_qty'];
            }
        }
        unset($bData);

        // 2. For unassigned / lot purchase batches (where no specific batch_no was stamped):
        // Consume unassigned outward movement FIFO across chronological purchases
        $unassignedOut = (float) StockLedger::where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->whereNull('batch_no')->orWhere('batch_no', '');
            })
            ->sum('qty_out');

        $remOutToDeduct = $unassignedOut;
        foreach ($batches as $key => &$bData) {
            if (isset($unassignedKeys[$key])) {
                $inQty = (float) $bData['purchased_qty'];
                if ($remOutToDeduct >= $inQty) {
                    $bData['remaining_qty'] = 0.0;
                    $remOutToDeduct -= $inQty;
                } else {
                    $bData['remaining_qty'] = max(0.0, round($inQty - $remOutToDeduct, 3));
                    $remOutToDeduct = 0.0;
                }
            }
        }
        unset($bData);

        // Cap remaining quantities by branch stock
        $currentBranchStock = (float) (\App\Models\ItemStock::where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->value('quantity') ?? 0);

        if ($currentBranchStock <= 0 && !($item->allow_negative_stock ?? false)) {
            foreach ($batches as &$bData) {
                $bData['remaining_qty'] = 0.0;
            }
            unset($bData);
        }

        return collect(array_values($batches));
    }

    /**
     * Resolves a single batch's inventory attributes and remaining quantity.
     */
    public function getBatchStock(int $itemId, int $branchId, string $batchNo): ?array
    {
        $batches = $this->getItemBatches($itemId, $branchId);

        $found = $batches->first(function ($b) use ($batchNo) {
            return strcasecmp(trim((string)$b['batch_no']), trim($batchNo)) === 0
                || strcasecmp(trim((string)($b['raw_batch_no'] ?? '')), trim($batchNo)) === 0;
        });

        if (!$found && in_array(strtoupper(trim($batchNo)), ['', 'DEFAULT', 'NO BATCH', 'LOT'], true)) {
            $found = $batches->firstWhere('remaining_qty', '>', 0) ?? $batches->first();
        }

        return $found;
    }
}
