<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesBillEditStockRestorationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        User::factory()->create(); // Super-user id 1
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main', 'code' => 'MAIN', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        \App\Models\TenderType::firstOrCreate(['id' => 1], ['name' => 'Cash', 'type' => 'Cash', 'status' => true]);

        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');

        $this->customer = Customer::create(['name' => 'Test Customer', 'mobile' => '9898000001', 'state' => 'Gujarat', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Item A',
            'item_code' => 'ITEM-A',
            'sell_price' => 100,
            'mrp' => 100,
            'cost_price' => 60,
            'gst_tax_id' => $gst->id,
            'tax_inclusive' => true,
            'status' => true,
            'allow_negative_stock' => false,
            'batch_expiry_details' => 'Batch and Expiry Date',
        ]);
    }

    public function test_sales_bill_edit_restores_deducted_stock_allowing_increase_from_10_to_11(): void
    {
        $expDate = now()->addMonths(6)->toDateString();
        $batchNo = 'BATCH-A1';

        // 1. Inward 12 units via StockLedger
        $stockLedger = app(StockLedgerService::class);
        $stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 12,
            unitCost: 60,
            referenceType: PurchaseInvoice::class,
            referenceId: 999,
            documentDate: now()->toDateString(),
            expDate: $expDate,
            batchNo: $batchNo,
            mrp: 100
        );

        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch->id],
            ['quantity' => 12, 'sell_price' => 100, 'cost_price' => 60, 'mrp' => 100]
        );

        // 2. Create Sales Bill for 10 units (remaining stock in warehouse becomes 2)
        $createResponse = $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => $batchNo,
                    'exp_date' => $expDate,
                    'qty' => 10,
                    'sell_price' => 100,
                    'mrp' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                ],
            ],
            'payments' => [
                ['tender_type_id' => 1, 'amount' => 1000],
            ],
        ]);

        $this->assertEquals(302, $createResponse->getStatusCode());
        $createResponse->assertSessionHasNoErrors();
        $bill = SalesBill::latest('id')->first();
        $this->assertNotNull($bill);

        // Physical remaining stock in warehouse is now 2
        $currStock = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals(2.0, $currStock);

        // 3. Test lookup-item endpoint for this bill: must restore stock to 12 (2 + 10)
        $lookupRes = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', [
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $bill->id,
        ]))->assertOk();

        $lookupData = $lookupRes->json();
        $this->assertEquals(12.0, (float) $lookupData['item']['stock'], 'Item stock in lookup should be 12 (2 available + 10 in bill)');
        $this->assertEquals(12.0, (float) $lookupData['item']['total_branch_stock'], 'total_branch_stock should be 12');
        $this->assertNotEmpty($lookupData['batches']);
        $this->assertEquals(12.0, (float) $lookupData['batches'][0]['qty'], 'Batch qty in lookup should be 12');

        // 4. Test item-list endpoint: should show 12
        $itemListRes = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $bill->id,
            'search' => 'Item A',
        ]))->assertOk();

        $items = $itemListRes->json('items');
        $this->assertNotEmpty($items);
        $this->assertEquals(12.0, (float) $items[0]['qty']);

        // 5. Test SalesBillController@edit: view pre-populates stock and batch_stock as 12
        $editResponse = $this->actingAs($this->owner)->get(route('sales.sales-bills.edit', $bill))->assertOk();
        $loadedBill = $editResponse->viewData('salesBill');
        $firstItem = $loadedBill->items->first();
        $this->assertEquals(12.0, (float) $firstItem->stock);
        $this->assertEquals(12.0, (float) $firstItem->batch_stock);

        // 6. Test SalesBillController@posTerminal with edit_id: pre-populates stock and batch_stock as 12
        $posResponse = $this->actingAs($this->owner)->get(route('pos.terminal', ['edit_id' => $bill->id]))->assertOk();
        $editBillFromPos = $posResponse->viewData('editBill');
        $posItem = $editBillFromPos->items->first();
        $this->assertEquals(12.0, (float) $posItem->stock);
        $this->assertEquals(12.0, (float) $posItem->batch_stock);

        // 7. Successfully update quantity from 10 to 11
        $updateResponse = $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), [
            'bill_number' => $bill->bill_number,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => $batchNo,
                    'exp_date' => $expDate,
                    'qty' => 11,
                    'sell_price' => 100,
                    'mrp' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                ],
            ],
            'payments' => [
                ['tender_type_id' => 1, 'amount' => 1100],
            ],
        ]);

        $this->assertEquals(302, $updateResponse->getStatusCode());
        $updateResponse->assertSessionHasNoErrors();

        // Stock in warehouse should now be 1 (12 - 11 = 1)
        $newStock = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals(1.0, $newStock);
    }

    public function test_legacy_imported_sales_bill_edit_allows_saving_existing_items_without_stock_ledger_records(): void
    {
        $expDate = now()->addMonths(6)->toDateString();
        $batchNo = 'LEGACY-B01';

        // Item warehouse stock is 0
        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch->id],
            ['quantity' => 0, 'sell_price' => 100, 'cost_price' => 60, 'mrp' => 100]
        );

        // Create legacy sales bill directly in DB with NO StockLedger records
        $legacyBill = SalesBill::create([
            'bill_number' => 'SI01/2627/000094',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'subtotal' => 100,
            'total' => 100,
            'status' => 'Paid',
        ]);

        $legacyBill->items()->create([
            'item_id' => $this->item->id,
            'batch_no' => $batchNo,
            'exp_date' => $expDate,
            'qty' => 1,
            'sell_price' => 100,
            'mrp' => 100,
            'cost_price' => 60,
            'gst_percent' => 18,
            'gst_amount' => 15.25,
            'net_amount' => 100,
        ]);

        // Verify there are 0 StockLedger records for this bill
        $this->assertEquals(0, \App\Models\StockLedger::where('reference_id', $legacyBill->id)->count());

        // 1. Controller edit view should load and stock / batch_stock should be at least 1 (not 0)
        $editResponse = $this->actingAs($this->owner)->get(route('sales.sales-bills.edit', $legacyBill))->assertOk();
        $loadedBill = $editResponse->viewData('salesBill');
        $lineItem = $loadedBill->items->first();
        $this->assertEquals(1.0, (float) $lineItem->stock, 'Product stock should be at least 1 for existing bill item');
        $this->assertEquals(1.0, (float) $lineItem->batch_stock, 'Batch stock should be at least 1 for existing bill item');

        // 2. Lookup item endpoint with sales_bill_id should return stock >= 1 and include the batch
        $lookupRes = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', [
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $legacyBill->id,
        ]))->assertOk();

        $lookupData = $lookupRes->json();
        $this->assertEquals(1.0, (float) $lookupData['item']['stock']);
        $this->assertNotEmpty($lookupData['batches']);
        $this->assertEquals($batchNo, $lookupData['batches'][0]['batch_no']);
        $this->assertEquals(1.0, (float) $lookupData['batches'][0]['qty']);

        // 3. Update existing bill preserving the same quantity should succeed smoothly
        $updateResponse = $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $legacyBill), [
            'bill_number' => $legacyBill->bill_number,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'batch_no' => $batchNo,
                    'exp_date' => $expDate,
                    'qty' => 1,
                    'sell_price' => 100,
                    'mrp' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                ],
            ],
            'payments' => [
                ['tender_type_id' => 1, 'amount' => 100],
            ],
        ]);

        $this->assertEquals(302, $updateResponse->getStatusCode());
        $updateResponse->assertSessionHasNoErrors();

        // Warehouse stock should still be 0 (restored 1, then re-deducted 1)
        $finalStock = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals(0.0, $finalStock);
    }
}
