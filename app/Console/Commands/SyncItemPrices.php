<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncItemPrices extends Command
{
    protected $signature = 'items:sync-prices';
    protected $description = 'Synchronize item cost, sell price, MRP and suppliers with branch-level accuracy from TruePOS CSVs';

    public function handle()
    {
        $this->info("====================================================================");
        $this->info("  SYNCHRONIZING ALL ITEM PRICES & SUPPLIERS (BRANCH-AWARE: HO & MOTERA)");
        $this->info("====================================================================");

        $suppliersFile = base_path('data_files/110266_Supplier_V_r_Vs_Items_1_2026_09_06_233001.csv');
        $priceListFile = base_path('data_files/110223_Price_list__1_2026_09_06_233050.csv');
        $closingStockFile = base_path('data_files/110204_Closing_St_sing_Stock_1_2026_09_11_161200.csv');
        $itemMasterFile = base_path('data_files/110110_Item_Maste_tem_Master_1_2026_09_06_232919.csv');

        // Resolve branch IDs
        $hoBranch = Branch::where('name', 'like', '%URBANPETS SERVICES%')->first();
        $moteraBranch = Branch::where('name', 'like', '%MOTERA%')->first();
        $globalBranch = Branch::where('name', 'GLOBAL')->first();

        $hoBranchId = $hoBranch ? $hoBranch->id : 2;
        $moteraBranchId = $moteraBranch ? $moteraBranch->id : 3;
        $globalBranchId = $globalBranch ? $globalBranch->id : 1;

        $this->info("HO Branch ID: {$hoBranchId}, Motera Branch ID: {$moteraBranchId}, Global Branch ID: {$globalBranchId}");

        $suppliersCache = Supplier::pluck('id', 'name')->toArray();

        // 1. BASE: Read Item Master
        $masterBase = [];
        if (file_exists($itemMasterFile)) {
            $this->info("1/4. Reading Item Master...");
            $f = fopen($itemMasterFile, 'r');
            for ($i = 0; $i < 4; $i++) fgets($f);
            $h = fgetcsv($f);
            while ($r = fgetcsv($f)) {
                if (count($r) > 4 && trim($r[3]) !== '') {
                    $code = trim($r[3]);
                    $landing = (float) str_replace(',', '', $r[19] ?? '0');
                    $mrp = (float) str_replace(',', '', $r[20] ?? '0');
                    $purNet = (float) str_replace(',', '', $r[21] ?? '0');
                    $packed = (float) str_replace(',', '', $r[22] ?? '0');
                    $cost = $purNet > 0 ? $purNet : ($landing > 0 ? $landing : $packed);
                    $selling = (float) str_replace(',', '', $r[23] ?? '0');
                    $supName = trim($r[42] ?? ($r[43] ?? ''));

                    $masterBase[$code] = [
                        'cost' => $cost,
                        'landing' => $landing > 0 ? $landing : $cost,
                        'selling' => $selling > 0 ? $selling : $mrp,
                        'mrp' => $mrp > 0 ? $mrp : $selling,
                        'supplier_name' => $supName,
                        'supplier_id' => $suppliersCache[$supName] ?? null,
                    ];
                }
            }
            fclose($f);
            $this->info("Loaded base data for " . count($masterBase) . " items from Item Master.");
        }

        // 2. AUTHORITATIVE PRICE LIST: Branch-wise (HO vs Motera)
        $hoPrices = [];
        $moteraPrices = [];

        if (file_exists($priceListFile)) {
            $this->info("2/4. Reading Price List (Branch-wise)...");
            $f = fopen($priceListFile, 'r');
            for ($i = 0; $i < 4; $i++) fgets($f);
            $h = fgetcsv($f);
            while ($r = fgetcsv($f)) {
                if (count($r) >= 13 && trim($r[4]) !== '') {
                    $branchName = trim($r[3] ?? '');
                    $code = trim($r[4]);
                    $stock = (float) str_replace(',', '', $r[7] ?? '0');
                    $selling = (float) str_replace(',', '', $r[9] ?? '0');
                    $mrp = (float) str_replace(',', '', $r[10] ?? '0');
                    $packed = (float) str_replace(',', '', $r[11] ?? '0');
                    $landing = (float) str_replace(',', '', $r[12] ?? '0');
                    $cost = $packed > 0 ? $packed : $landing;

                    $record = [
                        'cost' => $cost,
                        'landing' => $landing > 0 ? $landing : $cost,
                        'selling' => $selling > 0 ? $selling : $mrp,
                        'mrp' => $mrp > 0 ? $mrp : $selling,
                        'stock' => $stock,
                    ];

                    if (str_contains($branchName, 'LIMITED')) {
                        // HO
                        if (!isset($hoPrices[$code]) || ($stock > 0 && $hoPrices[$code]['stock'] <= 0) || $mrp > $hoPrices[$code]['mrp']) {
                            $hoPrices[$code] = $record;
                        }
                    } else {
                        // Motera
                        if (!isset($moteraPrices[$code]) || ($stock > 0 && $moteraPrices[$code]['stock'] <= 0) || $mrp > $moteraPrices[$code]['mrp']) {
                            $moteraPrices[$code] = $record;
                        }
                    }
                }
            }
            fclose($f);
            $this->info("Loaded " . count($hoPrices) . " items for HO and " . count($moteraPrices) . " items for Motera from Price List.");
        }

        // 3. CLOSING STOCK: Fallback for items missing from Price List
        if (file_exists($closingStockFile)) {
            $this->info("3/4. Reading Closing Stock (Fallback)...");
            $f = fopen($closingStockFile, 'r');
            for ($i = 0; $i < 4; $i++) fgets($f);
            $h = fgetcsv($f);
            while ($r = fgetcsv($f)) {
                if (count($r) > 24 && trim($r[2]) !== '') {
                    $storeId = trim($r[0] ?? '');
                    $code = trim($r[2]);
                    $stock = (float) str_replace(',', '', $r[9] ?? '0');
                    $netCost = (float) str_replace(',', '', $r[8] ?? '0');
                    $mrp = (float) str_replace(',', '', $r[24] ?? '0');

                    if ($stock > 0 && $netCost > 0) {
                        if ($storeId === '225' && !isset($hoPrices[$code])) {
                            $hoPrices[$code] = [
                                'cost' => $netCost,
                                'landing' => $netCost,
                                'selling' => $mrp > 0 ? $mrp : $netCost,
                                'mrp' => $mrp > 0 ? $mrp : $netCost,
                                'stock' => $stock,
                            ];
                        } elseif ($storeId === '32772' && !isset($moteraPrices[$code])) {
                            $moteraPrices[$code] = [
                                'cost' => $netCost,
                                'landing' => $netCost,
                                'selling' => $mrp > 0 ? $mrp : $netCost,
                                'mrp' => $mrp > 0 ? $mrp : $netCost,
                                'stock' => $stock,
                            ];
                        }
                    }
                }
            }
            fclose($f);
        }

        // 4. SUPPLIER VS ITEMS: Match primary supplier by MRP (without overwriting cost!)
        $hoSuppliers = [];
        $moteraSuppliers = [];

        if (file_exists($suppliersFile)) {
            $this->info("4/4. Reading Supplier Vs Items...");
            $f = fopen($suppliersFile, 'r');
            for ($i = 0; $i < 4; $i++) fgets($f);
            $h = fgetcsv($f);
            while ($r = fgetcsv($f)) {
                if (count($r) >= 8 && trim($r[0]) !== '') {
                    $code = trim($r[0]);
                    $supName = trim($r[3] ?? '');
                    $mrp = (float) str_replace(',', '', $r[7] ?? '0');

                    if ($supName !== '') {
                        if (!isset($suppliersCache[$supName])) {
                            $newSup = Supplier::create(['name' => $supName, 'currency' => 'INR', 'status' => true]);
                            $suppliersCache[$supName] = $newSup->id;
                        }
                        $supId = $suppliersCache[$supName];

                        // Match against HO MRP
                        $hoMrp = $hoPrices[$code]['mrp'] ?? ($masterBase[$code]['mrp'] ?? 0);
                        if ($hoMrp > 0 && abs($mrp - $hoMrp) < 0.01) {
                            $hoSuppliers[$code] = ['id' => $supId, 'name' => $supName, 'locked' => true];
                        } elseif (!isset($hoSuppliers[$code])) {
                            $hoSuppliers[$code] = ['id' => $supId, 'name' => $supName, 'locked' => false];
                        }

                        // Match against Motera MRP
                        $moteraMrp = $moteraPrices[$code]['mrp'] ?? ($masterBase[$code]['mrp'] ?? 0);
                        if ($moteraMrp > 0 && abs($mrp - $moteraMrp) < 0.01) {
                            $moteraSuppliers[$code] = ['id' => $supId, 'name' => $supName, 'locked' => true];
                        } elseif (!isset($moteraSuppliers[$code])) {
                            $moteraSuppliers[$code] = ['id' => $supId, 'name' => $supName, 'locked' => false];
                        }
                    }
                }
            }
            fclose($f);
        }

        // 5. SYNCHRONIZE DATABASE
        $this->info("Applying branch-aware pricing and suppliers to database...");

        DB::beginTransaction();

        try {
            $dbItems = Item::select('id', 'item_code')->get()->keyBy('item_code');
            $updatedItemsCount = 0;
            $updatedStocksCount = 0;

            foreach ($dbItems as $itemCode => $dbItem) {
                // Determine HO data
                $hoData = $hoPrices[$itemCode] ?? ($masterBase[$itemCode] ?? null);
                // Determine Motera data
                $moteraData = $moteraPrices[$itemCode] ?? ($hoData ?? ($masterBase[$itemCode] ?? null));

                // If no HO data but Motera data exists, use Motera for HO as well
                if (!$hoData && $moteraData) {
                    $hoData = $moteraData;
                }

                if (!$hoData && !$moteraData) {
                    continue;
                }

                $hoSupplierId = $hoSuppliers[$itemCode]['id'] ?? ($masterBase[$itemCode]['supplier_id'] ?? null);
                $moteraSupplierId = $moteraSuppliers[$itemCode]['id'] ?? ($hoSupplierId ?? null);

                // A. Update master Item record (defaults to HO)
                $itemUpdate = [];
                if ($hoData['cost'] > 0) {
                    $itemUpdate['cost_price'] = $hoData['cost'];
                    $itemUpdate['landing_cost'] = $hoData['landing'] ?? $hoData['cost'];
                }
                if ($hoData['selling'] > 0) {
                    $itemUpdate['sell_price'] = $hoData['selling'];
                }
                if ($hoData['mrp'] > 0) {
                    $itemUpdate['mrp'] = $hoData['mrp'];
                }
                if ($hoSupplierId) {
                    $itemUpdate['supplier_id'] = $hoSupplierId;
                }

                if (!empty($itemUpdate)) {
                    Item::where('id', $dbItem->id)->update($itemUpdate);
                    $updatedItemsCount++;
                }

                // B. Update/Create HO ItemStock (Branch 2)
                $hoStockFields = [
                    'cost_price' => $hoData['cost'] > 0 ? $hoData['cost'] : ($dbItem->cost_price ?? 0),
                    'landing_cost' => ($hoData['landing'] ?? 0) > 0 ? $hoData['landing'] : ($hoData['cost'] ?? 0),
                    'sell_price' => $hoData['selling'] > 0 ? $hoData['selling'] : ($dbItem->sell_price ?? 0),
                    'mrp' => $hoData['mrp'] > 0 ? $hoData['mrp'] : ($dbItem->mrp ?? 0),
                ];
                ItemStock::updateOrCreate(
                    ['item_id' => $dbItem->id, 'branch_id' => $hoBranchId],
                    $hoStockFields
                );
                $updatedStocksCount++;

                // C. Update/Create Motera ItemStock (Branch 3)
                $moteraStockFields = [
                    'cost_price' => $moteraData['cost'] > 0 ? $moteraData['cost'] : ($dbItem->cost_price ?? 0),
                    'landing_cost' => ($moteraData['landing'] ?? 0) > 0 ? $moteraData['landing'] : ($moteraData['cost'] ?? 0),
                    'sell_price' => $moteraData['selling'] > 0 ? $moteraData['selling'] : ($dbItem->sell_price ?? 0),
                    'mrp' => $moteraData['mrp'] > 0 ? $moteraData['mrp'] : ($dbItem->mrp ?? 0),
                ];
                ItemStock::updateOrCreate(
                    ['item_id' => $dbItem->id, 'branch_id' => $moteraBranchId],
                    $moteraStockFields
                );
                $updatedStocksCount++;

                // D. Update/Create Global ItemStock (Branch 1)
                ItemStock::updateOrCreate(
                    ['item_id' => $dbItem->id, 'branch_id' => $globalBranchId],
                    $hoStockFields
                );
                $updatedStocksCount++;
            }

            DB::commit();

            $this->info("SUCCESS! Updated {$updatedItemsCount} items and {$updatedStocksCount} branch stock records.");

            // Verification of key items
            $this->info("\n--- VERIFICATION OF KEY ITEMS ---");
            foreach (['4850', '11295', '4892', '1'] as $vCode) {
                $vItem = Item::where('item_code', $vCode)->with('supplier')->first();
                if ($vItem) {
                    $hoStk = ItemStock::where('item_id', $vItem->id)->where('branch_id', $hoBranchId)->first();
                    $motStk = ItemStock::where('item_id', $vItem->id)->where('branch_id', $moteraBranchId)->first();
                    $this->info("Code {$vCode} ({$vItem->name}):");
                    $this->info("  HO (Branch {$hoBranchId}): Cost={$hoStk?->cost_price}, Sell={$hoStk?->sell_price}, MRP={$hoStk?->mrp}, Sup={$vItem->supplier?->name}");
                    $this->info("  Motera (Branch {$moteraBranchId}): Cost={$motStk?->cost_price}, Sell={$motStk?->sell_price}, MRP={$motStk?->mrp}");
                }
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error syncing prices: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
