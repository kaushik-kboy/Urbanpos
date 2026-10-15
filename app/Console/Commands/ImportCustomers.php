<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCustomers extends Command
{
    protected $signature = 'import:customers {file? : Path to Customer CSV file} {--update : Update existing customers if code exists}';
    protected $description = 'Import TruePOS 110126 Customer Master CSV into customers table';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (! $filePath) {
            $default14Oct = base_path('data_files/customers_14oct/Customer_Master_14Oct_All.csv');
            if (file_exists($default14Oct)) {
                $filePath = $default14Oct;
            } else {
                $fallback = base_path('data_files/110126_Customer_M_mer_Master_1_2026_09_06_232628.csv');
                $filePath = $fallback;
            }
        }

        if (! file_exists($filePath)) {
            $this->error("Customer file not found: {$filePath}");
            return 1;
        }

        $this->info("=================================================");
        $this->info("   IMPORTING TRUEPOS CUSTOMER MASTER (110126)   ");
        $this->info(" File: " . basename($filePath) . " (" . number_format(filesize($filePath) / 1024 / 1024, 2) . " MB)");
        $this->info("=================================================");

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Cannot open file: {$filePath}");
            return 1;
        }

        $headerIdx = [];
        $categoriesCache = CustomerCategory::pluck('id', 'name')->toArray();
        $existingCodes = Customer::whereNotNull('customer_code')->where('customer_code', '!=', '')->pluck('id', 'customer_code')->toArray();
        $existingNames = Customer::pluck('id', 'name')->toArray();
        $existingMobiles = Customer::whereNotNull('mobile')->where('mobile', '!=', '')->pluck('id', 'mobile')->toArray();

        $validTitles = [
            'MR.' => 'Mr', 'MR' => 'Mr',
            'MS.' => 'Ms', 'MS' => 'Ms',
            'MRS.' => 'Mrs', 'MRS' => 'Mrs',
            'M/S' => 'M/s', 'DR.' => 'Dr', 'DR' => 'Dr'
        ];

        $cleanNum = fn($v) => (float) str_replace(',', '', trim((string)$v));

        $newCount = 0;
        $updatedCount = 0;
        $rowCount = 0;

        $shouldUpdate = $this->option('update') || true;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $cleanRow = array_map(fn($v) => trim((string)$v), $row);
                if (in_array('Customer Name', $cleanRow) || in_array('Code', $cleanRow)) {
                    foreach ($cleanRow as $idx => $col) {
                        if ($col !== '') {
                            $headerIdx[$col] = $idx;
                        }
                    }
                    continue;
                }

                if (empty($headerIdx)) {
                    continue;
                }

                $getVal = fn($name) => isset($headerIdx[$name]) ? ($cleanRow[$headerIdx[$name]] ?? '') : '';

                $custName = $getVal('Customer Name');
                $code = $getVal('Code') ?: null;
                $mobile = $getVal('Mobile') ?: null;

                if ($custName === '' && ! $code) {
                    continue;
                }

                if ($custName === '') {
                    $custName = "Customer {$code}";
                }

                $rowCount++;

                $catName = $getVal('Category') ?: 'WALK-IN';
                if (! isset($categoriesCache[$catName])) {
                    $cat = CustomerCategory::create(['name' => $catName, 'status' => true]);
                    $categoriesCache[$catName] = $cat->id;
                }
                $catId = $categoriesCache[$catName];

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
                    'city' => $getVal('Place') ?: ($getVal('City') ?: null),
                    'state' => $getVal('State Name') ?: null,
                    'postal_code' => $getVal('Postal / ZIP code') ?: null,
                    'phone' => $getVal('Phone') ?: null,
                    'mobile' => $mobile,
                    'email' => $getVal('Email') ?: null,
                    'gst_no' => $getVal('GST No.') ?: null,
                    'gender' => $gender,
                    'updated_at' => now(),
                ];

                // Check existing by Code, then Mobile, then Name
                $existingId = null;
                if ($code && isset($existingCodes[$code])) {
                    $existingId = $existingCodes[$code];
                } elseif ($mobile && isset($existingMobiles[$mobile])) {
                    $existingId = $existingMobiles[$mobile];
                }

                if ($existingId) {
                    if ($shouldUpdate) {
                        Customer::where('id', $existingId)->update($custData);
                        $updatedCount++;
                    }
                } else {
                    $custData['created_at'] = now();
                    $newCust = Customer::create($custData);
                    $existingId = $newCust->id;
                    if ($code) {
                        $existingCodes[$code] = $existingId;
                    }
                    if ($mobile) {
                        $existingMobiles[$mobile] = $existingId;
                    }
                    $existingNames[$custName] = $existingId;
                    $newCount++;
                }

                if ($rowCount % 2000 === 0) {
                    $this->line("  Processed {$rowCount} rows... (New: {$newCount}, Updated: {$updatedCount})");
                }
            }

            DB::commit();
            fclose($handle);

            $totalCustomers = Customer::count();
            $this->info("\n=================================================");
            $this->info("   CUSTOMER MASTER IMPORT COMPLETE!             ");
            $this->info(" Total Rows Processed: " . number_format($rowCount));
            $this->info(" New Customers Added:  " . number_format($newCount));
            $this->info(" Existing Updated:     " . number_format($updatedCount));
            $this->info(" Total Customers in DB: " . number_format($totalCustomers));
            $this->info("=================================================");

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Customer import failed: " . $e->getMessage() . " at line " . $e->getLine());
            return 1;
        }
    }
}
