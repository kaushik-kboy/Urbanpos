<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportReportsLive extends Command
{
    protected $signature = 'import:reports-live {--dry-run : Simulate import without writing to database} {--commit : Commit changes to database}';
    protected $description = 'Import or update masters from data_files/reports_live (Branch, Brand, Tax, Supplier, Item, Customer)';

    private string $baseDir;
    private bool $isDryRun = true;

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(1200);

        if ($this->option('commit')) {
            $this->isDryRun = false;
            $this->warn('*** LIVE COMMIT MODE ACTIVE: Changes will be written to the database! ***');
        } else {
            $this->isDryRun = true;
            $this->info('*** DRY RUN MODE: No changes will be written to the database. (Pass --commit to execute) ***');
        }

        $this->baseDir = base_path('data_files/reports_live');

        if (! is_dir($this->baseDir)) {
            $this->error("Directory not found: {$this->baseDir}");
            return 1;
        }

        $this->info("\n=== STARTING REPORTS_LIVE IMPORT AUDIT ===");

        try {
            $this->runStep('1. Branch Master', fn() => $this->importBranches());
            $this->runStep('2. Brand Master', fn() => $this->importBrands());
            $this->runStep('3. Tax Master', fn() => $this->importTaxes());
            $this->runStep('4. Supplier Master', fn() => $this->importSuppliers());
            $this->runStep('5. Item Master', fn() => $this->importItems());
            $this->runStep('6. Customer Master', fn() => $this->importCustomers());

            if ($this->isDryRun) {
                $this->info("\n[DRY RUN COMPLETE] Simulated successfully. No changes were made to the database.");
                $this->info("To apply these changes, re-run with: php artisan import:reports-live --commit");
            } else {
                $this->info("\n[SUCCESS] All masters committed successfully to the database!");
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("\n[ERROR] Import failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function runStep(string $title, callable $callback): void
    {
        $this->info("\n--- {$title} ---");
        DB::reconnect();

        if (! $this->isDryRun) {
            DB::beginTransaction();
            try {
                $callback();
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            $callback();
        }
    }

    private function importBranches(): void
    {
        $filePath = "{$this->baseDir}/Branch master.xls";
        if (! file_exists($filePath)) {
            $this->warn("Branch file not found: {$filePath}");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $skipped = 0;
        $updated = 0;
        $inserted = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Branch', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $getVal = fn($name) => isset($headerIdx[$name]) ? ($rowStr[$headerIdx[$name]] ?? '') : '';
            $branchName = $getVal('Branch');
            if ($branchName === '') continue;

            $bizType = $getVal('Business Type');
            $validTypes = ['COCO', 'FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'];
            if (! in_array($bizType, $validTypes)) {
                $bizType = 'BRANCH';
            }

            $branch = Branch::where('name', $branchName)->first();

            $data = [
                'business_type' => $bizType,
                'address_line1' => $getVal('Address1') ?: null,
                'address_line2' => $getVal('Address2') ?: null,
                'phone' => $getVal('Phone') ?: null,
                'contact_person' => $getVal('Contact person') ?: null,
                'erp_code' => $getVal('ERP code') ?: null,
                'state' => $getVal('State Name') ?: null,
                'gst_no' => $getVal('GST No.') ?: null,
                'pan_no' => $getVal('PAN No.') ?: null,
                'status' => strtoupper($getVal('Status')) !== 'INACTIVE',
            ];

            if ($branch) {
                $changes = [];
                foreach ($data as $k => $v) {
                    if (empty($branch->{$k}) && ! empty($v)) {
                        $changes[$k] = $v;
                    }
                }
                if (! empty($changes)) {
                    if (! $this->isDryRun) {
                        $branch->update($changes);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                if (! $this->isDryRun) {
                    Branch::create(array_merge(['name' => $branchName], $data));
                }
                $inserted++;
            }
        }

        $this->table(['Branches', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['Missing Fields Updated', $updated],
            ['New Inserted', $inserted],
        ]);
    }

    private function importBrands(): void
    {
        $filePath = "{$this->baseDir}/Brand master.xls";
        if (! file_exists($filePath)) {
            $this->warn("Brand file not found: {$filePath}");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $existingBrands = Brand::pluck('id', 'name')->toArray();
        $existingLower = [];
        foreach ($existingBrands as $name => $id) {
            $existingLower[strtolower(trim($name))] = $id;
        }

        $skipped = 0;
        $inserted = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Brand name', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $brandName = isset($headerIdx['Brand name']) ? ($rowStr[$headerIdx['Brand name']] ?? '') : '';
            if ($brandName === '') continue;

            $nameKey = strtolower($brandName);
            if (isset($existingLower[$nameKey])) {
                $skipped++;
                continue;
            }

            $statusStr = isset($headerIdx['Status']) ? ($rowStr[$headerIdx['Status']] ?? '') : '';
            $status = strtolower($statusStr) !== 'inactive';

            if (! $this->isDryRun) {
                $b = Brand::create([
                    'name' => $brandName,
                    'status' => $status,
                ]);
                $existingLower[$nameKey] = $b->id;
            } else {
                $existingLower[$nameKey] = true;
            }
            $inserted++;
        }

        $this->table(['Brands', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['New Inserted', $inserted],
        ]);
    }

    private function importTaxes(): void
    {
        $filePath = "{$this->baseDir}/Tax master.xls";
        if (! file_exists($filePath)) {
            $this->warn("Tax file not found: {$filePath}");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $existingTaxes = GstTax::pluck('id', 'description')->toArray();
        $existingLower = [];
        foreach ($existingTaxes as $desc => $id) {
            $existingLower[strtolower(trim($desc))] = $id;
        }

        $skipped = 0;
        $inserted = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Description', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $desc = isset($headerIdx['Description']) ? ($rowStr[$headerIdx['Description']] ?? '') : '';
            if ($desc === '') continue;

            $descKey = strtolower($desc);
            if (isset($existingLower[$descKey])) {
                $skipped++;
                continue;
            }

            $rateStr = isset($headerIdx['IGST']) ? ($rowStr[$headerIdx['IGST']] ?? '') : '';
            if ($rateStr === '' || $rateStr === '_') {
                $rateStr = isset($headerIdx['Rate']) ? ($rowStr[$headerIdx['Rate']] ?? '') : '0';
            }
            $rate = (float) str_replace(['%', '_'], '', $rateStr);

            $statusStr = isset($headerIdx['Status']) ? ($rowStr[$headerIdx['Status']] ?? '') : '';
            $status = strtolower($statusStr) !== 'inactive';

            if (! $this->isDryRun) {
                $t = GstTax::create([
                    'description' => $desc,
                    'percentage' => $rate,
                    'status' => $status,
                ]);
                $existingLower[$descKey] = $t->id;
            } else {
                $existingLower[$descKey] = true;
            }
            $inserted++;
        }

        $this->table(['Taxes', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['New Inserted', $inserted],
        ]);
    }

    private function importSuppliers(): void
    {
        $filePath = "{$this->baseDir}/supplior master.xls";
        if (! file_exists($filePath)) {
            $this->warn("Supplier file not found: {$filePath}");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $existingSuppliers = Supplier::all()->keyBy(fn($s) => strtolower(trim($s->name)));

        $skipped = 0;
        $updated = 0;
        $inserted = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Supplier', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $getVal = fn($name) => isset($headerIdx[$name]) ? ($rowStr[$headerIdx[$name]] ?? '') : '';
            $name = $getVal('Supplier');
            if ($name === '') continue;

            $mode = $getVal('Mode of purchase') ?: 'Credit';
            $purMode = in_array(strtoupper($mode), ['CASH', 'CREDIT', 'CONSIGNMENT']) ? ucfirst(strtolower($mode)) : 'Credit';

            $data = [
                'currency' => $getVal('Symbol') ?: $getVal('Currency') ?: 'INR',
                'purchase_type' => $getVal('Purchase type') ?: 'Local',
                'purchase_mode' => $purMode,
                'status' => strtoupper($getVal('Status')) !== 'INACTIVE',
                'gst_type' => 'Un Register',
                'mail_type' => $getVal('Mail Type') ?: 'None',
                'country' => $getVal('Country') ?: 'INDIA',
                'address' => $getVal('Address1') ?: null,
                'city' => $getVal('Place') ?: null,
                'state' => $getVal('State Name') ?: null,
                'postal_code' => $getVal('Postal / ZIP code') ?: null,
                'phone' => $getVal('Phone') ?: null,
                'email' => $getVal('Email') ?: null,
                'pan_no' => $getVal('PAN No.') ?: null,
                'gst_no' => $getVal('GST No.') ?: null,
                'aadhar_no' => $getVal('Aadhar No.') ?: null,
            ];

            $nameKey = strtolower($name);
            if ($existingSuppliers->has($nameKey)) {
                $sup = $existingSuppliers->get($nameKey);
                $changes = [];
                foreach ($data as $k => $v) {
                    if (empty($sup->{$k}) && ! empty($v)) {
                        $changes[$k] = $v;
                    }
                }
                if (! empty($changes)) {
                    if (! $this->isDryRun) {
                        $sup->update($changes);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                if (! $this->isDryRun) {
                    $newSup = Supplier::create(array_merge(['name' => $name], $data));
                    $existingSuppliers->put($nameKey, $newSup);
                } else {
                    $existingSuppliers->put($nameKey, true);
                }
                $inserted++;
            }
        }

        $this->table(['Suppliers', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['Missing Fields Updated', $updated],
            ['New Inserted', $inserted],
        ]);
    }

    private function importItems(): void
    {
        $filePath = "{$this->baseDir}/item master.xls";
        if (! file_exists($filePath)) {
            $this->warn("Item file not found: {$filePath}");
            return;
        }

        // 1. First load spreadsheet (memory heavy, no DB queries)
        $this->info("Loading Excel file: {$filePath}...");
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Build header index from row 3 using numeric index
        $colIndex = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $val = trim((string)$sheet->getCell($colLetter . '3')->getValue());
            if ($val !== '') {
                $colIndex[$val] = $colLetter;
            }
        }

        $this->info("Excel loaded with " . count($colIndex) . " columns and {$highestRow} rows.");

        // 2. Reconnect DB and fetch caches
        DB::reconnect();

        $deptHead = ItemCategory::firstOrCreate(['name' => 'DEPARTMENT'], ['is_mandatory' => false, 'status' => true]);
        $catHead = ItemCategory::firstOrCreate(['name' => 'CATEGORY'], ['is_mandatory' => false, 'status' => true]);
        $brandHead = ItemCategory::firstOrCreate(['name' => 'Brands'], ['is_mandatory' => false, 'status' => true]);

        $brandsCache = Brand::pluck('id', 'name')->toArray();
        $brandsCacheLower = [];
        foreach ($brandsCache as $k => $v) $brandsCacheLower[strtolower(trim($k))] = $v;

        $suppliersCache = Supplier::pluck('id', 'name')->toArray();
        $suppliersCacheLower = [];
        foreach ($suppliersCache as $k => $v) $suppliersCacheLower[strtolower(trim($k))] = $v;

        $taxesCache = [];
        foreach (GstTax::all() as $t) {
            $taxesCache[(string)(float)$t->percentage] = $t->id;
        }

        $deptValuesCache = ItemCategoryValue::where('item_category_id', $deptHead->id)->pluck('id', 'name')->toArray();
        $deptValuesLower = [];
        foreach ($deptValuesCache as $k => $v) $deptValuesLower[strtolower(trim($k))] = $v;

        $catValuesCache = ItemCategoryValue::where('item_category_id', $catHead->id)->pluck('id', 'name')->toArray();
        $catValuesLower = [];
        foreach ($catValuesCache as $k => $v) $catValuesLower[strtolower(trim($k))] = $v;

        $brandValuesCache = ItemCategoryValue::where('item_category_id', $brandHead->id)->pluck('id', 'name')->toArray();
        $brandValuesLower = [];
        foreach ($brandValuesCache as $k => $v) $brandValuesLower[strtolower(trim($k))] = $v;

        // Preload existing items in memory for fast lookup of missing fields
        $existingItemsMap = Item::all([
            'id', 'item_code', 'ean_upc_code', 'name', 'hsn_code', 'brand_id', 'supplier_id',
            'department_value_id', 'category_value_id', 'brand_value_id', 'gst_tax_id'
        ])->keyBy('id');

        $existingNamesLower = [];
        $existingCodes = [];
        $existingBarcodes = [];
        foreach ($existingItemsMap as $id => $itm) {
            if ($itm->name) $existingNamesLower[strtolower(trim($itm->name))] = $id;
            if ($itm->item_code) $existingCodes[trim($itm->item_code)] = $id;
            if ($itm->ean_upc_code) $existingBarcodes[trim($itm->ean_upc_code)] = $id;
        }

        $skipped = 0;
        $updated = 0;
        $inserted = 0;

        for ($r = 5; $r <= $highestRow; $r++) {
            $getVal = function (string $name) use ($sheet, $r, $colIndex) {
                if (! isset($colIndex[$name])) return '';
                return trim((string)$sheet->getCell($colIndex[$name] . $r)->getValue());
            };

            $itemName = $getVal('Item name');
            if ($itemName === '') continue;

            $itemCode = $getVal('Item') ?: null;
            $barcode = $getVal('ISBN') ?: $getVal('Unique Barcode Id');
            $barcode = trim($barcode);
            if ($barcode === '' || strtolower($barcode) === 'null' || $barcode === '0') {
                $barcode = null;
            }

            // Check if item exists
            $nameKey = strtolower($itemName);
            $existingId = $existingNamesLower[$nameKey] ?? ($itemCode && isset($existingCodes[$itemCode]) ? $existingCodes[$itemCode] : null);

            // Brand
            $brandName = $getVal('Brand name') ?: $getVal('BRANDS');
            $brandId = null;
            $brandValueId = null;
            if ($brandName !== '') {
                $bKey = strtolower($brandName);
                if (! isset($brandsCacheLower[$bKey])) {
                    if (! $this->isDryRun) {
                        $b = Brand::create(['name' => $brandName, 'status' => true]);
                        $brandsCacheLower[$bKey] = $b->id;
                    } else {
                        $brandsCacheLower[$bKey] = 999999;
                    }
                }
                $brandId = $brandsCacheLower[$bKey];

                if (! isset($brandValuesLower[$bKey])) {
                    if (! $this->isDryRun) {
                        $bv = ItemCategoryValue::create([
                            'item_category_id' => $brandHead->id,
                            'name' => $brandName,
                            'status' => true,
                        ]);
                        $brandValuesLower[$bKey] = $bv->id;
                    } else {
                        $brandValuesLower[$bKey] = 999999;
                    }
                }
                $brandValueId = $brandValuesLower[$bKey];
            }

            // Department
            $deptName = $getVal('DEPARTMENT');
            $deptValueId = null;
            if ($deptName !== '') {
                $dKey = strtolower($deptName);
                if (! isset($deptValuesLower[$dKey])) {
                    if (! $this->isDryRun) {
                        $dv = ItemCategoryValue::create([
                            'item_category_id' => $deptHead->id,
                            'name' => $deptName,
                            'status' => true,
                        ]);
                        $deptValuesLower[$dKey] = $dv->id;
                    } else {
                        $deptValuesLower[$dKey] = 999999;
                    }
                }
                $deptValueId = $deptValuesLower[$dKey];
            }

            // Category
            $catName = $getVal('CATEGORY');
            $catValueId = null;
            if ($catName !== '') {
                $cKey = strtolower($catName);
                if (! isset($catValuesLower[$cKey])) {
                    if (! $this->isDryRun) {
                        $cv = ItemCategoryValue::create([
                            'item_category_id' => $catHead->id,
                            'name' => $catName,
                            'status' => true,
                        ]);
                        $catValuesLower[$cKey] = $cv->id;
                    } else {
                        $catValuesLower[$cKey] = 999999;
                    }
                }
                $catValueId = $catValuesLower[$cKey];
            }

            // Supplier
            $supplierName = $getVal('Supplier');
            $supplierId = null;
            if ($supplierName !== '') {
                $sKey = strtolower($supplierName);
                if (! isset($suppliersCacheLower[$sKey])) {
                    if (! $this->isDryRun) {
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
                        $suppliersCacheLower[$sKey] = $sup->id;
                    } else {
                        $suppliersCacheLower[$sKey] = 999999;
                    }
                }
                $supplierId = $suppliersCacheLower[$sKey];
            }

            // Tax
            $gstRateStr = $getVal('GST Rate') ?: $getVal('Tax %');
            $gstRate = (float) str_replace(',', '', $gstRateStr);
            $rateKey = (string)(float)$gstRate;
            $gstTaxId = null;
            if ($gstRateStr !== '') {
                if (! isset($taxesCache[$rateKey])) {
                    $desc = "GST " . number_format($gstRate, 0) . "%";
                    if (! $this->isDryRun) {
                        $gt = GstTax::firstOrCreate(
                            ['percentage' => $gstRate],
                            ['description' => $desc, 'status' => true]
                        );
                        $taxesCache[$rateKey] = $gt->id;
                    } else {
                        $taxesCache[$rateKey] = 999999;
                    }
                }
                $gstTaxId = $taxesCache[$rateKey];
            }

            $cleanNum = fn($v) => (float) str_replace(',', '', $v);
            $landingCost = $cleanNum($getVal('Landing cost'));
            $mrp = $cleanNum($getVal('MRP'));
            $purNet = $cleanNum($getVal('Pur_net'));
            $costPrice = $purNet > 0 ? $purNet : $landingCost;
            $selling = $cleanNum($getVal('Selling'));

            $hsnCode = $getVal('HSN Code') ?: null;
            $status = strtolower($getVal('Status')) !== 'inactive';
            $storePickup = in_array(strtolower($getVal('Store Pickup')), ['yes', '1', 'true']);
            $taxInclusive = in_array(strtolower($getVal('Inclusive of tax')), ['yes', '1', 'true']);
            $allowNegative = in_array(strtolower($getVal('Allow Negative Stock')), ['yes', '1', 'true']);

            $batchExpiry = $getVal('Batch/Expiry');
            if (! in_array($batchExpiry, ['Not Required', 'Optional', 'Mandatory', 'Days', 'Month'])) {
                $batchExpiry = 'Not Required';
            }

            $shelfLife = (int) $getVal('Shelf Life');
            $minShelfLife = (int) $getVal('Minimum Shelf Life');

            $prodType = $getVal('Product type');
            if (! in_array($prodType, ['Standard', 'Serialized', 'Service Component', 'Gift Voucher'])) {
                $prodType = 'Standard';
            }

            // Barcode uniqueness check
            if ($barcode !== null && isset($existingBarcodes[$barcode])) {
                if ($existingId && $existingBarcodes[$barcode] !== $existingId) {
                    $barcode = null;
                } elseif (! $existingId) {
                    $barcode = null;
                }
            }

            $itemData = [
                'item_code' => $itemCode,
                'ean_upc_code' => $barcode,
                'name' => $itemName,
                'alias' => $getVal('Item alias') ?: null,
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
                'hsn_code' => $hsnCode,
            ];

            if ($existingId) {
                $existingItem = $existingItemsMap->get($existingId);
                $changes = [];
                if ($existingItem) {
                    foreach (['hsn_code', 'item_code', 'brand_id', 'supplier_id', 'department_value_id', 'category_value_id', 'brand_value_id', 'gst_tax_id'] as $f) {
                        if (empty($existingItem->{$f}) && ! empty($itemData[$f]) && $itemData[$f] !== 999999) {
                            $changes[$f] = $itemData[$f];
                        }
                    }
                }
                if (! empty($changes)) {
                    if (! $this->isDryRun) {
                        Item::where('id', $existingId)->update($changes);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                if (! $this->isDryRun) {
                    $newItem = Item::create($itemData);
                    $existingNamesLower[$nameKey] = $newItem->id;
                    if ($itemCode) $existingCodes[$itemCode] = $newItem->id;
                    if ($barcode) $existingBarcodes[$barcode] = $newItem->id;
                } else {
                    $existingNamesLower[$nameKey] = true;
                    if ($itemCode) $existingCodes[$itemCode] = true;
                    if ($barcode) $existingBarcodes[$barcode] = true;
                }
                $inserted++;
            }
        }

        $this->table(['Items', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['Missing Fields Updated', $updated],
            ['New Inserted', $inserted],
        ]);
    }

    private function importCustomers(): void
    {
        $filePath = "{$this->baseDir}/customer master.csv";
        if (! file_exists($filePath)) {
            $this->warn("Customer file not found: {$filePath}");
            return;
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Could not open customer CSV file.");
            return;
        }

        $headerIdx = [];
        $categoriesCache = CustomerCategory::pluck('id', 'name')->toArray();
        $categoriesLower = [];
        foreach ($categoriesCache as $k => $v) $categoriesLower[strtolower(trim($k))] = $v;

        // Preload customer fields in memory for instant missing-field check
        $this->info("Loading existing customers into memory for fast comparison...");
        $existingCustomersMap = Customer::all([
            'id', 'customer_code', 'name', 'mobile', 'address1', 'city', 'state', 'postal_code',
            'phone', 'email', 'gst_no', 'pan_no', 'gender'
        ])->keyBy('id');

        $existingCodesLower = [];
        $existingMobilesLower = [];
        $existingNamesLower = [];

        foreach ($existingCustomersMap as $id => $c) {
            if ($c->customer_code) $existingCodesLower[strtolower(trim($c->customer_code))] = $id;
            if ($c->mobile && trim($c->mobile) !== '0') $existingMobilesLower[trim($c->mobile)] = $id;
            if ($c->name) $existingNamesLower[strtolower(trim($c->name))] = $id;
        }

        $this->info("Indexed " . count($existingCustomersMap) . " customers. Processing CSV rows...");

        $skipped = 0;
        $updated = 0;
        $inserted = 0;
        $rowNum = 0;

        $validTitles = [
            'MR.' => 'Mr', 'MR' => 'Mr',
            'MS.' => 'Ms', 'MS' => 'Ms',
            'MRS.' => 'Mrs', 'MRS' => 'Mrs',
            'M/S' => 'M/s',
            'DR.' => 'Dr', 'DR' => 'Dr'
        ];

        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
            $rowNum++;
            $cleanRow = array_map(fn($v) => trim((string)$v), $row);

            if (in_array('Customer Name', $cleanRow)) {
                foreach ($cleanRow as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $getVal = fn($name) => isset($headerIdx[$name]) ? ($cleanRow[$headerIdx[$name]] ?? '') : '';

            $custName = $getVal('Customer Name');
            if ($custName === '') continue;

            $code = $getVal('Code') ?: null;
            $mobile = $getVal('Mobile') ?: null;
            if ($mobile === '0' || $mobile === '') $mobile = null;

            $catName = $getVal('Category') ?: 'WALK-IN';
            $catKey = strtolower($catName);
            if (! isset($categoriesLower[$catKey])) {
                if (! $this->isDryRun) {
                    $cat = CustomerCategory::create(['name' => $catName, 'status' => true]);
                    $categoriesLower[$catKey] = $cat->id;
                } else {
                    $categoriesLower[$catKey] = 999999;
                }
            }
            $catId = $categoriesLower[$catKey];

            $rawTitle = strtoupper($getVal('Title'));
            $cleanTitle = $validTitles[$rawTitle] ?? null;

            $gstType = $getVal('GST Type');
            if (! in_array($gstType, ['Regular', 'Composite', 'Un Register'])) {
                $gstType = 'Un Register';
            }

            $gender = ucfirst(strtolower($getVal('Gender')));
            if (! in_array($gender, ['Male', 'Female'])) {
                $gender = null;
            }

            $cleanNum = fn($v) => (float) str_replace(',', '', $v);
            $creditLimit = $cleanNum($getVal('Credit Limit'));

            $custData = [
                'title' => $cleanTitle,
                'name' => $custName,
                'customer_category_id' => $catId,
                'customer_code' => $code,
                'sales_type' => $getVal('Sales type') ?: 'Local',
                'payment_mode' => 'Both Cash and Credit',
                'credit_limit' => $creditLimit > 0 ? $creditLimit : 1000000,
                'credit_balance' => 0,
                'monthly_credit_balance' => 0,
                'credit_days' => 1000,
                'status' => strtoupper($getVal('Customer Status')) !== 'INACTIVE',
                'gst_type' => $gstType,
                'address1' => $getVal('Address') ?: null,
                'city' => $getVal('Place') ?: $getVal('City') ?: null,
                'state' => $getVal('State Name') ?: null,
                'postal_code' => $getVal('Postal / ZIP code') ?: null,
                'phone' => $getVal('Phone') ?: null,
                'mobile' => $mobile,
                'email' => $getVal('Email') ?: null,
                'gst_no' => $getVal('GST No.') ?: null,
                'pan_no' => $getVal('PAN No.') ?: null,
                'gender' => $gender,
                'customer_type' => $getVal('Invoice Type') ?: 'RETAIL INVOICE',
            ];

            // Match customer
            $existingId = null;
            if ($code && isset($existingCodesLower[strtolower($code)])) {
                $existingId = $existingCodesLower[strtolower($code)];
            } elseif ($mobile && isset($existingMobilesLower[$mobile])) {
                $existingId = $existingMobilesLower[$mobile];
            } elseif (isset($existingNamesLower[strtolower($custName)])) {
                $existingId = $existingNamesLower[strtolower($custName)];
            }

            if ($existingId) {
                $existingCust = $existingCustomersMap->get($existingId);
                $changes = [];
                if ($existingCust) {
                    foreach (['customer_code', 'address1', 'city', 'state', 'postal_code', 'phone', 'mobile', 'email', 'gst_no', 'pan_no', 'gender'] as $f) {
                        if (empty($existingCust->{$f}) && ! empty($custData[$f])) {
                            $changes[$f] = $custData[$f];
                        }
                    }
                }
                if (! empty($changes)) {
                    if (! $this->isDryRun) {
                        Customer::where('id', $existingId)->update($changes);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                if (! $this->isDryRun) {
                    $newCust = Customer::create(array_merge($custData, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    if ($code) $existingCodesLower[strtolower($code)] = $newCust->id;
                    if ($mobile) $existingMobilesLower[$mobile] = $newCust->id;
                    $existingNamesLower[strtolower($custName)] = $newCust->id;
                } else {
                    if ($code) $existingCodesLower[strtolower($code)] = true;
                    if ($mobile) $existingMobilesLower[$mobile] = true;
                    $existingNamesLower[strtolower($custName)] = true;
                }
                $inserted++;
            }

            if ($rowNum % 5000 === 0) {
                $this->output->write(".");
            }
        }

        fclose($handle);

        $this->output->writeln("");
        $this->table(['Customers', 'Count'], [
            ['Already Existing / Skipped', $skipped],
            ['Missing Fields Updated', $updated],
            ['New Inserted', $inserted],
        ]);
    }
}
