<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\StockLedger;
use App\Models\TenderType;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesDeliveryNoteTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private User $cashier;
    private Branch $branch;
    private Customer $customer;
    private Item $item;
    private TenderType $cashTender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'SDN-TEST-001'],
            [
                'name' => 'SDN Test Pet Carrier',
                'cost_price' => 500,
                'sell_price' => 800,
                'mrp' => 850,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
                'allow_negative_stock' => false,
            ]
        );

        $this->customer = Customer::firstOrCreate(
            ['name' => 'SDN Test Customer'],
            ['phone' => '9898989898', 'state' => 'Gujarat', 'credit_limit' => 50000]
        );

        $this->cashTender = TenderType::firstOrCreate(['name' => 'Cash'], ['status' => true]);

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('Cashier');
    }

    private function setInitialStock(float $qty, float $cost = 500.0): void
    {
        $stockService = app(StockLedgerService::class);
        $current = (float) (ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity') ?? 0);
        $delta = $qty - $current;
        if (abs($delta) > 0.0001) {
            $stockService->post(
                itemId: $this->item->id,
                branchId: $this->branch->id,
                movementType: 'OPENING',
                qtyDelta: $delta,
                unitCost: $cost,
                referenceType: null,
                referenceId: null,
                documentDate: now()->toDateString(),
                userId: $this->manager->id,
            );
        }
    }

    public function test_user_can_view_delivery_notes_index_and_create_form(): void
    {
        $this->actingAs($this->manager)
            ->get(route('sales.delivery-notes.index'))
            ->assertOk()
            ->assertSee('Sales Delivery Notes');

        $this->actingAs($this->manager)
            ->get(route('sales.delivery-notes.create'))
            ->assertOk()
            ->assertSee('Create Delivery Note (Challan)');
    }

    public function test_manager_can_create_delivery_note_and_stock_is_deducted(): void
    {
        $this->setInitialStock(20, 500.0);

        $response = $this->actingAs($this->manager)->post(route('sales.delivery-notes.store'), [
            'delivery_date' => now()->toDateString(),
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'vehicle_no' => 'GJ-01-XX-9999',
            'transporter_name' => 'Express Logistics',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'ordered_qty' => 5,
                    'dispatched_qty' => 5,
                    'unit_price' => 800,
                    'mrp' => 850,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $sdn = SalesDeliveryNote::latest('id')->first();
        $this->assertNotNull($sdn);
        $response->assertRedirect(route('sales.delivery-notes.show', $sdn));

        $this->assertEquals('Dispatched', $sdn->status);
        $this->assertEquals(5, (float) $sdn->total_dispatched_qty);
        $this->assertEquals(4000, (float) $sdn->total_amount);

        // Verify physical stock was reduced from 20 to 15
        $stock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(15, (float) $stock->quantity);

        // Verify StockLedger row
        $ledgerRow = StockLedger::where('reference_type', SalesDeliveryNote::class)
            ->where('reference_id', $sdn->id)
            ->first();
        $this->assertNotNull($ledgerRow);
        $this->assertEquals('SALES_DELIVERY', $ledgerRow->movement_type);
        $this->assertEquals(5, (float) $ledgerRow->qty_out);
        $this->assertEquals(0, (float) $ledgerRow->qty_in);
        $this->assertEquals(500, (float) $ledgerRow->unit_cost);

        // Verify item recorded cost_at_dispatch
        $dnItem = $sdn->items()->first();
        $this->assertEquals(500, (float) $dnItem->cost_at_dispatch);
    }

    public function test_delivery_note_from_sales_order_updates_dispatched_qty_and_status(): void
    {
        $this->setInitialStock(30, 500.0);

        // Create Sales Order
        $so = SalesOrder::create([
            'order_number' => 'SO-TEST-901',
            'order_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'status' => 'Open',
            'total' => 8000,
        ]);
        $soItem = $so->items()->create([
            'item_id' => $this->item->id,
            'qty' => 10,
            'sell_price' => 800,
            'mrp' => 850,
            'net_amount' => 8000,
        ]);

        // Dispatch 4 units against SO
        $response = $this->actingAs($this->manager)->post(route('sales.delivery-notes.store'), [
            'delivery_date' => now()->toDateString(),
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id,
            'items' => [
                [
                    'sales_order_item_id' => $soItem->id,
                    'item_id' => $this->item->id,
                    'ordered_qty' => 10,
                    'dispatched_qty' => 4,
                    'unit_price' => 800,
                    'mrp' => 850,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        // SO item dispatched_qty updated
        $soItem->refresh();
        $this->assertEquals(4, (float) $soItem->dispatched_qty);
        $this->assertEquals(6, (float) $soItem->pending_qty);

        // SO status becomes Partially Fulfilled
        $so->refresh();
        $this->assertEquals('Partially Fulfilled', $so->status);
    }

    public function test_converting_delivery_note_to_sales_bill_does_not_duplicate_stock_deduction(): void
    {
        $this->setInitialStock(20, 500.0);

        // 1. Dispatch 6 units via delivery note
        $sdn = SalesDeliveryNote::create([
            'delivery_number' => 'SDN-TEST-101',
            'delivery_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Dispatched',
            'total_ordered_qty' => 6,
            'total_dispatched_qty' => 6,
            'total_amount' => 4800,
            'created_by_id' => $this->manager->id,
        ]);

        $ledgerRow = app(StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALES_DELIVERY',
            qtyDelta: -6,
            unitCost: null,
            referenceType: SalesDeliveryNote::class,
            referenceId: $sdn->id,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
        );

        $sdn->items()->create([
            'item_id' => $this->item->id,
            'ordered_qty' => 6,
            'dispatched_qty' => 6,
            'unit_price' => 800,
            'cost_at_dispatch' => $ledgerRow->unit_cost,
            'mrp' => 850,
        ]);

        // Stock is now 14
        $stock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(14, (float) $stock->quantity);

        // 2. Convert to Sales Bill
        $response = $this->actingAs($this->manager)->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_delivery_note_id' => $sdn->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'round_off' => 0,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 6,
                    'sell_price' => 800,
                    'mrp' => 850,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => $this->cashTender->id,
                    'amount' => 4800,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $bill = SalesBill::latest('id')->first();
        $this->assertNotNull($bill);

        // 3. Delivery Note status is updated to Invoiced
        $sdn->refresh();
        $this->assertEquals('Invoiced', $sdn->status);
        $this->assertEquals($bill->id, $sdn->sales_bill_id);

        // 4. CRITICAL: Stock quantity MUST STILL BE 14 (Zero duplicate deduction!)
        $stock->refresh();
        $this->assertEquals(14, (float) $stock->quantity);

        // StockLedger has NO SALE movement for SalesBill (because SDN handled physical stock)
        $billLedger = StockLedger::where('reference_type', SalesBill::class)
            ->where('reference_id', $bill->id)
            ->first();
        $this->assertNull($billLedger);

        // Bill items inherit cost_at_sale from SDN cost_at_dispatch (500)
        $billItem = $bill->items()->first();
        $this->assertEquals(500, (float) $billItem->cost_at_sale);
    }

    public function test_cannot_cancel_invoiced_delivery_note(): void
    {
        $bill = SalesBill::create([
            'bill_number' => 'BILL-SDN-INV',
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'total' => 1888,
            'status' => 'Posted',
        ]);

        $sdn = SalesDeliveryNote::create([
            'delivery_number' => 'SDN-TEST-INV',
            'delivery_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Invoiced',
            'total_ordered_qty' => 2,
            'total_dispatched_qty' => 2,
            'total_amount' => 1600,
            'sales_bill_id' => $bill->id,
        ]);

        $response = $this->actingAs($this->manager)->delete(route('sales.delivery-notes.destroy', $sdn), [
            'reason' => 'Test invalid cancellation',
        ]);

        $response->assertSessionHasErrors(['delivery_note']);
        $sdn->refresh();
        $this->assertEquals('Invoiced', $sdn->status);
    }

    public function test_can_cancel_unbilled_delivery_note_and_reverses_stock(): void
    {
        $this->setInitialStock(20, 500.0);

        // Create and dispatch 5 units
        $sdn = SalesDeliveryNote::create([
            'delivery_number' => 'SDN-TEST-CAN',
            'delivery_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Dispatched',
            'total_ordered_qty' => 5,
            'total_dispatched_qty' => 5,
            'total_amount' => 4000,
            'created_by_id' => $this->manager->id,
        ]);

        app(StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALES_DELIVERY',
            qtyDelta: -5,
            unitCost: null,
            referenceType: SalesDeliveryNote::class,
            referenceId: $sdn->id,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
        );

        $sdn->items()->create([
            'item_id' => $this->item->id,
            'ordered_qty' => 5,
            'dispatched_qty' => 5,
            'unit_price' => 800,
            'cost_at_dispatch' => 500,
        ]);

        // Stock decreased to 15
        $stock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(15, (float) $stock->quantity);

        // Cancel delivery note
        $response = $this->actingAs($this->manager)->delete(route('sales.delivery-notes.destroy', $sdn), [
            'reason' => 'Customer requested cancellation before delivery',
        ]);

        $response->assertSessionHasNoErrors();
        $sdn->refresh();
        $this->assertEquals('Cancelled', $sdn->status);
        $this->assertEquals('Customer requested cancellation before delivery', $sdn->cancellation_reason);

        // Stock restored back to 20
        $stock->refresh();
        $this->assertEquals(20, (float) $stock->quantity);
    }

    public function test_deleting_sales_bill_reverts_delivery_note_to_dispatched(): void
    {
        $this->setInitialStock(20, 500.0);

        $sdn = SalesDeliveryNote::create([
            'delivery_number' => 'SDN-TEST-REV',
            'delivery_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Invoiced',
            'total_ordered_qty' => 3,
            'total_dispatched_qty' => 3,
            'total_amount' => 2400,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'BILL-SDN-REV',
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_delivery_note_id' => $sdn->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'total' => 2832,
            'status' => 'Posted',
        ]);

        $sdn->update(['sales_bill_id' => $bill->id]);

        // Delete sales bill
        $response = $this->actingAs($this->manager)->delete(route('sales.sales-bills.destroy', $bill));
        $response->assertSessionHasNoErrors();

        // Delivery Note reverts to Dispatched with null sales_bill_id
        $sdn->refresh();
        $this->assertEquals('Dispatched', $sdn->status);
        $this->assertNull($sdn->sales_bill_id);
    }

    public function test_delivery_note_views_and_print_render_ok(): void
    {
        $sdn = SalesDeliveryNote::create([
            'delivery_number' => 'SDN-TEST-PRINT',
            'delivery_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Dispatched',
            'total_ordered_qty' => 2,
            'total_dispatched_qty' => 2,
            'total_amount' => 1600,
            'vehicle_no' => 'GJ-01-ZZ-1111',
            'transporter_name' => 'Fast Cargo',
            'lr_no' => 'LR-999',
            'lr_date' => now()->toDateString(),
        ]);

        $sdn->items()->create([
            'item_id' => $this->item->id,
            'ordered_qty' => 2,
            'dispatched_qty' => 2,
            'unit_price' => 800,
            'cost_at_dispatch' => 500,
            'mrp' => 850,
        ]);

        $this->actingAs($this->manager)
            ->get(route('sales.delivery-notes.show', $sdn))
            ->assertOk()
            ->assertSee('SDN-TEST-PRINT')
            ->assertSee('Fast Cargo');

        $this->actingAs($this->manager)
            ->get(route('sales.delivery-notes.print', $sdn))
            ->assertOk()
            ->assertSee('DELIVERY CHALLAN')
            ->assertSee('SDN-TEST-PRINT');
    }
}
