<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportDailySalesDetailed extends Command
{
    protected $signature = 'import:daily-sales-detailed {file? : Specific CSV file to process} {--dry-run : Simulate without writing to DB}';
    protected $description = 'Import detailed billwise itemwise sales from TruePOS Report 110116 into sales_bills and sales_bill_items';

    private array $branchMap = [
        'co-225' => 2,   // Satellite
        'co-32772' => 3, // Motera
    ];

    private array $itemCache = [];
    private array $customerCache = [];
    private int $defaultCustomerId = 1;

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (! $filePath) {
            $defaultPaths = [
                base_path('data_files/daily_sales/110116_sales_details.csv'),
                'C:/Users/Prem/Downloads/110116_Bill_Wise__les_Detail_1_2026_10_07_095920.csv',
            ];
            foreach ($defaultPaths as $p) {
                if (File::exists($p)) {
                    $filePath = $p;
                    break;
                }
            }
        }

        if (! $filePath || ! File::exists($filePath)) {
            $this->error("Sales details CSV file not found: {$filePath}");
            return 1;
        }

        $isDryRun = (bool) $this->option('dry-run');

        $this->info("=============================================================");
        $this->info("  IMPORTING DETAILED SALES BILLS & ITEMS (Report 110116)");
        $this->info("=============================================================");
        $this->info("File: {$filePath}");
        $this->info("Mode: " . ($isDryRun ? "DRY RUN (Simulation only)" : "LIVE COMMIT"));

        // Preload fallback customer
        $defCust = Customer::first();
        if ($defCust) {
            $this->defaultCustomerId = $defCust->id;
        }

        // Parse CSV
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->error("File is empty.");
            return 1;
        }

        $headerIdx = -1;
        foreach (array_slice($lines, 0, 15) as $idx => $line) {
            if (stripos($line, 'Bill date') !== false && stripos($line, 'Bill No') !== false) {
                $headerIdx = $idx;
                break;
            }
        }

        if ($headerIdx === -1) {
            $this->error("Header line containing 'Bill date' and 'Bill No' not found.");
            return 1;
        }

        $headers = str_getcsv($lines[$headerIdx]);
        $headers = array_map(fn($h) => trim($h, " \t\n\r\0\x0B\"'"), $headers);

        $billGroups = [];
        for ($i = $headerIdx + 1; $i < count($lines); $i++) {
            $rawRow = str_getcsv($lines[$i]);
            if (count($rawRow) < count($headers)) {
                $rawRow = array_pad($rawRow, count($headers), '');
            }
            $row = array_combine($headers, array_slice($rawRow, 0, count($headers)));

            $billNo = trim($row['Bill No'] ?? '');
            if ($billNo === '') continue;

            $till = trim($row['Till'] ?? '');
            $billKey = ($till ? $till . '-' : '') . $billNo;

            if (! isset($billGroups[$billKey])) {
                $billGroups[$billKey] = [
                    'till' => $till,
                    'bill_no' => $billNo,
                    'bill_number' => $billKey,
                    'bill_date' => trim($row['Bill date'] ?? ''),
                    'customer_name' => trim($row['Customer Name'] ?? ''),
                    'mobile' => trim($row['Mobile number'] ?? ''),
                    'delivery_type' => trim($row['Delivery type'] ?? 'Delivered'),
                    'message' => trim($row['Message'] ?? ''),
                    'sales_executive' => trim($row['Sales executive'] ?? ''),
                    'items' => [],
                ];
            }

            $billGroups[$billKey]['items'][] = [
                'item_code' => trim($row['Item code'] ?? ''),
                'item_name' => trim($row['Item name'] ?? ''),
                'qty' => (float) str_replace(',', '', trim($row['Qty'] ?? '1')),
                'mrp' => (float) str_replace(',', '', trim($row['MRP'] ?? '0')),
                'bill_amount' => (float) str_replace(',', '', trim($row['Bill amount'] ?? '0')),
                'brand_name' => trim($row['Brand name'] ?? ''),
            ];
        }

        $this->info("Parsed " . count($billGroups) . " unique sales bills from CSV.");

        $billsCount = 0;
        $itemsCount = 0;
        $totalSalesAmount = 0.0;
        $branchStats = [];

        if (! $isDryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($billGroups as $bKey => $bData) {
                $tillLower = strtolower($bData['till']);
                $branchId = $this->branchMap[$tillLower] ?? 2;

                $branchStats[$branchId] = ($branchStats[$branchId] ?? 0) + 1;

                // Resolve customer
                $customerId = $this->resolveCustomer(
                    $bData['mobile'],
                    $bData['customer_name'],
                    $branchId,
                    $isDryRun
                );

                // Calculate bill totals
                $billTotal = 0.0;
                $billQty = 0.0;
                foreach ($bData['items'] as $it) {
                    $billTotal += $it['bill_amount'];
                    $billQty += $it['qty'];
                }

                $billDate = $bData['bill_date'];
                if ($billDate && ! str_contains($billDate, ':')) {
                    $billDate .= ' 12:00:00';
                }

                $salesBillId = null;

                if (! $isDryRun) {
                    $salesBill = SalesBill::updateOrCreate(
                        ['bill_number' => $bData['bill_number']],
                        [
                            'bill_date' => $billDate ?: now(),
                            'customer_id' => $customerId,
                            'branch_id' => $branchId,
                            'invoice_type' => 'Retail Invoice',
                            'delivery_type' => $bData['delivery_type'] ?: 'Delivered',
                            'sales_type' => 'Local',
                            'payment_type' => 'Cash',
                            'total_qty' => $billQty,
                            'total' => $billTotal,
                            'remarks' => $bData['sales_executive'] ? "Exec: {$bData['sales_executive']}" : null,
                            'message' => $bData['message'] ?: null,
                            'status' => 'Posted',
                        ]
                    );
                    $salesBillId = $salesBill->id;

                    // Clean previous items for this bill if re-importing
                    SalesBillItem::where('sales_bill_id', $salesBillId)->delete();
                }

                // Process Items
                foreach ($bData['items'] as $it) {
                    $itemModel = $this->resolveItem($it['item_code'], $it['item_name']);
                    $itemId = $itemModel?->id;

                    if (! $itemId) {
                        // Fallback item if not found
                        $fallback = Item::first();
                        $itemId = $fallback?->id ?? 1;
                    }

                    $qty = $it['qty'] > 0 ? $it['qty'] : 1.0;
                    $sellPrice = $qty > 0 ? round($it['bill_amount'] / $qty, 2) : $it['bill_amount'];
                    $mrp = $it['mrp'] > 0 ? $it['mrp'] : $sellPrice;
                    $discAmount = max(0.0, ($mrp * $qty) - $it['bill_amount']);

                    if (! $isDryRun && $salesBillId) {
                        SalesBillItem::create([
                            'sales_bill_id' => $salesBillId,
                            'item_id' => $itemId,
                            'qty' => $qty,
                            'sell_price' => $sellPrice,
                            'cost_at_sale' => $itemModel?->cost_price ?? 0.00,
                            'mrp' => $mrp,
                            'disc_amount' => $discAmount,
                            'net_amount' => $it['bill_amount'],
                        ]);
                    }

                    $itemsCount++;
                }

                $billsCount++;
                $totalSalesAmount += $billTotal;
            }

            if (! $isDryRun) {
                DB::commit();
            }

            $this->info("\n=============================================================");
            $this->info("  IMPORT COMPLETED SUCCESSFULLY" . ($isDryRun ? " (DRY RUN)" : " (LIVE COMMIT)"));
            $this->info("=============================================================");
            $this->info("Total Bills Processed: {$billsCount}");
            $this->info("Total Items Processed: {$itemsCount}");
            $this->info("Total Sales Amount: ₹" . number_format($totalSalesAmount, 2));
            foreach ($branchStats as $bId => $cnt) {
                $bName = $bId === 2 ? 'Satellite (Branch 2)' : ($bId === 3 ? 'Motera (Branch 3)' : "Branch {$bId}");
                $this->info(" - {$bName}: {$cnt} bills");
            }

            return 0;
        } catch (\Throwable $e) {
            if (! $isDryRun) {
                DB::rollBack();
            }
            $this->error("Error during sales import: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function resolveCustomer(string $mobile, string $name, int $branchId, bool $isDryRun): int
    {
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);

        if ($cleanMobile && strlen($cleanMobile) >= 10) {
            $key = substr($cleanMobile, -10);
            if (isset($this->customerCache[$key])) {
                return $this->customerCache[$key];
            }

            $existing = Customer::where('mobile', 'like', "%{$key}%")->first();
            if ($existing) {
                $this->customerCache[$key] = $existing->id;
                return $existing->id;
            }

            if (! $isDryRun) {
                $custCode = 'CUST-' . $key;
                $newCust = Customer::create([
                    'name' => $name ?: 'Customer ' . $key,
                    'customer_code' => $custCode,
                    'mobile' => $key,
                    'customer_category_id' => 1,
                    'sales_type' => 'Local',
                    'payment_mode' => 'Both Cash and Credit',
                    'credit_limit' => 0,
                    'credit_balance' => 0,
                    'monthly_credit_balance' => 0,
                    'credit_days' => 0,
                    'branch_id' => $branchId,
                    'status' => true,
                    'gst_type' => 'Un Register',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'customer_type' => 'RETAIL INVOICE',
                ]);
                $this->customerCache[$key] = $newCust->id;
                return $newCust->id;
            }
        }

        return $this->defaultCustomerId;
    }

    private function resolveItem(string $code, string $name): ?Item
    {
        $code = trim($code);
        if ($code !== '') {
            if (isset($this->itemCache['code_' . $code])) {
                return $this->itemCache['code_' . $code];
            }
            $item = Item::where('item_code', $code)->first();
            if ($item) {
                $this->itemCache['code_' . $code] = $item;
                return $item;
            }
        }

        $name = trim($name);
        if ($name !== '') {
            if (isset($this->itemCache['name_' . $name])) {
                return $this->itemCache['name_' . $name];
            }
            $item = Item::where('name', $name)->first();
            if ($item) {
                $this->itemCache['name_' . $name] = $item;
                return $item;
            }
        }

        return null;
    }
}
