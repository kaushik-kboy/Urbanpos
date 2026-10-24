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
        // 1. Item Code: 7022 - Vet Pro Obesity 3Kg
        $item7022 = DB::table('items')->where('item_code', '7022')->first();
        if ($item7022) {
            DB::table('items')->where('id', $item7022->id)->update([
                'cost_price'   => 863.90,
                'landing_cost' => 863.90,
                'sell_price'   => 1699.00,
                'mrp'          => 1699.00,
                'updated_at'   => now(),
            ]);

            // Branch 1 (Motera): Active Batches MRP is 1699.00
            DB::table('item_stocks')
                ->where('item_id', $item7022->id)
                ->where('branch_id', 1)
                ->update([
                    'cost_price' => 863.90,
                    'sell_price' => 1699.00,
                    'mrp'        => 1699.00,
                    'updated_at' => now(),
                ]);

            // Branch 2 (Satellite): Active Batch MRP is 1549.00
            DB::table('item_stocks')
                ->where('item_id', $item7022->id)
                ->where('branch_id', 2)
                ->update([
                    'cost_price' => 787.63,
                    'sell_price' => 1549.00,
                    'mrp'        => 1549.00,
                    'updated_at' => now(),
                ]);
        }

        // 2. Item Code: 2914 - VIRBAC IPRAZ TABLET
        $item2914 = DB::table('items')->where('item_code', '2914')->first();
        if ($item2914) {
            DB::table('items')->where('id', $item2914->id)->update([
                'cost_price'   => 62.08,
                'landing_cost' => 62.08,
                'sell_price'   => 101.72,
                'mrp'          => 101.72,
                'updated_at'   => now(),
            ]);

            DB::table('item_stocks')
                ->where('item_id', $item2914->id)
                ->update([
                    'cost_price' => 62.08,
                    'sell_price' => 101.72,
                    'mrp'        => 101.72,
                    'updated_at' => now(),
                ]);
        }

        // 3. Item Code: 2838 - INTAS Kiskin Lotion 100 ml
        $item2838 = DB::table('items')->where('item_code', '2838')->first();
        if ($item2838) {
            DB::table('items')->where('id', $item2838->id)->update([
                'cost_price'   => 89.91,
                'landing_cost' => 89.91,
                'sell_price'   => 210.00,
                'mrp'          => 210.00,
                'updated_at'   => now(),
            ]);

            DB::table('item_stocks')
                ->where('item_id', $item2838->id)
                ->update([
                    'cost_price' => 89.91,
                    'sell_price' => 210.00,
                    'mrp'        => 210.00,
                    'updated_at' => now(),
                ]);
        }

        // 4. Item Code: 2844 - DRONTAL PUPPY SUSPENSION 20 ml
        $item2844 = DB::table('items')->where('item_code', '2844')->first();
        if ($item2844) {
            DB::table('items')->where('id', $item2844->id)->update([
                'cost_price'   => 148.99,
                'landing_cost' => 148.99,
                'sell_price'   => 238.00,
                'mrp'          => 238.00,
                'updated_at'   => now(),
            ]);

            DB::table('item_stocks')
                ->where('item_id', $item2844->id)
                ->update([
                    'cost_price' => 148.99,
                    'sell_price' => 238.00,
                    'mrp'        => 238.00,
                    'updated_at' => now(),
                ]);
        }

        // 5. Item Code: 7105 - N and D GF PKN CP Adult Med-Maxi 12kg
        $item7105 = DB::table('items')->where('item_code', '7105')->first();
        if ($item7105) {
            DB::table('items')->where('id', $item7105->id)->update([
                'cost_price'   => 7316.18,
                'landing_cost' => 7316.18,
                'sell_price'   => 12490.00,
                'mrp'          => 12490.00,
                'updated_at'   => now(),
            ]);

            DB::table('item_stocks')
                ->where('item_id', $item7105->id)
                ->update([
                    'cost_price' => 7316.18,
                    'sell_price' => 12490.00,
                    'mrp'        => 12490.00,
                    'updated_at' => now(),
                ]);
        }

        // 6. Item Code: 2974 - Vivaldis Vet Thyro 0.6 10
        $item2974 = DB::table('items')->where('item_code', '2974')->first();
        if ($item2974) {
            DB::table('items')->where('id', $item2974->id)->update([
                'cost_price'   => 302.53,
                'landing_cost' => 302.53,
                'sell_price'   => 600.00,
                'mrp'          => 600.00,
                'updated_at'   => now(),
            ]);

            // Fix Satellite branch stock MRP (was erroneously 400.00)
            DB::table('item_stocks')
                ->where('item_id', $item2974->id)
                ->where('branch_id', 2)
                ->update([
                    'cost_price' => 302.53,
                    'sell_price' => 600.00,
                    'mrp'        => 600.00,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive pricing alignment; rollback not necessary.
    }
};
