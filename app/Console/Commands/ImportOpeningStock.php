<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Item;
use App\Models\OpeningStock;
use App\Models\OpeningStockItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOpeningStock extends Command
{
    protected $signature = 'opening-stock:import {file?} {--force : Confirm you understand this bypasses the Stock Ledger}';
    protected $description = 'Import official TruePOS Opening Stock Detail CSV (110410)';

    public function handle()
    {
        if (! $this->option('force')) {
            $this->error('This command writes opening stock directly, bypassing the Stock Ledger — it will NOT create the permanent, reversible movement record every other stock change in this app relies on. Re-run with --force once you have confirmed this is intended (e.g. a one-time data migration, not routine use).');

            return 1;
        }

        $fileArg = $this->argument('file');
        if ($fileArg && file_exists($fileArg)) {
            $csvFile = $fileArg;
        } else {
            $files = glob(base_path('data_files/*110410*.csv'));
            if (!empty($files)) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                $csvFile = $files[0];
            } else {
                $csvFile = base_path('data_files/110410_Opening_St_ock_Detail_1_2026_09_11_192213.csv');
            }
        }

        if (!file_exists($csvFile)) {
            $this->error("File not found: {$csvFile}");
            return 1;
        }

        $this->info("Importing Opening Stock from: " . basename($csvFile));

        $hoBranch = Branch::where('name', 'like', '%URBANPETS SERVICES%')->first() ?? Branch::find(2);
        $moteraBranch = Branch::where('name', 'like', '%MOTERA%')->first() ?? Branch::find(3);

        $f = fopen($csvFile, 'r');
        for ($i = 0; $i < 6; $i++) fgets($f);

        $vouchers = [];

        while ($r = fgetcsv($f)) {
            if (count($r) < 10) continue;
            $osNo = trim($r[0]);
            if (empty($osNo)) continue;

            $osDate = trim($r[2]);
            $bName = trim($r[3]);
            $bCode = trim($r[4]);
            $supplierName = trim($r[5]);
            $code = trim($r[7]);
            $name = trim($r[8]);
            $qty = (float)str_replace(',', '', $r[11] ?? '0');
            $cost = (float)str_replace(',', '', $r[12] ?? '0');
            $landing = (float)str_replace(',', '', $r[13] ?? '0');
            $selling = (float)str_replace(',', '', $r[16] ?? '0');
            $mrp = (float)str_replace(',', '', $r[17] ?? '0');

            $isMotera = str_contains($bName, 'MOTERA') || $bCode == '32772';
            $branchId = $isMotera ? $moteraBranch->id : $hoBranch->id;

            if (!isset($vouchers[$osNo])) {
                $vouchers[$osNo] = [
                    'entry_number' => 'OS-' . str_pad($osNo, 5, '0', STR_PAD_LEFT),
                    'branch_id' => $branchId,
                    'entry_date' => $osDate,
                    'total_qty' => 0,
                    'total' => 0,
                    'remarks' => "TruePOS Opening Stock #{$osNo}",
                    'items' => [],
                ];
            }

            $vouchers[$osNo]['total_qty'] += $qty;
            $vouchers[$osNo]['total'] += ($landing > 0 ? $landing * $qty : $cost * $qty);

            $vouchers[$osNo]['items'][] = [
                'code' => $code,
                'name' => $name,
                'supplier' => $supplierName,
                'qty' => $qty,
                'cost' => $cost,
                'landing' => $landing,
                'selling' => $selling,
                'mrp' => $mrp,
            ];
        }
        fclose($f);

        $this->info("Read " . count($vouchers) . " opening stock vouchers.");

        DB::beginTransaction();

        try {
            OpeningStockItem::query()->delete();
            OpeningStock::query()->delete();

            $vCount = 0;
            $itCount = 0;

            foreach ($vouchers as $osNo => $vData) {
                $os = OpeningStock::create([
                    'entry_number' => $vData['entry_number'],
                    'branch_id' => $vData['branch_id'],
                    'entry_date' => Carbon::parse($vData['entry_date']),
                    'total_qty' => $vData['total_qty'],
                    'total' => $vData['total'],
                    'remarks' => $vData['remarks'],
                    'message' => 'Imported from 110410_Opening_Stock_Detail',
                ]);

                foreach ($vData['items'] as $itemData) {
                    $item = Item::with('gstTax')->where('item_code', $itemData['code'])->first();
                    if (!$item) continue;

                    $supplier = null;
                    if (!empty($itemData['supplier'])) {
                        $supplier = Supplier::where('name', 'like', '%' . $itemData['supplier'] . '%')->first();
                    }

                    $taxPercent = (float)($item->gstTax?->percentage ?: 18);
                    $lineCost = $itemData['cost'] > 0 ? $itemData['cost'] : (float)$item->cost_price;
                    $taxAmt = round(($lineCost * $itemData['qty']) * ($taxPercent / 100), 2);
                    $netAmt = ($lineCost * $itemData['qty']) + $taxAmt;

                    OpeningStockItem::create([
                        'opening_stock_id' => $os->id,
                        'item_id' => $item->id,
                        'supplier_id' => $supplier?->id,
                        'exp_date' => Carbon::parse($vData['entry_date'])->addMonths(12),
                        'qty' => $itemData['qty'],
                        'cost_price' => $lineCost,
                        'sell_price' => $itemData['selling'] ?: (float)$item->sell_price,
                        'mrp' => $itemData['mrp'] ?: (float)$item->mrp,
                        'disc_percent' => 0,
                        'disc_amount' => 0,
                        'scheme_disc_percent' => 0,
                        'scheme_amount' => 0,
                        'scheme_others' => 0,
                        'gst_percent' => $taxPercent,
                        'gst_tax_amount' => $taxAmt,
                        'net_amount' => $netAmt,
                    ]);

                    $itCount++;
                }
                $vCount++;
            }

            DB::commit();
            $this->info("SUCCESS! Imported {$vCount} Opening Stock vouchers with {$itCount} line items.");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing opening stock: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
