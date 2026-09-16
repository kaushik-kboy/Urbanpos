<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\StockLedger;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesQuotationOrderTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%']);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'QO-TEST-001'],
            [
                'name' => 'Quotation Order Test Product',
                'cost_price' => 100,
                'sell_price' => 200,
                'mrp' => 220,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
            ]
        );

        $this->customer = Customer::firstOrCreate(
            ['name' => 'Retail Quote Customer'],
            ['phone' => '9123456780', 'state' => 'Maharashtra', 'credit_limit' => 50000]
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        // Seed current financial year
        FinancialYear::firstOrCreate(
            ['start_date' => '2026-04-01'],
            ['end_date' => '2027-03-31', 'name' => '2026-2027', 'is_locked' => false]
        );
    }

    public function test_manager_can_create_sales_quotation_without_mutating_stock_or_ledger(): void
    {
        $initialStockCount = StockLedger::count();

        $response = $this->actingAs($this->manager)->post(route('sales.sales-quotations.store'), [
            'quotation_date' => '2026-09-16',
            'valid_until' => '2026-09-30',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'round_off' => 0,
            'status' => 'Draft',
            'remarks' => 'Test Quote',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'sell_price' => 200,
                    'mrp' => 220,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ]);

        $this->assertDatabaseHas('sales_quotations', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Draft',
        ]);

        $quotation = SalesQuotation::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($quotation);
        $this->assertEquals(1180.00, (float) $quotation->total); // 1000 + 18% GST = 1180

        // ZERO stock ledger rows created
        $this->assertEquals($initialStockCount, StockLedger::count());
        $response->assertRedirect(route('sales.sales-quotations.show', $quotation));
    }

    public function test_manager_can_create_sales_order_and_link_from_quotation(): void
    {
        $quotation = SalesQuotation::create([
            'quotation_number' => 'SQ999001',
            'quotation_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 1180.00,
            'status' => 'Draft',
        ]);

        $createOrderView = $this->actingAs($this->manager)->get(route('sales.sales-orders.create', ['from_quotation' => $quotation->id]));
        $createOrderView->assertOk();
        $createOrderView->assertSee('SQ999001');

        $response = $this->actingAs($this->manager)->post(route('sales.sales-orders.store'), [
            'order_date' => '2026-09-16',
            'expected_delivery_date' => '2026-09-20',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'advance_amount' => 500,
            'round_off' => 0,
            'status' => 'Open',
            'from_quotation_id' => $quotation->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'sell_price' => 200,
                    'mrp' => 220,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ]);

        $order = SalesOrder::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(500.00, (float) $order->advance_amount);
        $this->assertEquals(1180.00, (float) $order->total);

        // Quotation marked as Accepted
        $this->assertEquals('Accepted', $quotation->fresh()->status);
        $response->assertRedirect(route('sales.sales-orders.show', $order));
    }

    public function test_1_click_convert_quotation_to_sales_bill(): void
    {
        // Setup initial stock for selling
        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch->id],
            ['quantity' => 20]
        );

        $quotation = SalesQuotation::create([
            'quotation_number' => 'SQ999002',
            'quotation_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 1180.00,
            'status' => 'Sent',
        ]);
        $quotation->items()->create([
            'item_id' => $this->item->id,
            'qty' => 3,
            'sell_price' => 200,
            'mrp' => 220,
            'gst_percent' => 18,
            'gst_tax_amount' => 108,
            'cgst_amount' => 54,
            'sgst_amount' => 54,
            'net_amount' => 708,
        ]);

        // GET create bill from quotation pre-populates
        $billView = $this->actingAs($this->manager)->get(route('sales.sales-bills.create', ['from_quotation' => $quotation->id]));
        $billView->assertOk();
        $billView->assertSee('SQ999002');

        // Post the bill with from_quotation_id
        $billResponse = $this->actingAs($this->manager)->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'round_off' => 0,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 3,
                    'sell_price' => 200,
                    'mrp' => 220,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
            'from_quotation_id' => $quotation->id,
        ]);

        $billResponse->assertRedirect(route('sales.sales-bills.index'));

        $salesBill = SalesBill::latest()->first();
        $this->assertNotNull($salesBill);

        // Verify quotation status updated to Converted and linked
        $quotation->refresh();
        $this->assertEquals('Converted', $quotation->status);
        $this->assertEquals($salesBill->id, $quotation->converted_sales_bill_id);

        // Verify stock was reduced by 3
        $remainingStock = ItemStock::where('item_id', $this->item->id)
            ->where('branch_id', $this->branch->id)
            ->value('quantity');
        $this->assertEquals(17.0, (float) $remainingStock);
    }

    public function test_1_click_convert_sales_order_to_sales_bill(): void
    {
        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch->id],
            ['quantity' => 15]
        );

        $order = SalesOrder::create([
            'order_number' => 'SO999002',
            'order_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'advance_amount' => 200,
            'total' => 472.00,
            'status' => 'Open',
        ]);
        $order->items()->create([
            'item_id' => $this->item->id,
            'qty' => 2,
            'sell_price' => 200,
            'mrp' => 220,
            'gst_percent' => 18,
            'gst_tax_amount' => 72,
            'cgst_amount' => 36,
            'sgst_amount' => 36,
            'net_amount' => 472,
        ]);

        $billView = $this->actingAs($this->manager)->get(route('sales.sales-bills.create', ['from_order' => $order->id]));
        $billView->assertOk();
        $billView->assertSee('SO999002');

        $billResponse = $this->actingAs($this->manager)->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'round_off' => 0,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'sell_price' => 200,
                    'mrp' => 220,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
            'from_order_id' => $order->id,
        ]);

        $billResponse->assertRedirect(route('sales.sales-bills.index'));

        $salesBill = SalesBill::latest()->first();
        $this->assertNotNull($salesBill);

        $order->refresh();
        $this->assertEquals('Converted', $order->status);
        $this->assertEquals($salesBill->id, $order->converted_sales_bill_id);

        $remainingStock = ItemStock::where('item_id', $this->item->id)
            ->where('branch_id', $this->branch->id)
            ->value('quantity');
        $this->assertEquals(13.0, (float) $remainingStock);
    }

    public function test_quotation_and_order_views_render(): void
    {
        $quotation = SalesQuotation::create([
            'quotation_number' => 'SQ999004',
            'quotation_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 500.00,
            'status' => 'Draft',
        ]);

        $order = SalesOrder::create([
            'order_number' => 'SO999004',
            'order_date' => '2026-09-16',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 500.00,
            'status' => 'Open',
        ]);

        $this->actingAs($this->manager)->get(route('sales.sales-quotations.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('sales.sales-quotations.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('sales.sales-quotations.show', $quotation))->assertOk();

        $this->actingAs($this->manager)->get(route('sales.sales-orders.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('sales.sales-orders.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('sales.sales-orders.show', $order))->assertOk();
    }
}

