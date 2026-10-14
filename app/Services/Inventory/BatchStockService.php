<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\OpeningStockItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockLedger;
use App\Models\StockTransferItem;
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
            ->selectRaw("COALESCE(NULLIF(TRIM(batch_no), ''), '') as b_no, DATE(exp_date) as exp_date_val, SUM(qty_in) - SUM(qty_out) as remaining_qty, SUM(qty_in) as total_in")
            ->groupBy('b_no', DB::raw('DATE(exp_date)'))
            ->get();

        $ledgerBatchMap = [];
        foreach ($ledgerRows as $lr) {
            $bNo = $lr->b_no ? trim($lr->b_no) : ($lr->batch_no ? trim($lr->batch_no) : '');
            $expStr = $lr->exp_date_val ? trim((string) $lr->exp_date_val) : '';

            // If bNo is just a formatted date matching expStr (e.g. '30/05/2031' or '2031-05-30' or 'DEFAULT'), treat as unbatched expiry movement
            if ($bNo !== '' && $expStr !== '') {
                $expDmy = '';
                try {
                    $expDmy = Carbon::parse($expStr)->format('d/m/Y');
                } catch (\Throwable) {}
                if ($bNo === $expStr || $bNo === $expDmy || strcasecmp($bNo, 'DEFAULT') === 0) {
                    $bNo = '';
                }
            }

            $key = $bNo !== '' ? $bNo : ($expStr !== '' ? $expStr : 'DEFAULT');
            if (!isset($ledgerBatchMap[$key])) {
                $ledgerBatchMap[$key] = [
                    'remaining' => (float) $lr->remaining_qty,
                    'total_in' => (float) $lr->total_in,
                    'exp_date' => $expStr,
                    'batch_no' => $bNo,
                    'assigned' => 0.0,
                ];
            } else {
                $ledgerBatchMap[$key]['remaining'] += (float) $lr->remaining_qty;
                $ledgerBatchMap[$key]['total_in'] += (float) $lr->total_in;
            }
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

        // 4. Fetch inward transfers received by this branch
        $stItems = StockTransferItem::with('stockTransfer')
            ->where('item_id', $itemId)
            ->whereHas('stockTransfer', fn ($q) => $q->where('to_branch_id', $branchId)->where('status', 'Received'))
            ->orderBy('id', 'asc')
            ->get();

        $allPurchases = $piItems->concat($osItems)->concat($stItems);

        // Fallback: If no purchase or transfer records for this specific branch, check across all branches as metadata fallback for exp_date
        $isCrossBranchFallback = false;
        if ($allPurchases->isEmpty()) {
            $isCrossBranchFallback = true;
            $fallbackPi = PurchaseInvoiceItem::with('purchaseInvoice')
                ->where('item_id', $itemId)
                ->whereNotNull('exp_date')
                ->where('exp_date', '!=', '')
                ->where('exp_date', '!=', '0000-00-00')
                ->orderBy('exp_date', 'asc')
                ->get();
            $fallbackOs = OpeningStockItem::with('openingStock')
                ->where('item_id', $itemId)
                ->whereNotNull('exp_date')
                ->where('exp_date', '!=', '')
                ->where('exp_date', '!=', '0000-00-00')
                ->orderBy('exp_date', 'asc')
                ->get();
            $allPurchases = $fallbackPi->concat($fallbackOs);
        }

        $branchStock = \App\Models\ItemStock::where('item_id', $itemId)->where('branch_id', $branchId)->first();

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

            if ($p instanceof StockTransferItem) {
                $cost = (float) (($p->unit_cost ?? 0) > 0 ? $p->unit_cost : ($item->cost_price ?? 0));
                $mrp  = (float) (($p->mrp ?? 0) > 0 ? $p->mrp : ($item->mrp ?? 0));
                $sell = (float) ($p->sell_price ?? ($item->sell_price ?? $mrp));
                $inQty = (float) ($p->received_qty > 0 ? $p->received_qty : $p->qty);
            } else {
                $cost = (float) (($p->cost_price ?? 0) > 0 ? $p->cost_price : ($item->cost_price ?? 0));
                $sell = (float) (($p->sell_price ?? 0) > 0 ? $p->sell_price : ($item->sell_price ?? 0));
                $mrp  = (float) (($p->mrp ?? 0) > 0 ? $p->mrp : ($item->mrp ?? 0));
                $inQty = (float) ($p->qty ?? 0) + (float) ($p->free_qty ?? 0);
            }

            // If using cross-branch fallback, preserve this branch's own stock pricing if set
            if ($isCrossBranchFallback && $branchStock) {
                if ($branchStock->cost_price > 0) $cost = (float) $branchStock->cost_price;
                if ($branchStock->sell_price > 0) $sell = (float) $branchStock->sell_price;
                if ($branchStock->mrp > 0) $mrp = (float) $branchStock->mrp;
            }

            if ($rawBNo !== '') {
                $displayBatchNo = $rawBNo;
                // Merge only if same batch_no, same expiry, and same prices
                $key = 'B_' . $rawBNo . '|' . round($cost, 2) . '|' . round($sell, 2) . '|' . round($mrp, 2) . '|' . ($exp ?: '');
            } else {
                if ($exp !== null) {
                    // Purchases/transfers with same expiry date AND same sell price / MRP merge into one batch; different prices stay separate
                    $key = 'EXP_' . $exp . '|' . round($cost, 2) . '|' . round($sell, 2) . '|' . round($mrp, 2);
                    $displayBatchNo = Carbon::parse($exp)->format('d/m/Y');
                } else {
                    if ($p instanceof PurchaseInvoiceItem && $p->purchaseInvoice) {
                        $invNum = $p->purchaseInvoice->supplier_inv_no ?: $p->purchaseInvoice->invoice_number;
                        $invDate = $p->purchaseInvoice->invoice_date ? Carbon::parse($p->purchaseInvoice->invoice_date)->format('d/m/Y') : null;
                        $displayBatchNo = $invNum ? ($invNum . ($invDate ? " ({$invDate})" : '')) : ($invDate ? "Lot {$invDate}" : ('Lot #' . $p->id));
                    } elseif ($p instanceof OpeningStockItem && $p->openingStock) {
                        $osDate = $p->openingStock->opening_date ? Carbon::parse($p->openingStock->opening_date)->format('d/m/Y') : null;
                        $displayBatchNo = $osDate ? "Opening ({$osDate})" : 'Opening Stock';
                    } elseif ($p instanceof StockTransferItem && $p->stockTransfer) {
                        $stNum = $p->stockTransfer->transfer_number;
                        $stDate = $p->stockTransfer->transfer_date ? Carbon::parse($p->stockTransfer->transfer_date)->format('d/m/Y') : null;
                        $displayBatchNo = $stNum ? ($stNum . ($stDate ? " ({$stDate})" : '')) : ($stDate ? "Transfer {$stDate}" : ('Transfer #' . $p->id));
                    } else {
                        $displayBatchNo = 'Lot #' . $p->id;
                    }
                    $key = 'SRC_' . (get_class($p) === 'App\Models\StockTransferItem' ? 'ST_' : (get_class($p) === 'App\Models\OpeningStockItem' ? 'OS_' : 'PI_')) . $p->id . '|' . round($cost, 2) . '|' . round($sell, 2) . '|' . round($mrp, 2);
                    $unassignedKeys[$key] = true;
                }
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
                    'source' => ($p instanceof StockTransferItem ? 'transfer' : ($p instanceof OpeningStockItem ? 'opening' : 'purchase')),
                ];
            } else {
                $batches[$key]['purchased_qty'] += $inQty;
                if ($exp && !$batches[$key]['exp_date']) $batches[$key]['exp_date'] = $exp;
                if ($sell > 0) $batches[$key]['sell_price'] = $sell;
                if ($mrp > 0) $batches[$key]['mrp'] = $mrp;
                if ($cost > 0) $batches[$key]['cost_price'] = $cost;
            }
        }

        // Count how many purchase batches map to each ledger key
        $matchCounts = [];
        foreach ($batches as $k => $b) {
            $raw = $b['raw_batch_no'] ?? '';
            $bNo = $b['batch_no'] ?? '';
            $exp = $b['exp_date'] ?? '';
            $mk = ($raw !== '' && isset($ledgerBatchMap[$raw])) ? $raw : (($bNo !== '' && isset($ledgerBatchMap[$bNo])) ? $bNo : (($raw === '' && $exp !== '' && isset($ledgerBatchMap[$exp])) ? $exp : null));
            if ($mk !== null) {
                $matchCounts[$mk] = ($matchCounts[$mk] ?? 0) + 1;
            }
        }

        // Apply remaining quantities from StockLedger to matching purchase batches
        $namedBatchRemaining = 0.0;
        $seenMatches = [];
        foreach ($batches as $key => &$bData) {
            $raw = $bData['raw_batch_no'] ?? '';
            $bNo = $bData['batch_no'] ?? '';
            $exp = $bData['exp_date'] ?? '';
            $pQty = (float) $bData['purchased_qty'];

            $matchedKey = null;
            if ($raw !== '' && isset($ledgerBatchMap[$raw])) {
                $matchedKey = $raw;
            } elseif ($bNo !== '' && isset($ledgerBatchMap[$bNo])) {
                $matchedKey = $bNo;
            } elseif ($raw === '' && $exp !== '' && isset($ledgerBatchMap[$exp])) {
                $matchedKey = $exp;
            }

            if ($matchedKey !== null) {
                $seenMatches[$matchedKey] = ($seenMatches[$matchedKey] ?? 0) + 1;
                $isLast = ($seenMatches[$matchedKey] === $matchCounts[$matchedKey]);
                $ledgerRem = max(0.0, $ledgerBatchMap[$matchedKey]['remaining'] - $ledgerBatchMap[$matchedKey]['assigned']);
                $alloc = $isLast ? $ledgerRem : min($pQty, $ledgerRem);
                $bData['remaining_qty'] = $alloc;
                $ledgerBatchMap[$matchedKey]['assigned'] += $alloc;
                $namedBatchRemaining += $alloc;
            } elseif (!isset($unassignedKeys[$key])) {
                $bData['remaining_qty'] = $pQty;
                $namedBatchRemaining += $bData['remaining_qty'];
            }
        }
        unset($bData);

        // Also add any batches present in StockLedger not fully assigned to purchases
        foreach ($ledgerBatchMap as $key => $lData) {
            $unassignedInLedger = max(0.0, $lData['remaining'] - ($lData['assigned'] ?? 0));
            if ($unassignedInLedger <= 0) {
                continue; // Fully matched to actual purchases!
            }
            $lBNo = $lData['batch_no'] ?: ($key !== 'DEFAULT' ? $key : '');
            if ($lBNo !== '') {
                $batches['L_' . $lBNo] = [
                    'item_id' => $itemId,
                    'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                    'item_name' => $item->name,
                    'batch_no' => $lBNo,
                    'raw_batch_no' => $lData['batch_no'] ?: '',
                    'exp_date' => $lData['exp_date'] ?: null,
                    'cost_price' => (float) ($item->cost_price ?? 0),
                    'sell_price' => (float) ($item->sell_price ?? 0),
                    'mrp' => (float) ($item->mrp ?? 0),
                    'purchased_qty' => $lData['total_in'],
                    'remaining_qty' => $unassignedInLedger,
                    'source' => 'ledger',
                ];
                $namedBatchRemaining += $unassignedInLedger;
            }
        }

        // Fetch current physical branch stock
        $currentBranchStock = (float) (\App\Models\ItemStock::where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->value('quantity') ?? 0);

        if (empty($ledgerBatchMap) && !empty($batches)) {
            // FIFO allocation across purchases by expiry date
            $totalIn = array_sum(array_column($batches, 'purchased_qty'));
            $effectiveStock = max(0.0, (float) $currentBranchStock);
            $consumedQty = max(0.0, $totalIn - $effectiveStock);

            $toDeduct = $consumedQty;
            foreach ($batches as &$bData) {
                $batchOrig = (float) $bData['purchased_qty'];
                if ($toDeduct >= $batchOrig) {
                    $bData['remaining_qty'] = 0.0;
                    $toDeduct -= $batchOrig;
                } else {
                    $bData['remaining_qty'] = max(0.0, round($batchOrig - $toDeduct, 3));
                    $toDeduct = 0.0;
                }
            }
            unset($bData);
        }

        // 2. For unassigned / lot purchase batches (where neither batch_no nor expiry was stamped):
        // Remaining unassigned stock is the branch physical stock minus what is in named batches.
        $unassignedAvailable = max(0.0, round($currentBranchStock - $namedBatchRemaining, 3));
        $unassignedCount = count($unassignedKeys);

        if ($unassignedCount > 0) {
            $remToDistribute = $unassignedAvailable;
            $i = 0;
            foreach ($batches as $key => &$bData) {
                if (isset($unassignedKeys[$key])) {
                    $i++;
                    $pQty = (float) $bData['purchased_qty'];
                    if ($i === $unassignedCount) {
                        $bData['remaining_qty'] = $remToDistribute;
                    } else {
                        $alloc = min($pQty, $remToDistribute);
                        $bData['remaining_qty'] = $alloc;
                        $remToDistribute = max(0.0, round($remToDistribute - $alloc, 3));
                    }
                }
            }
            unset($bData);
        }

        // 3. If no batches exist but branch has physical stock (e.g. from transfer in or manual stock), provide default batch
        if (empty($batches) && $currentBranchStock > 0) {
            $batches['DEFAULT'] = [
                'item_id' => $itemId,
                'item_code' => $item->item_code ?: ($item->ean_upc_code ?: ''),
                'item_name' => $item->name,
                'batch_no' => 'DEFAULT',
                'raw_batch_no' => '',
                'exp_date' => null,
                'cost_price' => (float) ($item->cost_price ?? 0),
                'sell_price' => (float) ($item->sell_price ?? 0),
                'mrp' => (float) ($item->mrp ?? 0),
                'purchased_qty' => $currentBranchStock,
                'remaining_qty' => $currentBranchStock,
            ];
        }

        if ($currentBranchStock <= 0 && !($item->allow_negative_stock ?? false)) {
            foreach ($batches as &$bData) {
                $bData['remaining_qty'] = 0.0;
            }
            unset($bData);
        } else if ($currentBranchStock > 0) {
            // Safety cap: total batch remaining should never exceed physical branch stock
            $batchTotal = array_sum(array_column($batches, 'remaining_qty'));
            if ($batchTotal > $currentBranchStock) {
                $excess = $batchTotal - $currentBranchStock;
                $revKeys = array_reverse(array_keys($batches));
                foreach ($revKeys as $rk) {
                    if ($excess <= 0) break;
                    $cur = (float) $batches[$rk]['remaining_qty'];
                    $ded = min($cur, $excess);
                    $batches[$rk]['remaining_qty'] = max(0.0, round($cur - $ded, 3));
                    $excess -= $ded;
                }
            }
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
