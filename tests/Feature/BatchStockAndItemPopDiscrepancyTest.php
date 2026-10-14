<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\BatchStockService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchStockAndItemPopDiscrepancyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch1;
    private Branch $branch2;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create(['name' => 'Motera', 'code' => 'MOT', 'status' => true]);
        $this->branch2 = Branch::create(['name' => 'Satellite', 'code' => 'SAT', 'status' => true]);

        $this->admin = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branch1->id,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Main Supplier',
            'status' => true,
        ]);
    }

    public function test_two_purchases_and_sale_reconcile_properly_in_batch_stock_service(): void
    {
        $item = Item::create([
            'name' => 'Pedigree Adult Dog Food',
            'item_code' => 'PED001',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'status' => true,
        ]);

        $stockLedger = app(StockLedgerService::class);

        // Purchase 1: 10 qty, exp 2030-12-31, unbatched (batch_no = null)
        $pi1 = PurchaseInvoice::create([
            'invoice_number' => 'PI01/2627/000001',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch1->id,
            'total' => 1000,
            'status' => 'Received',
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi1->id,
            'item_id' => $item->id,
            'qty' => 10,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'exp_date' => '2030-12-31',
            'batch_no' => null,
            'net_amount' => 1000,
        ]);
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 10,
            unitCost: 100,
            referenceType: PurchaseInvoice::class,
            referenceId: $pi1->id,
            documentDate: now()->toDateString(),
            expDate: '2030-12-31',
            batchNo: null,
        );

        // Purchase 2: 10 qty, exp 2030-12-31, unbatched (batch_no = '')
        $pi2 = PurchaseInvoice::create([
            'invoice_number' => 'PI01/2627/000002',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch1->id,
            'total' => 1000,
            'status' => 'Received',
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi2->id,
            'item_id' => $item->id,
            'qty' => 10,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'exp_date' => '2030-12-31',
            'batch_no' => '',
            'net_amount' => 1000,
        ]);
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 10,
            unitCost: 100,
            referenceType: PurchaseInvoice::class,
            referenceId: $pi2->id,
            documentDate: now()->toDateString(),
            expDate: '2030-12-31',
            batchNo: '',
        );

        // Sale: 5 qty sold where cashier entered '30/12/2030' as batch_no
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'SALE',
            qtyDelta: -5,
            unitCost: 100,
            referenceType: \App\Models\SalesBill::class,
            referenceId: 999,
            documentDate: now()->toDateString(),
            expDate: '2030-12-31',
            batchNo: '30/12/2030',
        );

        // Physical branch stock in ItemStock is 10 + 10 - 5 = 15
        $branchStock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch1->id)->value('quantity');
        $this->assertEquals(15.0, (float) $branchStock);

        // Test BatchStockService
        $batchService = app(BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $this->branch1->id);

        // Total remaining in batches must equal 15 (NOT 0!)
        $totalBatchRem = $batches->sum('remaining_qty');
        $this->assertEquals(15.0, (float) $totalBatchRem);

        // Lookup Item API should return matching stock
        $this->actingAs($this->admin);
        $response = $this->getJson(route('sales.sales-bills.lookup-item', [
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
        ]));

        $response->assertOk();
        $this->assertEquals(15.0, (float) $response->json('item.stock'));

        // Item List (itempop) must also return matching qty 15
        $popResponse = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch1->id,
            'search' => 'Pedigree',
        ]));
        $popResponse->assertOk();
        $items = $popResponse->json('items');
        $this->assertNotEmpty($items);
        $this->assertEquals(15.0, (float) $items[0]['qty']);
    }

    public function test_batches_with_same_expiry_but_different_mrp_deduct_precisely_by_mrp(): void
    {
        $item = Item::create([
            'name' => 'Drools Cat Adult Ocean Fish 5kg',
            'item_code' => 'DROOLS-148',
            'cost_price' => 1000,
            'sell_price' => 2500,
            'mrp' => 2500,
            'is_active' => true,
        ]);

        $purchase = PurchaseInvoice::create([
            'branch_id' => $this->branch1->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PI-TEST-87',
            'invoice_date' => now()->toDateString(),
            'status' => 'Received',
            'total' => 50000,
        ]);

        // Purchase 1: 20 qty, Exp 2034-05-30, MRP 2500
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $purchase->id,
            'item_id' => $item->id,
            'qty' => 20,
            'cost_price' => 1000,
            'sell_price' => 2500,
            'mrp' => 2500,
            'exp_date' => '2034-05-30',
            'batch_no' => '',
        ]);

        // Purchase 2: 20 qty, Exp 2034-05-30, MRP 3000
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $purchase->id,
            'item_id' => $item->id,
            'qty' => 20,
            'cost_price' => 1500,
            'sell_price' => 3000,
            'mrp' => 3000,
            'exp_date' => '2034-05-30',
            'batch_no' => '',
        ]);

        $stockLedger = app(StockLedgerService::class);

        // Ledger: Purchase 1 in
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 20,
            unitCost: 1000,
            referenceType: PurchaseInvoice::class,
            referenceId: $purchase->id,
            documentDate: now()->toDateString(),
            expDate: '2034-05-30',
            batchNo: '',
            mrp: 2500,
        );

        // Ledger: Purchase 2 in
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 20,
            unitCost: 1500,
            referenceType: PurchaseInvoice::class,
            referenceId: $purchase->id,
            documentDate: now()->toDateString(),
            expDate: '2034-05-30',
            batchNo: '',
            mrp: 3000,
        );

        // Sale: 4 sold from MRP 2500 batch
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'SALE',
            qtyDelta: -4,
            unitCost: 1000,
            referenceType: \App\Models\SalesBill::class,
            referenceId: 101,
            documentDate: now()->toDateString(),
            expDate: '2034-05-30',
            batchNo: '30/05/2034',
            mrp: 2500,
        );

        // Transfer: 1 transferred from MRP 2500 batch
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'TRANSFER_OUT',
            qtyDelta: -1,
            unitCost: 1000,
            referenceType: \App\Models\StockTransfer::class,
            referenceId: 201,
            documentDate: now()->toDateString(),
            expDate: '2034-05-30',
            batchNo: '',
            mrp: 2500,
        );

        // Transfer: 1 transferred from MRP 3000 batch
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch1->id,
            movementType: 'TRANSFER_OUT',
            qtyDelta: -1,
            unitCost: 1500,
            referenceType: \App\Models\StockTransfer::class,
            referenceId: 202,
            documentDate: now()->toDateString(),
            expDate: '2034-05-30',
            batchNo: '',
            mrp: 3000,
        );

        // Assert ItemStock total = 20 + 20 - 4 - 1 - 1 = 34
        $totalStock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch1->id)->value('quantity');
        $this->assertEquals(34.0, $totalStock);

        // Assert BatchStockService resolves:
        // MRP 2500 batch: remaining = 20 - 4 - 1 = 15
        // MRP 3000 batch: remaining = 20 - 1 = 19
        $batchService = app(BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $this->branch1->id);

        $batch2500 = $batches->firstWhere('mrp', 2500);
        $batch3000 = $batches->firstWhere('mrp', 3000);

        $this->assertNotNull($batch2500, 'Batch with MRP 2500 must exist');
        $this->assertNotNull($batch3000, 'Batch with MRP 3000 must exist');

        $this->assertEquals(15.0, (float) $batch2500['remaining_qty'], 'MRP 2500 batch must have 15 remaining');
        $this->assertEquals(19.0, (float) $batch3000['remaining_qty'], 'MRP 3000 batch must have 19 remaining');
    }

    public function test_transferred_batches_preserve_individual_selling_prices_and_mrp_caps(): void
    {
        // Item in Master has high sell_price (e.g. 4000)
        $item = Item::create([
            'name' => 'Drools Cat Adult Ocean Fish 5KG',
            'item_code' => 'DROOLS001',
            'cost_price' => 1500,
            'sell_price' => 4000,
            'mrp' => 4000,
            'status' => true,
        ]);

        $stockLedger = app(StockLedgerService::class);

        // Branch 2 (Satellite) already has 10 units at 4000 MRP from purchase
        $pi = PurchaseInvoice::create([
            'invoice_number' => 'PI01/2627/000155',
            'branch_id' => $this->branch2->id,
            'supplier_id' => $this->supplier->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'Received',
            'subtotal' => 15000,
            'total' => 15000,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi->id,
            'item_id' => $item->id,
            'qty' => 10,
            'cost_price' => 1500,
            'sell_price' => 4000,
            'mrp' => 4000,
            'exp_date' => '2031-05-30',
        ]);
        $stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch2->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 10,
            unitCost: 1500,
            referenceType: PurchaseInvoice::class,
            referenceId: $pi->id,
            documentDate: now()->toDateString(),
            expDate: '2031-05-30',
            batchNo: '',
            mrp: 4000,
        );

        // Motera transfers 4 items to Satellite with different MRPs (1500, 2000, 2500, 3000)
        $st = \App\Models\StockTransfer::create([
            'transfer_number' => 'ST02/2627/000002',
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Received',
            'total_value' => 4300,
        ]);

        $transferredBatches = [
            ['exp' => '2033-05-30', 'mrp' => 1500.0, 'cost' => 850.0],
            ['exp' => '2028-05-15', 'mrp' => 2000.0, 'cost' => 950.0],
            ['exp' => '2034-05-30', 'mrp' => 2500.0, 'cost' => 1000.0],
            ['exp' => '2034-05-30', 'mrp' => 3000.0, 'cost' => 1500.0],
        ];

        foreach ($transferredBatches as $tb) {
            \App\Models\StockTransferItem::create([
                'stock_transfer_id' => $st->id,
                'item_id' => $item->id,
                'qty' => 1,
                'received_qty' => 1,
                'unit_cost' => $tb['cost'],
                'sell_price' => $tb['mrp'],
                'mrp' => $tb['mrp'],
                'exp_date' => $tb['exp'],
            ]);

            $stockLedger->post(
                itemId: $item->id,
                branchId: $this->branch2->id,
                movementType: 'TRANSFER_IN',
                qtyDelta: 1,
                unitCost: $tb['cost'],
                referenceType: \App\Models\StockTransfer::class,
                referenceId: $st->id,
                documentDate: now()->toDateString(),
                expDate: $tb['exp'],
                batchNo: '',
                mrp: $tb['mrp'],
            );
        }

        // Assert BatchStockService for Satellite (Branch 2):
        // Each batch must show its OWN sell_price and mrp (1500, 2000, 2500, 3000, 4000)
        // NOT all 4000!
        $batchService = app(BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $this->branch2->id);

        $this->assertCount(5, $batches);

        $b1500 = $batches->firstWhere('mrp', 1500);
        $b2000 = $batches->firstWhere('mrp', 2000);
        $b2500 = $batches->firstWhere('mrp', 2500);
        $b3000 = $batches->firstWhere('mrp', 3000);
        $b4000 = $batches->firstWhere('mrp', 4000);

        $this->assertNotNull($b1500);
        $this->assertEquals(1500.0, (float) $b1500['sell_price']);
        $this->assertEquals(1500.0, (float) $b1500['mrp']);

        $this->assertNotNull($b2000);
        $this->assertEquals(2000.0, (float) $b2000['sell_price']);
        $this->assertEquals(2000.0, (float) $b2000['mrp']);

        $this->assertNotNull($b2500);
        $this->assertEquals(2500.0, (float) $b2500['sell_price']);
        $this->assertEquals(2500.0, (float) $b2500['mrp']);

        $this->assertNotNull($b3000);
        $this->assertEquals(3000.0, (float) $b3000['sell_price']);
        $this->assertEquals(3000.0, (float) $b3000['mrp']);

        $this->assertNotNull($b4000);
        $this->assertEquals(4000.0, (float) $b4000['sell_price']);
        $this->assertEquals(4000.0, (float) $b4000['mrp']);
    }
}
