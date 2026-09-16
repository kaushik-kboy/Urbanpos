<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportHistoricalTransactions extends Command
{
    protected $signature = 'import:historical-transactions {--module=all}';
    protected $description = 'Safely and idempotently import historical transactions (Sales, Purchases, Returns, Quotations, Orders, Deliveries) from TruePOS CSV exports';

    private array $branchMap = [];
    private array $customerMap = [];
    private array $supplierMap = [];
    private int $defaultBranchId = 1;
    private int $defaultCustomerId = 1;
    private int $defaultSupplierId = 1;

    public function handle(): int
    {
        $this->info("=== Starting Historical Transaction Data Import ===");

        $baseDir = base_path('data_files/historical_exports');
        if (!File::exists($baseDir)) {
            $this->error("Directory not found: {$baseDir}");
            return 1;
        }

        $this->loadLookups();

        $module = strtolower($this->option('module') ?: 'all');

        if ($module === 'all' || $module === 'purchase_orders') {
            $this->importPurchaseOrders($baseDir . '/purchase_orders.csv');
        }

        if ($module === 'all' || $module === 'purchase_invoices') {
            $this->importPurchaseInvoices($baseDir . '/purchase_invoices.csv');
        }

        if ($module === 'all' || $module === 'purchase_returns') {
            $this->importPurchaseReturns($baseDir . '/purchase_returns.csv');
        }

        if ($module === 'all' || $module === 'sales_quotations') {
            $this->importSalesQuotations($baseDir . '/sales_quotations.csv');
        }

        if ($module === 'all' || $module === 'sales_orders') {
            $this->importSalesOrders($baseDir . '/sales_orders.csv');
        }

        if ($module === 'all' || $module === 'sales_delivery_notes') {
            $this->importSalesDeliveryNotes($baseDir . '/sales_delivery_notes.csv');
        }

        if ($module === 'all' || $module === 'sales_returns') {
            $this->importSalesReturns($baseDir . '/sales_returns.csv');
        }

        if ($module === 'all' || $module === 'sales_bills') {
            $this->importSalesBills($baseDir . '/sales_bills.csv');
        }

        $this->info("\n=== Historical Transaction Data Import Finished Successfully! ===");
        return 0;
    }

    private function loadLookups(): void
    {
        $this->info("Loading reference mappings into memory...");

        // Ensure at least one Branch exists
        $defBranch = Branch::first();
        if (!$defBranch) {
            $defBranch = Branch::create([
                'name' => 'URBANPETS SERVICES PRIVATE LIMITED',
                'code' => '225',
                'status' => 1,
            ]);
        }
        $this->defaultBranchId = $defBranch->id;

        $branches = Branch::all();
        foreach ($branches as $b) {
            $this->branchMap[strtolower(trim($b->name))] = $b->id;
            if ($b->code) {
                $this->branchMap[strtolower(trim($b->code))] = $b->id;
            }
        }

        // Ensure at least one Supplier exists
        $defSupplier = Supplier::first();
        if (!$defSupplier) {
            $defSupplier = Supplier::create([
                'name' => 'General Trade Supplier',
                'supplier_code' => 'SUPP-GEN',
                'status' => 1,
            ]);
        }
        $this->defaultSupplierId = $defSupplier->id;

        $suppliers = Supplier::all();
        foreach ($suppliers as $s) {
            $this->supplierMap[strtolower(trim($s->name))] = $s->id;
            if ($s->supplier_code) {
                $this->supplierMap['code_' . strtolower(trim($s->supplier_code))] = $s->id;
            }
        }

        // Ensure default customer exists
        $defCustomer = Customer::first();
        if (!$defCustomer) {
            $defCustomer = Customer::create([
                'name' => 'Walk-in Customer',
                'customer_code' => 'WALKIN-01',
                'mobile' => '9999999999',
                'status' => 1,
            ]);
        }
        $this->defaultCustomerId = $defCustomer->id;

        $customers = Customer::select('id', 'name', 'mobile', 'customer_code')->get();
        foreach ($customers as $c) {
            if ($c->mobile) {
                $this->customerMap[trim($c->mobile)] = $c->id;
            }
            if ($c->customer_code) {
                $this->customerMap['code_' . trim($c->customer_code)] = $c->id;
            }
            $this->customerMap['name_' . strtolower(trim($c->name))] = $c->id;
        }

        $this->info(sprintf(
            "Lookups loaded: %d branches, %d suppliers, %d customers.",
            count($branches),
            count($suppliers),
            count($customers)
        ));
    }

    private function resolveSupplier(?string $name, ?string $code = null): int
    {
        $nameClean = strtolower(trim($name ?: ''));
        if ($nameClean && isset($this->supplierMap[$nameClean])) {
            return $this->supplierMap[$nameClean];
        }

        if ($code && isset($this->supplierMap['code_' . strtolower(trim($code))])) {
            return $this->supplierMap['code_' . strtolower(trim($code))];
        }

        if ($nameClean) {
            $supp = Supplier::firstOrCreate(
                ['name' => ucwords($name)],
                [
                    'supplier_code' => 'SUPP-' . ($code ?: substr(md5($nameClean), 0, 6)),
                    'status' => 1
                ]
            );
            $this->supplierMap[$nameClean] = $supp->id;
            return $supp->id;
        }

        return $this->defaultSupplierId;
    }

    private function cleanNumber($val): float
    {
        if (!$val) return 0.0;
        $val = str_replace([',', '₹', '$', ' '], '', (string)$val);
        return is_numeric($val) ? (float)$val : 0.0;
    }

    private function parseCsv(string $filePath): \Generator
    {
        if (!File::exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) return;

        $header = null;
        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
            // Find header line
            if (!$header) {
                $trimmed = array_map(fn($v) => trim((string)$v), $row);
                // Detect header row by checking for known keywords
                if (in_array('Store', $trimmed) || in_array('Bill date', $trimmed) || in_array('GRN No', $trimmed) || in_array('Return No', $trimmed) || in_array('Quotation No', $trimmed) || in_array('Order No', $trimmed) || in_array('DN No', $trimmed) || in_array('PR No', $trimmed) || in_array('Sequence No', $trimmed)) {
                    $header = $trimmed;
                }
                continue;
            }

            // Skip summary rows or empty rows
            if (empty($row) || count($row) < 2) continue;

            $record = [];
            foreach ($header as $idx => $colName) {
                if ($colName !== '') {
                    $record[$colName] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                }
            }

            yield $record;
        }

        fclose($handle);
    }

    /* ----------------------------------------------------------------------
     * 1. PURCHASE ORDERS
     * ---------------------------------------------------------------------- */
    private function importPurchaseOrders(string $filePath): void
    {
        $this->info("\n--- Importing Purchase Orders ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $seqNo = $row['Sequence No'] ?? null;
            if (!$seqNo || !is_numeric($seqNo)) continue;

            $poNumber = 'PO-' . ($row['Branch prefix'] ?? 'LN') . '-' . str_pad($seqNo, 5, '0', STR_PAD_LEFT);
            $poDate = !empty($row['PO date']) ? date('Y-m-d', strtotime($row['PO date'])) : now()->format('Y-m-d');
            $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);

            $branchName = strtolower(trim($row['Branch Name'] ?? ''));
            $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

            $total = $this->cleanNumber($row['PO amount'] ?? 0);
            $gst = $this->cleanNumber($row['GST TaxAmt'] ?? 0);
            $rawStatus = ucfirst(strtolower(trim($row['Status'] ?? '')));
            $status = match ($rawStatus) {
                'Cancelled' => 'Cancelled',
                'Closed' => 'Closed',
                default => 'Open',
            };

            PurchaseOrder::updateOrCreate(
                ['po_number' => $poNumber],
                [
                    'po_date' => $poDate,
                    'supplier_id' => $supplierId,
                    'branch_id' => $branchId,
                    'purchase_type' => 'Local',
                    'total_gst' => $gst,
                    'total' => $total,
                    'status' => $status,
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Purchase Orders.");
    }

    /* ----------------------------------------------------------------------
     * 2. PURCHASE INVOICES
     * ---------------------------------------------------------------------- */
    private function importPurchaseInvoices(string $filePath): void
    {
        $this->info("\n--- Importing Purchase Invoices ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $grnNo = $row['GRN No'] ?? null;
                if (!$grnNo || !is_numeric($grnNo)) continue;

                $invNo = 'GRN-' . $grnNo;
                $invDate = !empty($row['GRN date']) ? date('Y-m-d', strtotime($row['GRN date'])) : now()->format('Y-m-d');
                $supplierBillNo = $row['Supplier Bill No'] ?? null;
                $supplierBillDate = !empty($row['Supplier Bill Date']) ? date('Y-m-d', strtotime($row['Supplier Bill Date'])) : null;

                $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);

                $branchName = strtolower(trim($row['Branch Name'] ?? ''));
                $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

                $total = $this->cleanNumber($row['GRN amount'] ?? ($row['Bill amount'] ?? 0));
                $gst = $this->cleanNumber($row['GST TaxAmt'] ?? 0);
                $cgst = $this->cleanNumber($row['CGST TaxAmt'] ?? 0);
                $sgst = $this->cleanNumber($row['SGST TaxAmt'] ?? 0);
                $igst = $this->cleanNumber($row['IGST TaxAmt'] ?? 0);
                $totalQty = (int) $this->cleanNumber($row['Total items'] ?? 0);
                $disc = $this->cleanNumber($row['Cash disc. Amount'] ?? 0);
                $rawStatus = ucfirst(strtolower(trim($row['Status'] ?? '')));
                $status = ($rawStatus === 'Cancelled') ? 'Cancelled' : 'Posted';

                PurchaseInvoice::updateOrCreate(
                    ['invoice_number' => $invNo],
                    [
                        'invoice_date' => $invDate,
                        'supplier_id' => $supplierId,
                        'branch_id' => $branchId,
                        'supplier_inv_no' => $supplierBillNo,
                        'supplier_inv_date' => $supplierBillDate,
                        'purchase_type' => 'Local',
                        'total_gst' => $gst,
                        'total_cgst' => $cgst,
                        'total_sgst' => $sgst,
                        'total_igst' => $igst,
                        'disc_amount' => $disc,
                        'total_qty' => $totalQty ?: 1,
                        'total' => $total,
                        'status' => $status,
                    ]
                );

                $count++;
                if ($count % 500 === 0) {
                    $this->line("Processed {$count} Purchase Invoices...");
                    DB::commit();
                    DB::beginTransaction();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing purchase invoices: " . $e->getMessage());
        }

        $this->info("Imported / Synchronized {$count} Purchase Invoices.");
    }

    /* ----------------------------------------------------------------------
     * 3. PURCHASE RETURNS
     * ---------------------------------------------------------------------- */
    private function importPurchaseReturns(string $filePath): void
    {
        $this->info("\n--- Importing Purchase Returns ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $prNo = $row['PR No'] ?? null;
            if (!$prNo || !is_numeric($prNo)) continue;

            $returnNo = 'PR-' . $prNo;
            $returnDate = !empty($row['PR date']) ? date('Y-m-d', strtotime($row['PR date'])) : now()->format('Y-m-d');
            $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);

            $branchName = strtolower(trim($row['Branch Name'] ?? ''));
            $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

            $total = $this->cleanNumber($row['PR amount'] ?? 0);
            $gst = $this->cleanNumber($row['GST TaxAmt'] ?? 0);
            $cgst = $this->cleanNumber($row['CGST TaxAmt'] ?? 0);
            $sgst = $this->cleanNumber($row['SGST TaxAmt'] ?? 0);
            $igst = $this->cleanNumber($row['IGST TaxAmt'] ?? 0);

            PurchaseReturn::updateOrCreate(
                ['return_number' => $returnNo],
                [
                    'return_date' => $returnDate,
                    'supplier_id' => $supplierId,
                    'branch_id' => $branchId,
                    'purchase_type' => 'Local',
                    'total_gst' => $gst,
                    'total_cgst' => $cgst,
                    'total_sgst' => $sgst,
                    'total_igst' => $igst,
                    'total' => $total,
                    'status' => 'Posted',
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Purchase Returns.");
    }

    /* ----------------------------------------------------------------------
     * 4. SALES QUOTATIONS
     * ---------------------------------------------------------------------- */
    private function importSalesQuotations(string $filePath): void
    {
        $this->info("\n--- Importing Sales Quotations ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $qNo = $row['Quotation No'] ?? null;
            if (!$qNo) continue;

            $qDate = !empty($row['Quotation date']) ? date('Y-m-d', strtotime($row['Quotation date'])) : now()->format('Y-m-d');
            $branchName = strtolower(trim($row['Store'] ?? ''));
            $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

            $custName = trim($row['Customer'] ?? '');
            $customerId = $this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId;

            $total = $this->cleanNumber($row['SO amount'] ?? 0);
            $rawStatus = ucfirst(strtolower(trim($row['Status'] ?? '')));
            $status = in_array($rawStatus, ['Draft', 'Sent', 'Accepted', 'Converted', 'Cancelled']) ? $rawStatus : 'Draft';

            SalesQuotation::updateOrCreate(
                ['quotation_number' => $qNo],
                [
                    'quotation_date' => $qDate,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'sales_type' => 'Local',
                    'total' => $total,
                    'status' => $status,
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Sales Quotations.");
    }

    /* ----------------------------------------------------------------------
     * 5. SALES ORDERS
     * ---------------------------------------------------------------------- */
    private function importSalesOrders(string $filePath): void
    {
        $this->info("\n--- Importing Sales Orders ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $orderNo = $row['Order No'] ?? null;
            if (!$orderNo) continue;

            $orderDate = !empty($row['Order date']) ? date('Y-m-d', strtotime($row['Order date'])) : now()->format('Y-m-d');
            $branchName = strtolower(trim($row['Ordered From Branch'] ?? ''));
            $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

            $custName = trim($row['Customer Name'] ?? '');
            $customerId = $this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId;

            $total = $this->cleanNumber($row['SO amount'] ?? 0);
            $advance = $this->cleanNumber($row['SO advance amount'] ?? 0);
            $gst = $this->cleanNumber($row['GST TaxAmt'] ?? 0);
            $rawStatus = ucfirst(strtolower(trim($row['Status'] ?? '')));
            $status = in_array($rawStatus, ['Open', 'Partially Fulfilled', 'Converted', 'Cancelled']) ? $rawStatus : 'Open';

            SalesOrder::updateOrCreate(
                ['order_number' => $orderNo],
                [
                    'order_date' => $orderDate,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'sales_type' => 'Local',
                    'total_gst' => $gst,
                    'advance_amount' => $advance,
                    'total' => $total,
                    'status' => $status,
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Sales Orders.");
    }

    /* ----------------------------------------------------------------------
     * 6. SALES DELIVERY NOTES
     * ---------------------------------------------------------------------- */
    private function importSalesDeliveryNotes(string $filePath): void
    {
        $this->info("\n--- Importing Sales Delivery Notes ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $dnNo = $row['DN No'] ?? null;
            if (!$dnNo) continue;

            $dnDate = !empty($row['DN date']) ? date('Y-m-d', strtotime($row['DN date'])) : now()->format('Y-m-d');
            $branchName = strtolower(trim($row['Branch Name'] ?? ''));
            $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

            $custPhone = trim($row['Mobile'] ?? ($row['Phone'] ?? ''));
            $customerId = $this->customerMap[$custPhone] ?? $this->defaultCustomerId;

            $total = $this->cleanNumber($row['Sales DN amount'] ?? 0);
            $totalQty = (int) $this->cleanNumber($row['Total No of items'] ?? 0);
            $rawStatus = ucfirst(strtolower(trim($row['Status'] ?? '')));
            $status = in_array($rawStatus, ['Dispatched', 'Invoiced', 'Cancelled']) ? $rawStatus : 'Dispatched';

            SalesDeliveryNote::updateOrCreate(
                ['delivery_number' => $dnNo],
                [
                    'delivery_date' => $dnDate,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'total_ordered_qty' => $totalQty,
                    'total_dispatched_qty' => $totalQty,
                    'total_amount' => $total,
                    'status' => $status,
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Sales Delivery Notes.");
    }

    /* ----------------------------------------------------------------------
     * 7. SALES RETURNS
     * ---------------------------------------------------------------------- */
    private function importSalesReturns(string $filePath): void
    {
        $this->info("\n--- Importing Sales Returns ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $retNo = $row['Return No'] ?? null;
                if (!$retNo || !is_numeric($retNo)) continue;

                $prefix = $row['SR Prefix'] ?? 'SR';
                $returnNumber = $prefix . '-' . $retNo;
                $returnDate = !empty($row['Return date']) ? date('Y-m-d', strtotime($row['Return date'])) : now()->format('Y-m-d');

                $branchName = strtolower(trim($row['Branch Name'] ?? ''));
                $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

                $custCode = trim($row['Code'] ?? '');
                $custName = trim($row['Customer Name'] ?? '');
                $customerId = $this->customerMap['code_' . $custCode]
                    ?? ($this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId);

                $total = $this->cleanNumber($row['Return amount'] ?? 0);
                $gst = $this->cleanNumber($row['GST TaxAmt'] ?? 0);
                $cgst = $this->cleanNumber($row['CGST TaxAmt'] ?? 0);
                $sgst = $this->cleanNumber($row['SGST TaxAmt'] ?? 0);
                $igst = $this->cleanNumber($row['IGST TaxAmt'] ?? 0);
                $rawMode = trim($row['Payment type'] ?? '');
                $mode = match ($rawMode) {
                    'RRN' => 'RRN',
                    'Credit Note', 'Credit' => 'Credit Note',
                    'Card' => 'Card',
                    'Wallet' => 'Wallet',
                    default => 'Cash',
                };

                SalesReturn::updateOrCreate(
                    ['return_number' => $returnNumber],
                    [
                        'return_date' => $returnDate,
                        'customer_id' => $customerId,
                        'branch_id' => $branchId,
                        'return_mode' => $mode,
                        'sales_type' => 'Local',
                        'total_gst' => $gst,
                        'total_cgst' => $cgst,
                        'total_sgst' => $sgst,
                        'total_igst' => $igst,
                        'total' => $total,
                        'status' => 'Posted',
                    ]
                );

                $count++;
                if ($count % 500 === 0) {
                    $this->line("Processed {$count} Sales Returns...");
                    DB::commit();
                    DB::beginTransaction();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing sales returns: " . $e->getMessage());
        }

        $this->info("Imported / Synchronized {$count} Sales Returns.");
    }

    /* ----------------------------------------------------------------------
     * 8. SALES BILLS
     * ---------------------------------------------------------------------- */
    private function importSalesBills(string $filePath): void
    {
        $this->info("\n--- Importing Sales Bills ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $billNo = $row['Till sequence'] ?? null;
                if (!$billNo) continue;

                $billDate = !empty($row['Bill date']) ? date('Y-m-d', strtotime($row['Bill date'])) : now()->format('Y-m-d');
                $branchName = strtolower(trim($row['Store'] ?? ''));
                $branchId = $this->branchMap[$branchName] ?? $this->defaultBranchId;

                $mobile = trim($row['Mobile'] ?? '');
                $custName = trim($row['Customer'] ?? '');
                $customerId = $this->customerMap[$mobile]
                    ?? ($this->customerMap['name_' . strtolower($custName)] ?? null);

                // If customer is not found but mobile is given, create customer dynamically
                if (!$customerId && $mobile && strlen($mobile) >= 7) {
                    $newCust = Customer::create([
                        'name' => $custName ?: 'Customer ' . $mobile,
                        'mobile' => $mobile,
                        'customer_code' => 'CUST-' . substr($mobile, -6),
                        'branch_id' => $branchId,
                        'status' => 1,
                    ]);
                    $customerId = $newCust->id;
                    $this->customerMap[$mobile] = $customerId;
                }

                if (!$customerId) {
                    $customerId = $this->defaultCustomerId;
                }

                $total = $this->cleanNumber($row['Bill amount'] ?? 0);
                $remarks = $row['Remarks'] ?? null;
                $message = $row['Message'] ?? null;

                SalesBill::updateOrCreate(
                    ['bill_number' => $billNo],
                    [
                        'bill_date' => $billDate,
                        'customer_id' => $customerId,
                        'branch_id' => $branchId,
                        'invoice_type' => 'Retail Invoice',
                        'delivery_type' => 'Store Pickup',
                        'sales_type' => 'Local',
                        'payment_type' => 'Cash',
                        'total_qty' => 1,
                        'total' => $total,
                        'remarks' => $remarks,
                        'message' => $message,
                        'status' => 'Posted',
                    ]
                );

                $count++;
                if ($count % 1000 === 0) {
                    $this->line("Processed {$count} Sales Bills...");
                    DB::commit();
                    DB::beginTransaction();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing sales bills: " . $e->getMessage());
        }

        $this->info("Imported / Synchronized {$count} Sales Bills.");
    }
}
