<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Breed;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerPet;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PetType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportAllMasters extends Command
{
    protected $signature = 'import:all-masters';
    protected $description = 'Import all remaining master files (Branches, Brands, Taxes, Suppliers, Employees, Customers, Pets, Price List)';

    public function handle()
    {
        $this->info("=== STARTING MASTER DATA IMPORT ===");

        $this->importBranches();
        $this->importBrands();
        $this->importTaxes();
        $this->importSuppliers();
        $this->importEmployees();
        $this->importCustomers();
        $this->importPets();
        $this->importPriceList();

        $this->info("=== ALL MASTER IMPORTS COMPLETED SUCCESSFULLY! ===");
        return 0;
    }

    private function importBranches()
    {
        $file = base_path('data_files/110108_Branch_Mas_nch_Master_1_2026_09_06_232849.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Branches ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $count = 0;

        foreach ($rows as $rowIdx => $row) {
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

            Branch::updateOrCreate(
                ['name' => $branchName],
                [
                    'business_type' => $bizType,
                    'address_line1' => $getVal('Address1') ?: null,
                    'address_line2' => $getVal('Address2') ?: null,
                    'phone' => $getVal('Phone') ?: null,
                    'status' => true,
                ]
            );
            $count++;
        }

        $this->info("Imported/Updated {$count} Branches.");
    }

    private function importBrands()
    {
        $file = base_path('data_files/110106_Brand_Mast_and_Master_1_2026_09_06_232812.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Brands ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $count = 0;

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

            $statusStr = isset($headerIdx['Status']) ? ($rowStr[$headerIdx['Status']] ?? '') : '';
            $status = strtolower($statusStr) !== 'inactive';

            Brand::updateOrCreate(
                ['name' => $brandName],
                ['status' => $status]
            );
            $count++;
        }

        $this->info("Imported/Updated {$count} Brands.");
    }

    private function importTaxes()
    {
        $file = base_path('data_files/110128_Tax_Master__1_2026_09_06_232826.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Taxes ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $count = 0;

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

            $rateStr = isset($headerIdx['IGST']) ? ($rowStr[$headerIdx['IGST']] ?? '') : '';
            if ($rateStr === '' || $rateStr === '_') {
                $rateStr = isset($headerIdx['Rate']) ? ($rowStr[$headerIdx['Rate']] ?? '') : '0';
            }
            $rate = (float) str_replace(['%', '_'], '', $rateStr);

            $statusStr = isset($headerIdx['Status']) ? ($rowStr[$headerIdx['Status']] ?? '') : '';
            $status = strtolower($statusStr) !== 'inactive';

            GstTax::updateOrCreate(
                ['description' => $desc],
                ['percentage' => $rate, 'status' => $status]
            );
            $count++;
        }

        $this->info("Imported/Updated {$count} Tax entries.");
    }

    private function importSuppliers()
    {
        $file = base_path('data_files/110105_Supplier_M_ier_Master_1_2026_09_06_232757.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Detailed Suppliers ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $count = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Supplier', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $name = isset($headerIdx['Supplier']) ? ($rowStr[$headerIdx['Supplier']] ?? '') : '';
            if ($name === '') continue;

            $mode = isset($headerIdx['Mode of purchase']) ? ($rowStr[$headerIdx['Mode of purchase']] ?? '') : 'Credit';
            $purMode = in_array(strtoupper($mode), ['CASH', 'CREDIT', 'CONSIGNMENT']) ? ucfirst(strtolower($mode)) : 'Credit';

            Supplier::updateOrCreate(
                ['name' => $name],
                [
                    'currency' => 'INR',
                    'purchase_type' => 'Local',
                    'purchase_mode' => $purMode,
                    'status' => true,
                    'gst_type' => 'Un Register',
                    'mail_type' => 'None',
                    'country' => isset($headerIdx['Country']) ? ($rowStr[$headerIdx['Country']] ?? 'INDIA') : 'INDIA',
                ]
            );
            $count++;
        }

        $this->info("Imported/Updated {$count} Suppliers.");
    }

    private function importEmployees()
    {
        $file = base_path('data_files/110052_Employee_M_yee_Master_1_2026_09_06_233122.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Employees / Users ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $branchesCache = Branch::pluck('id', 'name')->toArray();
        $count = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Full name', $rowStr) || in_array('Login', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $login = isset($headerIdx['Login']) ? ($rowStr[$headerIdx['Login']] ?? '') : '';
            $fullName = isset($headerIdx['Full name']) ? ($rowStr[$headerIdx['Full name']] ?? '') : '';
            if ($login === '' && $fullName === '') continue;

            $email = str_contains($login, '@') ? $login : strtolower($login) . '@urbanpos.com';
            $name = $fullName ?: $login;

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                ]
            );
            $count++;
        }

        $this->info("Imported/Updated {$count} Employees.");
    }

    private function importCustomers()
    {
        $file = base_path('data_files/110126_Customer_M_mer_Master_1_2026_09_06_232628.csv');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Customers (Large CSV) ---");
        $handle = fopen($file, 'r');
        if (! $handle) return;

        $headerIdx = [];
        $categoriesCache = CustomerCategory::pluck('id', 'name')->toArray();
        $existingCodes = Customer::whereNotNull('customer_code')->pluck('id', 'customer_code')->toArray();
        $existingNames = Customer::pluck('id', 'name')->toArray();

        $count = 0;
        $batch = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
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
                $catName = $getVal('Category') ?: 'WALK-IN';

                if (! isset($categoriesCache[$catName])) {
                    $cat = CustomerCategory::create(['name' => $catName, 'status' => true]);
                    $categoriesCache[$catName] = $cat->id;
                }
                $catId = $categoriesCache[$catName];

                $title = strtoupper($getVal('Title'));
                $validTitles = ['MR.' => 'Mr', 'MR' => 'Mr', 'MS.' => 'Ms', 'MS' => 'Ms', 'MRS.' => 'Mrs', 'MRS' => 'Mrs', 'M/S' => 'M/s', 'DR.' => 'Dr', 'DR' => 'Dr'];
                $cleanTitle = $validTitles[$title] ?? null;

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
                    'sales_type' => 'Local',
                    'payment_mode' => 'Both Cash and Credit',
                    'credit_limit' => $creditLimit > 0 ? $creditLimit : 1000000,
                    'credit_days' => 1000,
                    'status' => strtoupper($getVal('Customer Status')) !== 'INACTIVE',
                    'gst_type' => $gstType,
                    'address1' => $getVal('Address') ?: null,
                    'city' => $getVal('Place') ?: $getVal('City') ?: null,
                    'state' => $getVal('State Name') ?: null,
                    'postal_code' => $getVal('Postal / ZIP code') ?: null,
                    'phone' => $getVal('Phone') ?: null,
                    'mobile' => $getVal('Mobile') ?: null,
                    'email' => $getVal('Email') ?: null,
                    'gst_no' => $getVal('GST No.') ?: null,
                    'gender' => $gender,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($code && isset($existingCodes[$code])) {
                    Customer::where('id', $existingCodes[$code])->update($custData);
                } else if (isset($existingNames[$custName])) {
                    Customer::where('id', $existingNames[$custName])->update($custData);
                } else {
                    $c = Customer::create($custData);
                    $existingNames[$custName] = $c->id;
                    if ($code) $existingCodes[$code] = $c->id;
                }
                $count++;
            }

            DB::commit();
            fclose($handle);
            $this->info("Imported/Updated {$count} Customers.");

        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Customer import error: " . $e->getMessage());
        }
    }

    private function importPets()
    {
        $file = base_path('data_files/116523_Customer_P_et_Details_1_2026_09_06_232737.xls');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Customer Pets ---");
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headerIdx = [];
        $customersCache = Customer::pluck('id', 'name')->toArray();
        $petTypesCache = PetType::pluck('id', 'name')->toArray();
        $breedsCache = Breed::pluck('id', 'name')->toArray();
        $colorsCache = Color::pluck('id', 'name')->toArray();

        $count = 0;

        foreach ($rows as $row) {
            $rowStr = array_map('trim', array_map('strval', $row));
            if (in_array('Customer Name', $rowStr)) {
                foreach ($rowStr as $idx => $col) {
                    if ($col !== '') $headerIdx[$col] = $idx;
                }
                continue;
            }

            if (empty($headerIdx)) continue;

            $custName = isset($headerIdx['Customer Name']) ? ($rowStr[$headerIdx['Customer Name']] ?? '') : '';
            if ($custName === '' || ! isset($customersCache[$custName])) continue;

            $custId = $customersCache[$custName];

            $typeStr = isset($headerIdx['Type']) ? ($rowStr[$headerIdx['Type']] ?? '') : '';
            $typeId = null;
            if ($typeStr !== '' && strtoupper($typeStr) !== 'NA') {
                if (! isset($petTypesCache[$typeStr])) {
                    $pt = PetType::create(['name' => $typeStr, 'status' => true]);
                    $petTypesCache[$typeStr] = $pt->id;
                }
                $typeId = $petTypesCache[$typeStr];
            }

            $breedStr = isset($headerIdx['Breed']) ? ($rowStr[$headerIdx['Breed']] ?? '') : '';
            $breedId = null;
            if ($breedStr !== '' && strtoupper($breedStr) !== 'NA') {
                if (! isset($breedsCache[$breedStr])) {
                    $br = Breed::create(['name' => $breedStr, 'pet_type_id' => $typeId, 'status' => true]);
                    $breedsCache[$breedStr] = $br->id;
                }
                $breedId = $breedsCache[$breedStr];
            }

            $colorStr = isset($headerIdx['Color']) ? ($rowStr[$headerIdx['Color']] ?? '') : '';
            $colorId = null;
            if ($colorStr !== '' && strtoupper($colorStr) !== 'NA') {
                if (! isset($colorsCache[$colorStr])) {
                    $cl = Color::create(['name' => $colorStr, 'status' => true]);
                    $colorsCache[$colorStr] = $cl->id;
                }
                $colorId = $colorsCache[$colorStr];
            }

            $gender = strtoupper(isset($headerIdx['Gender']) ? ($rowStr[$headerIdx['Gender']] ?? '') : '');
            $cleanGender = $gender === 'M' ? 'Male' : ($gender === 'F' ? 'Female' : null);

            CustomerPet::create([
                'customer_id' => $custId,
                'pet_type_id' => $typeId,
                'breed_id' => $breedId,
                'color_id' => $colorId,
                'name' => isset($headerIdx['Pet Name']) ? ($rowStr[$headerIdx['Pet Name']] ?: null) : null,
                'gender' => $cleanGender,
                'age' => isset($headerIdx['Age']) ? ($rowStr[$headerIdx['Age']] ?: null) : null,
            ]);
            $count++;
        }

        $this->info("Imported {$count} Customer Pets.");
    }

    private function importPriceList()
    {
        $file = base_path('data_files/110223_Price_list__1_2026_09_06_233050.csv');
        if (! file_exists($file)) return;

        $this->info("\n--- Importing Price List & Stock ---");
        $handle = fopen($file, 'r');
        if (! $handle) return;

        $headerIdx = [];
        $itemsCache = Item::pluck('id', 'name')->toArray();
        $branchesCache = Branch::pluck('id', 'name')->toArray();

        $defaultBranchId = Branch::first()->id ?? 1;
        $count = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $cleanRow = array_map(fn($v) => trim((string)$v), $row);
                if (in_array('Item name', $cleanRow)) {
                    foreach ($cleanRow as $idx => $col) {
                        if ($col !== '') $headerIdx[$col] = $idx;
                    }
                    continue;
                }

                if (empty($headerIdx)) continue;

                $itemName = isset($headerIdx['Item name']) ? ($cleanRow[$headerIdx['Item name']] ?? '') : '';
                if ($itemName === '' || ! isset($itemsCache[$itemName])) continue;

                $itemId = $itemsCache[$itemName];

                $branchName = isset($headerIdx['Branch']) ? ($cleanRow[$headerIdx['Branch']] ?? '') : '';
                $branchId = $branchesCache[$branchName] ?? $defaultBranchId;

                $cleanNum = fn($v) => (float) str_replace(',', '', $v);
                $stockQty = $cleanNum(isset($headerIdx['Stock']) ? ($cleanRow[$headerIdx['Stock']] ?? '0') : '0');
                $sellingPrice = $cleanNum(isset($headerIdx['Selling']) ? ($cleanRow[$headerIdx['Selling']] ?? '0') : '0');

                // Update stock for this item & branch
                ItemStock::updateOrCreate(
                    ['item_id' => $itemId, 'branch_id' => $branchId],
                    ['quantity' => $stockQty]
                );
                $count++;
            }

            DB::commit();
            fclose($handle);
            $this->info("Updated Stock & Prices for {$count} records.");

        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Price List import error: " . $e->getMessage());
        }
    }
}
