<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPriceIsolationAndBatchTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branchMotera;
    private Branch $branchSatellite;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchMotera = Branch::create([
            'name' => 'Motera Stadium',
            'code' => 'MOTERA',
            'status' => true,
        ]);

        $this->branchSatellite = Branch::create([
            'name' => 'Satellite Store',
            'code' => 'SAT',
            'status' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Supplier A',
            'phone' => '9876543210',
            'status' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branchMotera->id,
        ]);
    }

    public function test_branch_price_isolation_after_stock_transfer_and_subsequent_purchase(): void
    {
        $item = Item::create([
            'name' => 'Item A MultiBranch',
            'item_code' => 'ITEM-MB-001',
            'cost_price' => 10000,
            'landing_cost' => 10000,
            'sell_price' => 11000,
            'mrp' => 12000,
            'status' => true,
            'batch_expiry_details' => 'Not Required',
        ]);

        // 1. Initial purchase in Motera at cost 10,000, sell 11,000
        $pi1 = PurchaseInvoice::create([
            'invoice_number' => 'PI-MOT-001',
            'invoice_date' => now()->toDateString(),
            'branch_id' => $this->branchMotera->id,
            'supplier_id' => $this->supplier->id,
            'total_qty' => 10,
            'total_amount' => 100000,
            'final_amount' => 100000,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi1->id,
            'item_id' => $item->id,
            'qty' => 10,
            'cost_price' => 10000,
            'effective_cost' => 10000,
            'sell_price' => 11000,
            'mrp' => 12000,
            'taxable_amount' => 100000,
            'total' => 100000,
        ]);

        ItemStock::updateOrCreate(
            ['item_id' => $item->id, 'branch_id' => $this->branchMotera->id],
            ['quantity' => 10, 'cost_price' => 10000, 'landing_cost' => 10000, 'sell_price' => 11000, 'mrp' => 12000]
        );

        // 2. Transfer out 5 qty to Satellite store at 10,000
        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-001',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchMotera->id,
            'to_branch_id' => $this->branchSatellite->id,
            'total_items' => 1,
            'total_qty' => 5,
            'status' => 'Dispatched',
        ]);

        $stItem = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty' => 5,
            'unit_cost' => 10000,
            'mrp' => 12000,
        ]);

        // Receive the transfer at Satellite
        $response = $this->actingAs($this->user)->post(route('inventory.stock-transfers.receive', $transfer), [
            'items' => [
                [
                    'id' => $stItem->id,
                    'received_qty' => 5,
                ],
            ],
            'remarks' => 'Received at Satellite',
        ]);
        $response->assertSessionHasNoErrors();

        // Verify Satellite ItemStock has 11,000 price
        $satStock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branchSatellite->id)->first();
        $this->assertNotNull($satStock);
        $this->assertEquals(11000, (float) $satStock->sell_price);

        // 3. Now Motera Stadium purchases the same item again from same supplier at cost 12,000, sell 13,500
        $responsePi = $this->actingAs($this->user)->post(route('purchase.purchase-invoices.store'), [
            'invoice_number' => 'PI-MOT-002',
            'supplier_inv_no' => 'SINV-002',
            'invoice_date' => now()->toDateString(),
            'branch_id' => $this->branchMotera->id,
            'supplier_id' => $this->supplier->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 5,
                    'free_qty' => 0,
                    'cost_price' => 12000,
                    'sell_price' => 13500,
                    'mrp' => 15000,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
            'total_qty' => 5,
            'total_amount' => 60000,
            'final_amount' => 60000,
            'supplier_inv_amount' => 60000,
        ]);
        $responsePi->assertSessionHasNoErrors();

        // 4. Verify Motera item stock is now 13,500
        $motStock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branchMotera->id)->first();
        $this->assertEquals(13500, (float) $motStock->sell_price);

        // 5. CRITICAL: Verify Satellite store item price is STILL 11,000!
        $satStockAfter = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branchSatellite->id)->first();
        $this->assertEquals(11000, (float) $satStockAfter->sell_price);

        // 6. Verify lookup-item endpoint for Satellite branch returns 11,000
        $lookupSat = $this->actingAs($this->user)->getJson(
            route('sales.sales-bills.lookup-item', ['item_id' => $item->id, 'branch_id' => $this->branchSatellite->id])
        );
        $lookupSat->assertOk();
        $this->assertEquals(11000, (float) $lookupSat->json('item.sell_price'));

        // 7. Verify item-list endpoint for Satellite branch returns 11,000
        $itemListSat = $this->actingAs($this->user)->getJson(
            route('sales.sales-bills.item-list', ['search' => 'Item A MultiBranch', 'branch_id' => $this->branchSatellite->id])
        );
        $itemListSat->assertOk();
        $items = $itemListSat->json('items');
        $this->assertNotEmpty($items);
        $this->assertEquals(11000, (float) $items[0]['sell_price']);

        // 8. Verify lookup-item endpoint for Motera branch returns 13,500
        $lookupMot = $this->actingAs($this->user)->getJson(
            route('sales.sales-bills.lookup-item', ['item_id' => $item->id, 'branch_id' => $this->branchMotera->id])
        );
        $lookupMot->assertOk();
        $this->assertEquals(13500, (float) $lookupMot->json('item.sell_price'));
    }
}
