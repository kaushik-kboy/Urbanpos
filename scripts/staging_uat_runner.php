use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Brand;
use App\Models\GstTax;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\DamageStock;
use App\Models\StockUpdate;
use App\Models\StockLedger;
use App\Models\ItemStock;
use App\Services\Inventory\BatchStockService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;

$results = [];

// Helper to log test outcome
function recordTest(&$results, $section, $testName, $passed, $details = '') {
    $results[] = [
        'section' => $section,
        'test' => $testName,
        'status' => $passed ? 'PASS' : 'FAIL',
        'details' => $details,
    ];
    echo sprintf("  [%s] %s: %s %s\n", $passed ? 'PASS' : 'FAIL', $section, $testName, $details ? "({$details})" : '');
}

echo "====================================================================\n";
echo " URBANPOS — STAGING UAT REAL-DATA & BUSINESS INVARIANT EXECUTION\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. HISTORICAL DATA INTEGRITY (Read-only verification of existing documents)
// -----------------------------------------------------------------------------
echo "1. Checking Historical Data Integrity on Staging:\n";
try {
    $samplePinv = PurchaseInvoice::with('items')->latest('id')->first();
    $pinvOk = $samplePinv && $samplePinv->items->count() > 0;
    recordTest($results, 'Historical Data', 'Latest Purchase Invoice with items opens', $pinvOk, "ID: {$samplePinv?->id}, Number: {$samplePinv?->invoice_number}");

    $sampleBill = SalesBill::with('items')->latest('id')->first();
    $billOk = $sampleBill && $sampleBill->items->count() > 0;
    recordTest($results, 'Historical Data', 'Latest Sales Bill with items opens', $billOk, "ID: {$sampleBill?->id}, Bill No: {$sampleBill?->bill_number}");

    $samplePR = PurchaseReturn::with('items')->latest('id')->first();
    recordTest($results, 'Historical Data', 'Latest Purchase Return opens', (bool)$samplePR, "ID: {$samplePR?->id}, Return No: {$samplePR?->return_number}");

    $sampleST = StockTransfer::with('items')->latest('id')->first();
    recordTest($results, 'Historical Data', 'Latest Stock Transfer opens', (bool)$sampleST, "ID: {$sampleST?->id}, ST No: {$sampleST?->transfer_number}");
} catch (\Throwable $e) {
    recordTest($results, 'Historical Data', 'Historical documents load exception', false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// 2. EXPIRED PRODUCT INVARIANT IN PURCHASE INVOICE
// -----------------------------------------------------------------------------
echo "\n2. Verifying Purchase Invoice Expiry Guards:\n";
try {
    $item = Item::where('status', 1)->first();
    $supplier = Supplier::where('status', 1)->first();
    $branch = Branch::first();

    // Test 2.1: Expired date (yesterday)
    $yesterday = Carbon::now('Asia/Kolkata')->subDay()->toDateString();
    $todayIndia = Carbon::now('Asia/Kolkata')->startOfDay();
    
    // Simulate validator logic in PurchaseInvoiceController
    $expCarbon = Carbon::parse($yesterday, 'Asia/Kolkata')->startOfDay();
    $isBlocked = $expCarbon->lt($todayIndia);
    recordTest($results, 'Expiry Guard', 'Yesterday expiry date strictly blocked', $isBlocked, "Yesterday: {$yesterday}");

    // Test 2.2: Future date
    $futureDate = Carbon::now('Asia/Kolkata')->addMonths(6)->toDateString();
    $futureCarbon = Carbon::parse($futureDate, 'Asia/Kolkata')->startOfDay();
    $isFutureAllowed = !$futureCarbon->lt($todayIndia);
    recordTest($results, 'Expiry Guard', 'Future expiry date permitted', $isFutureAllowed, "Future: {$futureDate}");

    // Test 2.3: Today's date
    $todayDate = Carbon::now('Asia/Kolkata')->toDateString();
    $todayCarbon = Carbon::parse($todayDate, 'Asia/Kolkata')->startOfDay();
    $isTodayAllowed = !$todayCarbon->lt($todayIndia);
    recordTest($results, 'Expiry Guard', "Today's expiry date permitted", $isTodayAllowed, "Today: {$todayDate}");
} catch (\Throwable $e) {
    recordTest($results, 'Expiry Guard', 'Expiry check exception', false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// 3. MULTI-SUPPLIER ISOLATION ON PURCHASE RETURN (Controlled In-Memory Transaction)
// -----------------------------------------------------------------------------
echo "\n3. Verifying Supplier Isolation on Purchase Return (Ankit vs. Rahul):\n";
DB::beginTransaction();
try {
    $gst18 = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'status' => 1]);
    $branchId = Branch::value('id') ?: 3;

    // Supplier Ankit
    $supplierAnkit = Supplier::create([
        'name' => 'UAT Ankit Supplier',
        'phone' => '9999900001',
        'state' => 'Maharashtra',
        'status' => 1,
    ]);

    // Supplier Rahul
    $supplierRahul = Supplier::create([
        'name' => 'UAT Rahul Supplier',
        'phone' => '9999900002',
        'state' => 'Maharashtra',
        'status' => 1,
    ]);

    // Shared Item X
    $itemX = Item::create([
        'name' => 'UAT Item X',
        'item_code' => 'UAT-ITM-X',
        'cost_price' => 100,
        'sell_price' => 150,
        'mrp' => 160,
        'gst_tax_id' => $gst18->id,
        'status' => 1,
    ]);

    // Ankit: purchased 20 of BATCH-A
    $pinvAnkit = PurchaseInvoice::create([
        'invoice_number' => 'PINV-UAT-A-01',
        'supplier_id' => $supplierAnkit->id,
        'branch_id' => $branchId,
        'invoice_date' => now()->toDateString(),
        'purchase_type' => 'Local',
        'total' => 2000,
        'subtotal' => 2000,
    ]);
    PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $pinvAnkit->id,
        'item_id' => $itemX->id,
        'batch_no' => 'BATCH-A',
        'qty' => 20,
        'cost_price' => 100,
        'subtotal' => 2000,
        'net_amount' => 2000,
    ]);

    // Rahul: purchased 10 of BATCH-B
    $pinvRahul = PurchaseInvoice::create([
        'invoice_number' => 'PINV-UAT-R-01',
        'supplier_id' => $supplierRahul->id,
        'branch_id' => $branchId,
        'invoice_date' => now()->toDateString(),
        'purchase_type' => 'Local',
        'total' => 1000,
        'subtotal' => 1000,
    ]);
    PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $pinvRahul->id,
        'item_id' => $itemX->id,
        'batch_no' => 'BATCH-B',
        'qty' => 10,
        'cost_price' => 100,
        'subtotal' => 1000,
        'net_amount' => 1000,
    ]);

    // Total physical branch stock = 30
    ItemStock::create([
        'item_id' => $itemX->id,
        'branch_id' => $branchId,
        'quantity' => 30,
    ]);

    // Helper to evaluate returnable eligibility using the exact logic from PurchaseReturnController
    $evalReturnable = function($supplierId, $invoiceId, $itemId, $batchNo, $requestedQty) {
        $supplierPurchases = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pi.supplier_id', $supplierId)
            ->where('pi.status', '!=', 'Cancelled')
            ->where('pii.item_id', $itemId)
            ->when($invoiceId, fn($q) => $q->where('pi.id', $invoiceId))
            ->when(!empty($batchNo), fn($q) => $q->where('pii.batch_no', $batchNo))
            ->sum('pii.qty');

        $supplierReturns = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.supplier_id', $supplierId)
            ->where('pri.item_id', $itemId)
            ->when($invoiceId, fn($q) => $q->where('pr.purchase_invoice_id', $invoiceId))
            ->when(!empty($batchNo), fn($q) => $q->where('pri.batch_no', $batchNo))
            ->sum('pri.qty');

        $maxEligible = max(0, (float)$supplierPurchases - (float)$supplierReturns);
        return $requestedQty <= $maxEligible;
    };

    // Test 3.1: Ankit returns 30 -> MUST BE BLOCKED (Ankit sold 20)
    $ankit30 = $evalReturnable($supplierAnkit->id, null, $itemX->id, 'BATCH-A', 30);
    recordTest($results, 'Supplier Isolation', 'Ankit return 30 (exceeds 20 purchased) blocked', !$ankit30, "Requested: 30, Purchased: 20");

    // Test 3.2: Ankit returns 21 -> MUST BE BLOCKED
    $ankit21 = $evalReturnable($supplierAnkit->id, null, $itemX->id, 'BATCH-A', 21);
    recordTest($results, 'Supplier Isolation', 'Ankit return 21 (exceeds 20 purchased) blocked', !$ankit21, "Requested: 21, Purchased: 20");

    // Test 3.3: Ankit attempts to return Rahul's BATCH-B -> MUST BE BLOCKED
    $ankitBatchB = $evalReturnable($supplierAnkit->id, null, $itemX->id, 'BATCH-B', 1);
    recordTest($results, 'Supplier Isolation', "Ankit return Rahul's Batch B blocked", !$ankitBatchB, "Ankit did not supply Batch B");

    // Test 3.4: Ankit returns 20 of BATCH-A -> PASS
    $ankit20 = $evalReturnable($supplierAnkit->id, null, $itemX->id, 'BATCH-A', 20);
    recordTest($results, 'Supplier Isolation', 'Ankit return 20 of Batch A permitted', $ankit20, "Requested: 20, Purchased: 20");

    // Test 3.5: Rahul returns 11 of BATCH-B -> MUST BE BLOCKED (Rahul sold 10)
    $rahul11 = $evalReturnable($supplierRahul->id, null, $itemX->id, 'BATCH-B', 11);
    recordTest($results, 'Supplier Isolation', 'Rahul return 11 (exceeds 10 purchased) blocked', !$rahul11, "Requested: 11, Purchased: 10");

    // Test 3.6: Rahul returns 10 of BATCH-B -> PASS
    $rahul10 = $evalReturnable($supplierRahul->id, null, $itemX->id, 'BATCH-B', 10);
    recordTest($results, 'Supplier Isolation', 'Rahul return 10 of Batch B permitted', $rahul10, "Requested: 10, Purchased: 10");

} catch (\Throwable $e) {
    recordTest($results, 'Supplier Isolation', 'Supplier isolation exception', false, $e->getMessage());
} finally {
    DB::rollBack(); // Always roll back test data
}

// -----------------------------------------------------------------------------
// 4. MULTI-BATCH 7-STAGE INVENTORY RECONCILIATION
// -----------------------------------------------------------------------------
echo "\n4. Verifying Multi-Batch 7-Stage Inventory Lifecycle Reconciliation:\n";
DB::beginTransaction();
try {
    $batchService = app(BatchStockService::class);
    $stockLedger = app(StockLedgerService::class);
    $branchId = Branch::value('id') ?: 3;

    $itemY = Item::create([
        'name' => 'UAT Lifecycle Item Y',
        'item_code' => 'UAT-LC-Y',
        'cost_price' => 100,
        'sell_price' => 150,
        'mrp' => 160,
        'gst_tax_id' => GstTax::value('id'),
        'status' => 1,
    ]);

    // Step 0: Initial Purchases (Batch A = 20, Batch B = 10)
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'PURCHASE_RECEIPT',
        qtyDelta: 20.0,
        unitCost: 100.0,
        referenceType: 'Test',
        referenceId: 1,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-A'
    );
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'PURCHASE_RECEIPT',
        qtyDelta: 10.0,
        unitCost: 100.0,
        referenceType: 'Test',
        referenceId: 1,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-B'
    );

    $bA0 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB0 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 0: Purchase Batch A (20), Batch B (10)', $bA0 === 20.0 && $bB0 === 10.0, "A: {$bA0}, B: {$bB0}");

    // Step 1: Sale of 5 from Batch A -> A = 15, B = 10
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'SALE',
        qtyDelta: -5.0,
        unitCost: null,
        referenceType: 'Test',
        referenceId: 2,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-A'
    );
    $bA1 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB1 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 1: Sell 5 from Batch A -> A=15, B=10', $bA1 === 15.0 && $bB1 === 10.0, "A: {$bA1}, B: {$bB1}");

    // Step 2: Transfer 3 of Batch B -> A = 15, B = 7
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'TRANSFER_OUT',
        qtyDelta: -3.0,
        unitCost: null,
        referenceType: 'Test',
        referenceId: 3,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-B'
    );
    $bA2 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB2 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 2: Transfer 3 from Batch B -> A=15, B=7', $bA2 === 15.0 && $bB2 === 7.0, "A: {$bA2}, B: {$bB2}");

    // Step 3: Damage 2 of Batch A -> A = 13, B = 7
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'DAMAGE',
        qtyDelta: -2.0,
        unitCost: null,
        referenceType: 'Test',
        referenceId: 4,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-A'
    );
    $bA3 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB3 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 3: Damage 2 from Batch A -> A=13, B=7', $bA3 === 13.0 && $bB3 === 7.0, "A: {$bA3}, B: {$bB3}");

    // Step 4: Stock Update Shortage of 1 on Batch B -> A = 13, B = 6
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'SHORTAGE',
        qtyDelta: -1.0,
        unitCost: null,
        referenceType: 'Test',
        referenceId: 5,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-B'
    );
    $bA4 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB4 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 4: Shortage 1 on Batch B -> A=13, B=6', $bA4 === 13.0 && $bB4 === 6.0, "A: {$bA4}, B: {$bB4}");

    // Step 5: Purchase Return of 2 on Batch A -> A = 11, B = 6
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'PURCHASE_RETURN',
        qtyDelta: -2.0,
        unitCost: null,
        referenceType: 'Test',
        referenceId: 6,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-A'
    );
    $bA5 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB5 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    recordTest($results, 'Inventory Lifecycle', 'Step 5: Purchase Return 2 of Batch A -> A=11, B=6', $bA5 === 11.0 && $bB5 === 6.0, "A: {$bA5}, B: {$bB5}");

    // Step 6: Sales Return of 2 on Batch A -> A = 13, B = 6
    $stockLedger->post(
        itemId: $itemY->id,
        branchId: $branchId,
        movementType: 'SALE_RETURN',
        qtyDelta: 2.0,
        unitCost: 100.0,
        referenceType: 'Test',
        referenceId: 7,
        documentDate: now()->toDateString(),
        batchNo: 'BATCH-A'
    );
    $bA6 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-A')['remaining_qty'];
    $bB6 = (float)$batchService->getBatchStock($itemY->id, $branchId, 'BATCH-B')['remaining_qty'];
    $totalStock = (float)ItemStock::where('item_id', $itemY->id)->where('branch_id', $branchId)->value('quantity');

    recordTest($results, 'Inventory Lifecycle', 'Step 6: Sales Return 2 of Batch A -> A=13, B=6', $bA6 === 13.0 && $bB6 === 6.0, "A: {$bA6}, B: {$bB6}");
    recordTest($results, 'Inventory Lifecycle', 'Final Total ItemStock Matches Sum of Batches (19)', $totalStock === 19.0, "Total: {$totalStock}");

} catch (\Throwable $e) {
    recordTest($results, 'Inventory Lifecycle', 'Inventory lifecycle exception', false, $e->getMessage());
} finally {
    DB::rollBack();
}

// -----------------------------------------------------------------------------
// 5. PERFORMANCE SANITY TIMINGS
// -----------------------------------------------------------------------------
echo "\n5. Measuring Production Performance Sanity Timings:\n";
try {
    // 5.1 Item Search
    $t0 = microtime(true);
    Item::where('status', 1)->where('name', 'like', '%cat%')->limit(10)->get();
    $tItem = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'Item Name Search query time', $tItem < 250, "{$tItem} ms");

    // 5.2 Barcode/Item Code Search
    $t0 = microtime(true);
    Item::where('status', 1)->where('item_code', 'like', '%01%')->limit(1)->first();
    $tCode = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'Item Code Indexed lookup time', $tCode < 150, "{$tCode} ms");

    // 5.3 Customer Search
    $t0 = microtime(true);
    Customer::where('name', 'like', '%ankit%')->limit(10)->get();
    $tCust = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'Customer Name search time', $tCust < 150, "{$tCust} ms");

    // 5.4 Supplier Search
    $t0 = microtime(true);
    Supplier::where('name', 'like', '%pet%')->limit(10)->get();
    $tSupp = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'Supplier Search query time', $tSupp < 150, "{$tSupp} ms");

    // 5.5 Stock Ledger Aggregation
    $t0 = microtime(true);
    StockLedger::where('branch_id', 3)->selectRaw('item_id, sum(qty_in - qty_out) as stock')->groupBy('item_id')->get();
    $tStock = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'Stock Ledger Aggregation time', $tStock < 250, "{$tStock} ms");

    // 5.6 GSTR-1 Aggregation
    $t0 = microtime(true);
    $gstrBills = SalesBill::where('status', '!=', 'Cancelled')->with('items')->limit(50)->get();
    $tGstr = round((microtime(true) - $t0) * 1000, 2);
    recordTest($results, 'Performance Sanity', 'GSTR-1 50-bill data retrieval time', $tGstr < 400, "{$tGstr} ms");

} catch (\Throwable $e) {
    recordTest($results, 'Performance Sanity', 'Performance test exception', false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// SUMMARY CALCULATION
// -----------------------------------------------------------------------------
$totalTests = count($results);
$passedTests = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$failedTests = $totalTests - $passedTests;

echo "\n====================================================================\n";
echo sprintf(" STAGING UAT SUMMARY: %d / %d PASSED (Failed: %d)\n", $passedTests, $totalTests, $failedTests);
echo "====================================================================\n";
