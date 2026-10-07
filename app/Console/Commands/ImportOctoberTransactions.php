<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportOctoberTransactions extends Command
{
    protected $signature = 'import:october-transactions {--dry-run : Simulate without writing to database}';
    protected $description = 'Import October 6, 2026 missing purchases and inter-branch transfers from TruePOS exports';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("==================================================");
        $this->info($isDryRun ? ">>> RUNNING IN DRY-RUN MODE (SIMULATION) <<<" : ">>> RUNNING IN LIVE COMMIT MODE <<<");
        $this->info("==================================================");

        $purchaseFile = base_path('data_files/sync_october/purchase_detail_110120_oct2026.csv');
        $transferFile = base_path('data_files/sync_october/transfer_detail_110282_oct2026.csv');

        if (!file_exists($purchaseFile) || !file_exists($transferFile)) {
            $this->error("Required CSV files not found in data_files/sync_october!");
            return 1;
        }

        $this->importPurchases($purchaseFile, $isDryRun);
        $this->importTransfers($transferFile, $isDryRun);

        $this->info("\n=== OCTOBER TRANSACTION SYNC FINISHED ===");
        return 0;
    }

    private function importPurchases(string $filePath, bool $isDryRun): void
    {
        $this->info("\n--- 1. PROCESSING PURCHASE INVOICES (2026-10-06) ---");

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $headerIndex = -1;
        foreach ($lines as $idx => $line) {
            if (str_contains($line, 'GRN No') && str_contains($line, 'Supplier Name')) {
                $headerIndex = $idx;
                break;
            }
        }

        if ($headerIndex === -1) {
            $this->error("Header not found in purchase CSV!");
            return;
        }

        $headers = str_getcsv($lines[$headerIndex]);
        $rows = [];
        for ($i = $headerIndex + 1; $i < count($lines); $i++) {
            $data = str_getcsv($lines[$i]);
            if (count($data) === count($headers)) {
                $row = array_combine($headers, $data);
                // Filter only 2026-10-06
                if (($row['GRN date'] ?? '') === '2026-10-06') {
                    $rows[] = $row;
                }
            }
        }

        $this->info("Found " . count($rows) . " purchase item lines for 2026-10-06.");

        // Group by GRN No
        $grouped = [];
        foreach ($rows as $row) {
            $grn = trim($row['GRN No']);
            $grouped[$grn][] = $row;
        }

        $itemCodeMap = Item::pluck('id', 'item_code')->toArray();
        $supplierNameMap = Supplier::pluck('id', 'name')->toArray();
        $defaultSupplierId = Supplier::first()->id ?? 1;

        // Find last invoice number
        $lastInv = PurchaseInvoice::orderBy('id', 'desc')->first();
        $lastNum = 46;
        if ($lastInv && preg_match('/PINV(\d+)/', $lastInv->invoice_number, $m)) {
            $lastNum = (int) $m[1];
        }

        foreach ($grouped as $grnNo => $items) {
            $grnDate = $items[0]['GRN date'];
            $supplierName = trim($items[0]['Supplier Name']);
            
            // Map supplier
            $supplierId = $defaultSupplierId;
            foreach ($supplierNameMap as $name => $id) {
                if (stripos($name, $supplierName) !== false || stripos($supplierName, $name) !== false) {
                    $supplierId = $id;
                    break;
                }
            }

            // Check if already exists by GRN number
            $existing = PurchaseInvoice::where('grn_number', "GRN{$grnNo}")
                ->orWhere('grn_number', "GRN000{$grnNo}")
                ->orWhere('grn_number', 'LIKE', "%{$grnNo}%")
                ->first();

            if ($existing) {
                $this->warn("Purchase Invoice for GRN {$grnNo} already exists (ID: {$existing->id}, Invoice: {$existing->invoice_number}). Skipping.");
                continue;
            }

            $lastNum++;
            $invNumber = 'PINV' . str_pad($lastNum, 5, '0', STR_PAD_LEFT);
            $grnNumber = 'GRN' . str_pad($grnNo, 7, '0', STR_PAD_LEFT);

            $totQty = 0;
            $totAmount = 0;
            $totCgst = 0;
            $totSgst = 0;
            $totIgst = 0;
            $totGst = 0;
            $parsedItems = [];

            foreach ($items as $itemRow) {
                $qty = (float) str_replace(',', '', $itemRow['Received Qty'] ?? 0);
                $amt = (float) str_replace(',', '', $itemRow['Purchase amount'] ?? 0);
                $cgst = (float) str_replace(',', '', $itemRow['CGST TaxAmt'] ?? 0);
                $sgst = (float) str_replace(',', '', $itemRow['SGST TaxAmt'] ?? 0);
                $igst = (float) str_replace(',', '', $itemRow['IGST TaxAmt'] ?? 0);
                $gstTax = (float) str_replace(',', '', $itemRow['GST TaxAmt'] ?? 0);
                $mrp = (float) str_replace(',', '', $itemRow['MRP'] ?? 0);
                $rate = (float) str_replace(',', '', $itemRow['Purchase rate'] ?? 0);
                $sell = (float) str_replace(',', '', $itemRow['Selling'] ?? 0);
                $expDate = !empty($itemRow['Expiry date']) ? $itemRow['Expiry date'] : null;

                $itemCode = trim($itemRow['Item code']);
                $itemName = trim($itemRow['Item name'] ?? '');
                $itemId = $itemCodeMap[$itemCode] ?? null;

                if (!$itemId && $itemCode !== '') {
                    $existingItem = Item::where('item_code', $itemCode)->first();
                    if ($existingItem) {
                        $itemId = $existingItem->id;
                    } elseif (!$isDryRun) {
                        $newItem = Item::create([
                            'item_code' => $itemCode,
                            'name' => $itemName ?: "Item {$itemCode}",
                            'cost_price' => $rate,
                            'landing_cost' => $rate,
                            'sell_price' => $sell ?: ($mrp ?: $rate),
                            'mrp' => $mrp ?: ($sell ?: $rate),
                            'hsn_code' => trim($itemRow['HSN Code'] ?? '') ?: null,
                            'status' => true,
                            'product_type' => 'Standard',
                        ]);
                        $itemId = $newItem->id;
                        $this->info("  -> Auto-created missing SKU {$itemCode}: {$newItem->name} (ID: {$itemId})");
                    } else {
                        $itemId = 999999;
                    }
                    if ($itemId) {
                        $itemCodeMap[$itemCode] = $itemId;
                    }
                }

                $totQty += $qty;
                $totAmount += $amt;
                $totCgst += $cgst;
                $totSgst += $sgst;
                $totIgst += $igst;
                $totGst += $gstTax;

                $parsedItems[] = [
                    'item_id' => $itemId,
                    'item_code' => $itemCode,
                    'item_name' => $itemRow['Item name'],
                    'batch_no' => 'B-' . date('ymd', strtotime($grnDate)),
                    'exp_date' => $expDate,
                    'qty' => $qty,
                    'free_qty' => 0,
                    'cost_price' => $rate,
                    'effective_cost' => $rate,
                    'sell_price' => $sell,
                    'mrp' => $mrp,
                    'disc_percent' => (float) ($itemRow['Discount %'] ?? 0),
                    'disc_amount' => (float) str_replace(',', '', $itemRow['Disc. Amount'] ?? 0),
                    'gst_percent' => (float) ($itemRow['GST Perc'] ?? 0),
                    'gst_tax_amount' => $gstTax,
                    'cgst_amount' => $cgst,
                    'sgst_amount' => $sgst,
                    'igst_amount' => $igst,
                    'net_amount' => $amt,
                ];
            }

            $this->info("Creating {$invNumber} ({$grnNumber}) | Supplier: {$supplierName} (ID: {$supplierId}) | Items: " . count($parsedItems) . " | Qty: {$totQty} | Amt: ₹" . number_format($totAmount, 2));

            if (!$isDryRun) {
                DB::transaction(function () use ($invNumber, $grnNumber, $grnDate, $supplierId, $totQty, $totAmount, $totGst, $totCgst, $totSgst, $totIgst, $parsedItems) {
                    $supplier = Supplier::find($supplierId);
                    $pinv = PurchaseInvoice::create([
                        'invoice_number' => $invNumber,
                        'invoice_date' => $grnDate,
                        'supplier_id' => $supplierId,
                        'supplier_gstin' => $supplier->gstin ?? null,
                        'branch_id' => 3, // Motera / Distribution Center
                        'grn_number' => $grnNumber,
                        'grn_date' => $grnDate,
                        'supplier_inv_no' => 'INV-' . substr($grnNumber, -4),
                        'supplier_inv_date' => $grnDate,
                        'supplier_inv_amount' => $totAmount,
                        'purchase_type' => 'Local',
                        'c_form' => 'No Forms',
                        'item_disc_amount' => 0,
                        'disc_percent' => 0,
                        'disc_amount' => 0,
                        'freight' => 0,
                        'round_off' => 0,
                        'total_gst' => $totGst,
                        'total_cgst' => $totCgst,
                        'total_sgst' => $totSgst,
                        'total_igst' => $totIgst,
                        'total_qty' => $totQty,
                        'total_weight' => 0,
                        'total' => $totAmount,
                        'status' => 'Posted',
                        'posting_key' => (string) Str::uuid(),
                    ]);

                    foreach ($parsedItems as $pItem) {
                        $pItem['purchase_invoice_id'] = $pinv->id;
                        $itemCode = $pItem['item_code'];
                        unset($pItem['item_code'], $pItem['item_name']);
                        PurchaseInvoiceItem::create($pItem);
                    }
                });
                $this->info("  -> COMMITTED {$invNumber} successfully!");
            }
        }
    }

    private function importTransfers(string $filePath, bool $isDryRun): void
    {
        $this->info("\n--- 2. PROCESSING STOCK TRANSFERS (2026-10-06) ---");

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $headerIndex = -1;
        foreach ($lines as $idx => $line) {
            if (str_contains($line, 'TO date') && str_contains($line, 'Item name')) {
                $headerIndex = $idx;
                break;
            }
        }

        if ($headerIndex === -1) {
            $this->error("Header not found in transfer CSV!");
            return;
        }

        $headers = str_getcsv($lines[$headerIndex]);
        $rows = [];
        for ($i = $headerIndex + 1; $i < count($lines); $i++) {
            $data = str_getcsv($lines[$i]);
            if (count($data) === count($headers)) {
                $row = array_combine($headers, $data);
                if (($row['TO date'] ?? '') === '2026-10-06') {
                    $rows[] = $row;
                }
            }
        }

        $this->info("Found " . count($rows) . " transfer item lines for 2026-10-06.");

        // Group by Direction:
        // Motera (32772 -> Branch 3) to Satellite (225 -> Branch 2)
        // Satellite (225 -> Branch 2) to Motera (32772 -> Branch 3)
        $grouped = [];
        foreach ($rows as $row) {
            $fromCode = trim($row['Branch Code'] ?? '');
            $toCode = trim($row['To Branch code'] ?? '');
            
            // Branch mapping
            $fromId = ($fromCode === '32772' || str_contains($row['Branch from'], 'MOTERA')) ? 3 : 2;
            $toId = ($toCode === '32772' || str_contains($row['TO branch name'], 'MOTERA')) ? 3 : 2;

            $key = "{$fromId}_to_{$toId}";
            $grouped[$key]['from_id'] = $fromId;
            $grouped[$key]['to_id'] = $toId;
            $grouped[$key]['items'][] = $row;
        }

        $itemCodeMap = Item::pluck('id', 'item_code')->toArray();

        // Find last transfer number
        $lastStf = StockTransfer::orderBy('id', 'desc')->first();
        $lastNum = 26;
        if ($lastStf && preg_match('/STF(\d+)/', $lastStf->transfer_number, $m)) {
            $lastNum = (int) $m[1];
        }

        foreach ($grouped as $key => $batch) {
            $fromId = $batch['from_id'];
            $toId = $batch['to_id'];
            $transferDate = '2026-10-06';

            // Check if already exists for this date and direction
            $existing = StockTransfer::whereDate('transfer_date', $transferDate)
                ->where('from_branch_id', $fromId)
                ->where('to_branch_id', $toId)
                ->first();

            if ($existing) {
                $this->warn("Stock Transfer for {$transferDate} ({$fromId} -> {$toId}) already exists (ID: {$existing->id}, STF: {$existing->transfer_number}). Skipping.");
                continue;
            }

            $lastNum++;
            $stfNumber = 'STF' . str_pad($lastNum, 5, '0', STR_PAD_LEFT);

            $totQty = 0;
            $totValue = 0;
            $parsedItems = [];

            foreach ($batch['items'] as $itemRow) {
                $qty = (float) str_replace(',', '', $itemRow['Qty'] ?? 0);
                $amt = (float) str_replace(',', '', $itemRow['Total amount'] ?? 0);
                $unitCost = $qty > 0 ? ($amt / $qty) : 0;
                $expDate = !empty($itemRow['Expiry date']) ? $itemRow['Expiry date'] : null;

                $itemCode = trim($itemRow['Item code']);
                $itemName = trim($itemRow['Item name'] ?? '');
                $itemId = $itemCodeMap[$itemCode] ?? null;

                if (!$itemId && $itemCode !== '') {
                    $existingItem = Item::where('item_code', $itemCode)->first();
                    if ($existingItem) {
                        $itemId = $existingItem->id;
                    } elseif (!$isDryRun) {
                        $newItem = Item::create([
                            'item_code' => $itemCode,
                            'name' => $itemName ?: "Item {$itemCode}",
                            'cost_price' => $unitCost,
                            'landing_cost' => $unitCost,
                            'sell_price' => $unitCost,
                            'mrp' => $unitCost,
                            'status' => true,
                            'product_type' => 'Standard',
                        ]);
                        $itemId = $newItem->id;
                        $this->info("  -> Auto-created missing transfer SKU {$itemCode}: {$newItem->name} (ID: {$itemId})");
                    } else {
                        $itemId = 999999;
                    }
                    if ($itemId) {
                        $itemCodeMap[$itemCode] = $itemId;
                    }
                }

                $totQty += $qty;
                $totValue += $amt;

                $parsedItems[] = [
                    'item_id' => $itemId,
                    'batch_no' => 'TRF-261006',
                    'exp_date' => $expDate,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'received_qty' => $qty,
                    'gst_percent' => 0,
                    'taxable_value' => $amt,
                    'gst_tax_amount' => 0,
                    'cgst_amount' => 0,
                    'sgst_amount' => 0,
                    'igst_amount' => 0,
                ];
            }

            $fromName = $fromId === 3 ? "Motera (3)" : "Satellite (2)";
            $toName = $toId === 3 ? "Motera (3)" : "Satellite (2)";

            $this->info("Creating {$stfNumber} | Direction: {$fromName} -> {$toName} | Items: " . count($parsedItems) . " | Qty: {$totQty} | Value: ₹" . number_format($totValue, 2));

            if (!$isDryRun) {
                DB::transaction(function () use ($stfNumber, $transferDate, $fromId, $toId, $totQty, $totValue, $parsedItems) {
                    $stf = StockTransfer::create([
                        'transfer_number' => $stfNumber,
                        'transfer_date' => $transferDate,
                        'from_branch_id' => $fromId,
                        'to_branch_id' => $toId,
                        'status' => 'Received',
                        'posting_key' => (string) Str::uuid(),
                        'remarks' => "TruePOS sync 2026-10-06",
                        'total_qty' => $totQty,
                        'total_value' => $totValue,
                        'dispatched_at' => Carbon::parse("{$transferDate} 11:00:00"),
                        'received_at' => Carbon::parse("{$transferDate} 14:00:00"),
                    ]);

                    foreach ($parsedItems as $tItem) {
                        $tItem['stock_transfer_id'] = $stf->id;
                        StockTransferItem::create($tItem);
                    }
                });
                $this->info("  -> COMMITTED {$stfNumber} successfully!");
            }
        }
    }
}
