<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportClosingStock extends Command
{
    protected $signature = 'import:closing-stock {file? : Path to the Closing Stock CSV file} {--force : Confirm you understand this bypasses the Stock Ledger}';
    protected $description = 'Import Closing Stock inventory and valuations into Urbanpos database';

    public function handle()
    {
        if (! $this->option('force')) {
            $this->error('This command writes stock quantity/cost directly, bypassing the Stock Ledger — it will NOT create the permanent, reversible movement record every other stock change in this app relies on. Re-run with --force once you have confirmed this is intended (e.g. a one-time data migration, not routine use).');

            return 1;
        }

        $filePath = $this->argument('file') ?? base_path('data_files/110204_Closing_St_sing_Stock_1_2026_09_11_161200.csv');

        if (! file_exists($filePath)) {
            $this->error("Closing Stock file not found at: {$filePath}");
            return 1;
        }

        $this->info("=================================================");
        $this->info("  STARTING CLOSING STOCK INVENTORY IMPORT");
        $this->info("=================================================");
        $this->info("File: {$filePath}");

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Failed to open file.");
            return 1;
        }

        // 1. Locate header
        $header = null;
        $colIndex = [];
        $headerLineNum = 0;
        $lineNum = 0;

        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
            $lineNum++;
            $cleanRow = array_map(fn($v) => trim((string)$v), $row);
            if (in_array('Store ID', $cleanRow) || in_array('Item code', $cleanRow)) {
                $header = $cleanRow;
                $headerLineNum = $lineNum;
                foreach ($header as $idx => $name) {
                    if ($name !== '') {
                        $colIndex[$name] = $idx;
                    }
                }
                break;
            }
        }

        if (! $header) {
            $this->error("Could not find table header with 'Store ID' or 'Item code'.");
            fclose($handle);
            return 1;
        }

        $this->info("Found table header on line {$headerLineNum} with " . count($colIndex) . " recognized columns.");

        // 2. Load Branch mappings
        $branches = Branch::all();
        $branchMap = []; // Store ID or Name -> Branch Model
        foreach ($branches as $branch) {
            $branchMap[strtoupper(trim($branch->name))] = $branch;
        }

        // Explicit store ID mappings based on verified ERP data
        $storeIdMap = [
            '225' => $branches->firstWhere('name', 'URBANPETS SERVICES PRIVATE LIMITED') ?? $branches->find(2),
            '32772' => $branches->firstWhere('name', 'URBAN PETS / MOTERA') ?? $branches->find(3),
        ];

        // 3. Prepare Item Caches
        $itemsByName = Item::pluck('id', 'name')->toArray();
        $itemsByBarcode = Item::whereNotNull('ean_upc_code')->where('ean_upc_code', '!=', '')->pluck('id', 'ean_upc_code')->toArray();
        $brandsCache = Brand::pluck('id', 'name')->toArray();

        $catHead = ItemCategory::firstOrCreate(['name' => 'CATEGORY'], ['is_mandatory' => false, 'status' => true]);
        $catValuesCache = ItemCategoryValue::where('item_category_id', $catHead->id)->pluck('id', 'name')->toArray();

        // 4. Read & Aggregate rows
        $this->info("Parsing and aggregating stock across batch records...");

        $stockAgg = []; // [branch_id => [item_id => ['qty' => float, 'cost' => float, 'mrp' => float]]]
        $rawRowCount = 0;
        $newItemsCount = 0;
        $unmatchedCount = 0;

        $cleanNum = fn($v) => (float) str_replace(',', '', (string)$v);

        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
            $rawRowCount++;
            $cleanRow = array_map(fn($v) => trim((string)$v), $row);

            $getVal = fn($key) => isset($colIndex[$key]) ? ($cleanRow[$colIndex[$key]] ?? '') : '';

            $storeId = $getVal('Store ID');
            $storeName = $getVal('Store');
            $itemName = $getVal('Item name');
            $isbn = $getVal('ISBN');
            $stockVal = $cleanNum($getVal('Closing stock'));
            $netCost = $cleanNum($getVal('Net cost'));
            $mrp = $cleanNum($getVal('MRP'));
            $hsn = $getVal('HSN Code');
            $brandName = $getVal('Brand name');
            $catName = $getVal('Cat2 name');

            // Skip empty summary/footer rows
            if ($storeId === '' && $itemName === '') {
                continue;
            }

            // Resolve Branch
            $branch = $storeIdMap[$storeId] ?? ($branchMap[strtoupper($storeName)] ?? null);
            if (! $branch) {
                continue;
            }
            $branchId = $branch->id;

            // Resolve Item
            $itemId = $itemsByName[$itemName] ?? ($isbn !== '' && isset($itemsByBarcode[$isbn]) ? $itemsByBarcode[$isbn] : null);

            // Auto-create item if missing
            if (! $itemId && $itemName !== '') {
                // Ensure Brand
                $brandId = null;
                if ($brandName !== '') {
                    if (! isset($brandsCache[$brandName])) {
                        $b = Brand::create(['name' => $brandName, 'status' => true]);
                        $brandsCache[$brandName] = $b->id;
                    }
                    $brandId = $brandsCache[$brandName];
                }

                // Ensure Category
                $catValId = null;
                if ($catName !== '') {
                    if (! isset($catValuesCache[$catName])) {
                        $cv = ItemCategoryValue::create([
                            'item_category_id' => $catHead->id,
                            'name' => $catName,
                            'status' => true,
                        ]);
                        $catValuesCache[$catName] = $cv->id;
                    }
                    $catValId = $catValuesCache[$catName];
                }

                $barcodeToUse = ($isbn !== '' && ! isset($itemsByBarcode[$isbn])) ? $isbn : null;

                $newItem = Item::create([
                    'ean_upc_code' => $barcodeToUse,
                    'name' => $itemName,
                    'brand_id' => $brandId,
                    'category_value_id' => $catValId,
                    'cost_price' => $netCost,
                    'landing_cost' => $netCost,
                    'sell_price' => $mrp > 0 ? $mrp : $netCost,
                    'mrp' => $mrp,
                    'status' => true,
                    'hsn_code' => $hsn ?: null,
                ]);

                $itemId = $newItem->id;
                $itemsByName[$itemName] = $itemId;
                if ($barcodeToUse) {
                    $itemsByBarcode[$barcodeToUse] = $itemId;
                }
                $newItemsCount++;
            }

            if (! $itemId) {
                $unmatchedCount++;
                continue;
            }

            // Aggregate into branch-item bucket
            if (! isset($stockAgg[$branchId][$itemId])) {
                $stockAgg[$branchId][$itemId] = [
                    'qty' => 0.0,
                    'cost' => $netCost,
                    'mrp' => $mrp,
                ];
            }

            $stockAgg[$branchId][$itemId]['qty'] += $stockVal;
            if ($netCost > 0) {
                $stockAgg[$branchId][$itemId]['cost'] = $netCost;
            }
            if ($mrp > 0) {
                $stockAgg[$branchId][$itemId]['mrp'] = $mrp;
            }
        }

        fclose($handle);

        $this->info("Processed {$rawRowCount} CSV rows.");
        if ($newItemsCount > 0) {
            $this->info("Auto-created {$newItemsCount} missing items from closing stock data.");
        }
        if ($unmatchedCount > 0) {
            $this->warn("Unmatched rows skipped: {$unmatchedCount}");
        }

        // 5. Database Update in Chunks
        $this->info("\nUpdating ItemStock records in database...");

        DB::beginTransaction();

        try {
            $totalPairsUpdated = 0;
            $branchSummaries = [];

            // Existing ItemStocks cache: [branch_id][item_id] => ItemStock model / attributes
            $existingStocks = ItemStock::select('id', 'item_id', 'branch_id', 'sell_price')->get();
            $existingMap = [];
            foreach ($existingStocks as $es) {
                $existingMap[$es->branch_id][$es->item_id] = $es;
            }

            // Also load items for sell_price fallback
            $itemsPriceCache = Item::pluck('sell_price', 'id')->toArray();

            foreach ($stockAgg as $branchId => $items) {
                $branch = Branch::find($branchId);
                $branchName = $branch ? $branch->name : "Branch #{$branchId}";
                $branchTotalQty = 0.0;
                $branchTotalVal = 0.0;
                $branchPairCount = 0;

                $recordsToUpsert = [];

                foreach ($items as $itemId => $data) {
                    $qty = round($data['qty'], 3);
                    $cost = round($data['cost'], 2);
                    $mrp = round($data['mrp'], 2);

                    $existing = $existingMap[$branchId][$itemId] ?? null;
                    $sellPrice = ($existing && (float)$existing->sell_price > 0)
                        ? (float)$existing->sell_price
                        : (($itemsPriceCache[$itemId] ?? 0) > 0 ? (float)$itemsPriceCache[$itemId] : $mrp);

                    $recordsToUpsert[] = [
                        'item_id' => $itemId,
                        'branch_id' => $branchId,
                        'quantity' => $qty,
                        'cost_price' => $cost,
                        'landing_cost' => $cost,
                        'sell_price' => $sellPrice,
                        'mrp' => $mrp,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $branchTotalQty += $qty;
                    if ($qty > 0) {
                        $branchTotalVal += ($qty * $cost);
                    }
                    $branchPairCount++;
                    $totalPairsUpdated++;

                    // Also update master Item cost & MRP if currently 0
                    Item::where('id', $itemId)
                        ->where(function ($q) {
                            $q->where('cost_price', 0)->orWhere('mrp', 0);
                        })
                        ->update(array_filter([
                            'cost_price' => $cost > 0 ? $cost : null,
                            'landing_cost' => $cost > 0 ? $cost : null,
                            'mrp' => $mrp > 0 ? $mrp : null,
                        ]));
                }

                // Chunked upsert into item_stocks
                foreach (array_chunk($recordsToUpsert, 500) as $chunk) {
                    ItemStock::upsert(
                        $chunk,
                        ['item_id', 'branch_id'],
                        ['quantity', 'cost_price', 'landing_cost', 'sell_price', 'mrp', 'updated_at']
                    );
                }

                $branchSummaries[] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                    'items_count' => $branchPairCount,
                    'total_qty' => $branchTotalQty,
                    'total_val' => $branchTotalVal,
                ];
            }

            DB::commit();

            $this->info("\n=================================================");
            $this->info("     CLOSING STOCK IMPORT COMPLETED SUCCESSFULLY");
            $this->info("=================================================");

            $grandTotalQty = 0;
            $grandTotalVal = 0;

            $tableRows = [];
            foreach ($branchSummaries as $bs) {
                $grandTotalQty += $bs['total_qty'];
                $grandTotalVal += $bs['total_val'];
                $tableRows[] = [
                    $bs['branch_id'],
                    $bs['branch_name'],
                    number_format($bs['items_count']),
                    number_format($bs['total_qty'], 2),
                    '₹ ' . number_format($bs['total_val'], 2),
                ];
            }

            $tableRows[] = [
                'TOTAL',
                'ALL BRANCHES',
                number_format($totalPairsUpdated),
                number_format($grandTotalQty, 2),
                '₹ ' . number_format($grandTotalVal, 2),
            ];

            $this->table(
                ['Branch ID', 'Branch Name', 'Items Tracked', 'Total Stock Qty', 'Total Cost Valuation'],
                $tableRows
            );

            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nImport failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
