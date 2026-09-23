<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmartAnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Outlet', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_smart_analytics_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.smart-analytics'));

        $response->assertOk();
        $response->assertSee('Smart Item & Customer 360', false);
        $response->assertSee('Single Item 360', false);
        $response->assertSee('Single Customer 360', false);
    }

    public function test_single_item_analytics_calculates_sales_and_stock_accurately(): void
    {
        $itemA = Item::create([
            'name' => 'Parle-G 100g',
            'item_code' => 'PARLE-100',
            'sell_price' => 10.00,
            'cost_price' => 7.00,
            'mrp' => 10.00,
            'status' => true,
        ]);

        $itemB = Item::create([
            'name' => 'Good Day 100g',
            'item_code' => 'GOOD-100',
            'sell_price' => 20.00,
            'cost_price' => 15.00,
            'mrp' => 20.00,
            'status' => true,
        ]);

        // Stock for itemA
        ItemStock::create([
            'item_id' => $itemA->id,
            'branch_id' => $this->branch->id,
            'quantity' => 45.0,
            'sell_price' => 10.00,
        ]);

        $customer = Customer::create([
            'name' => 'Ramesh Gupta',
            'mobile' => '9876500001',
            'status' => true,
        ]);

        // Bill 1: 5 qty of ItemA
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-2026-0001',
            'bill_date' => now()->startOfMonth()->addDays(2),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Paid',
            'total_qty' => 5,
            'total' => 50.00,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill1->id,
            'item_id' => $itemA->id,
            'qty' => 5,
            'sell_price' => 10.00,
            'cost_at_sale' => 7.00,
            'disc_amount' => 0.00,
            'net_amount' => 50.00,
        ]);

        // Bill 2: 3 qty of ItemA + 2 qty of ItemB
        $bill2 = SalesBill::create([
            'bill_number' => 'SB-2026-0002',
            'bill_date' => now()->startOfMonth()->addDays(5),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Paid',
            'total_qty' => 5,
            'total' => 70.00,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill2->id,
            'item_id' => $itemA->id,
            'qty' => 3,
            'sell_price' => 10.00,
            'cost_at_sale' => 7.00,
            'disc_amount' => 2.00,
            'net_amount' => 28.00,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill2->id,
            'item_id' => $itemB->id,
            'qty' => 2,
            'sell_price' => 20.00,
            'cost_at_sale' => 15.00,
            'disc_amount' => 0.00,
            'net_amount' => 40.00,
        ]);

        // Query analytics for ItemA for this month
        $response = $this->actingAs($this->user)->getJson(route('reports.smart-analytics.item', [
            'item_id' => $itemA->id,
            'branch_id' => $this->branch->id,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('summary.total_qty', 8);
        $response->assertJsonPath('summary.total_net_amount', 78);
        $response->assertJsonPath('summary.bills_count', 2);
        $response->assertJsonPath('summary.current_stock', 45);

        // Verify bill breakdown contains both bills
        $bills = $response->json('bills');
        $this->assertCount(2, $bills);
        $this->assertSame('SB-2026-0002', $bills[0]['bill_number']);
        $this->assertSame('SB-2026-0001', $bills[1]['bill_number']);
    }

    public function test_single_customer_analytics_aggregates_spend_and_recent_bills(): void
    {
        $customer = Customer::create([
            'name' => 'Sunil Kumar',
            'mobile' => '9876543210',
            'credit_balance' => 250.00,
            'status' => true,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-2026-0010',
            'bill_date' => now()->startOfMonth()->addDays(3),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Paid',
            'total_qty' => 4,
            'total' => 200.00,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.smart-analytics.customer', [
            'customer_id' => $customer->id,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('customer.name', 'Sunil Kumar');
        $response->assertJsonPath('summary.total_bills', 1);
        $response->assertJsonPath('summary.total_spend', 200);
        $response->assertJsonPath('customer.credit_balance', 250);
    }

    public function test_ranking_analytics_returns_top_selling_items(): void
    {
        $customer = Customer::create(['name' => 'Walk-in Customer', 'mobile' => '9000000000', 'status' => true]);
        $item1 = Item::create(['name' => 'Fast Item', 'item_code' => 'FAST-1', 'sell_price' => 50.00, 'cost_price' => 30.00, 'mrp' => 50.00, 'status' => true]);
        $item2 = Item::create(['name' => 'Slow Item', 'item_code' => 'SLOW-1', 'sell_price' => 50.00, 'cost_price' => 30.00, 'mrp' => 50.00, 'status' => true]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-2026-0020',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Paid',
            'total' => 500.00,
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item1->id,
            'qty' => 10,
            'sell_price' => 50.00,
            'cost_at_sale' => 30.00,
            'net_amount' => 500.00,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.smart-analytics.ranking', [
            'type' => 'top_selling',
            'metric' => 'qty',
            'limit' => 5,
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertSame('Fast Item', $items[0]['name']);
        $this->assertEquals(10, $items[0]['total_sold_qty']);
    }

    public function test_export_item_csv_streams_file_download(): void
    {
        $customer = Customer::create(['name' => 'Export Customer', 'mobile' => '9000000001', 'status' => true]);
        $item = Item::create(['name' => 'Export Item', 'item_code' => 'EXP-01', 'sell_price' => 50.00, 'cost_price' => 30.00, 'mrp' => 50.00, 'status' => true]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-2026-EXP',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'Paid',
            'total' => 100.00,
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 2,
            'sell_price' => 50.00,
            'cost_at_sale' => 30.00,
            'disc_amount' => 0.00,
            'net_amount' => 100.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.smart-analytics.export-item', [
            'item_id' => $item->id,
        ]));

        $response->assertOk();
        $contentType = $response->headers->get('content-type') ?? '';
        $this->assertTrue(str_contains($contentType, 'application/vnd.ms-excel') || str_contains($contentType, 'text/csv'));
        $this->assertStringContainsString('EXP-01', $response->headers->get('content-disposition'));
    }
}
