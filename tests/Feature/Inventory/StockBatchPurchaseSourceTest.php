<?php

namespace Tests\Feature\Inventory;

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
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\BatchStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockBatchPurchaseSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Supplier $supplier;
    private Customer $customer;
    private GstTax $gst;
    private Item $item;
    private BatchStockService $batchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->user = User::factory()->create(['id' => 1]);
        $this->actingAs($this->user);

        $this->branch = Branch::create(['name' => 'Main Branch', 'state' => 'Gujarat']);
        $this->supplier = Supplier::create(['name' => 'Supplier X', 'phone' => '9988776655', 'state' => 'Gujarat']);
        $this->customer = Customer::create(['name' => 'Customer Y', 'mobile' => '9988776644', 'state' => 'Gujarat', 'status' => true]);
        $this->gst = GstTax::firstOrCreate(['percentage' => 0], ['name' => 'GST 0%', 'description' => 'None', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Item A',
            'item_code' => 'ITM-A',
            'sell_price' => 900,
            'mrp' => 950,
            'cost_price' => 700,
            'landing_cost' => 700,
            'gst_tax_id' => $this->gst->id,
            'status' => true,
            'allow_negative_stock' => true,
            'batch_expiry_details' => 'Batch and Expiry Required',
        ]);

        $this->batchService = app(BatchStockService::class);
    }

    /**
     * Helper to post a purchase invoice
     */
    private function postPurchase(string $invoiceNo, string $batchNo, string $expDate, float $costPrice, float $mrp, float $sellPrice, float $qty): PurchaseInvoice
    {
        $response = $this->post(route('purchase.purchase-invoices.store'), [
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'supplier_inv_no' => $invoiceNo,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => $costPrice * $qty,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => $batchNo,
                    'exp_date' => $expDate,
                    'cost_price' => $costPrice,
                    'mrp' => $mrp,
                    'sell_price' => $sellPrice,
                    'qty' => $qty,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        return PurchaseInvoice::where('supplier_inv_no', $invoiceNo)->firstOrFail();
    }

    /**
     * TEST 1: Purchase one batch -> Stock matches exactly.
     */
    public function test_purchase_one_batch_stock_matches_exactly(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);

        $batches = $this->batchService->getItemBatches($this->item->id, $this->branch->id);

        $this->assertCount(1, $batches);
        $b = $batches[0];
        $this->assertEquals('B001', $b['batch_no']);
        $this->assertEquals('2027-05-20', $b['exp_date']);
        $this->assertEquals(800.0, (float) $b['cost_price']);
        $this->assertEquals(1000.0, (float) $b['mrp']);
        $this->assertEquals(950.0, (float) $b['sell_price']);
        $this->assertEquals(10.0, (float) $b['purchased_qty']);
        $this->assertEquals(10.0, (float) $b['remaining_qty']);
    }

    /**
     * TEST 2: Purchase same item with second batch -> both batches remain separate.
     */
    public function test_purchase_second_batch_both_batches_remain_separate(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        $batches = $this->batchService->getItemBatches($this->item->id, $this->branch->id);

        $this->assertCount(2, $batches);
        $batchNos = $batches->pluck('batch_no')->all();
        $this->assertContains('B001', $batchNos);
        $this->assertContains('B002', $batchNos);
    }

    /**
     * TEST 3: Different expiry per batch -> expiry remains separate.
     */
    public function test_different_expiry_per_batch_remains_separate(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals('2027-05-20', $b1['exp_date']);
        $this->assertEquals('2027-08-20', $b2['exp_date']);
        $this->assertNotEquals($b1['exp_date'], $b2['exp_date']);
    }

    /**
     * TEST 4: Different MRP per batch -> MRP remains separate.
     */
    public function test_different_mrp_per_batch_remains_separate(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(1000.0, (float) $b1['mrp']);
        $this->assertEquals(1100.0, (float) $b2['mrp']);
        $this->assertNotEquals($b1['mrp'], $b2['mrp']);
    }

    /**
     * TEST 5: Different sales price per batch -> sales price remains separate.
     */
    public function test_different_sales_price_per_batch_remains_separate(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(950.0, (float) $b1['sell_price']);
        $this->assertEquals(1050.0, (float) $b2['sell_price']);
        $this->assertNotEquals($b1['sell_price'], $b2['sell_price']);
    }

    /**
     * TEST 6: Different purchase cost per batch -> cost remains separate.
     */
    public function test_different_purchase_cost_per_batch_remains_separate(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(800.0, (float) $b1['cost_price']);
        $this->assertEquals(850.0, (float) $b2['cost_price']);
        $this->assertNotEquals($b1['cost_price'], $b2['cost_price']);
    }

    /**
     * TEST 7: Sell from B001 -> only B001 stock decreases.
     */
    public function test_sell_from_b001_only_b001_stock_decreases(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        // Sell 3 units from B001
        $res = $this->post(route('sales.sales-bills.store'), [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'sales_type' => 'Local',
            'delivery_type' => 'Counter Sale',
            'invoice_type' => 'Retail Invoice',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => 'B001',
                    'exp_date' => '2027-05-20',
                    'qty' => 3.0,
                    'sell_price' => 950.0,
                    'mrp' => 1000.0,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => \App\Models\TenderType::firstOrCreate(['name' => 'Cash'], ['code' => 'CSH', 'status' => true])->id,
                    'amount' => 2850.0,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(7.0, (float) $b1['remaining_qty']);
        $this->assertEquals(20.0, (float) $b2['remaining_qty']); // Untouched!
    }

    /**
     * TEST 8: Purchase Return B001 -> only B001 stock decreases.
     */
    public function test_purchase_return_b001_only_b001_stock_decreases(): void
    {
        $pinv1 = $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        // Return 2 units of B001 against PINV-001
        $res = $this->post(route('purchase.purchase-returns.store'), [
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $pinv1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => 'B001',
                    'exp_date' => '2027-05-20',
                    'qty' => 2.0,
                    'cost_price' => 800.0,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $b1 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2 = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(8.0, (float) $b1['remaining_qty']);
        $this->assertEquals(20.0, (float) $b2['remaining_qty']); // B002 untouched!
    }

    /**
     * TEST 9: Sales Return -> original batch information is preserved according to business rule.
     */
    public function test_sales_return_preserves_original_batch_information(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);

        // Sell 5 units of B001
        $resBill = $this->post(route('sales.sales-bills.store'), [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'sales_type' => 'Local',
            'delivery_type' => 'Counter Sale',
            'invoice_type' => 'Retail Invoice',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => 'B001',
                    'exp_date' => '2027-05-20',
                    'qty' => 5.0,
                    'sell_price' => 950.0,
                    'mrp' => 1000.0,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => \App\Models\TenderType::firstOrCreate(['name' => 'Cash'], ['code' => 'CSH', 'status' => true])->id,
                    'amount' => 4750.0,
                ],
            ],
        ]);
        $resBill->assertSessionHasNoErrors();

        $salesBill = SalesBill::latest('id')->firstOrFail();

        // Stock after sale: 5 remaining
        $b1AfterSale = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $this->assertEquals(5.0, (float) $b1AfterSale['remaining_qty']);

        // Return 2 units against this bill
        $resRet = $this->post(route('sales.sales-returns.store'), [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'sales_bill_id' => $salesBill->id,
            'return_date' => now()->toDateString(),
            'return_mode' => 'Cash',
            'sales_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => 'B001',
                    'exp_date' => '2027-05-20',
                    'qty' => 2.0,
                    'sell_price' => 950.0,
                    'mrp' => 1000.0,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ]);
        $resRet->assertSessionHasNoErrors();

        // Check SalesReturnItem batch_no
        $returnItem = SalesReturnItem::latest('id')->firstOrFail();
        $this->assertEquals('B001', $returnItem->batch_no);

        // Check BatchStockService remaining qty: 5 + 2 = 7
        $b1AfterReturn = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $this->assertEquals(7.0, (float) $b1AfterReturn['remaining_qty']);
        $this->assertEquals('2027-05-20', $b1AfterReturn['exp_date']);
        $this->assertEquals(800.0, (float) $b1AfterReturn['cost_price']);
        $this->assertEquals(1000.0, (float) $b1AfterReturn['mrp']);
        $this->assertEquals(950.0, (float) $b1AfterReturn['sell_price']);
    }

    /**
     * TEST 10: Historical batch is not overwritten by a newer purchase.
     */
    public function test_historical_batch_is_not_overwritten_by_newer_purchase(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);

        // Verify initial state of B001
        $b1Before = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $this->assertEquals(800.0, (float) $b1Before['cost_price']);
        $this->assertEquals(1000.0, (float) $b1Before['mrp']);
        $this->assertEquals(950.0, (float) $b1Before['sell_price']);
        $this->assertEquals('2027-05-20', $b1Before['exp_date']);

        // Post newer purchase with different attributes
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        // Verify historical B001 remains completely unchanged
        $b1After = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $this->assertEquals(800.0, (float) $b1After['cost_price']);
        $this->assertEquals(1000.0, (float) $b1After['mrp']);
        $this->assertEquals(950.0, (float) $b1After['sell_price']);
        $this->assertEquals('2027-05-20', $b1After['exp_date']);
        $this->assertEquals(10.0, (float) $b1After['remaining_qty']);

        // Verify B002 has its distinct attributes
        $b2After = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');
        $this->assertEquals(850.0, (float) $b2After['cost_price']);
        $this->assertEquals(1100.0, (float) $b2After['mrp']);
        $this->assertEquals(1050.0, (float) $b2After['sell_price']);
        $this->assertEquals('2027-08-20', $b2After['exp_date']);
        $this->assertEquals(20.0, (float) $b2After['remaining_qty']);
    }

    /**
     * TEST 11: Stock Update physical count derives batch attributes from original purchase and adjusts only that batch.
     */
    public function test_stock_update_derives_batch_attributes_and_adjusts_batch_stock(): void
    {
        $this->postPurchase('PINV-001', 'B001', '2027-05-20', 800.0, 1000.0, 950.0, 10.0);
        $this->postPurchase('PINV-002', 'B002', '2027-08-20', 850.0, 1100.0, 1050.0, 20.0);

        // Submit Stock Update counting B001 as 12 (an excess of +2)
        $res = $this->post(route('inventory.stock-updates.store'), [
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'remarks' => 'Count adjustment',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => 'B001',
                    'physical_qty' => 12.0,
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();

        $stockUpdate = \App\Models\StockUpdate::latest('id')->firstOrFail();
        $itemLine = $stockUpdate->items->first();

        // Verify derived batch attributes stored on StockUpdateItem
        $this->assertEquals('B001', $itemLine->batch_no);
        $this->assertEquals('2027-05-20', $itemLine->exp_date?->toDateString());
        $this->assertEquals(800.0, (float) $itemLine->cost_price);
        $this->assertEquals(1000.0, (float) $itemLine->mrp);
        $this->assertEquals(950.0, (float) $itemLine->sell_price);
        $this->assertEquals(10.0, (float) $itemLine->system_qty_at_entry);
        $this->assertEquals(2.0, (float) $itemLine->delta_qty);

        // Approve the stock update
        $resApprove = $this->post(route('inventory.stock-update-approval.approve', $stockUpdate->id));
        $resApprove->assertSessionHasNoErrors();

        // Verify batch stock: B001 is now 12, B002 remains 20
        $b1After = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B001');
        $b2After = $this->batchService->getBatchStock($this->item->id, $this->branch->id, 'B002');

        $this->assertEquals(12.0, (float) $b1After['remaining_qty']);
        $this->assertEquals(20.0, (float) $b2After['remaining_qty']);
    }
}
