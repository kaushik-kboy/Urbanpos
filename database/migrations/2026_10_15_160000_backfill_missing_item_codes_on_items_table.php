<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill any existing items where item_code is null or empty
        $missingItems = Item::whereNull('item_code')
            ->orWhere('item_code', '')
            ->orderBy('id')
            ->get();

        foreach ($missingItems as $item) {
            $item->item_code = Item::generateUniqueItemCode();
            $item->saveQuietly();
        }

        // 2. Resolve any duplicate item_codes (keep oldest, re-generate unique code for duplicates)
        $duplicates = DB::table('items')
            ->select('item_code')
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->groupBy('item_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('item_code');

        foreach ($duplicates as $dupCode) {
            $dupItems = Item::where('item_code', $dupCode)->orderBy('id')->get();
            foreach ($dupItems->skip(1) as $item) {
                $item->item_code = Item::generateUniqueItemCode();
                $item->saveQuietly();
            }
        }
    }

    public function down(): void
    {
    }
};
