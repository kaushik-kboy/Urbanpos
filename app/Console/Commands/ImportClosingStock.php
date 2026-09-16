<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\ClosingStock;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportClosingStock extends Command
{
    protected $signature = 'import:closing-stock {file? : Specific CSV file or directory} {--force : Bypass stock ledger} {--truncate : Truncate closing_stocks before import}';
    protected $description = 'Import TruePOS 110204 Closing Stock files into closing_stocks and update item_stocks';

    public function handle()
    {
        $target = $this->argument('file');
        $filesToProcess = [];

        if ($target && is_file($target)) {
            $filesToProcess[] = $target;
        } elseif ($target && is_dir($target)) {
            $filesToProcess = glob(rtrim($target, '/\\') . '/*.csv');
        } else {
            // Default directory
            $dir = base_path('data_files/closing_stock');
            if (is_dir($dir)) {
                $filesToProcess = glob($dir . '/*.csv');
            }
            if (empty($filesToProcess)) {
                // Fallback to legacy single file
                $fallback = base_path('data_files/110204_Closing_St_sing_Stock_1_2026_09_11_161200.csv');
                if (file_exists($fallback)) {
                    $filesToProcess[] = $fallback;
                }
            }
        }

        if (empty($filesToProcess)) {
            $this->error("No Closing Stock CSV files found to process.");
            return 1;
        }

        $this->info("=================================================");
        $this->info("  STARTING TRUEPOS CLOSING STOCK IMPORT (110204)");
        $this->info("=================================================");
        $this->info("Found " . count($filesToProcess) . " file(s) to process:");
        foreach ($filesToProcess as $f) {
            $this->line("  - " . basename($f) . " (" . number_format(filesize($f) / 1024 / 1024, 2) . " MB)");
        }

        if ($this->option('truncate') || true) {
            $this->info("Clearing existing records in closing_stocks table...");
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            ClosingStock::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Cache Master Data
        $branches = Branch::all();
        $branchNameMap = [];
        foreach ($branches as $b) {
            $branchNameMap[strtoupper(trim($b->name))] = $b;
        }

        $storeIdMap = [
            '225' => $branches->firstWhere('name', 'URBANPETS SERVICES PRIVATE LIMITED') ?? $branches->first(),
            '32772' => $branches->firstWhere('name', 'URBAN PETS / MOTERA') ?? $branches->skip(1)->first(),
        ];

        $itemsByCode = Item::whereNotNull('item_code')->where('item_code', '!=', '')->pluck('id', 'item_code')->toArray();
        $itemsByName = Item::pluck('id', 'name')->toArray();
        $itemsByBarcode = Item::whereNotNull('ean_upc_code')->where('ean_upc_code', '!=', '')->pluck('id', 'ean_upc_code')->toArray();

        $cleanNum = fn($v) => (float) str_replace(',', '', trim((string)$v));

        $totalRecordsInserted = 0;
        $totalQtyConsolidated = 0.0;
        $totalValuationConsolidated = 0.0;
        $stockAgg = []; // [branch_id][item_id] => ['qty' => 0.0, 'cost' => 0.0, 'mrp' => 0.0]

        foreach ($filesToProcess as $filePath) {
            $this->info("\nProcessing: " . basename($filePath) . "...");
            $handle = fopen($filePath, 'r');
            if (! $handle) {
                $this->error("Cannot open file: {$filePath}");
                continue;
            }

            // Extract As On Date and Location from header preamble if present
            $asOnDate = '2026-09-02';
            $fileHeader = null;
            $colIndex = [];
            $lineCount = 0;

            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $lineCount++;
                if (isset($row[0]) && str_contains($row[0], 'As On')) {
                    if (preg_match('/As On\s+(\d{2}-\d{2}-\d{4})/', $row[0], $m)) {
                        $parts = explode('-', $m[1]);
                        if (count($parts) === 3) {
                            $asOnDate = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                        }
                    }
                }

                $cleanRow = array_map(fn($v) => trim((string)$v), $row);
                if (in_array('Store ID', $cleanRow) || in_array('Item code', $cleanRow)) {
                    $fileHeader = $cleanRow;
                    foreach ($fileHeader as $idx => $col) {
                        if ($col !== '') {
                            $colIndex[$col] = $idx;
                        }
                    }
                    break;
                }
            }

            if (! $fileHeader) {
                $this->error("Could not find table header in " . basename($filePath));
                fclose($handle);
                continue;
            }

            $batchRows = [];
            $fileRows = 0;
            $fileQty = 0.0;
            $fileVal = 0.0;

            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $cleanRow = array_map(fn($v) => trim((string)$v), $row);
                $get = fn($k) => isset($colIndex[$k]) ? ($cleanRow[$colIndex[$k]] ?? '') : '';

                $storeId = $get('Store ID');
                $storeName = $get('Store');
                $itemCode = $get('Item code');
                $itemName = $get('Item name');

                // Skip summary / empty rows
                if ($storeId === '' && $itemCode === '' && $itemName === '') {
                    continue;
                }
                if ($storeId === '' && $get('Net cost') !== '') {
                    // This is the total summary row at the top/bottom of TruePOS exports
                    continue;
                }

                $qty = $cleanNum($get('Closing stock'));
                $netCost = $cleanNum($get('Net cost'));
                $stockAmt = $cleanNum($get('Closing stock amount'));
                $mrp = $cleanNum($get('MRP'));
                $isbn = $get('ISBN');
                $batchNo = $get('Batch no');
                $expiryDate = $get('Expiry date');
                if ($expiryDate === '' || $expiryDate === '0000-00-00' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
                    $expiryDate = null;
                }

                // Resolve Branch
                $branch = $storeIdMap[$storeId] ?? ($branchNameMap[strtoupper($storeName)] ?? null);
                $branchId = $branch ? $branch->id : null;

                // Resolve Item
                $itemId = $itemsByCode[$itemCode] ?? ($itemsByName[$itemName] ?? ($isbn !== '' && isset($itemsByBarcode[$isbn]) ? $itemsByBarcode[$isbn] : null));

                $batchRows[] = [
                    'store_id' => $storeId,
                    'store_name' => $storeName ?: ($branch ? $branch->name : null),
                    'branch_id' => $branchId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'item_id' => $itemId,
                    'isbn' => $isbn ?: null,
                    'item_alias' => $get('Item alias') ?: null,
                    'cat1_code' => $get('Cat1 code') ?: null,
                    'cat1_name' => $get('Cat1 name') ?: null,
                    'cat2_code' => $get('Cat2 code') ?: null,
                    'cat2_name' => $get('Cat2 name') ?: null,
                    'cat3_code' => $get('Cat3_Code') ?: null,
                    'cat3_name' => $get('Cat3_Name') ?: null,
                    'brand_code' => $get('Brand code') ?: null,
                    'brand_name' => $get('Brand name') ?: null,
                    'batch_no' => $batchNo ?: null,
                    'expiry_date' => $expiryDate,
                    'hsn_code' => $get('HSN Code') ?: null,
                    'status' => $get('Status') ?: 'Active',
                    'net_cost' => $netCost,
                    'closing_stock' => $qty,
                    'closing_stock_amount' => $stockAmt,
                    'mrp' => $mrp,
                    'as_on_date' => $asOnDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $fileRows++;
                $fileQty += $qty;
                $fileVal += $stockAmt;

                // Aggregate for item_stocks
                if ($branchId && $itemId) {
                    if (! isset($stockAgg[$branchId][$itemId])) {
                        $stockAgg[$branchId][$itemId] = [
                            'qty' => 0.0,
                            'cost' => $netCost,
                            'mrp' => $mrp,
                        ];
                    }
                    $stockAgg[$branchId][$itemId]['qty'] += $qty;
                    if ($netCost > 0) {
                        $stockAgg[$branchId][$itemId]['cost'] = $netCost;
                    }
                    if ($mrp > 0) {
                        $stockAgg[$branchId][$itemId]['mrp'] = $mrp;
                    }
                }

                // Batch insert into closing_stocks
                if (count($batchRows) >= 500) {
                    DB::table('closing_stocks')->insert($batchRows);
                    $totalRecordsInserted += count($batchRows);
                    $batchRows = [];
                }
            }

            if (! empty($batchRows)) {
                DB::table('closing_stocks')->insert($batchRows);
                $totalRecordsInserted += count($batchRows);
                $batchRows = [];
            }

            fclose($handle);

            $this->info("  -> Imported {$fileRows} records from " . basename($filePath));
            $this->info("  -> File Closing Stock: " . number_format($fileQty, 2) . " | Valuation: ₹ " . number_format($fileVal, 2));
            $totalQtyConsolidated += $fileQty;
            $totalValuationConsolidated += $fileVal;
        }

        $this->info("\n=================================================");
        $this->info("  TOTAL CLOSING STOCK RECORDS: " . number_format($totalRecordsInserted));
        $this->info("  TOTAL CONSOLIDATED QUANTITY: " . number_format($totalQtyConsolidated, 2));
        $this->info("  TOTAL CONSOLIDATED VALUATION: ₹ " . number_format($totalValuationConsolidated, 2));
        $this->info("=================================================");

        // Sync ItemStock table for POS operations
        if (! empty($stockAgg)) {
            $this->info("\nSyncing branch-wise item_stocks table...");
            $existingStocks = ItemStock::select('id', 'item_id', 'branch_id')->get();
            $existingMap = [];
            foreach ($existingStocks as $es) {
                $existingMap[$es->branch_id][$es->item_id] = $es->id;
            }

            $itemsSellPrice = Item::pluck('sell_price', 'id')->toArray();
            $upsertData = [];

            foreach ($stockAgg as $bId => $items) {
                foreach ($items as $itmId => $dat) {
                    $sellPrice = $itemsSellPrice[$itmId] ?? ($dat['mrp'] > 0 ? $dat['mrp'] : $dat['cost']);
                    $record = [
                        'item_id' => $itmId,
                        'branch_id' => $bId,
                        'quantity' => max(0, round($dat['qty'], 3)),
                        'cost_price' => round($dat['cost'], 2),
                        'landing_cost' => round($dat['cost'], 2),
                        'sell_price' => round($sellPrice, 2),
                        'mrp' => round($dat['mrp'], 2),
                        'updated_at' => now(),
                    ];

                    if (isset($existingMap[$bId][$itmId])) {
                        $record['id'] = $existingMap[$bId][$itmId];
                    } else {
                        $record['created_at'] = now();
                    }

                    $upsertData[] = $record;
                    if (count($upsertData) >= 500) {
                        ItemStock::upsert($upsertData, ['id'], ['quantity', 'cost_price', 'landing_cost', 'sell_price', 'mrp', 'updated_at']);
                        $upsertData = [];
                    }
                }
            }

            if (! empty($upsertData)) {
                ItemStock::upsert($upsertData, ['id'], ['quantity', 'cost_price', 'landing_cost', 'sell_price', 'mrp', 'updated_at']);
            }
            $this->info("item_stocks table successfully synchronized with Closing Stock!");
        }

        $this->info("\nImport completed successfully!");
        return 0;
    }
}
