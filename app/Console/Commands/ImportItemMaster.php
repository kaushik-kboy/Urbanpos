<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportItemMaster extends Command
{
    protected $signature = 'import:item-master {file? : Path to the CSV file}';
    protected $description = 'Import Item Master CSV file into database';

    public function handle()
    {
        $filePath = $this->argument('file') ?? base_path('data_files/110110_Item_Maste_tem_Master_1_2026_09_06_232919.csv');

        if (! file_exists($filePath)) {
            $this->error("File not found at: {$filePath}");
            return 1;
        }

        $this->info("Reading CSV file: {$filePath}");

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Could not open file.");
            return 1;
        }

        // Find header row (line with 'Item name')
        $headers = null;
        $headerLineNum = 0;
        $lineNum = 0;

        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
            $lineNum++;
            // Check if this row contains 'Item name'
            $cleanRow = array_map(fn($v) => trim((string)$v), $row);
            if (in_array('Item name', $cleanRow)) {
                $headers = $cleanRow;
                $headerLineNum = $lineNum;
                break;
            }
        }

        if (! $headers) {
            $this->error("Could not locate header row containing 'Item name'.");
            fclose($handle);
            return 1;
        }

        $this->info("Header found on line {$headerLineNum} with " . count($headers) . " columns.");

        // Build column index map
        $colIndex = [];
        foreach ($headers as $idx => $name) {
            if ($name !== '') {
                $colIndex[$name] = $idx;
            }
        }

        // Ensure category heads exist
        $deptHead = ItemCategory::firstOrCreate(['name' => 'DEPARTMENT'], ['is_mandatory' => false, 'status' => true]);
        $catHead = ItemCategory::firstOrCreate(['name' => 'CATEGORY'], ['is_mandatory' => false, 'status' => true]);
        $brandHead = ItemCategory::firstOrCreate(['name' => 'Brands'], ['is_mandatory' => false, 'status' => true]);

        // Caches for fast relation resolution
        $brandsCache = Brand::pluck('id', 'name')->toArray();
        $suppliersCache = Supplier::pluck('id', 'name')->toArray();
        $taxesCache = []; // rate => id
        foreach (GstTax::all() as $t) {
            $taxesCache[(string)(float)$t->percentage] = $t->id;
        }

        $deptValuesCache = ItemCategoryValue::where('item_category_id', $deptHead->id)->pluck('id', 'name')->toArray();
        $catValuesCache = ItemCategoryValue::where('item_category_id', $catHead->id)->pluck('id', 'name')->toArray();
        $brandValuesCache = ItemCategoryValue::where('item_category_id', $brandHead->id)->pluck('id', 'name')->toArray();

        $existingBarcodes = Item::whereNotNull('ean_upc_code')->pluck('id', 'ean_upc_code')->toArray();
        $existingNames = Item::pluck('id', 'name')->toArray();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $lineNum++;

                if (count($row) < 5) {
                    continue; // Skip blank or empty lines
                }

                $getValue = function (string $colName) use ($row, $colIndex) {
                    if (! isset($colIndex[$colName])) return '';
                    $idx = $colIndex[$colName];
                    return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                };

                $itemName = $getValue('Item name');
                if ($itemName === '') {
                    continue; // Skip rows without Item Name
                }

                // Brand
                $brandName = $getValue('Brand name');
                if ($brandName === '') {
                    $brandName = $getValue('BRANDS');
                }
                $brandId = null;
                $brandValueId = null;
                if ($brandName !== '') {
                    if (! isset($brandsCache[$brandName])) {
                        $b = Brand::create(['name' => $brandName, 'status' => true]);
                        $brandsCache[$brandName] = $b->id;
                    }
                    $brandId = $brandsCache[$brandName];

                    if (! isset($brandValuesCache[$brandName])) {
                        $bv = ItemCategoryValue::create([
                            'item_category_id' => $brandHead->id,
                            'name' => $brandName,
                            'status' => true,
                        ]);
                        $brandValuesCache[$brandName] = $bv->id;
                    }
                    $brandValueId = $brandValuesCache[$brandName];
                }

                // Department
                $deptName = $getValue('DEPARTMENT');
                $deptValueId = null;
                if ($deptName !== '') {
                    if (! isset($deptValuesCache[$deptName])) {
                        $dv = ItemCategoryValue::create([
                            'item_category_id' => $deptHead->id,
                            'name' => $deptName,
                            'status' => true,
                        ]);
                        $deptValuesCache[$deptName] = $dv->id;
                    }
                    $deptValueId = $deptValuesCache[$deptName];
                }

                // Category
                $catName = $getValue('CATEGORY');
                $catValueId = null;
                if ($catName !== '') {
                    if (! isset($catValuesCache[$catName])) {
                        $cv = ItemCategoryValue::create([
                            'item_category_id' => $catHead->id,
                            'name' => $catName,
                            'status' => true,
                        ]);
                        $catValuesCache[$catName] = $cv->id;
                    }
                    $catValueId = $catValuesCache[$catName];
                }

                // Supplier
                $supplierName = $getValue('Supplier');
                $supplierId = null;
                if ($supplierName !== '') {
                    if (! isset($suppliersCache[$supplierName])) {
                        $sup = Supplier::create([
                            'name' => $supplierName,
                            'currency' => 'INR',
                            'purchase_type' => 'Local',
                            'purchase_mode' => 'Credit',
                            'credit_limit' => 0,
                            'credit_balance' => 0,
                            'credit_days' => 0,
                            'status' => true,
                            'gst_type' => 'Un Register',
                            'mail_type' => 'None',
                        ]);
                        $suppliersCache[$supplierName] = $sup->id;
                    }
                    $supplierId = $suppliersCache[$supplierName];
                }

                // GST Tax
                $gstRateStr = $getValue('GST Rate');
                if ($gstRateStr === '') {
                    $gstRateStr = $getValue('Tax %');
                }
                $gstRate = (float) str_replace(',', '', $gstRateStr);
                $gstTaxId = null;
                if ($gstRateStr !== '') {
                    $rateKey = (string)(float)$gstRate;
                    if (! isset($taxesCache[$rateKey])) {
                        $desc = "GST " . number_format($gstRate, 0) . "%";
                        $gt = GstTax::firstOrCreate(
                            ['percentage' => $gstRate],
                            ['description' => $desc, 'status' => true]
                        );
                        $taxesCache[$rateKey] = $gt->id;
                    }
                    $gstTaxId = $taxesCache[$rateKey];
                }

                // Prices
                $cleanNum = fn($v) => (float) str_replace(',', '', $v);
                $landingCost = $cleanNum($getValue('Landing cost'));
                $mrp = $cleanNum($getValue('MRP'));
                $purNet = $cleanNum($getValue('Pur_net'));
                $costPrice = $purNet > 0 ? $purNet : $landingCost;
                $selling = $cleanNum($getValue('Selling'));

                // Barcode / EAN
                $barcode = $getValue('ISBN');
                if ($barcode === '') {
                    $barcode = $getValue('Unique Barcode Id');
                }
                $barcode = trim($barcode);
                if ($barcode === '' || strtolower($barcode) === 'null') {
                    $barcode = null;
                }

                // Check duplicate barcode if present
                if ($barcode !== null && isset($existingBarcodes[$barcode])) {
                    // Unique constraint on ean_upc_code: if barcode belongs to another item name, make barcode null or handle
                    $existingId = $existingBarcodes[$barcode];
                    if (! isset($existingNames[$itemName]) || $existingNames[$itemName] !== $existingId) {
                        $barcode = null; // nullify barcode to avoid unique violation if assigned to another product
                    }
                }

                // Flags
                $status = strtolower($getValue('Status')) !== 'inactive';
                $storePickup = in_array(strtolower($getValue('Store Pickup')), ['yes', '1', 'true']);
                $taxInclusive = in_array(strtolower($getValue('Inclusive of tax')), ['yes', '1', 'true']);
                $allowNegative = in_array(strtolower($getValue('Allow Negative Stock')), ['yes', '1', 'true']);

                // Batch / Expiry
                $batchExpiry = $getValue('Batch/Expiry');
                if (! in_array($batchExpiry, ['Not Required', 'Optional', 'Mandatory', 'Days', 'Month'])) {
                    $batchExpiry = 'Not Required';
                }

                $shelfLife = (int) $getValue('Shelf Life');
                $minShelfLife = (int) $getValue('Minimum Shelf Life');

                // Product type
                $prodType = $getValue('Product type');
                if (! in_array($prodType, ['Standard', 'Serialized', 'Service Component', 'Gift Voucher'])) {
                    $prodType = 'Standard';
                }

                $itemData = [
                    'ean_upc_code' => $barcode,
                    'name' => $itemName,
                    'alias' => $getValue('Item alias') ?: null,
                    'brand_id' => $brandId,
                    'supplier_id' => $supplierId,
                    'product_type' => $prodType,
                    'cost_price' => $costPrice,
                    'landing_cost' => $landingCost,
                    'sell_price' => $selling,
                    'mrp' => $mrp,
                    'status' => $status,
                    'store_pickup' => $storePickup,
                    'tax_inclusive' => $taxInclusive,
                    'batch_expiry_details' => $batchExpiry,
                    'shelf_life_days' => $shelfLife > 0 ? $shelfLife : null,
                    'minimum_shelf_life_days' => $minShelfLife > 0 ? $minShelfLife : null,
                    'allow_negative_stock' => $allowNegative,
                    'department_value_id' => $deptValueId,
                    'category_value_id' => $catValueId,
                    'brand_value_id' => $brandValueId,
                    'gst_tax_id' => $gstTaxId,
                    'hsn_code' => $getValue('HSN Code') ?: null,
                ];

                if (isset($existingNames[$itemName])) {
                    Item::where('id', $existingNames[$itemName])->update($itemData);
                    $updated++;
                } else {
                    $newItem = Item::create($itemData);
                    $existingNames[$itemName] = $newItem->id;
                    if ($barcode !== null) {
                        $existingBarcodes[$barcode] = $newItem->id;
                    }
                    $created++;
                }
            }

            DB::commit();
            fclose($handle);

            $this->info("Import completed successfully!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Created Items', $created],
                    ['Updated Items', $updated],
                    ['Skipped Items', $skipped],
                    ['Total Unique Brands Created', count($brandsCache)],
                    ['Total Suppliers Created', count($suppliersCache)],
                    ['Total Departments Created', count($deptValuesCache)],
                    ['Total Categories Created', count($catValuesCache)],
                ]
            );

            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Import failed on line {$lineNum}: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
