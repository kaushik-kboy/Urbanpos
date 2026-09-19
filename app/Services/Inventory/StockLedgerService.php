<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single writer for stock quantity/value movement. Every controller that changes
 * physical stock must go through post() (or reverse()) instead of touching ItemStock
 * directly, so item_stocks stays a derived cache and stock_ledger stays the append-only
 * source of truth. See docs/POS foundation spec — Stock Ledger + Cost/Valuation engines.
 */
class StockLedgerService
{
    /**
     * @param  float  $qtyDelta  Positive for incoming stock, negative for outgoing.
     * @param  float|null  $unitCost  Cost to use for an incoming movement. Ignored for
     *                                outgoing movements — those always release stock at
     *                                the item's current moving-weighted-average cost.
     *                                Null on an incoming movement means "cost-neutral"
     *                                (keeps the existing average unchanged) — used by the
     *                                legacy ItemStock::adjust() forwarder, which has no
     *                                cost context to supply.
     * @return StockLedger The posted ledger row. Its unit_cost is the cost actually used
     *                      for this movement — on an outgoing movement this is the COGS
     *                      the caller should persist as e.g. sales_bill_items.cost_at_sale.
     */
    public function post(
        int $itemId,
        int $branchId,
        string $movementType,
        float $qtyDelta,
        ?float $unitCost,
        ?string $referenceType,
        ?int $referenceId,
        string $documentDate,
        ?int $userId = null,
        ?string $reasonCode = null,
        ?int $reversalOf = null,
        ?string $expDate = null,
    ): StockLedger {
        if ($qtyDelta === 0.0) {
            throw new \InvalidArgumentException('StockLedgerService::post() called with a zero quantity delta.');
        }

        return DB::transaction(function () use (
            $itemId, $branchId, $movementType, $qtyDelta, $unitCost,
            $referenceType, $referenceId, $documentDate, $userId, $reasonCode, $reversalOf, $expDate,
        ) {
            $stock = ItemStock::firstOrCreate(
                ['item_id' => $itemId, 'branch_id' => $branchId],
                ['quantity' => 0, 'cost_price' => 0]
            );
            $stock = ItemStock::whereKey($stock->id)->lockForUpdate()->first();

            $oldQty = (float) $stock->quantity;
            $oldAvgCost = (float) $stock->cost_price;

            if ($qtyDelta > 0) {
                $incomingCost = $unitCost ?? $oldAvgCost;
                $newQty = $oldQty + $qtyDelta;
                $newAvgCost = $newQty > 0
                    ? (($oldQty * $oldAvgCost) + ($qtyDelta * $incomingCost)) / $newQty
                    : $incomingCost;

                $qtyIn = $qtyDelta;
                $qtyOut = 0.0;
                $valueIn = round($qtyDelta * $incomingCost, 2);
                $valueOut = 0.0;
                $costUsed = $incomingCost;
            } else {
                $qtyOutAbs = abs($qtyDelta);
                if (round($oldQty, 4) < round($qtyOutAbs, 4)) {
                    $item = Item::find($itemId);
                    $itemName = $item ? $item->name : "Item #{$itemId}";
                    throw ValidationException::withMessages([
                        'stock' => "Stock cannot be negative for \"{$itemName}\". Available: {$oldQty}, attempted to deduct: {$qtyOutAbs}.",
                    ]);
                }
                $newQty = max(0.0, round($oldQty - $qtyOutAbs, 4));
                $newAvgCost = $oldAvgCost; // outgoing movements never change the average

                $qtyIn = 0.0;
                $qtyOut = $qtyOutAbs;
                $valueIn = 0.0;
                $valueOut = round($qtyOutAbs * $oldAvgCost, 2);
                $costUsed = $oldAvgCost;
            }

            $newValue = round($newQty * $newAvgCost, 2);

            $stock->update([
                'quantity' => $newQty,
                'cost_price' => $newAvgCost,
            ]);

            return StockLedger::create([
                'item_id' => $itemId,
                'branch_id' => $branchId,
                'exp_date' => $expDate,
                'movement_type' => $movementType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'unit_cost' => round($costUsed, 2),
                'value_in' => $valueIn,
                'value_out' => $valueOut,
                'running_balance_qty' => $newQty,
                'running_balance_value' => $newValue,
                'user_id' => $userId ?? Auth::id(),
                'reason_code' => $reasonCode,
                'reversal_of' => $reversalOf,
                'document_date' => $documentDate,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Posts an equal-and-opposite ledger row for every not-yet-reversed movement tied to
     * a source document, instead of deleting history. Safe to call more than once — rows
     * that already have a reversal are skipped.
     */
    public function reverseByReference(string $referenceType, int $referenceId): void
    {
        $originals = StockLedger::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of')
            ->get();

        foreach ($originals as $original) {
            $alreadyReversed = StockLedger::where('reversal_of', $original->id)->exists();
            if ($alreadyReversed) {
                continue;
            }

            $qtyDelta = (float) $original->qty_in > 0
                ? -1 * (float) $original->qty_in
                : (float) $original->qty_out;

            $this->post(
                itemId: $original->item_id,
                branchId: $original->branch_id,
                movementType: $original->movement_type,
                qtyDelta: $qtyDelta,
                unitCost: (float) $original->unit_cost,
                referenceType: $referenceType,
                referenceId: $referenceId,
                documentDate: now()->toDateString(),
                userId: $original->user_id,
                reasonCode: 'REVERSAL',
                reversalOf: $original->id,
                expDate: $original->exp_date?->toDateString(),
            );
        }
    }
}
