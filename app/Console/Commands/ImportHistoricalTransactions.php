<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportHistoricalTransactions extends Command
{
    protected $signature = 'import:historical-transactions 
                            {--module=all : Module to import (sales_bills, purchase_invoices, purchase_returns, sales_returns, stock_transfers, sales_orders, purchase_orders, all)} 
                            {--from=2026-02-01 : Filter transactions from date (YYYY-MM-DD)} 
                            {--to=2026-10-15 : Filter transactions to date (YYYY-MM-DD)}';

    protected $description = 'Safely and idempotently import historical transactions (Sales, Purchases, Returns, Transfers, Quotations, Orders, Deliveries) from TruePOS CSV exports';

    private array $branchMap = [];
    private array $customerMap = [];
    private array $supplierMap = [];
    private array $itemsMap = [];
    private int $defaultBranchId = 2;
    private int $satelliteBranchId = 2;
    private int $moteraBranchId = 3;
    private int $defaultCustomerId = 1;
    private int $defaultSupplierId = 1;

    private string $fromDate = '2026-02-01';
    private string $toDate = '2026-10-15';

    public function handle(): int
    {
        $this->fromDate = $this->option('from') ?: '2026-02-01';
        $this->toDate = $this->option('to') ?: '2026-10-15';

        $this->info("==========================================================");
        $this->info("      TRUEPOS ➔ URBANPOS HISTORICAL TRANSACTIONS SYNC     ");
        $this->info(" Date Range: {$this->fromDate} to {$this->toDate}");
        $this->info(" Module:     " . ($this->option('module') ?: 'all'));
        $this->info("==========================================================");

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
            // Also import October 2026 purchases if file exists
            $octPur = base_path('data_files/sync_october/purchase_detail_110120_oct2026.csv');
            if (File::exists($octPur)) {
                $this->importOctoberPurchases($octPur);
            }
        }

        if ($module === 'all' || $module === 'purchase_returns') {
            $this->importPurchaseReturns($baseDir . '/purchase_returns.csv');
        }

        if ($module === 'all' || $module === 'stock_transfers') {
            $octTrans = base_path('data_files/sync_october/transfer_detail_110282_oct2026.csv');
            if (File::exists($octTrans)) {
                $this->importOctoberTransfers($octTrans);
            }
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

        $this->info("\n==========================================================");
        $this->info("   HISTORICAL TRANSACTIONS IMPORT FINISHED SUCCESSFULLY!  ");
        $this->info("==========================================================");
        return 0;
    }

    private function loadLookups(): void
    {
        $this->info("Loading reference mappings into memory...");

        // Ensure at least one Branch exists
        $branches = Branch::all();
        $sat = $branches->first(fn($b) => str_contains(strtoupper($b->name), 'SATELLITE') || str_contains(strtoupper($b->name), 'SERVICES'))
            ?? $branches->first(fn($b) => $b->id == 2)
            ?? $branches->first();

        $mot = $branches->first(fn($b) => str_contains(strtoupper($b->name), 'MOTERA'))
            ?? $branches->first(fn($b) => $b->id == 3)
            ?? $branches->skip(1)->first()
            ?? $branches->first();

        $this->satelliteBranchId = $sat ? $sat->id : 2;
        $this->moteraBranchId = $mot ? $mot->id : 3;
        $this->defaultBranchId = $this->satelliteBranchId;

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

        // Cache items by item_code for fast item resolution
        $this->itemsMap = Item::whereNotNull('item_code')->where('item_code', '!=', '')->pluck('id', 'item_code')->toArray();

        $this->info(sprintf(
            "Lookups loaded: %d branches (Satellite: #%d, Motera: #%d), %d suppliers, %d customers, %d items.",
            count($branches),
            $this->satelliteBranchId,
            $this->moteraBranchId,
            count($suppliers),
            count($customers),
            count($this->itemsMap)
        ));
    }

    private function resolveBranch(?string $nameOrCode): int
    {
        $clean = strtoupper(trim($nameOrCode ?? ''));
        if ($clean === '') return $this->defaultBranchId;

        if (str_contains($clean, 'MOTERA') || str_contains($clean, '32772')) {
            return $this->moteraBranchId;
        }
        if (str_contains($clean, 'SATELLITE') || str_contains($clean, 'SERVICES') || str_contains($clean, '225') || str_contains($clean, 'LN')) {
            return $this->satelliteBranchId;
        }
        return $this->branchMap[strtolower($clean)] ?? $this->defaultBranchId;
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
            if (!$header) {
                $trimmed = array_map(fn($v) => trim((string)$v), $row);
                if (in_array('Store', $trimmed) || in_array('Bill date', $trimmed) || in_array('GRN No', $trimmed) || in_array('Return No', $trimmed) || in_array('Quotation No', $trimmed) || in_array('Order No', $trimmed) || in_array('DN No', $trimmed) || in_array('PR No', $trimmed) || in_array('Sequence No', $trimmed) || in_array('TO date', $trimmed)) {
                    $header = $trimmed;
                }
                continue;
            }

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
        $this->info("\n--- Importing Purchase Orders ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $seqNo = $row['Sequence No'] ?? null;
            if (!$seqNo || !is_numeric($seqNo)) continue;

            $poDate = !empty($row['PO date']) ? date('Y-m-d', strtotime($row['PO date'])) : now()->format('Y-m-d');
            if ($poDate < $this->fromDate || $poDate > $this->toDate) continue;

            $poNumber = 'PO-' . ($row['Branch prefix'] ?? 'LN') . '-' . str_pad($seqNo, 5, '0', STR_PAD_LEFT);
            $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);
            $branchId = $this->resolveBranch($row['Branch Name'] ?? '');

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
     * 2. PURCHASE INVOICES (GRNs)
     * ---------------------------------------------------------------------- */
    private function importPurchaseInvoices(string $filePath): void
    {
        $this->info("\n--- Importing Purchase Invoices ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $grnNo = $row['GRN No'] ?? null;
                if (!$grnNo || !is_numeric($grnNo)) continue;

                $invDate = !empty($row['GRN date']) ? date('Y-m-d', strtotime($row['GRN date'])) : now()->format('Y-m-d');
                if ($invDate < $this->fromDate || $invDate > $this->toDate) continue;

                $invNo = 'GRN-' . $grnNo;
                $supplierBillNo = $row['Supplier Bill No'] ?? null;
                $supplierBillDate = !empty($row['Supplier Bill Date']) ? date('Y-m-d', strtotime($row['Supplier Bill Date'])) : null;

                $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);
                $branchId = $this->resolveBranch($row['Branch Name'] ?? ($row['Branch ID'] ?? ''));

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
     * 2b. OCTOBER DETAILED PURCHASES (GRN items)
     * ---------------------------------------------------------------------- */
    private function importOctoberPurchases(string $filePath): void
    {
        $this->info("\n--- Importing October Detailed Purchases ({$filePath}) ---");
        $count = 0;

        DB::beginTransaction();
        try {
            $affectedInvoiceIds = [];
            foreach ($this->parseCsv($filePath) as $row) {
                $grnNo = $row['GRN No'] ?? null;
                if (!$grnNo || !is_numeric($grnNo)) continue;

                $invDate = !empty($row['GRN date']) ? date('Y-m-d', strtotime($row['GRN date'])) : now()->format('Y-m-d');
                if ($invDate < $this->fromDate || $invDate > $this->toDate) continue;

                $invNo = 'GRN-' . $grnNo;
                $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null);
                $branchId = $this->resolveBranch($row['Store'] ?? ($row['Branch Area'] ?? ''));

                $qty = (float) $this->cleanNumber($row['Received Qty'] ?? 1);
                $rate = $this->cleanNumber($row['Purchase rate'] ?? ($row['Purchase Rate'] ?? ($row['Net cost'] ?? 0)));
                $amt = $this->cleanNumber($row['Purchase amount'] ?? ($row['Local Purchase amount'] ?? ($row['Total amount'] ?? ($qty * $rate))));
                $mrp = $this->cleanNumber($row['MRP'] ?? ($row['Selling'] ?? $rate));
                $sellPrice = $this->cleanNumber($row['Selling'] ?? ($row['MRP'] ?? 0));
                $effectiveCost = $this->cleanNumber($row['Net cost'] ?? ($row['Packed price'] ?? $rate));
                $expDate = !empty($row['Expiry date']) ? date('Y-m-d', strtotime($row['Expiry date'])) : null;
                $gstPerc = $this->cleanNumber($row['GST Perc'] ?? ($row['Tax%'] ?? 0));
                $gstTaxAmt = $this->cleanNumber($row['GST TaxAmt'] ?? ($row['Tax amount'] ?? 0));
                $discAmt = $this->cleanNumber($row['Disc. Amount'] ?? 0);
                $discPerc = $this->cleanNumber($row['Discount %'] ?? 0);
                $cgst = $this->cleanNumber($row['CGST TaxAmt'] ?? 0);
                $sgst = $this->cleanNumber($row['SGST TaxAmt'] ?? 0);
                $igst = $this->cleanNumber($row['IGST TaxAmt'] ?? 0);
                $totalGst = $gstTaxAmt > 0 ? $gstTaxAmt : ($cgst + $sgst + $igst);

                $pi = PurchaseInvoice::firstOrCreate(
                    ['invoice_number' => $invNo],
                    [
                        'invoice_date' => $invDate,
                        'supplier_id' => $supplierId,
                        'branch_id' => $branchId,
                        'purchase_type' => 'Local',
                        'total_gst' => $totalGst,
                        'total_cgst' => $cgst,
                        'total_sgst' => $sgst,
                        'total_igst' => $igst,
                        'total_qty' => $qty,
                        'total' => $amt,
                        'status' => 'Posted',
                    ]
                );

                // Attach line item if item exists
                $itemCode = trim($row['Item code'] ?? '');
                $itemId = $this->itemsMap[$itemCode] ?? null;
                if ($itemId) {
                    PurchaseInvoiceItem::updateOrCreate(
                        ['purchase_invoice_id' => $pi->id, 'item_id' => $itemId],
                        [
                            'qty' => $qty,
                            'free_qty' => 0,
                            'cost_price' => $rate,
                            'effective_cost' => $effectiveCost > 0 ? $effectiveCost : $rate,
                            'sell_price' => $sellPrice,
                            'mrp' => $mrp > 0 ? $mrp : $rate,
                            'disc_percent' => $discPerc,
                            'disc_amount' => $discAmt,
                            'gst_percent' => $gstPerc,
                            'gst_tax_amount' => $totalGst,
                            'cgst_amount' => $cgst,
                            'sgst_amount' => $sgst,
                            'igst_amount' => $igst,
                            'net_amount' => $amt,
                            'exp_date' => $expDate,
                        ]
                    );
                }

                $affectedInvoiceIds[] = $pi->id;
                $count++;
            }

            // Sync aggregate totals for all affected invoices
            foreach (array_unique($affectedInvoiceIds) as $piId) {
                $piRecord = PurchaseInvoice::find($piId);
                if ($piRecord && $piRecord->items()->exists()) {
                    $itemNetSum = (float) $piRecord->items()->sum('net_amount');
                    $itemQtySum = (float) $piRecord->items()->sum('qty');
                    $itemGstSum = (float) $piRecord->items()->sum('gst_tax_amount');
                    $itemCgstSum = (float) $piRecord->items()->sum('cgst_amount');
                    $itemSgstSum = (float) $piRecord->items()->sum('sgst_amount');
                    $itemIgstSum = (float) $piRecord->items()->sum('igst_amount');

                    $piRecord->update([
                        'total' => $itemNetSum > 0 ? $itemNetSum : $piRecord->total,
                        'total_qty' => $itemQtySum > 0 ? $itemQtySum : $piRecord->total_qty,
                        'total_gst' => $itemGstSum > 0 ? $itemGstSum : $piRecord->total_gst,
                        'total_cgst' => $itemCgstSum > 0 ? $itemCgstSum : $piRecord->total_cgst,
                        'total_sgst' => $itemSgstSum > 0 ? $itemSgstSum : $piRecord->total_sgst,
                        'total_igst' => $itemIgstSum > 0 ? $itemIgstSum : $piRecord->total_igst,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing October purchases: " . $e->getMessage());
        }

        $this->info("Imported / Synchronized {$count} October Purchase line records.");
    }

    /* ----------------------------------------------------------------------
     * 3. PURCHASE RETURNS
     * ---------------------------------------------------------------------- */
    private function importPurchaseReturns(string $filePath): void
    {
        $this->info("\n--- Importing Purchase Returns ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $prNo = $row['PR No'] ?? null;
            if (!$prNo || !is_numeric($prNo)) continue;

            $returnDate = !empty($row['PR date']) ? date('Y-m-d', strtotime($row['PR date'])) : now()->format('Y-m-d');
            if ($returnDate < $this->fromDate || $returnDate > $this->toDate) continue;

            $returnNo = 'PR-' . $prNo;
            $supplierId = $this->resolveSupplier($row['Supplier Name'] ?? null, $row['Code'] ?? null);
            $branchId = $this->resolveBranch($row['Branch Name'] ?? ($row['Branch ID'] ?? ''));

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
     * 4. STOCK TRANSFERS
     * ---------------------------------------------------------------------- */
    private function importOctoberTransfers(string $filePath): void
    {
        $this->info("\n--- Importing Stock Transfers ({$filePath}) ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $toDate = !empty($row['TO date']) ? date('Y-m-d', strtotime($row['TO date'])) : now()->format('Y-m-d');
                if ($toDate < $this->fromDate || $toDate > $this->toDate) continue;

                $fromBranchId = $this->resolveBranch($row['Branch Code'] ?? ($row['Branch from'] ?? ''));
                $toBranchId = $this->resolveBranch($row['To Branch code'] ?? ($row['TO branch name'] ?? ''));

                if ($fromBranchId === $toBranchId) {
                    $toBranchId = ($fromBranchId === $this->satelliteBranchId) ? $this->moteraBranchId : $this->satelliteBranchId;
                }

                $transferNo = 'ST-' . date('Ymd', strtotime($toDate)) . '-' . $fromBranchId . '-' . $toBranchId;
                $qty = (int) $this->cleanNumber($row['Qty'] ?? 1);
                $amt = $this->cleanNumber($row['Total amount'] ?? 0);

                $st = StockTransfer::firstOrCreate(
                    ['transfer_number' => $transferNo],
                    [
                        'transfer_date' => $toDate,
                        'from_branch_id' => $fromBranchId,
                        'to_branch_id' => $toBranchId,
                        'status' => 'Received',
                        'total_qty' => $qty,
                        'total_value' => $amt,
                        'dispatched_at' => $toDate . ' 10:00:00',
                        'received_at' => $toDate . ' 18:00:00',
                    ]
                );

                $itemCode = trim($row['Item code'] ?? '');
                $itemId = $this->itemsMap[$itemCode] ?? null;
                if ($itemId) {
                    $unitCost = $qty > 0 ? ($amt / $qty) : 0;
                    StockTransferItem::create([
                        'stock_transfer_id' => $st->id,
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'received_qty' => $qty,
                        'unit_cost' => $unitCost,
                        'batch_no' => 'TO-' . date('dmy', strtotime($toDate)),
                        'exp_date' => !empty($row['Expiry date']) ? date('Y-m-d', strtotime($row['Expiry date'])) : null,
                    ]);
                }

                $count++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error importing stock transfers: " . $e->getMessage());
        }

        $this->info("Imported / Synchronized {$count} Stock Transfer item lines.");
    }

    /* ----------------------------------------------------------------------
     * 5. SALES QUOTATIONS
     * ---------------------------------------------------------------------- */
    private function importSalesQuotations(string $filePath): void
    {
        $this->info("\n--- Importing Sales Quotations ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $qNo = $row['Quotation No'] ?? null;
            if (!$qNo) continue;

            $qDate = !empty($row['Quotation date']) ? date('Y-m-d', strtotime($row['Quotation date'])) : now()->format('Y-m-d');
            if ($qDate < $this->fromDate || $qDate > $this->toDate) continue;

            $branchId = $this->resolveBranch($row['Store'] ?? '');
            $total = $this->cleanNumber($row['Quotation amount'] ?? 0);
            $mobile = trim($row['Mobile'] ?? '');
            $custName = trim($row['Customer'] ?? '');
            $customerId = $this->customerMap[$mobile] ?? ($this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId);

            SalesQuotation::updateOrCreate(
                ['quotation_number' => $qNo],
                [
                    'quotation_date' => $qDate,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'total' => $total,
                    'status' => 'Confirmed',
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Sales Quotations.");
    }

    /* ----------------------------------------------------------------------
     * 6. SALES ORDERS
     * ---------------------------------------------------------------------- */
    private function importSalesOrders(string $filePath): void
    {
        $this->info("\n--- Importing Sales Orders ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $orderNo = $row['Order No'] ?? null;
            if (!$orderNo) continue;

            $orderDate = !empty($row['Order date']) ? date('Y-m-d', strtotime($row['Order date'])) : now()->format('Y-m-d');
            if ($orderDate < $this->fromDate || $orderDate > $this->toDate) continue;

            $branchId = $this->resolveBranch($row['Store'] ?? '');
            $mobile = trim($row['Mobile'] ?? '');
            $custName = trim($row['Customer'] ?? '');
            $customerId = $this->customerMap[$mobile] ?? ($this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId);
            $total = $this->cleanNumber($row['Order amount'] ?? 0);
            $adv = $this->cleanNumber($row['Advance amount'] ?? 0);

            SalesOrder::updateOrCreate(
                ['order_number' => $orderNo],
                [
                    'order_date' => $orderDate,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'advance_paid' => $adv,
                    'total' => $total,
                    'status' => 'Confirmed',
                ]
            );
            $count++;
        }

        $this->info("Imported / Synchronized {$count} Sales Orders.");
    }

    /* ----------------------------------------------------------------------
     * 7. SALES DELIVERY NOTES
     * ---------------------------------------------------------------------- */
    private function importSalesDeliveryNotes(string $filePath): void
    {
        $this->info("\n--- Importing Sales Delivery Notes ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        foreach ($this->parseCsv($filePath) as $row) {
            $dnNo = $row['DN No'] ?? null;
            if (!$dnNo) continue;

            $dnDate = !empty($row['DC Date']) ? date('Y-m-d', strtotime($row['DC Date'])) : now()->format('Y-m-d');
            if ($dnDate < $this->fromDate || $dnDate > $this->toDate) continue;

            $branchId = $this->resolveBranch($row['Store'] ?? '');
            $mobile = trim($row['Mobile'] ?? '');
            $custName = trim($row['Customer'] ?? '');
            $customerId = $this->customerMap[$mobile] ?? ($this->customerMap['name_' . strtolower($custName)] ?? $this->defaultCustomerId);
            $total = $this->cleanNumber($row['DC amount'] ?? 0);
            $totalQty = (int) $this->cleanNumber($row['Total Qty'] ?? 1);
            $status = ($row['Status'] ?? '') === 'Closed' ? 'Delivered' : 'Dispatched';

            SalesDeliveryNote::updateOrCreate(
                ['delivery_note_number' => $dnNo],
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
     * 8. SALES RETURNS
     * ---------------------------------------------------------------------- */
    private function importSalesReturns(string $filePath): void
    {
        $this->info("\n--- Importing Sales Returns ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $retNo = $row['Return No'] ?? null;
                if (!$retNo || !is_numeric($retNo)) continue;

                $returnDate = !empty($row['Return date']) ? date('Y-m-d', strtotime($row['Return date'])) : now()->format('Y-m-d');
                if ($returnDate < $this->fromDate || $returnDate > $this->toDate) continue;

                $prefix = $row['SR Prefix'] ?? 'SR';
                $returnNumber = $prefix . '-' . $retNo;

                $branchId = $this->resolveBranch($row['Branch Name'] ?? '');

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
     * 9. SALES BILLS
     * ---------------------------------------------------------------------- */
    private function importSalesBills(string $filePath): void
    {
        $this->info("\n--- Importing Sales Bills ({$this->fromDate} to {$this->toDate}) ---");
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($this->parseCsv($filePath) as $row) {
                $billNo = $row['Till sequence'] ?? null;
                if (!$billNo) continue;

                $billDate = !empty($row['Bill date']) ? date('Y-m-d', strtotime($row['Bill date'])) : now()->format('Y-m-d');
                if ($billDate < $this->fromDate || $billDate > $this->toDate) continue;

                $branchId = $this->resolveBranch($row['Store'] ?? '');

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

                $rawCard = $this->cleanNumber($row['Adv Card Amt'] ?? 0);
                $rawCoupon = $this->cleanNumber($row['Adv Coupon Amt'] ?? 0);
                $paymentType = 'Cash';
                if ($rawCard > 0) {
                    $paymentType = 'Card';
                } elseif ($rawCoupon > 0) {
                    $paymentType = 'Coupon';
                } elseif (trim($row['Bill type'] ?? '') === 'CR') {
                    $paymentType = 'Credit';
                }

                SalesBill::updateOrCreate(
                    ['bill_number' => $billNo],
                    [
                        'bill_date' => $billDate,
                        'customer_id' => $customerId,
                        'branch_id' => $branchId,
                        'invoice_type' => 'Retail Invoice',
                        'delivery_type' => 'Store Pickup',
                        'sales_type' => 'Local',
                        'payment_type' => $paymentType,
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
