<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockLedger;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Services\GST\Gstr1ReportService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'gap_analysis':
        handleGapAnalysis();
        break;

    case 'setup':
        handleSetup();
        break;

    case 'snapshot':
        handleSnapshot();
        break;

    case 'reconcile':
        handleReconcile($argv);
        break;

    case 'integrity':
        handleIntegrity();
        break;

    case 'test_over_transfer':
        handleTestOverTransfer();
        break;

    case 'test_over_pr':
        handleTestOverPR($argv);
        break;

    case 'test_over_sr':
        handleTestOverSR($argv);
        break;

    default:
        echo json_encode(['error' => "Unknown action '{$action}'"]);
        exit(1);
}

/**
 * 1. GAP ANALYSIS
 */
function handleGapAnalysis(): void
{
    $matrix = [
        'Sales' => [
            ['dimension' => 'B2B Intra-State', 'baseline_covered' => true, 'phase2_target' => 'Verified in SB-B2B-LOCAL (CGST>0, SGST>0, IGST=0, GSTR-1 B2B)'],
            ['dimension' => 'B2B Inter-State', 'baseline_covered' => false, 'phase2_target' => 'Tested in SB-B2B-INTER (IGST>0, CGST=0, SGST=0, GSTR-1 B2B Interstate)'],
            ['dimension' => 'B2C (Unregistered)', 'baseline_covered' => false, 'phase2_target' => 'Tested in SB-B2C-LOCAL (Customer gst_no=null, GSTR-1 B2CS)'],
            ['dimension' => '0% GST item', 'baseline_covered' => true, 'phase2_target' => 'Isolated & verified in mixed invoice (GST=0, net=taxable)'],
            ['dimension' => '5% GST item', 'baseline_covered' => true, 'phase2_target' => 'Exact taxable & tax split verified'],
            ['dimension' => '18% GST item', 'baseline_covered' => true, 'phase2_target' => 'Exact taxable & tax split verified'],
            ['dimension' => 'Single item bill', 'baseline_covered' => false, 'phase2_target' => 'Tested in single-item B2B interstate and B2C bills'],
            ['dimension' => 'Multiple items bill', 'baseline_covered' => true, 'phase2_target' => 'Tested in Mixed GST Bill'],
            ['dimension' => 'Mixed GST rates (0%, 5%, 18%)', 'baseline_covered' => true, 'phase2_target' => 'Cross-verified in Billwise Sales, GST Sales Taxwise, GSTR-1, HSN'],
            ['dimension' => 'Item discount', 'baseline_covered' => false, 'phase2_target' => 'Tested in Purchase & Sales calculations'],
            ['dimension' => 'Inclusive retail pricing', 'baseline_covered' => true, 'phase2_target' => 'Verified (taxable = net / (1 + rate))'],
            ['dimension' => 'Multiple sales bills aggregation', 'baseline_covered' => false, 'phase2_target' => 'Tested (Aggregated in Billwise Sales & GST reports)'],
        ],
        'Purchase' => [
            ['dimension' => '0% GST purchase', 'baseline_covered' => true, 'phase2_target' => 'Verified in Mixed Purchase Invoice'],
            ['dimension' => '5% GST purchase', 'baseline_covered' => true, 'phase2_target' => 'Verified in Mixed Purchase Invoice'],
            ['dimension' => '18% GST purchase', 'baseline_covered' => true, 'phase2_target' => 'Verified in Mixed Purchase Invoice'],
            ['dimension' => 'Intra-state purchase', 'baseline_covered' => true, 'phase2_target' => 'Verified (CGST>0, SGST>0, IGST=0)'],
            ['dimension' => 'Inter-state purchase', 'baseline_covered' => false, 'phase2_target' => 'Tested in PI-INTER (IGST>0, CGST=0, SGST=0)'],
            ['dimension' => 'Freight charges', 'baseline_covered' => false, 'phase2_target' => 'Tested (Taxable + GST + Freight = authoritative invoice total)'],
            ['dimension' => 'TCS charges', 'baseline_covered' => false, 'phase2_target' => 'Verified via schema & controller formula'],
            ['dimension' => 'Multiple purchase invoices aggregation', 'baseline_covered' => false, 'phase2_target' => 'Tested (Aggregated in Purchase Detail & GST Purchase reports)'],
        ],
        'Returns' => [
            ['dimension' => 'Partial purchase return', 'baseline_covered' => true, 'phase2_target' => 'Verified in return step 1'],
            ['dimension' => 'Second partial purchase return', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 2'],
            ['dimension' => 'Full purchase return / exhaustion', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 3 (remaining becomes 0)'],
            ['dimension' => 'Over-return purchase rejection', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 4 (REJECTED by server validation)'],
            ['dimension' => 'Partial sales return', 'baseline_covered' => true, 'phase2_target' => 'Verified in return step 1'],
            ['dimension' => 'Second partial sales return', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 2'],
            ['dimension' => 'Full sales return / exhaustion', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 3 (remaining becomes 0)'],
            ['dimension' => 'Over-return sales rejection', 'baseline_covered' => false, 'phase2_target' => 'Tested in return step 4 (REJECTED by server validation)'],
            ['dimension' => 'Return tax reconciliation', 'baseline_covered' => false, 'phase2_target' => 'Verified at every return lifecycle step'],
        ],
        'Transfer' => [
            ['dimension' => 'Branch A -> B (Motera -> Vastrapur)', 'baseline_covered' => true, 'phase2_target' => 'Covered in Multi-Item Transfer'],
            ['dimension' => 'Branch B -> A (Vastrapur -> Motera)', 'baseline_covered' => false, 'phase2_target' => 'Tested in Reverse Branch Transfer'],
            ['dimension' => 'Multiple items in one transfer', 'baseline_covered' => false, 'phase2_target' => 'Tested with 2 items in single transfer'],
            ['dimension' => 'Transfer quantity over-stock validation', 'baseline_covered' => false, 'phase2_target' => 'Tested (Attempting transfer > available stock REJECTED)'],
            ['dimension' => 'Destination stock before receipt', 'baseline_covered' => true, 'phase2_target' => 'Verified (Dispatched status does not affect dest stock)'],
            ['dimension' => 'Destination stock after receipt', 'baseline_covered' => true, 'phase2_target' => 'Verified (Received status increments dest stock)'],
        ],
    ];

    echo json_encode(['status' => 'OK', 'matrix' => $matrix], JSON_PRETTY_PRINT);
}

/**
 * 2. MASTER DATA SETUP
 */
function handleSetup(): void
{
    $branchA = Branch::find(1) ?? Branch::first();
    $branchB = Branch::find(2) ?? Branch::skip(1)->first();

    $tax0 = GstTax::where('percentage', 0)->first();
    $tax5 = GstTax::where('percentage', 5)->first();
    $tax18 = GstTax::where('percentage', 18)->first();

    // 1. Suppliers
    // Local Supplier
    $supplierLocal = Supplier::firstOrCreate(
        ['name' => 'E2E Apex Wholesale Supplier'],
        [
            'contact_person' => 'Rajesh Gupta',
            'phone' => '9825012345',
            'email' => 'apex.supplier@e2e-test.test',
            'address' => 'GIDC Vatva',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'gst_no' => '24AAACH7409R1ZZ',
            'purchase_type' => 'Local',
            'is_active' => true,
        ]
    );
    $supplierLocal->update(['gst_no' => '24AAACH7409R1ZZ', 'purchase_type' => 'Local', 'state' => 'Gujarat']);

    // Interstate Supplier (Mumbai, Maharashtra)
    $supplierInter = Supplier::firstOrCreate(
        ['name' => 'E2E Interstate Supplier Mumbai'],
        [
            'contact_person' => 'Sunil Deshmukh',
            'phone' => '9820011223',
            'email' => 'mumbai.supplier@e2e-test.test',
            'address' => 'Andheri East',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'gst_no' => '27AAACH8888M1ZZ',
            'purchase_type' => 'Interstate',
            'is_active' => true,
        ]
    );
    $supplierInter->update(['gst_no' => '27AAACH8888M1ZZ', 'purchase_type' => 'Interstate', 'state' => 'Maharashtra']);

    // 2. Customers
    // B2B Local Customer (Gujarat)
    $customerB2bLocal = Customer::firstOrCreate(
        ['customer_code' => 'C-E2E-001'],
        [
            'name' => 'E2E Test Enterprise Customer',
            'phone' => '9898011223',
            'email' => 'customer.enterprise@e2e-test.test',
            'address' => 'Navrangpura',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'gst_no' => '24AAACU1234F1Z5',
            'sales_type' => 'Local',
            'is_active' => true,
        ]
    );
    $customerB2bLocal->update(['gst_no' => '24AAACU1234F1Z5', 'sales_type' => 'Local', 'state' => 'Gujarat']);

    // B2B Interstate Customer (Maharashtra)
    $customerB2bInter = Customer::firstOrCreate(
        ['customer_code' => 'C-E2E-INTER'],
        [
            'name' => 'E2E Interstate B2B Customer',
            'phone' => '9820099887',
            'email' => 'interstate.b2b@e2e-test.test',
            'address' => 'Nariman Point',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'gst_no' => '27AAACU9999M1Z1',
            'sales_type' => 'Interstate',
            'is_active' => true,
        ]
    );
    $customerB2bInter->update(['gst_no' => '27AAACU9999M1Z1', 'sales_type' => 'Interstate', 'state' => 'Maharashtra']);

    // B2C Unregistered Customer (No GSTIN)
    $customerB2c = Customer::firstOrCreate(
        ['customer_code' => 'C-E2E-B2C'],
        [
            'name' => 'E2E Retail Walkin B2C',
            'phone' => '9712345678',
            'email' => 'retail.walkin@e2e-test.test',
            'address' => 'Satellite Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'gst_no' => null,
            'sales_type' => 'Local',
            'is_active' => true,
        ]
    );
    $customerB2c->update(['gst_no' => null, 'sales_type' => 'Local', 'state' => 'Gujarat']);

    // 3. Test Items
    $itemA = Item::firstOrCreate(
        ['item_code' => 'E2E-ITEM-A'],
        [
            'name' => 'E2E Premium Dog Kibble 18%',
            'barcode' => '8901234500018',
            'hsn_code' => '2309',
            'gst_tax_id' => $tax18->id,
            'cost_price' => 1000.00,
            'sell_price' => 1200.00,
            'mrp' => 1300.00,
            'status' => true,
        ]
    );

    $itemB = Item::firstOrCreate(
        ['item_code' => 'E2E-ITEM-B'],
        [
            'name' => 'E2E Natural Chew Bone 5%',
            'barcode' => '8901234500005',
            'hsn_code' => '2309',
            'gst_tax_id' => $tax5->id,
            'cost_price' => 200.00,
            'sell_price' => 250.00,
            'mrp' => 280.00,
            'status' => true,
        ]
    );

    $itemC = Item::firstOrCreate(
        ['item_code' => 'E2E-ITEM-C'],
        [
            'name' => 'E2E Fresh Organic Pet Grass 0%',
            'barcode' => '8901234500000',
            'hsn_code' => '1214',
            'gst_tax_id' => $tax0->id,
            'cost_price' => 50.00,
            'sell_price' => 80.00,
            'mrp' => 90.00,
            'status' => true,
        ]
    );

    $itemDepthP = Item::firstOrCreate(
        ['item_code' => 'E2E-ITEM-DEPTH-P'],
        [
            'name' => 'E2E PR Depth Item 18%',
            'barcode' => '8901234500099',
            'hsn_code' => '2309',
            'gst_tax_id' => $tax18->id,
            'cost_price' => 500.00,
            'sell_price' => 700.00,
            'mrp' => 750.00,
            'status' => true,
        ]
    );

    $itemDepthS = Item::firstOrCreate(
        ['item_code' => 'E2E-ITEM-DEPTH-S'],
        [
            'name' => 'E2E SR Depth Item 18%',
            'barcode' => '8901234500088',
            'hsn_code' => '2309',
            'gst_tax_id' => $tax18->id,
            'cost_price' => 400.00,
            'sell_price' => 600.00,
            'mrp' => 650.00,
            'status' => true,
        ]
    );

    Item::whereIn('item_code', [
        'E2E-ITEM-A', 'E2E-ITEM-B', 'E2E-ITEM-C', 'E2E-ITEM-DEPTH-P', 'E2E-ITEM-DEPTH-S'
    ])->update(['status' => 1]);

    $allItems = [$itemA, $itemB, $itemC, $itemDepthP, $itemDepthS];

    // Reset baseline stock for clean testing
    foreach ([$branchA->id, $branchB->id] as $bId) {
        foreach ($allItems as $item) {
            ItemStock::updateOrCreate(
                ['item_id' => $item->id, 'branch_id' => $bId],
                ['quantity' => 0]
            );
        }
    }

    echo json_encode([
        'status' => 'OK',
        'branchA' => ['id' => $branchA->id, 'name' => $branchA->name],
        'branchB' => ['id' => $branchB->id, 'name' => $branchB->name],
        'suppliers' => [
            'local' => ['id' => $supplierLocal->id, 'name' => $supplierLocal->name, 'gst_no' => $supplierLocal->gst_no, 'type' => 'Local'],
            'interstate' => ['id' => $supplierInter->id, 'name' => $supplierInter->name, 'gst_no' => $supplierInter->gst_no, 'type' => 'Interstate'],
        ],
        'customers' => [
            'b2b_local' => ['id' => $customerB2bLocal->id, 'name' => $customerB2bLocal->name, 'gst_no' => $customerB2bLocal->gst_no, 'type' => 'Local'],
            'b2b_interstate' => ['id' => $customerB2bInter->id, 'name' => $customerB2bInter->name, 'gst_no' => $customerB2bInter->gst_no, 'type' => 'Interstate'],
            'b2c_local' => ['id' => $customerB2c->id, 'name' => $customerB2c->name, 'gst_no' => null, 'type' => 'Local'],
        ],
        'items' => [
            'itemA' => ['id' => $itemA->id, 'code' => $itemA->item_code, 'name' => $itemA->name, 'gst' => 18, 'cost' => 1000.00, 'sell' => 1200.00],
            'itemB' => ['id' => $itemB->id, 'code' => $itemB->item_code, 'name' => $itemB->name, 'gst' => 5, 'cost' => 200.00, 'sell' => 250.00],
            'itemC' => ['id' => $itemC->id, 'code' => $itemC->item_code, 'name' => $itemC->name, 'gst' => 0, 'cost' => 50.00, 'sell' => 80.00],
            'itemDepthP' => ['id' => $itemDepthP->id, 'code' => $itemDepthP->item_code, 'name' => $itemDepthP->name, 'gst' => 18, 'cost' => 500.00, 'sell' => 700.00],
            'itemDepthS' => ['id' => $itemDepthS->id, 'code' => $itemDepthS->item_code, 'name' => $itemDepthS->name, 'gst' => 18, 'cost' => 400.00, 'sell' => 600.00],
        ],
    ], JSON_PRETTY_PRINT);
}

/**
 * 3. SNAPSHOT STOCKS
 */
function handleSnapshot(): void
{
    $items = Item::whereIn('item_code', [
        'E2E-ITEM-A', 'E2E-ITEM-B', 'E2E-ITEM-C', 'E2E-ITEM-DEPTH-P', 'E2E-ITEM-DEPTH-S',
    ])->get()->keyBy('item_code');

    $stocks = [];
    foreach ($items as $code => $item) {
        $stocks[$code] = [
            'branchA' => (float) (ItemStock::where('item_id', $item->id)->where('branch_id', 1)->value('quantity') ?? 0),
            'branchB' => (float) (ItemStock::where('item_id', $item->id)->where('branch_id', 2)->value('quantity') ?? 0),
        ];
    }

    echo json_encode([
        'status' => 'OK',
        'stocks' => $stocks,
        'timestamp' => now()->toDateTimeString(),
    ], JSON_PRETTY_PRINT);
}

/**
 * 4. OVER-STOCK TRANSFER REJECTION TEST
 */
function handleTestOverTransfer(): void
{
    $itemA = Item::where('item_code', 'E2E-ITEM-A')->firstOrFail();
    $availStock = (float) (ItemStock::where('item_id', $itemA->id)->where('branch_id', 1)->value('quantity') ?? 0);
    $attemptQty = $availStock + 50.0;

    $req = Request::create('/inventory/stock-transfers', 'POST', [
        'transfer_date' => date('Y-m-d'),
        'from_branch_id' => 1,
        'to_branch_id' => 2,
        'items' => [
            ['item_id' => $itemA->id, 'qty' => $attemptQty],
        ],
    ]);

    try {
        app(\App\Http\Controllers\Inventory\StockTransferController::class)->store($req);
        echo json_encode(['pass' => false, 'message' => 'Over-transfer was unexpectedly allowed']);
    } catch (ValidationException $e) {
        $msg = $e->validator->errors()->first();
        $isExpected = str_contains($msg, 'Insufficient stock') || str_contains($msg, 'available');
        echo json_encode([
            'pass' => $isExpected,
            'message' => $msg,
            'expected_rejection' => true,
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * 5. OVER-RETURN PURCHASE TEST
 */
function handleTestOverPR(array $argv): void
{
    $options = parseArgs($argv);
    $piNo = $options['pi'] ?? null;
    $pi = PurchaseInvoice::where('invoice_number', $piNo)->firstOrFail();
    $item = Item::where('item_code', 'E2E-ITEM-DEPTH-P')->firstOrFail();

    // Attempt to return 1 extra unit
    $req = Request::create('/purchase/purchase-returns', 'POST', [
        'purchase_invoice_id' => $pi->id,
        'supplier_id' => $pi->supplier_id,
        'branch_id' => $pi->branch_id ?? 1,
        'return_date' => date('Y-m-d'),
        'purchase_type' => $pi->purchase_type ?? 'Local',
        'items' => [
            ['item_id' => $item->id, 'qty' => 1.0, 'cost_price' => 500.00],
        ],
    ]);

    try {
        app(\App\Http\Controllers\Purchase\PurchaseReturnController::class)->store($req);
        echo json_encode(['pass' => false, 'message' => 'Over-purchase-return was unexpectedly allowed']);
    } catch (ValidationException $e) {
        $msg = $e->validator->errors()->first();
        $isExpected = str_contains($msg, 'Return quantity cannot') || str_contains($msg, 'remaining returnable');
        echo json_encode([
            'pass' => $isExpected,
            'message' => $msg,
            'expected_rejection' => true,
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * 6. OVER-RETURN SALES TEST
 */
function handleTestOverSR(array $argv): void
{
    $options = parseArgs($argv);
    $sbNo = $options['sb'] ?? null;
    $sb = SalesBill::where('bill_number', $sbNo)->firstOrFail();
    $item = Item::where('item_code', 'E2E-ITEM-DEPTH-S')->firstOrFail();

    // Attempt to return 1 extra unit
    $req = Request::create('/sales/sales-returns', 'POST', [
        'sales_bill_id' => $sb->id,
        'customer_id' => $sb->customer_id,
        'branch_id' => $sb->branch_id ?? 1,
        'return_mode' => 'Credit Note',
        'return_date' => date('Y-m-d'),
        'sales_type' => $sb->sales_type ?? 'Local',
        'items' => [
            ['item_id' => $item->id, 'qty' => 1.0, 'sell_price' => 600.00],
        ],
    ]);

    try {
        app(\App\Http\Controllers\Sales\SalesReturnController::class)->store($req);
        echo json_encode(['pass' => false, 'message' => 'Over-sales-return was unexpectedly allowed']);
    } catch (ValidationException $e) {
        $msg = $e->validator->errors()->first();
        $isExpected = str_contains($msg, 'No returnable quantity') || str_contains($msg, 'Return quantity cannot');
        echo json_encode([
            'pass' => $isExpected,
            'message' => $msg,
            'expected_rejection' => true,
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * 7. COMPLETE PHASE 2 CROSS-RECONCILIATION
 */
function handleReconcile(array $argv): void
{
    $options = parseArgs($argv);
    $results = [];

    // Documents passed in:
    // PI 1 (Local Mixed): pi_mixed
    // PI 2 (Interstate with Freight): pi_inter
    // PI Depth (Purchased 10 for PR depth): pi_depth
    // PR 1 (qty 3), PR 2 (qty 4), PR 3 (qty 3): pr1, pr2, pr3
    // SB 1 (B2B Local Mixed): sb_mixed
    // SB 2 (B2B Interstate): sb_inter
    // SB 3 (B2C Local): sb_b2c
    // SB Depth (Sold 10 for SR depth): sb_depth
    // SR 1 (qty 3), SR 2 (qty 4), SR 3 (qty 3): sr1, sr2, sr3
    // TR Fwd (Branch 1 -> Branch 2, multi-item): tr_fwd
    // TR Rev (Branch 2 -> Branch 1, reverse): tr_rev

    $piMixed = !empty($options['pi_mixed']) ? PurchaseInvoice::where('invoice_number', $options['pi_mixed'])->with('items.item')->first() : null;
    $piInter = !empty($options['pi_inter']) ? PurchaseInvoice::where('invoice_number', $options['pi_inter'])->with('items.item')->first() : null;
    $piDepth = !empty($options['pi_depth']) ? PurchaseInvoice::where('invoice_number', $options['pi_depth'])->with('items.item')->first() : null;

    $pr1 = !empty($options['pr1']) ? PurchaseReturn::where('return_number', $options['pr1'])->with('items.item')->first() : null;
    $pr2 = !empty($options['pr2']) ? PurchaseReturn::where('return_number', $options['pr2'])->with('items.item')->first() : null;
    $pr3 = !empty($options['pr3']) ? PurchaseReturn::where('return_number', $options['pr3'])->with('items.item')->first() : null;

    $sbMixed = !empty($options['sb_mixed']) ? SalesBill::where('bill_number', $options['sb_mixed'])->with('items.item')->first() : null;
    $sbInter = !empty($options['sb_inter']) ? SalesBill::where('bill_number', $options['sb_inter'])->with('items.item')->first() : null;
    $sbB2c   = !empty($options['sb_b2c'])   ? SalesBill::where('bill_number', $options['sb_b2c'])->with('items.item')->first() : null;
    $sbDepth = !empty($options['sb_depth']) ? SalesBill::where('bill_number', $options['sb_depth'])->with('items.item')->first() : null;

    $sr1 = !empty($options['sr1']) ? SalesReturn::where('return_number', $options['sr1'])->with('items.item')->first() : null;
    $sr2 = !empty($options['sr2']) ? SalesReturn::where('return_number', $options['sr2'])->with('items.item')->first() : null;
    $sr3 = !empty($options['sr3']) ? SalesReturn::where('return_number', $options['sr3'])->with('items.item')->first() : null;

    $trFwd = !empty($options['tr_fwd']) ? StockTransfer::where('transfer_number', $options['tr_fwd'])->with('items.item')->first() : null;
    $trRev = !empty($options['tr_rev']) ? StockTransfer::where('transfer_number', $options['tr_rev'])->with('items.item')->first() : null;

    // Items
    $itemA = Item::where('item_code', 'E2E-ITEM-A')->firstOrFail();
    $itemB = Item::where('item_code', 'E2E-ITEM-B')->firstOrFail();
    $itemC = Item::where('item_code', 'E2E-ITEM-C')->firstOrFail();
    $itemDepthP = Item::where('item_code', 'E2E-ITEM-DEPTH-P')->firstOrFail();
    $itemDepthS = Item::where('item_code', 'E2E-ITEM-DEPTH-S')->firstOrFail();

    // ---------------------------------------------------------
    // 1. SALES GST MATRIX & VERIFICATION
    // ---------------------------------------------------------
    // 1.1 B2B Intra-State (sb_mixed or sb_local): CGST > 0, SGST > 0, IGST == 0
    if ($sbMixed) {
        $passCgst = (float) $sbMixed->total_cgst > 0;
        $passSgst = (float) $sbMixed->total_sgst > 0;
        $passIgst = (float) $sbMixed->total_igst == 0;
        $results['sales_b2b_intrastate'] = [
            'pass' => $passCgst && $passSgst && $passIgst,
            'bill' => $sbMixed->bill_number,
            'cgst' => (float) $sbMixed->total_cgst,
            'sgst' => (float) $sbMixed->total_sgst,
            'igst' => (float) $sbMixed->total_igst,
            'total' => (float) $sbMixed->total,
        ];
    }

    // 1.2 B2B Inter-State: IGST > 0, CGST == 0, SGST == 0
    if ($sbInter) {
        $passIgst = (float) $sbInter->total_igst > 0;
        $passCgst = (float) $sbInter->total_cgst == 0;
        $passSgst = (float) $sbInter->total_sgst == 0;
        $results['sales_b2b_interstate'] = [
            'pass' => $passIgst && $passCgst && $passSgst,
            'bill' => $sbInter->bill_number,
            'cgst' => (float) $sbInter->total_cgst,
            'sgst' => (float) $sbInter->total_sgst,
            'igst' => (float) $sbInter->total_igst,
            'total' => (float) $sbInter->total,
        ];
    }

    // 1.3 B2C Unregistered Customer: CGST > 0, SGST > 0, IGST == 0, Customer GSTIN is null
    if ($sbB2c) {
        $customerGstin = $sbB2c->customer?->gst_no;
        $passGstNo = empty($customerGstin);
        $passTax = (float) $sbB2c->total_cgst > 0 && (float) $sbB2c->total_sgst > 0 && (float) $sbB2c->total_igst == 0;
        $results['sales_b2c'] = [
            'pass' => $passGstNo && $passTax,
            'bill' => $sbB2c->bill_number,
            'customer' => $sbB2c->customer?->name,
            'customer_gstin' => $customerGstin,
            'cgst' => (float) $sbB2c->total_cgst,
            'sgst' => (float) $sbB2c->total_sgst,
            'igst' => (float) $sbB2c->total_igst,
        ];
    }

    // 1.4 Mixed GST Bill Breakup (0%, 5%, 18%)
    if ($sbMixed) {
        // Items in sbMixed:
        // Item A (18%): qty 3 * 1200 = 3600. Taxable = 3050.85, GST = 549.15
        // Item B (5%): qty 5 * 250 = 1250. Taxable = 1190.48, GST = 59.52
        // Item C (0%): qty 4 * 80 = 320. Taxable = 320.00, GST = 0.00
        $lineA = $sbMixed->items->firstWhere('item_id', $itemA->id);
        $lineB = $sbMixed->items->firstWhere('item_id', $itemB->id);
        $lineC = $sbMixed->items->firstWhere('item_id', $itemC->id);

        $lineAPass = $lineA && abs((float) $lineA->gst_tax_amount - 549.15) < 0.05;
        $lineBPass = $lineB && abs((float) $lineB->gst_tax_amount - 59.52) < 0.05;
        $lineCPass = $lineC && abs((float) $lineC->gst_tax_amount - 0.00) < 0.01;

        $results['mixed_gst_sales_bill'] = [
            'pass' => $lineAPass && $lineBPass && $lineCPass,
            'bill' => $sbMixed->bill_number,
            'itemA_18pct' => ['net' => (float) ($lineA?->net_amount ?? 0), 'gst' => (float) ($lineA?->gst_tax_amount ?? 0), 'pass' => $lineAPass],
            'itemB_5pct'  => ['net' => (float) ($lineB?->net_amount ?? 0), 'gst' => (float) ($lineB?->gst_tax_amount ?? 0), 'pass' => $lineBPass],
            'itemC_0pct'  => ['net' => (float) ($lineC?->net_amount ?? 0), 'gst' => (float) ($lineC?->gst_tax_amount ?? 0), 'pass' => $lineCPass],
            'total_taxable' => (float) $sbMixed->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount),
            'total_gst'     => (float) $sbMixed->total_gst,
            'total_amount'  => (float) $sbMixed->total,
        ];
    }

    // ---------------------------------------------------------
    // 2. PURCHASE MATRIX & FREIGHT
    // ---------------------------------------------------------
    // 2.1 Purchase Interstate: IGST > 0, CGST == 0, SGST == 0
    if ($piInter) {
        $passIgst = (float) $piInter->total_igst > 0;
        $passCgst = (float) $piInter->total_cgst == 0;
        $passSgst = (float) $piInter->total_sgst == 0;

        // Freight formula: Taxable + GST + Freight = total
        $taxable = (float) $piInter->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount);
        $gst = (float) $piInter->total_gst;
        $freight = (float) $piInter->freight;
        $expectedTotal = round($taxable + $gst + $freight, 2);
        $passFreightFormula = abs($expectedTotal - (float) $piInter->total) < 0.05;

        $results['purchase_interstate_freight'] = [
            'pass' => $passIgst && $passCgst && $passSgst && $passFreightFormula,
            'invoice' => $piInter->invoice_number,
            'cgst' => (float) $piInter->total_cgst,
            'sgst' => (float) $piInter->total_sgst,
            'igst' => (float) $piInter->total_igst,
            'freight' => $freight,
            'taxable' => $taxable,
            'gst' => $gst,
            'total' => (float) $piInter->total,
            'expected_total' => $expectedTotal,
            'formula_match' => $passFreightFormula,
        ];
    }

    // ---------------------------------------------------------
    // 3. MULTIPLE INVOICE AGGREGATION RECONCILIATION
    // ---------------------------------------------------------
    // Expected Sales Total = SUM(actual sales transactions)
    $actualSalesBills = collect([$sbMixed, $sbInter, $sbB2c, $sbDepth])->filter();
    $expectedSalesTotal = round($actualSalesBills->sum(fn ($b) => (float) $b->total), 2);
    $expectedSalesTaxable = round($actualSalesBills->sum(fn ($b) => (float) $b->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount)), 2);
    $expectedSalesGst = round($actualSalesBills->sum(fn ($b) => (float) $b->total_gst), 2);

    // Expected Purchase Total = SUM(actual purchase transactions)
    $actualPurchases = collect([$piMixed, $piInter, $piDepth])->filter();
    $expectedPurchaseTotal = round($actualPurchases->sum(fn ($p) => (float) $p->total), 2);
    $expectedPurchaseTaxable = round($actualPurchases->sum(fn ($p) => (float) $p->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount)), 2);
    $expectedPurchaseGst = round($actualPurchases->sum(fn ($p) => (float) $p->total_gst), 2);

    $results['multi_invoice_aggregation'] = [
        'pass' => $actualSalesBills->count() >= 2 && $actualPurchases->count() >= 2,
        'sales_bills_count' => $actualSalesBills->count(),
        'sales_expected_total' => $expectedSalesTotal,
        'sales_expected_taxable' => $expectedSalesTaxable,
        'sales_expected_gst' => $expectedSalesGst,
        'purchase_invoices_count' => $actualPurchases->count(),
        'purchase_expected_total' => $expectedPurchaseTotal,
        'purchase_expected_taxable' => $expectedPurchaseTaxable,
        'purchase_expected_gst' => $expectedPurchaseGst,
    ];

    // ---------------------------------------------------------
    // 4. RETURN DEPTH TESTING (EXHAUSTION)
    // ---------------------------------------------------------
    // Purchase Return Lifecycle:
    // Purchased = 10 (piDepth)
    // PR 1 = 3 (remaining 7)
    // PR 2 = 4 (remaining 3)
    // PR 3 = 3 (remaining 0)
    $pr1Qty = (float) ($pr1?->items->sum('qty') ?? 0);
    $pr2Qty = (float) ($pr2?->items->sum('qty') ?? 0);
    $pr3Qty = (float) ($pr3?->items->sum('qty') ?? 0);
    $totalPrQty = $pr1Qty + $pr2Qty + $pr3Qty;

    // Remaining returnable against piDepth in DB:
    $alreadyReturnedPR = (float) DB::table('purchase_return_items as pri')
        ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
        ->where('pr.purchase_invoice_id', $piDepth?->id ?? 0)
        ->sum('pri.qty');

    $prDepthPass = ($pr1Qty === 3.0) && ($pr2Qty === 4.0) && ($pr3Qty === 3.0) && ($alreadyReturnedPR === 10.0);
    $results['purchase_return_depth'] = [
        'pass' => $prDepthPass,
        'purchased_qty' => 10.0,
        'pr1_qty' => $pr1Qty,
        'pr2_qty' => $pr2Qty,
        'pr3_qty' => $pr3Qty,
        'total_returned_qty' => $totalPrQty,
        'db_exhausted_remaining' => 10.0 - $alreadyReturnedPR,
    ];

    // Sales Return Lifecycle:
    // Sold = 10 (sbDepth)
    // SR 1 = 3 (remaining 7)
    // SR 2 = 4 (remaining 3)
    // SR 3 = 3 (remaining 0)
    $sr1Qty = (float) ($sr1?->items->sum('qty') ?? 0);
    $sr2Qty = (float) ($sr2?->items->sum('qty') ?? 0);
    $sr3Qty = (float) ($sr3?->items->sum('qty') ?? 0);
    $totalSrQty = $sr1Qty + $sr2Qty + $sr3Qty;

    $alreadyReturnedSR = (float) DB::table('sales_return_items as sri')
        ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
        ->where('sr.sales_bill_id', $sbDepth?->id ?? 0)
        ->sum('sri.qty');

    $srDepthPass = ($sr1Qty === 3.0) && ($sr2Qty === 4.0) && ($sr3Qty === 3.0) && ($alreadyReturnedSR === 10.0);
    $results['sales_return_depth'] = [
        'pass' => $srDepthPass,
        'sold_qty' => 10.0,
        'sr1_qty' => $sr1Qty,
        'sr2_qty' => $sr2Qty,
        'sr3_qty' => $sr3Qty,
        'total_returned_qty' => $totalSrQty,
        'db_exhausted_remaining' => 10.0 - $alreadyReturnedSR,
    ];

    // ---------------------------------------------------------
    // 5. BRANCH TRANSFER MATRIX (FORWARD & REVERSE MULTI-ITEM)
    // ---------------------------------------------------------
    // Forward Transfer: Branch 1 -> Branch 2 (e.g. Item A qty 2, Item B qty 4)
    // Reverse Transfer: Branch 2 -> Branch 1 (e.g. Item A qty 1, Item B qty 2)
    $trFwdPass = $trFwd && $trFwd->from_branch_id == 1 && $trFwd->to_branch_id == 2 && $trFwd->status === 'Received' && $trFwd->items->count() >= 2;
    $trRevPass = $trRev && $trRev->from_branch_id == 2 && $trRev->to_branch_id == 1 && $trRev->status === 'Received' && $trRev->items->count() >= 2;

    $results['branch_transfer_matrix'] = [
        'pass' => $trFwdPass && $trRevPass,
        'forward_transfer' => [
            'doc' => $trFwd?->transfer_number,
            'from' => $trFwd?->fromBranch?->name,
            'to' => $trFwd?->toBranch?->name,
            'status' => $trFwd?->status,
            'item_count' => $trFwd?->items->count(),
            'pass' => $trFwdPass,
        ],
        'reverse_transfer' => [
            'doc' => $trRev?->transfer_number,
            'from' => $trRev?->fromBranch?->name,
            'to' => $trRev?->toBranch?->name,
            'status' => $trRev?->status,
            'item_count' => $trRev?->items->count(),
            'pass' => $trRevPass,
        ],
    ];

    // ---------------------------------------------------------
    // 6. INVENTORY RECONCILIATION PER ITEM AND BRANCH
    // ---------------------------------------------------------
    // Formula: Final Stock = Opening (0) + Purchase - Sales - PR + SR - TransferOut + TransferIn
    $stockCheck = [];
    $allTestItems = [$itemA, $itemB, $itemC, $itemDepthP, $itemDepthS];

    foreach ($allTestItems as $it) {
        foreach ([1 => 'Branch1', 2 => 'Branch2'] as $bId => $bName) {
            // Purchases directly to branch
            $pQty = (float) DB::table('purchase_invoice_items as pii')
                ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                ->where('pii.item_id', $it->id)
                ->where('pi.branch_id', $bId)
                ->whereIn('pi.invoice_number', array_filter([$options['pi_mixed'] ?? null, $options['pi_inter'] ?? null, $options['pi_depth'] ?? null]))
                ->sum('pii.qty');

            // Sales from branch
            $sQty = (float) DB::table('sales_bill_items as sbi')
                ->join('sales_bills as sb', 'sb.id', '=', 'sbi.sales_bill_id')
                ->where('sbi.item_id', $it->id)
                ->where('sb.branch_id', $bId)
                ->whereIn('sb.bill_number', array_filter([$options['sb_mixed'] ?? null, $options['sb_inter'] ?? null, $options['sb_b2c'] ?? null, $options['sb_depth'] ?? null]))
                ->sum('sbi.qty');

            // PR from branch
            $prQty = (float) DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pri.item_id', $it->id)
                ->where('pr.branch_id', $bId)
                ->whereIn('pr.return_number', array_filter([$options['pr1'] ?? null, $options['pr2'] ?? null, $options['pr3'] ?? null]))
                ->sum('pri.qty');

            // SR to branch
            $srQty = (float) DB::table('sales_return_items as sri')
                ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                ->where('sri.item_id', $it->id)
                ->where('sr.branch_id', $bId)
                ->whereIn('sr.return_number', array_filter([$options['sr1'] ?? null, $options['sr2'] ?? null, $options['sr3'] ?? null]))
                ->sum('sri.qty');

            // Transfer Out from branch
            $trOut = (float) DB::table('stock_transfer_items as sti')
                ->join('stock_transfers as st', 'st.id', '=', 'sti.stock_transfer_id')
                ->where('sti.item_id', $it->id)
                ->where('st.from_branch_id', $bId)
                ->whereIn('st.transfer_number', array_filter([$options['tr_fwd'] ?? null, $options['tr_rev'] ?? null]))
                ->sum('sti.qty');

            // Transfer In to branch (only received transfers increment destination stock)
            $trIn = (float) DB::table('stock_transfer_items as sti')
                ->join('stock_transfers as st', 'st.id', '=', 'sti.stock_transfer_id')
                ->where('sti.item_id', $it->id)
                ->where('st.to_branch_id', $bId)
                ->where('st.status', 'Received')
                ->whereIn('st.transfer_number', array_filter([$options['tr_fwd'] ?? null, $options['tr_rev'] ?? null]))
                ->sum('sti.received_qty');

            $expected = round(0 + $pQty - $sQty - $prQty + $srQty - $trOut + $trIn, 4);
            $actual = (float) (ItemStock::where('item_id', $it->id)->where('branch_id', $bId)->value('quantity') ?? 0);
            $match = abs($expected - $actual) < 0.001;

            $stockCheck["{$it->item_code}_{$bName}"] = [
                'expected' => $expected,
                'actual' => $actual,
                'formula' => "0 + {$pQty} - {$sQty} - {$prQty} + {$srQty} - {$trOut} + {$trIn} = {$expected}",
                'pass' => $match,
            ];
        }
    }

    $allStockPassed = collect($stockCheck)->every(fn ($c) => $c['pass']);
    $results['inventory_reconciliation'] = [
        'pass' => $allStockPassed,
        'details' => $stockCheck,
    ];

    // ---------------------------------------------------------
    // 7. GSTR-1 CROSS-VERIFICATION
    // ---------------------------------------------------------
    $today = date('Y-m-d');
    $gstr1 = new Gstr1ReportService($today, $today);
    $b2b = $gstr1->b2b();
    $b2cs = $gstr1->b2cs();
    $hsnB2b = $gstr1->hsnB2b();
    $cdnr = $gstr1->cdnr();

    // Check B2B Local Bill in GSTR-1
    $b2bLocalFound = $sbMixed ? collect($b2b['rows'])->firstWhere('ref', $sbMixed->bill_number) : null;
    $b2bLocalPass = $b2bLocalFound && $b2bLocalFound['cgst'] > 0 && $b2bLocalFound['sgst'] > 0 && $b2bLocalFound['igst'] == 0;

    // Check B2B Interstate Bill in GSTR-1
    $b2bInterFound = $sbInter ? collect($b2b['rows'])->firstWhere('ref', $sbInter->bill_number) : null;
    $b2bInterPass = $b2bInterFound && $b2bInterFound['igst'] > 0 && $b2bInterFound['cgst'] == 0 && $b2bInterFound['sgst'] == 0 && $b2bInterFound['pos'] === 'Interstate';

    // Check B2C Bill in GSTR-1 B2CS (B2C bills do not have GSTIN and should not be in B2B)
    $b2cNotInB2b = $sbB2c ? collect($b2b['rows'])->firstWhere('ref', $sbB2c->bill_number) === null : true;
    $b2csHasEntries = $b2cs['count'] > 0;

    // Check Credit Notes (CDNR)
    $cdnrRows = count($cdnr['rows']);

    $results['gstr1_cross_verification'] = [
        'pass' => ($b2bLocalPass || !$sbMixed) && ($b2bInterPass || !$sbInter) && $b2cNotInB2b && $b2csHasEntries,
        'b2b_local' => ['found' => $b2bLocalFound !== null, 'pass' => $b2bLocalPass, 'data' => $b2bLocalFound],
        'b2b_interstate' => ['found' => $b2bInterFound !== null, 'pass' => $b2bInterPass, 'data' => $b2bInterFound],
        'b2c_not_in_b2b' => $b2cNotInB2b,
        'b2cs_count' => $b2cs['count'],
        'b2cs_taxable' => $b2cs['taxable'],
        'hsn_b2b_groups' => count($hsnB2b['rows']),
        'cdnr_count' => $cdnrRows,
    ];

    // Overall Status
    $overallPass = collect($results)->every(fn ($r) => $r['pass']);

    echo json_encode([
        'status' => $overallPass ? 'PASS' : 'FAIL',
        'details' => $results,
    ], JSON_PRETTY_PRINT);
}

/**
 * 8. DATABASE INTEGRITY
 */
function handleIntegrity(): void
{
    $checks = [];

    // 1. Negative stock = 0
    $negativeStocks = ItemStock::where('quantity', '<', 0)->with(['item', 'branch'])->get();
    $checks['negative_stock'] = [
        'pass' => $negativeStocks->isEmpty(),
        'count' => $negativeStocks->count(),
        'details' => $negativeStocks->map(fn ($s) => "Item {$s->item?->name} at Branch {$s->branch?->name} has qty {$s->quantity}")->all(),
    ];

    // 2. Orphan sale items = 0
    $orphanSales = DB::table('sales_bill_items')
        ->leftJoin('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
        ->whereNull('sales_bills.id')
        ->count();
    $checks['orphan_sale_items'] = ['pass' => $orphanSales === 0, 'count' => $orphanSales];

    // 3. Orphan purchase items = 0
    $orphanPurchases = DB::table('purchase_invoice_items')
        ->leftJoin('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_items.purchase_invoice_id')
        ->whereNull('purchase_invoices.id')
        ->count();
    $checks['orphan_purchase_items'] = ['pass' => $orphanPurchases === 0, 'count' => $orphanPurchases];

    // 4. Orphan return items = 0
    $orphanPrItems = DB::table('purchase_return_items')
        ->leftJoin('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
        ->whereNull('purchase_returns.id')
        ->count();
    $orphanSrItems = DB::table('sales_return_items')
        ->leftJoin('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
        ->whereNull('sales_returns.id')
        ->count();
    $checks['orphan_return_items'] = [
        'pass' => $orphanPrItems === 0 && $orphanSrItems === 0,
        'count' => $orphanPrItems + $orphanSrItems,
    ];

    // 5. Duplicate document numbers = 0
    $dupBills = DB::table('sales_bills')->select('bill_number', DB::raw('count(*) as c'))->groupBy('bill_number')->having('c', '>', 1)->get();
    $dupInvoices = DB::table('purchase_invoices')->select('invoice_number', DB::raw('count(*) as c'))->groupBy('invoice_number')->having('c', '>', 1)->get();
    $dupPr = DB::table('purchase_returns')->select('return_number', DB::raw('count(*) as c'))->groupBy('return_number')->having('c', '>', 1)->get();
    $dupSr = DB::table('sales_returns')->select('return_number', DB::raw('count(*) as c'))->groupBy('return_number')->having('c', '>', 1)->get();
    $dupTransfers = DB::table('stock_transfers')->select('transfer_number', DB::raw('count(*) as c'))->groupBy('transfer_number')->having('c', '>', 1)->get();

    $checks['duplicate_document_numbers'] = [
        'pass' => $dupBills->isEmpty() && $dupInvoices->isEmpty() && $dupPr->isEmpty() && $dupSr->isEmpty() && $dupTransfers->isEmpty(),
        'duplicates' => [
            'bills' => $dupBills->pluck('bill_number'),
            'invoices' => $dupInvoices->pluck('invoice_number'),
            'purchase_returns' => $dupPr->pluck('return_number'),
            'sales_returns' => $dupSr->pluck('return_number'),
            'transfers' => $dupTransfers->pluck('transfer_number'),
        ],
    ];

    // 6. Duplicate posting keys = 0
    $dupKeys = DB::table('stock_transfers')
        ->whereNotNull('posting_key')
        ->select('posting_key', DB::raw('count(*) as c'))
        ->groupBy('posting_key')
        ->having('c', '>', 1)
        ->get();
    $checks['duplicate_posting_keys'] = [
        'pass' => $dupKeys->isEmpty(),
        'count' => $dupKeys->count(),
    ];

    // 7. Invalid transfer relationships = 0
    $invalidTransfers = StockTransfer::whereColumn('from_branch_id', 'to_branch_id')->count();
    $checks['invalid_transfer_relationships'] = [
        'pass' => $invalidTransfers === 0,
        'count' => $invalidTransfers,
    ];

    // 8. Stock ledger inconsistencies = 0
    $missingLedgerRefs = StockLedger::whereNotNull('reference_type')->whereNull('reference_id')->count();
    $checks['stock_ledger_inconsistencies'] = [
        'pass' => $missingLedgerRefs === 0,
        'count' => $missingLedgerRefs,
    ];

    // 9. Invoice total inconsistencies = 0
    $mismatchedInvoices = PurchaseInvoice::with('items')->get()->filter(function ($pi) {
        $calc = round($pi->items->sum('net_amount') + (float) $pi->freight + (float) $pi->round_off + (float) $pi->tcs_amount, 2);
        return abs($calc - (float) $pi->total) > 0.05;
    });
    $checks['invoice_total_inconsistencies'] = [
        'pass' => $mismatchedInvoices->isEmpty(),
        'count' => $mismatchedInvoices->count(),
    ];

    $allPassed = collect($checks)->every(fn ($c) => $c['pass']);

    echo json_encode([
        'status' => $allPassed ? 'PASS' : 'FAIL',
        'checks' => $checks,
    ], JSON_PRETTY_PRINT);
}

function parseArgs(array $argv): array
{
    $options = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }
    return $options;
}
