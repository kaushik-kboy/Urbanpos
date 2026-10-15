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
        // 1. Update item_stocks where sell_price is less than MRP or <= 0
        DB::statement("
            UPDATE item_stocks
            SET sell_price = mrp
            WHERE mrp > 0 AND (sell_price <= 0 OR sell_price < mrp)
        ");

        // 2. Update items master table where stock has newer MRP than master item
        // Bring items.mrp and items.sell_price in sync with latest active branch stock
        DB::statement("
            UPDATE items i
            INNER JOIN (
                SELECT item_id, MAX(mrp) AS max_mrp
                FROM item_stocks
                WHERE mrp > 0
                GROUP BY item_id
            ) st ON st.item_id = i.id
            SET i.mrp = st.max_mrp,
                i.sell_price = st.max_mrp
            WHERE st.max_mrp > i.mrp OR i.sell_price < st.max_mrp
        ");

        // 3. For any remaining items where sell_price < mrp, align sell_price = mrp
        DB::statement("
            UPDATE items
            SET sell_price = mrp
            WHERE mrp > 0 AND (sell_price <= 0 OR sell_price < mrp)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data alignment migration; no rollback necessary.
    }
};
