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
}
