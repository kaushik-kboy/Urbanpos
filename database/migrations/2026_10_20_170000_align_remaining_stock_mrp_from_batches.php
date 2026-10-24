<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Explicitly align Item 6993 (Drools Focus Adult 12 kg)
        $item6993 = DB::table('items')->where('item_code', '6993')->first();
        if ($item6993) {
            DB::table('items')->where('id', $item6993->id)->update([
                'cost_price'   => 2907.97,
                'landing_cost' => 2907.97,
                'sell_price'   => 5700.00,
                'mrp'          => 5700.00,
                'updated_at'   => now(),
            ]);

            DB::table('item_stocks')->where('item_id', $item6993->id)->update([
                'cost_price' => 2907.97,
                'sell_price' => 5700.00,
                'mrp'        => 5700.00,
                'updated_at' => now(),
            ]);
        }

        // 2. Align all remaining item_stocks where sell_price > mrp
        // (Caused by historical TruePOS OP1/OS1 revaluations setting lower outdated MRP in item_stocks)
        $stocksToFix = DB::table('item_stocks')
            ->whereRaw('sell_price > mrp')
            ->get();

        foreach ($stocksToFix as $st) {
            $cs = DB::table('closing_stocks')
                ->where('item_id', $st->item_id)
                ->where('branch_id', $st->branch_id)
                ->where('closing_stock', '>', 0)
                ->whereNotIn('batch_no', ['OP1', 'OS1'])
                ->orderByDesc('id')
                ->first();

            if ($cs && (float)$cs->mrp > 0) {
                $batchMrp = (float) $cs->mrp;
                $batchCost = (float) ($cs->net_cost > 0 ? $cs->net_cost : $st->cost_price);
                $batchSell = $batchMrp;

                DB::table('item_stocks')
                    ->where('id', $st->id)
                    ->update([
                        'cost_price' => $batchCost,
                        'sell_price' => $batchSell,
                        'mrp'        => $batchMrp,
                        'updated_at' => now(),
                    ]);
            } else {
                // Fallback: MRP can never be lower than retail sell price
                DB::table('item_stocks')
                    ->where('id', $st->id)
                    ->update([
                        'mrp'        => $st->sell_price,
                        'updated_at' => now(),
                    ]);
            }
        }

        // 3. Keep master items in sync with active branch stock
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE items
                SET mrp = (SELECT MAX(mrp) FROM item_stocks WHERE item_stocks.item_id = items.id AND mrp > 0),
                    sell_price = (SELECT MAX(sell_price) FROM item_stocks WHERE item_stocks.item_id = items.id AND sell_price > 0)
                WHERE id IN (
                    SELECT item_id FROM item_stocks WHERE mrp > 0
                ) AND (
                    mrp < (SELECT MAX(mrp) FROM item_stocks WHERE item_stocks.item_id = items.id AND mrp > 0)
                    OR sell_price < (SELECT MAX(sell_price) FROM item_stocks WHERE item_stocks.item_id = items.id AND sell_price > 0)
                )
            ");
        } else {
            DB::statement("
                UPDATE items i
                INNER JOIN (
                    SELECT item_id, MAX(mrp) AS max_mrp, MAX(sell_price) AS max_sell
                    FROM item_stocks
                    WHERE mrp > 0
                    GROUP BY item_id
                ) st ON st.item_id = i.id
                SET i.mrp = st.max_mrp,
                    i.sell_price = st.max_sell
                WHERE st.max_mrp > i.mrp OR st.max_sell > i.sell_price
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data alignment migration; rollback not necessary.
    }
};
