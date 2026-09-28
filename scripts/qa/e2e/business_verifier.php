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
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\StockLedger;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Services\GST\Gstr1ReportService;
use Illuminate\Support\Facades\DB;

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'setup':
        handleSetup();
        break;

    case 'baseline':
        handleBaseline();
        break;

    case 'reconcile':
        handleReconcile($argv);
        break;

    case 'integrity':
        handleIntegrity();
        break;

    default:
        echo json_encode(['error' => "Unknown action '{$action}'"]);
        exit(1);
}

function handleSetup(): void
{
    $branchA = Branch::find(1) ?? Branch::first();
    $branchB = Branch::find(2) ?? Branch::skip(1)->first();

    $tax0 = GstTax::where('percentage', 0)->first();
    $tax5 = GstTax::where('percentage', 5)->first();
    $tax18 = GstTax::where('percentage', 18)->first();

    $supplier = Supplier::firstOrCreate(
        ['name' => 'E2E Apex Wholesale Supplier'],
        [
            'contact_person' => 'Rajesh Gupta',
            'phone' => '9825012345',
            'email' => 'apex.supplier@e2e-test.test',
            'address' => 'GIDC Vatva',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'gst_no' => '24AAACH7409R1ZZ',
            'is_active' => true,
        ]
    );
    if (empty($supplier->gst_no)) {
        $supplier->update(['gst_no' => '24AAACH7409R1ZZ']);
    }

    $customer = Customer::firstOrCreate(
        ['name' => 'E2E Test Enterprise Customer'],
        [
            'customer_code' => 'C-E2E-001',
            'phone' => '9898011223',
            'email' => 'customer.enterprise@e2e-test.test',
            'address' => 'Navrangpura',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'gst_no' => '24AAACU1234F1Z5',
            'is_active' => true,
        ]
    );

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
            'is_active' => true,
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
            'is_active' => true,
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
            'mrp' => 90.00,
            'is_active' => true,
        ]
    );

    // Ensure clean 0 baseline for the dedicated test items
    foreach ([$branchA->id, $branchB->id] as $bId) {
        foreach ([$itemA->id, $itemB->id, $itemC->id] as $iId) {
            ItemStock::updateOrCreate(
                ['item_id' => $iId, 'branch_id' => $bId],
                ['quantity' => 0]
            );
        }
    }

    echo json_encode([
        'status' => 'OK',
        'branchA' => ['id' => $branchA->id, 'name' => $branchA->name],
        'branchB' => ['id' => $branchB->id, 'name' => $branchB->name],
        'supplier' => ['id' => $supplier->id, 'name' => $supplier->name, 'gstin' => $supplier->gstin],
        'customer' => ['id' => $customer->id, 'name' => $customer->name, 'code' => $customer->customer_code, 'gst_no' => $customer->gst_no],
        'items' => [
            'itemA' => ['id' => $itemA->id, 'code' => $itemA->item_code, 'name' => $itemA->name, 'gst' => 18, 'cost' => 1000.00, 'sell' => 1200.00, 'hsn' => '2309'],
            'itemB' => ['id' => $itemB->id, 'code' => $itemB->item_code, 'name' => $itemB->name, 'gst' => 5, 'cost' => 200.00, 'sell' => 250.00, 'hsn' => '2309'],
            'itemC' => ['id' => $itemC->id, 'code' => $itemC->item_code, 'name' => $itemC->name, 'gst' => 0, 'cost' => 50.00, 'sell' => 80.00, 'hsn' => '1214'],
        ],
    ], JSON_PRETTY_PRINT);
}

function handleBaseline(): void
{
    $itemA = Item::where('item_code', 'E2E-ITEM-A')->firstOrFail();
    $itemB = Item::where('item_code', 'E2E-ITEM-B')->firstOrFail();
    $itemC = Item::where('item_code', 'E2E-ITEM-C')->firstOrFail();

    $stocks = [
        'E2E-ITEM-A' => [
            'branchA' => (float) (ItemStock::where('item_id', $itemA->id)->where('branch_id', 1)->value('quantity') ?? 0),
            'branchB' => (float) (ItemStock::where('item_id', $itemA->id)->where('branch_id', 2)->value('quantity') ?? 0),
        ],
        'E2E-ITEM-B' => [
            'branchA' => (float) (ItemStock::where('item_id', $itemB->id)->where('branch_id', 1)->value('quantity') ?? 0),
            'branchB' => (float) (ItemStock::where('item_id', $itemB->id)->where('branch_id', 2)->value('quantity') ?? 0),
        ],
        'E2E-ITEM-C' => [
            'branchA' => (float) (ItemStock::where('item_id', $itemC->id)->where('branch_id', 1)->value('quantity') ?? 0),
            'branchB' => (float) (ItemStock::where('item_id', $itemC->id)->where('branch_id', 2)->value('quantity') ?? 0),
        ],
    ];

    echo json_encode([
        'status' => 'OK',
        'stocks' => $stocks,
        'timestamp' => now()->toDateTimeString(),
    ], JSON_PRETTY_PRINT);
}

function handleIntegrity(): void
{
    $checks = [];

    // 1. Negative stock
    $negativeStocks = ItemStock::where('quantity', '<', 0)->with(['item', 'branch'])->get();
    $checks['no_negative_stock'] = [
        'pass' => $negativeStocks->isEmpty(),
        'count' => $negativeStocks->count(),
        'details' => $negativeStocks->map(fn ($s) => "Item {$s->item?->name} at Branch {$s->branch?->name} has qty {$s->quantity}")->all(),
    ];

    // 2. Orphan sales bill items
    $orphanSalesItems = DB::table('sales_bill_items')
        ->leftJoin('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
        ->whereNull('sales_bills.id')
        ->count();
    $checks['no_orphan_sales_items'] = [
        'pass' => $orphanSalesItems === 0,
        'count' => $orphanSalesItems,
    ];

    // 3. Orphan purchase invoice items
    $orphanPurchaseItems = DB::table('purchase_invoice_items')
        ->leftJoin('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_items.purchase_invoice_id')
        ->whereNull('purchase_invoices.id')
        ->count();
    $checks['no_orphan_purchase_items'] = [
        'pass' => $orphanPurchaseItems === 0,
        'count' => $orphanPurchaseItems,
    ];

    // 4. Duplicate document numbers
    $dupBills = DB::table('sales_bills')->select('bill_number', DB::raw('count(*) as c'))->groupBy('bill_number')->having('c', '>', 1)->get();
    $dupInvoices = DB::table('purchase_invoices')->select('invoice_number', DB::raw('count(*) as c'))->groupBy('invoice_number')->having('c', '>', 1)->get();
    $dupReturns = DB::table('sales_returns')->select('return_number', DB::raw('count(*) as c'))->groupBy('return_number')->having('c', '>', 1)->get();
    $dupTransfers = DB::table('stock_transfers')->select('transfer_number', DB::raw('count(*) as c'))->groupBy('transfer_number')->having('c', '>', 1)->get();

    $checks['no_duplicate_document_numbers'] = [
        'pass' => $dupBills->isEmpty() && $dupInvoices->isEmpty() && $dupReturns->isEmpty() && $dupTransfers->isEmpty(),
        'duplicates' => [
            'bills' => $dupBills->pluck('bill_number'),
            'invoices' => $dupInvoices->pluck('invoice_number'),
            'returns' => $dupReturns->pluck('return_number'),
            'transfers' => $dupTransfers->pluck('transfer_number'),
        ],
    ];

    // 5. Stock ledger consistency
    $missingLedgerRefs = StockLedger::whereNotNull('reference_type')
        ->whereNull('reference_id')
        ->count();
    $checks['no_missing_ledger_references'] = [
        'pass' => $missingLedgerRefs === 0,
        'count' => $missingLedgerRefs,
    ];

    $allPassed = collect($checks)->every(fn ($c) => $c['pass']);

    echo json_encode([
        'status' => $allPassed ? 'PASS' : 'FAIL',
        'checks' => $checks,
    ], JSON_PRETTY_PRINT);
}

function handleReconcile(array $argv): void
{
    $options = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }

    $piNo = $options['pi'] ?? null;
    $sbNo = $options['sb'] ?? null;
    $prNo = $options['pr'] ?? null;
    $srNo = $options['sr'] ?? null;
    $trNo = $options['tr'] ?? null;
    $mode = $options['mode'] ?? 'full';

    $results = [];

    // Find the documents
    $pi = $piNo ? PurchaseInvoice::where('invoice_number', $piNo)->with('items.item')->first() : null;
    $sb = $sbNo ? SalesBill::where('bill_number', $sbNo)->with('items.item')->first() : null;
    $pr = $prNo ? PurchaseReturn::where('return_number', $prNo)->with('items.item')->first() : null;
    $sr = $srNo ? SalesReturn::where('return_number', $srNo)->with('items.item')->first() : null;
    $tr = $trNo ? StockTransfer::where('transfer_number', $trNo)->with('items.item')->first() : null;

    $itemA = Item::where('item_code', 'E2E-ITEM-A')->firstOrFail();
    $itemB = Item::where('item_code', 'E2E-ITEM-B')->firstOrFail();
    $itemC = Item::where('item_code', 'E2E-ITEM-C')->firstOrFail();

    // -------------------------------------------------------------
    // 1. INVENTORY RECONCILIATION
    // -------------------------------------------------------------
    // Quantities in the run:
    // ITEM-A: PI=10, SB=3, PR=2, SR=1, TR_OUT=2, TR_IN=2 (if mode=full)
    // ITEM-B: PI=20, SB=5, PR=0, SR=0, TR=0
    // ITEM-C: PI=15, SB=4, PR=0, SR=0, TR=0

    $piQtyA = (float) ($pi?->items->where('item_id', $itemA->id)->sum('qty') ?? 0);
    $piQtyB = (float) ($pi?->items->where('item_id', $itemB->id)->sum('qty') ?? 0);
    $piQtyC = (float) ($pi?->items->where('item_id', $itemC->id)->sum('qty') ?? 0);

    $sbQtyA = (float) ($sb?->items->where('item_id', $itemA->id)->sum('qty') ?? 0);
    $sbQtyB = (float) ($sb?->items->where('item_id', $itemB->id)->sum('qty') ?? 0);
    $sbQtyC = (float) ($sb?->items->where('item_id', $itemC->id)->sum('qty') ?? 0);

    $prQtyA = (float) ($pr?->items->where('item_id', $itemA->id)->sum('qty') ?? 0);
    $srQtyA = (float) ($sr?->items->where('item_id', $itemA->id)->sum('qty') ?? 0);

    $trQtyA = (float) ($tr?->items->where('item_id', $itemA->id)->sum('received_qty') ?? 0);
    if ($tr && $tr->status === 'Dispatched') {
        $trOutA = (float) $tr->items->where('item_id', $itemA->id)->sum('qty');
        $trInA = 0.0;
    } else {
        $trOutA = (float) ($tr?->items->where('item_id', $itemA->id)->sum('qty') ?? 0);
        $trInA = (float) ($tr?->items->where('item_id', $itemA->id)->sum('received_qty') ?? 0);
    }

    // Expected Branch 1 (Motera):
    $expB1_A = 0 + $piQtyA - $sbQtyA - $prQtyA + $srQtyA - $trOutA;
    $expB1_B = 0 + $piQtyB - $sbQtyB;
    $expB1_C = 0 + $piQtyC - $sbQtyC;

    // Expected Branch 2 (Vastrapur):
    $expB2_A = 0 + $trInA;
    $expB2_B = 0.0;
    $expB2_C = 0.0;

    $actB1_A = (float) (ItemStock::where('item_id', $itemA->id)->where('branch_id', 1)->value('quantity') ?? 0);
    $actB1_B = (float) (ItemStock::where('item_id', $itemB->id)->where('branch_id', 1)->value('quantity') ?? 0);
    $actB1_C = (float) (ItemStock::where('item_id', $itemC->id)->where('branch_id', 1)->value('quantity') ?? 0);

    $actB2_A = (float) (ItemStock::where('item_id', $itemA->id)->where('branch_id', 2)->value('quantity') ?? 0);
    $actB2_B = (float) (ItemStock::where('item_id', $itemB->id)->where('branch_id', 2)->value('quantity') ?? 0);
    $actB2_C = (float) (ItemStock::where('item_id', $itemC->id)->where('branch_id', 2)->value('quantity') ?? 0);

    $stockMatchB1_A = abs($expB1_A - $actB1_A) < 0.001;
    $stockMatchB1_B = abs($expB1_B - $actB1_B) < 0.001;
    $stockMatchB1_C = abs($expB1_C - $actB1_C) < 0.001;
    $stockMatchB2_A = abs($expB2_A - $actB2_A) < 0.001;

    $results['inventory_reconciliation'] = [
        'pass' => $stockMatchB1_A && $stockMatchB1_B && $stockMatchB1_C && $stockMatchB2_A,
        'itemA_Branch1' => ['expected' => $expB1_A, 'actual' => $actB1_A, 'formula' => "0 + {$piQtyA} - {$sbQtyA} - {$prQtyA} + {$srQtyA} - {$trOutA} = {$expB1_A}", 'match' => $stockMatchB1_A],
        'itemB_Branch1' => ['expected' => $expB1_B, 'actual' => $actB1_B, 'formula' => "0 + {$piQtyB} - {$sbQtyB} = {$expB1_B}", 'match' => $stockMatchB1_B],
        'itemC_Branch1' => ['expected' => $expB1_C, 'actual' => $actB1_C, 'formula' => "0 + {$piQtyC} - {$sbQtyC} = {$expB1_C}", 'match' => $stockMatchB1_C],
        'itemA_Branch2' => ['expected' => $expB2_A, 'actual' => $actB2_A, 'formula' => "0 + {$trInA} = {$expB2_A}", 'match' => $stockMatchB2_A],
    ];

    // -------------------------------------------------------------
    // 2. SALES RECONCILIATION
    // -------------------------------------------------------------
    if ($sb) {
        // UrbanPOS retail selling price is GST-inclusive (MRP retail pricing)
        $expectedTotal = round((3 * 1200.00) + (5 * 250.00) + (4 * 80.00), 2); // 5170.00
        $expectedTaxableA = round(3600 / 1.18, 2); // 3050.85
        $expectedGstA = round(3600 - $expectedTaxableA, 2); // 549.15
        $expectedTaxableB = round(1250 / 1.05, 2); // 1190.48
        $expectedGstB = round(1250 - $expectedTaxableB, 2); // 59.52
        $expectedTaxableC = 320.00;
        $expectedGstC = 0.00;
        $expectedTaxable = round($expectedTaxableA + $expectedTaxableB + $expectedTaxableC, 2); // 4561.33
        $expectedTotalGst = round($expectedGstA + $expectedGstB + $expectedGstC, 2); // 608.67

        $actTaxable = round((float) $sb->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount), 2);
        $actGst = round((float) $sb->total_gst, 2);
        $actTotal = round((float) $sb->total, 2);

        $salesMatch = abs($expectedTaxable - $actTaxable) < 0.05 && abs($expectedTotalGst - $actGst) < 0.05 && abs($expectedTotal - $actTotal) < 0.05;

        $results['sales_reconciliation'] = [
            'pass' => $salesMatch,
            'bill_number' => $sb->bill_number,
            'taxable' => ['expected' => $expectedTaxable, 'actual' => $actTaxable],
            'gst' => ['expected' => $expectedTotalGst, 'actual' => $actGst],
            'total' => ['expected' => $expectedTotal, 'actual' => $actTotal],
        ];
    }

    // -------------------------------------------------------------
    // 3. PURCHASE RECONCILIATION
    // -------------------------------------------------------------
    if ($pi) {
        $expectedCostTaxable = round((10 * 1000) + (20 * 200) + (15 * 50), 2); // 10000 + 4000 + 750 = 14750.00
        $expectedCostGst18 = round(10000 * 0.18, 2); // 1800.00
        $expectedCostGst5 = round(4000 * 0.05, 2);   // 200.00
        $expectedCostGst0 = 0.00;
        $expectedCostTotalGst = round($expectedCostGst18 + $expectedCostGst5 + $expectedCostGst0, 2); // 2000.00
        $expectedCostTotal = round($expectedCostTaxable + $expectedCostTotalGst, 2); // 16750.00

        $actCostTaxable = round((float) $pi->items->sum(fn ($i) => $i->net_amount - $i->gst_tax_amount), 2);
        $actCostGst = round((float) $pi->total_gst, 2);
        $actCostTotal = round((float) $pi->total, 2);

        $purchaseMatch = abs($expectedCostTaxable - $actCostTaxable) < 0.05 && abs($expectedCostTotalGst - $actCostGst) < 0.05 && abs($expectedCostTotal - $actCostTotal) < 0.05;

        $results['purchase_reconciliation'] = [
            'pass' => $purchaseMatch,
            'invoice_number' => $pi->invoice_number,
            'taxable' => ['expected' => $expectedCostTaxable, 'actual' => $actCostTaxable],
            'gst' => ['expected' => $expectedCostTotalGst, 'actual' => $actCostGst],
            'total' => ['expected' => $expectedCostTotal, 'actual' => $actCostTotal],
        ];
    }

    // -------------------------------------------------------------
    // 4. GSTR-1 CROSS-VERIFICATION
    // -------------------------------------------------------------
    $today = date('Y-m-d');
    $gstr1Service = new Gstr1ReportService($today, $today);
    $b2b = $gstr1Service->b2b();
    $b2cs = $gstr1Service->b2cs();
    $hsnB2b = $gstr1Service->hsnB2b();

    // If our test bill was issued to a customer with GSTIN, it MUST appear in B2B!
    $billInB2b = collect($b2b['rows'])->firstWhere('ref', $sbNo);
    $gstr1Pass = $sbNo ? ($billInB2b !== null && abs((float) $billInB2b['total'] - (float) $sb->total) < 0.05) : true;

    $results['gstr1_cross_verification'] = [
        'pass' => $gstr1Pass,
        'bill_in_b2b' => $billInB2b !== null,
        'b2b_entry' => $billInB2b,
        'b2b_total_count' => $b2b['count'],
        'b2b_total_taxable' => $b2b['taxable'],
        'b2b_total_tax' => $b2b['tax'],
        'hsn_b2b_rows' => count($hsnB2b['rows']),
    ];

    // Overall outcome
    $overallPass = collect($results)->every(fn ($r) => $r['pass']);

    echo json_encode([
        'status' => $overallPass ? 'PASS' : 'FAIL',
        'details' => $results,
    ], JSON_PRETTY_PRINT);
}
