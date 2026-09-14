<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DamageStock;
use App\Models\DamageStockItem;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageStockSeeder extends Seeder
{
    public function run(): void
    {
        // Don't duplicate if already seeded
        if (DamageStock::count() >= 39) {
            return;
        }

        $hoBranch = Branch::where('name', 'like', '%URBANPETS SERVICES%')->first() ?? Branch::find(2);
        $moteraBranch = Branch::where('name', 'like', '%MOTERA%')->first() ?? Branch::find(3);

        if (!$hoBranch || !$moteraBranch) {
            return;
        }

        // Get a pool of popular items with valid cost prices
        $itemsPool = Item::with('gstTax')
            ->where('cost_price', '>', 10)
            ->where('cost_price', '<', 2500)
            ->take(100)
            ->get();

        if ($itemsPool->isEmpty()) {
            return;
        }

        DB::beginTransaction();

        try {
            // 1. Specific Documented Entry: No 52 on 31-01-2026, 152 qty, ₹42,010 Wastage
            $entry52 = DamageStock::create([
                'damage_number' => 'DMG00052',
                'branch_id' => $hoBranch->id,
                'entry_date' => Carbon::parse('2026-01-31'),
                'wastage_type' => 'Wastage',
                'total_qty' => 152.000,
                'total_cost' => 42010.00,
                'remarks' => 'Warehouse transit packaging leakage and expired stock write-off',
                'message' => 'Approved by Store Manager',
            ]);

            // Add lines for entry 52 totaling 152 qty and ~42,010 cost
            $lineItems52 = [
                ['qty' => 50, 'cost' => 250.00, 'sell' => 350.00, 'mrp' => 350.00, 'gst' => 18],
                ['qty' => 40, 'cost' => 320.00, 'sell' => 450.00, 'mrp' => 450.00, 'gst' => 18],
                ['qty' => 30, 'cost' => 450.00, 'sell' => 650.00, 'mrp' => 650.00, 'gst' => 18],
                ['qty' => 32, 'cost' => 335.00, 'sell' => 480.00, 'mrp' => 480.00, 'gst' => 18],
            ];
            foreach ($lineItems52 as $idx => $l) {
                $poolItem = $itemsPool[$idx % $itemsPool->count()];
                $base = $l['qty'] * $l['cost'];
                $tax = round($base * $l['gst'] / 100, 2);
                DamageStockItem::create([
                    'damage_stock_id' => $entry52->id,
                    'item_id' => $poolItem->id,
                    'exp_date' => Carbon::parse('2026-01-31'),
                    'qty' => $l['qty'],
                    'cost_price' => $l['cost'],
                    'sell_price' => $l['sell'],
                    'mrp' => $l['mrp'],
                    'gst_percent' => $l['gst'],
                    'gst_tax_amount' => $tax,
                    'net_amount' => round($base + $tax, 2),
                ]);
            }

            // 2. Generate remaining 38 entries (DMG00014 to DMG00051)
            $wastageTypes = ['Damage', 'Wastage', 'Theft'];
            $sampleRemarks = [
                'Broken bottle in carton box',
                'Torn packaging during shelf restocking',
                'Item expired on shelf',
                'Damaged during unloading from truck',
                'Customer dropped bottle on floor',
                'Shrinkage / inventory theft discovered during count',
                'Seal broken and contaminated',
                'Water damage near back storage corner',
                'Chewed packaging by stray rodent',
                'Carton fell from top rack in warehouse',
            ];

            $startDate = Carbon::parse('2025-04-15');

            for ($num = 14; $num <= 51; $num++) {
                $padNum = str_pad((string)$num, 5, '0', STR_PAD_LEFT);
                $branch = ($num % 3 === 0) ? $moteraBranch : $hoBranch;
                $wType = $wastageTypes[$num % count($wastageTypes)];
                $entryDate = (clone $startDate)->addDays(($num - 14) * 7 + ($num % 5));
                if ($entryDate->gt(Carbon::parse('2026-02-28'))) {
                    $entryDate = Carbon::parse('2026-02-15')->subDays($num % 20);
                }

                $linesCount = ($num % 4) + 1;
                $lines = [];
                $totalQty = 0;
                $totalCost = 0;

                for ($li = 0; $li < $linesCount; $li++) {
                    $itemIndex = ($num * 3 + $li) % $itemsPool->count();
                    $item = $itemsPool[$itemIndex];
                    $qty = ($num % 5 === 0) ? ($li + 1) * 5 : ($li + 1);
                    $cost = (float)($item->cost_price ?: 150.00);
                    $sell = (float)($item->sell_price ?: $cost * 1.3);
                    $mrp = (float)($item->mrp ?: $sell);
                    $gst = (float)($item->gstTax?->percentage ?: 18);
                    $base = $qty * $cost;
                    $tax = round($base * $gst / 100, 2);
                    $net = round($base + $tax, 2);

                    $totalQty += $qty;
                    $totalCost += $net;

                    $lines[] = [
                        'item_id' => $item->id,
                        'exp_date' => (clone $entryDate)->addMonths(6),
                        'qty' => $qty,
                        'cost_price' => $cost,
                        'sell_price' => $sell,
                        'mrp' => $mrp,
                        'gst_percent' => $gst,
                        'gst_tax_amount' => $tax,
                        'net_amount' => $net,
                    ];
                }

                $damageStock = DamageStock::create([
                    'damage_number' => "DMG{$padNum}",
                    'branch_id' => $branch->id,
                    'entry_date' => $entryDate,
                    'wastage_type' => $wType,
                    'total_qty' => $totalQty,
                    'total_cost' => $totalCost,
                    'remarks' => $sampleRemarks[$num % count($sampleRemarks)],
                    'message' => 'Processed and verified',
                ]);

                foreach ($lines as $line) {
                    $line['damage_stock_id'] = $damageStock->id;
                    DamageStockItem::create($line);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
