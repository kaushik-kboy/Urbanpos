<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockUpdate;
use App\Models\StockUpdateItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportStockUpdates extends Command
{
    protected $signature = 'stock-update:import {file?} {--force : Confirm you understand this bypasses the Stock Ledger}';
    protected $description = 'Import official TruePOS Stock Update Detail CSV (110310)';

    public function handle()
    {
        if (! $this->option('force')) {
            $this->error('This command writes stock updates directly, bypassing the Stock Ledger — it will NOT create the permanent, reversible movement record every other stock change in this app relies on. Re-run with --force once you have confirmed this is intended (e.g. a one-time data migration, not routine use).');

            return 1;
        }

        $fileArg = $this->argument('file');
        if ($fileArg && file_exists($fileArg)) {
            $csvFile = $fileArg;
        } else {
            $files = glob(base_path('data_files/*110310*.csv'));
            if (!empty($files)) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                $csvFile = $files[0];
            } else {
                $csvFile = base_path('data_files/110310_Stock_Upda_ate_Detail_1_2026_09_11_191140.csv');
            }
        }

        if (!file_exists($csvFile)) {
            $this->error("File not found: {$csvFile}");
            return 1;
        }

        $this->info("Importing Stock Updates from: " . basename($csvFile));

        $hoBranch = Branch::where('name', 'like', '%URBANPETS SERVICES%')->first() ?? Branch::find(2);
        $moteraBranch = Branch::where('name', 'like', '%MOTERA%')->first() ?? Branch::find(3);

        $f = fopen($csvFile, 'r');
        for ($i = 0; $i < 6; $i++) fgets($f);

        $vouchers = [];

        while ($r = fgetcsv($f)) {
            if (count($r) < 15) continue;
            $vNo = trim($r[0]);
            if (empty($vNo)) continue;

            $vDate = trim($r[1]);
            $code = trim($r[3]);
            $name = trim($r[4]);
            $physQty = (float)str_replace(',', '', $r[8] ?? '0');
            $actQty = (float)str_replace(',', '', $r[9] ?? '0');
            $diffQty = (float)str_replace(',', '', $r[10] ?? '0');
            $status = trim($r[11] ?? 'Approved & Adjusted');
            $mrp = (float)str_replace(',', '', $r[13] ?? '0');
            $selling = (float)str_replace(',', '', $r[14] ?? '0');
            $expiry = trim($r[17] ?? '');
            $remarks = trim($r[24] ?? '');
            $bCode = trim($r[28] ?? '');
            $bName = trim($r[29] ?? '');

            $isMotera = str_contains($bName, 'MOTERA') || $bCode == '32772';
            $branchId = $isMotera ? $moteraBranch->id : $hoBranch->id;

            if (!isset($vouchers[$vNo])) {
                $vouchers[$vNo] = [
                    'update_number' => 'STK-' . str_pad($vNo, 5, '0', STR_PAD_LEFT),
                    'branch_id' => $branchId,
                    'entry_date' => $vDate,
                    'remarks' => $remarks !== '.' ? $remarks : "TruePOS Stock Update #{$vNo}",
                    'status' => 'Approved',
                    'items' => [],
                ];
            }

            $vouchers[$vNo]['items'][] = [
                'code' => $code,
                'name' => $name,
                'phys_qty' => $physQty,
                'act_qty' => $actQty,
                'diff_qty' => $diffQty,
                'selling' => $selling,
                'mrp' => $mrp,
                'expiry' => $expiry,
            ];
        }
        fclose($f);

        $this->info("Read " . count($vouchers) . " vouchers from CSV. Starting database import...");

        // Preload items map
        $itemsMap = Item::pluck('id', 'item_code')->toArray();

        DB::beginTransaction();

        try {
            StockUpdateItem::query()->delete();
            StockUpdate::query()->delete();

            $voucherCount = 0;
            $itemCount = 0;

            foreach ($vouchers as $vNo => $vData) {
                $stockUpdate = StockUpdate::create([
                    'update_number' => $vData['update_number'],
                    'branch_id' => $vData['branch_id'],
                    'entry_date' => Carbon::parse($vData['entry_date']),
                    'remarks' => $vData['remarks'],
                    'status' => $vData['status'],
                ]);

                $lines = [];
                foreach ($vData['items'] as $itemData) {
                    $itemId = $itemsMap[$itemData['code']] ?? null;
                    if (!$itemId) {
                        continue;
                    }

                    $expDate = null;
                    if (!empty($itemData['expiry']) && $itemData['expiry'] !== '0000-00-00') {
                        try {
                            $expDate = Carbon::parse($itemData['expiry'])->format('Y-m-d');
                        } catch (\Throwable $t) {
                            $expDate = null;
                        }
                    }

                    $lines[] = [
                        'stock_update_id' => $stockUpdate->id,
                        'item_id' => $itemId,
                        'exp_date' => $expDate,
                        'physical_qty' => $itemData['phys_qty'],
                        'system_qty_at_entry' => $itemData['act_qty'],
                        'delta_qty' => $itemData['diff_qty'],
                        'sell_price' => $itemData['selling'],
                        'mrp' => $itemData['mrp'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $itemCount++;
                }

                if (!empty($lines)) {
                    StockUpdateItem::insert($lines);
                }

                $voucherCount++;
            }

            DB::commit();

            $this->info("SUCCESS! Imported {$voucherCount} Stock Updates with {$itemCount} line items.");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing stock updates: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
