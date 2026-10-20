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
        // 1. Explicitly align Item 7022 (Vet Pro Obesity 3Kg)
        $item7022 = DB::table('items')->where('item_code', '7022')->first();
        if ($item7022) {
            DB::table('items')->where('id', $item7022->id)->update([
                'sell_price' => 1699.00,
                'mrp'        => 1699.00,
            ]);
            DB::table('item_stocks')->where('item_id', $item7022->id)->update([
                'sell_price' => 1699.00,
                'mrp'        => 1699.00,
            ]);
            DB::table('closing_stocks')->where('item_id', $item7022->id)->where('closing_stock', '>', 0)->update([
                'mrp' => 1699.00,
            ]);
            DB::table('stock_transfer_items')->where('item_id', $item7022->id)->update([
                'mrp'        => 1699.00,
                'sell_price' => 1699.00,
            ]);
        }

        // 2. Explicitly align Item 2838 (INTAS Kiskin Lotion 100 ml)
        $item2838 = DB::table('items')->where('item_code', '2838')->first();
        if ($item2838) {
            DB::table('items')->where('id', $item2838->id)->update([
                'sell_price' => 210.00,
                'mrp'        => 210.00,
            ]);
            DB::table('item_stocks')->where('item_id', $item2838->id)->update([
                'sell_price' => 210.00,
                'mrp'        => 210.00,
            ]);
            DB::table('closing_stocks')->where('item_id', $item2838->id)->where('closing_stock', '>', 0)->update([
                'mrp' => 210.00,
            ]);
        }

        // 3. Explicitly align Item 2974 (Vivaldis Vet Thyro 0.6 10)
        $item2974 = DB::table('items')->where('item_code', '2974')->first();
        if ($item2974) {
            DB::table('items')->where('id', $item2974->id)->update([
                'sell_price' => 600.00,
                'mrp'        => 600.00,
            ]);
            DB::table('item_stocks')->where('item_id', $item2974->id)->update([
                'sell_price' => 600.00,
                'mrp'        => 600.00,
            ]);
            DB::table('closing_stocks')->where('item_id', $item2974->id)->where('closing_stock', '>', 0)->update([
                'mrp' => 600.00,
            ]);
        }

        // 4. Align all remaining item_stocks where branch price is outdated (< master price)
        DB::update("
            UPDATE item_stocks ist
            JOIN items i ON i.id = ist.item_id
            SET ist.mrp = i.mrp,
                ist.sell_price = i.sell_price,
                ist.updated_at = NOW()
            WHERE i.mrp > 0
              AND (ist.mrp < i.mrp OR ist.sell_price < i.sell_price)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data alignment migration
    }
};
