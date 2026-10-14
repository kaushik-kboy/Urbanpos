<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill ItemStock prices where mrp or sell_price are 0
        $stocks = \App\Models\ItemStock::with('item')->where('mrp', '<=', 0)->orWhere('sell_price', '<=', 0)->get();
        foreach ($stocks as $stock) {
            $item = $stock->item;
            if (!$item) continue;

            // Check latest purchase invoice item for this branch & item
            $latestPi = \App\Models\PurchaseInvoiceItem::with('purchaseInvoice')
                ->where('item_id', $stock->item_id)
                ->whereHas('purchaseInvoice', fn ($q) => $q->where('branch_id', $stock->branch_id))
                ->orderBy('id', 'desc')
                ->first();

            $updates = [];
            if ((float) $stock->mrp <= 0) {
                $updates['mrp'] = ($latestPi && (float) $latestPi->mrp > 0) ? (float) $latestPi->mrp : (float) ($item->mrp ?? 0);
            }
            if ((float) $stock->sell_price <= 0) {
                $updates['sell_price'] = ($latestPi && (float) $latestPi->sell_price > 0) ? (float) $latestPi->sell_price : (float) ($item->sell_price ?? 0);
            }
            if ((float) $stock->cost_price <= 0) {
                $updates['cost_price'] = ($latestPi && (float) $latestPi->cost_price > 0) ? (float) $latestPi->cost_price : (float) ($item->cost_price ?? 0);
            }
            if (!empty($updates)) {
                $stock->update($updates);
            }
        }

        // 2. Normalize StockLedger rows where batch_no was mistakenly saved as formatted expiry date or 'DEFAULT'
        $ledgers = \App\Models\StockLedger::whereNotNull('batch_no')->where('batch_no', '!=', '')->get();
        foreach ($ledgers as $l) {
            $b = trim((string) $l->batch_no);
            $exp = $l->exp_date ? \Carbon\Carbon::parse($l->exp_date)->format('Y-m-d') : '';
            $expDmy = $l->exp_date ? \Carbon\Carbon::parse($l->exp_date)->format('d/m/Y') : '';
            if ($b === $exp || $b === $expDmy || strcasecmp($b, 'DEFAULT') === 0) {
                $l->update(['batch_no' => null]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive data repair migration; no reversal needed
    }
};
