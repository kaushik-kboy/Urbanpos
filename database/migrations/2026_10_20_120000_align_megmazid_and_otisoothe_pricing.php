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
        // 1. Megmazid 600 MG Tab (item_code: 10393):
        // Align master item to per-tablet rate: Cost = 21.00, Sell/MRP = 38.10
        DB::table('items')
            ->where('item_code', '10393')
            ->update([
                'cost_price'   => 21.00,
                'landing_cost' => 21.00,
                'sell_price'   => 38.10,
                'mrp'          => 38.10,
                'updated_at'   => now(),
            ]);

        $megmazid = DB::table('items')->where('item_code', '10393')->first();
        if ($megmazid) {
            DB::table('item_stocks')
                ->where('item_id', $megmazid->id)
                ->update([
                    'sell_price' => 38.10,
                    'mrp'        => 38.10,
                    'updated_at' => now(),
                ]);
        }

        // 2. Otisoothe (item_code: 2953):
        // Fix Motera branch stock so Sell Price <= MRP (MRP is 400.00, set sell_price = 400.00)
        $otisoothe = DB::table('items')->where('item_code', '2953')->first();
        if ($otisoothe) {
            DB::table('item_stocks')
                ->where('item_id', $otisoothe->id)
                ->where('branch_id', 1) // Motera branch
                ->where('mrp', 400.00)
                ->update([
                    'sell_price' => 400.00,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data alignment migration; no rollback necessary.
    }
};
