<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\Supplier;
use App\Models\TillSession;
use App\Models\User;
use App\Models\UserSavedReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analytics Builder (custom report studio) and Smart Analytics (item / customer 360) endpoints,
 * asserting the computed aggregates, filters, drill-downs and exports.
 */
class ReportsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b1;
    private Branch $b2;
    private User $owner;
    private Item $itemA;
    private Item $itemB;
    private Customer $custX;
    private Customer $custY;
    private Supplier $sup1;
    private Supplier $sup2;

    private const RANGE = ['date_preset' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->owner = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'olivia@example.test']);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);

        $this->b1 = Branch::create(['name' => 'Main Store', 'state' => 'Gujarat', 'status' => true]);
        $this->b2 = Branch::create(['name' => 'Second Store', 'state' => 'Gujarat', 'status' => true]);
    }

    /** Seeds the shared sales/purchase data set described in each test's assertions. */
    private function seedSales(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $food = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $this->sup1 = Supplier::create(['name' => 'Alpha Agro', 'mobile' => '9000000001', 'city' => 'Rajkot', 'status' => true]);
        $this->sup2 = Supplier::create(['name' => 'Beta Bulk', 'city' => 'Pune', 'status' => true]);
        $this->itemA = Item::create(['name' => 'Alpha Kibble', 'item_code' => 'A1', 'ean_upc_code' => '8900000000011', 'brand_id' => $brand->id, 'category_value_id' => $food->id, 'supplier_id' => $this->sup1->id, 'cost_price' => 60, 'sell_price' => 120, 'mrp' => 130, 'status' => true]);
        $this->itemB = Item::create(['name' => 'Bravo Toy', 'item_code' => 'B1', 'cost_price' => 30, 'sell_price' => 118, 'mrp' => 120, 'status' => true]);
        $this->custX = Customer::create(['name' => 'Xena Buyer', 'mobile' => '9111111111', 'phone' => '0791111111', 'status' => true]);
        $this->custY = Customer::create(['name' => 'Yash Buyer', 'phone' => '0792222222', 'status' => true]);

        $reg = Register::create(['branch_id' => $this->b1->id, 'name' => 'Counter 1', 'status' => 'Active']);
        $till = TillSession::create(['register_id' => $reg->id, 'branch_id' => $this->b1->id, 'user_id' => $this->owner->id, 'opening_cash' => 100, 'opened_at' => '2026-09-05 09:00:00', 'status' => 'Open']);

        $bill = fn ($no, $date, $b, $c, $total, $pay, $extra = []) => SalesBill::create($extra + [
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $b->id, 'sales_type' => 'Local',
            'total' => $total, 'payment_type' => $pay, 'status' => 'Posted', 'total_qty' => 1,
        ]);
        $line = fn ($b, $item, $qty, $net, $disc, $cost) => SalesBillItem::create(['sales_bill_id' => $b->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => $net / $qty, 'disc_amount' => $disc, 'net_amount' => $net, 'cost_at_sale' => $cost]);

        $s1 = $bill('S-1', '2026-09-05', $this->b1, $this->custX, 590, 'Cash', ['disc_amount' => 10, 'total_qty' => 3, 'till_session_id' => $till->id]);
        $line($s1, $this->itemA, 2, 236, 5, 50);
        $line($s1, $this->itemB, 1, 118, 0, null);       // no cost_at_sale -> falls back to items.cost_price (30)
        $s2 = $bill('S-2', '2026-09-10', $this->b2, $this->custY, 300, 'UPI', ['disc_amount' => 0, 'total_qty' => 1]);
        $line($s2, $this->itemA, 1, 100, 0, 55);
        $s3 = $bill('S-3', '2026-09-10', $this->b1, $this->custX, 900, 'Cash', ['status' => 'Cancelled', 'total_qty' => 9]);
        $line($s3, $this->itemA, 9, 900, 0, 1);
        $s4 = $bill('S-4', '2026-08-01', $this->b1, $this->custX, 500, 'Cash');
        $line($s4, $this->itemB, 1, 500, 0, 30);
    }

    private function seedPurchases(): void
    {
        $inv = fn ($no, $date, $sup, $b, $total, $extra = []) => PurchaseInvoice::create($extra + ['invoice_number' => $no, 'invoice_date' => $date, 'supplier_id' => $sup->id, 'branch_id' => $b->id, 'total' => $total, 'status' => 'Posted']);
        $line = fn ($i, $qty, $cost, $net) => PurchaseInvoiceItem::create(['purchase_invoice_id' => $i->id, 'item_id' => $this->itemA->id, 'qty' => $qty, 'cost_price' => $cost, 'sell_price' => 120, 'mrp' => 130, 'net_amount' => $net]);
        $p1 = $inv('P-1', '2026-09-06', $this->sup1, $this->b1, 1000, ['disc_amount' => 20, 'total_qty' => 10]);
        $line($p1, 10, 50, 500);
        $p2 = $inv('P-2', '2026-09-07', $this->sup2, $this->b2, 400, ['total_qty' => 4]);
        $line($p2, 4, 55, 220);
        $p4 = $inv('P-4', '2026-09-20', $this->sup1, $this->b1, 300, ['total_qty' => 5]);
        $line($p4, 5, 60, 300);
        $p3 = $inv('P-3', '2026-09-08', $this->sup1, $this->b1, 5000, ['status' => 'Cancelled', 'total_qty' => 50]);
        $line($p3, 50, 1, 5000);
    }

    private function gen(array $q = [])
    {
        return $this->postJson(route('reports.analytics-builder.generate'), array_merge(self::RANGE, $q));
    }

    private function rowsBy($json, string $key = 'group_name'): array
    {
        return collect($json['rows'])->keyBy($key)->all();
    }

    // ================================================================ Analytics Builder: generate

    public function test_group_by_item_aggregates_sales_margin_and_distinct_bills(): void
    {
        $this->seedSales();
        $j = $this->gen(['group_by' => 'item'])->assertOk()->json();

        $rows = $this->rowsBy($j);
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], array_keys($rows));   // default sort: sales desc
        $a = $rows['Alpha Kibble'];
        $this->assertEquals([3.0, 336.0, 5.0, 181.0, 2], [(float) $a['total_qty'], (float) $a['total_sales'], (float) $a['total_disc'], (float) $a['total_profit'], (int) $a['bill_count']]);
        $this->assertSame('₹ 181.00 (53.9%)', $a['formatted_profit']);
        $this->assertSame('₹ 168.00', $a['formatted_aov']);
        $this->assertSame('A1', $a['group_subtext']);
        $b = $rows['Bravo Toy'];
        $this->assertEquals([118.0, 88.0], [(float) $b['total_sales'], (float) $b['total_profit']]); // cost falls back to items.cost_price

        $t = $j['totals'];
        $this->assertSame('4.00', $t['total_qty']);
        $this->assertSame('₹ 454.00', $t['total_sales']);
        $this->assertSame('₹ 269.00 (59.3%)', $t['total_profit']);
        $this->assertSame(2, $t['total_bills']);              // S-1 counted once although it holds two items
        $this->assertSame('₹ 227.00', $t['aov']);            // 454 / 2 bills
        $this->assertSame(2, $t['count']);
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], $j['chart']['labels']);
        $this->assertEquals([336.0, 118.0], $j['chart']['values']);
        $this->assertSame('Total Sales (₹) by Item Name', $j['chart']['label']);
        $this->assertSame(['group_name', 'group_subtext', 'formatted_qty', 'formatted_sales', 'formatted_profit', 'bill_count'], array_column($j['columns'], 'key'));
    }

    public function test_item_filter_metrics_sorting_limit_and_date_presets(): void
    {
        $this->seedSales();

        $j = $this->gen(['group_by' => 'item', 'item_id' => $this->itemB->id, 'metrics' => ['qty', 'aov', 'discount', 'last_date']])->json();
        $this->assertCount(1, $j['rows']);
        $this->assertSame(['group_name', 'group_subtext', 'formatted_qty', 'formatted_disc', 'formatted_aov', 'formatted_last_date'], array_column($j['columns'], 'key'));
        $this->assertSame('05-Sep-2026 00:00', $j['rows'][0]['formatted_last_date']);
        $this->assertSame('1', (string) $j['totals']['total_bills']);

        $j = $this->gen(['group_by' => 'item', 'sort_by' => 'group_name', 'sort_dir' => 'asc', 'limit' => 1])->json();
        $this->assertSame(['Alpha Kibble'], array_column($j['rows'], 'group_name'));
        $j = $this->gen(['group_by' => 'item', 'sort_by' => 'group_name', 'sort_dir' => 'desc'])->json();
        $this->assertSame(['Bravo Toy', 'Alpha Kibble'], array_column($j['rows'], 'group_name'));
        $j = $this->gen(['group_by' => 'item', 'sort_by' => 'DROP TABLE', 'sort_dir' => 'sideways'])->json();
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], array_column($j['rows'], 'group_name'), 'unknown sort column falls back to total_sales desc');

        $j = $this->gen(['group_by' => 'item', 'branch_id' => $this->b2->id])->json();
        $this->assertSame(['Alpha Kibble'], array_column($j['rows'], 'group_name'));
        $this->assertEquals(100.0, (float) $j['rows'][0]['total_sales']);
        $this->assertSame(1, $j['totals']['total_bills']);

        // all_time pulls in the August bill; the default preset (this month) sees none of the seeded data
        $j = $this->postJson(route('reports.analytics-builder.generate'), ['group_by' => 'item', 'date_preset' => 'all_time'])->json();
        $this->assertSame('₹ 954.00', $j['totals']['total_sales']);
        $this->assertNull($j['date_from']);
        $this->assertSame(3, $j['totals']['total_bills']);
        // default preset is month-to-date
        $j = $this->postJson(route('reports.analytics-builder.generate'), ['group_by' => 'item'])->json();
        $this->assertStringStartsWith(now()->startOfMonth()->format('Y-m-d'), $j['date_from']);
        $this->assertStringStartsWith(now()->format('Y-m-d'), $j['date_to']);
    }

    public function test_relative_date_presets_pick_the_right_window(): void
    {
        $this->seedSales();
        $mk = function (string $no, Carbon $when, float $total) {
            $b = SalesBill::create(['bill_number' => $no, 'bill_date' => $when->toDateString(), 'customer_id' => $this->custX->id, 'branch_id' => $this->b1->id, 'sales_type' => 'Local', 'total' => $total, 'status' => 'Posted', 'payment_type' => 'Voucher']);
        };
        $mk('PR-TODAY', Carbon::today(), 11);
        $mk('PR-YEST', Carbon::yesterday(), 22);
        $mk('PR-LASTM', Carbon::now()->subMonth()->startOfMonth(), 44);

        // Only the 'Voucher' payment-mode rows come from this test's relative-date bills.
        $voucher = function ($preset) {
            $rows = collect($this->postJson(route('reports.analytics-builder.generate'), ['group_by' => 'payment_mode', 'date_preset' => $preset])->json('rows'));
            return (float) ($rows->firstWhere('group_name', 'Voucher')['total_sales'] ?? 0);
        };
        $this->assertSame(11.0, $voucher('today'));
        $this->assertSame(22.0, $voucher('yesterday'));
        $this->assertSame(44.0, $voucher('last_month'));
        $this->assertSame((float) (11 + (Carbon::yesterday()->gte(Carbon::now()->startOfWeek()) ? 22 : 0)), $voucher('this_week'));
        // this_month / default / custom-without-dates are all month-to-date
        $expectedMonth = (float) (11 + (Carbon::yesterday()->month === Carbon::today()->month ? 22 : 0));
        $this->assertSame($expectedMonth, $voucher('this_month'));
        $this->assertSame($expectedMonth, $voucher('custom'));
        $this->assertSame($expectedMonth, $voucher('something-unknown'));
        $this->assertSame(77.0, $voucher('all_time'));
    }

    public function test_group_by_customer_category_brand_cashier_payment_mode_and_date(): void
    {
        $this->seedSales();

        $j = $this->gen(['group_by' => 'customer'])->json();
        $r = $this->rowsBy($j);
        $this->assertSame(['Xena Buyer', 'Yash Buyer'], array_keys($r));
        $this->assertEquals([1, 590.0, 10.0, 3.0], [(int) $r['Xena Buyer']['bill_count'], (float) $r['Xena Buyer']['total_sales'], (float) $r['Xena Buyer']['total_disc'], (float) $r['Xena Buyer']['total_qty']]);
        $this->assertSame('0791111111', $r['Xena Buyer']['group_subtext']);
        $this->assertSame('₹ 890.00', $j['totals']['total_sales']);
        $this->assertSame(2, $j['totals']['total_bills']);
        $this->assertSame(['Yash Buyer'], array_column($this->gen(['group_by' => 'customer', 'customer_id' => $this->custY->id])->json('rows'), 'group_name'));
        $this->assertSame(['Yash Buyer', 'Xena Buyer'], array_column($this->gen(['group_by' => 'customer', 'item_id' => $this->itemA->id, 'sort_by' => 'total_sales', 'sort_dir' => 'asc'])->json('rows'), 'group_name'));
        $this->assertSame(['Xena Buyer'], array_column($this->gen(['group_by' => 'customer', 'item_id' => $this->itemB->id])->json('rows'), 'group_name'));

        $r = $this->rowsBy($this->gen(['group_by' => 'category'])->json());
        $this->assertEquals([3.0, 336.0], [(float) $r['Food']['total_qty'], (float) $r['Food']['total_sales']]);
        $this->assertEquals([118.0, 88.0], [(float) $r['Uncategorized']['total_sales'], (float) $r['Uncategorized']['total_profit']]);

        $r = $this->rowsBy($this->gen(['group_by' => 'brand'])->json());
        $this->assertEquals(336.0, (float) $r['Acme']['total_sales']);
        $this->assertEquals(118.0, (float) $r['No Brand']['total_sales']);

        $r = $this->rowsBy($this->gen(['group_by' => 'cashier'])->json());
        $this->assertEquals(590.0, (float) $r['Olivia Owner']['total_sales']);
        $this->assertSame('olivia@example.test', $r['Olivia Owner']['group_subtext']);
        $this->assertEquals(300.0, (float) $r['Admin / POS Direct']['total_sales']);

        $r = $this->rowsBy($this->gen(['group_by' => 'payment_mode'])->json());
        $this->assertEquals([590.0, 300.0], [(float) $r['Cash']['total_sales'], (float) $r['UPI']['total_sales']]); // cancelled 900 excluded

        $r = $this->rowsBy($this->gen(['group_by' => 'date'])->json());
        $this->assertEquals([590.0, 300.0], [(float) $r['2026-09-05']['total_sales'], (float) $r['2026-09-10']['total_sales']]);
    }

    public function test_group_by_supplier_and_item_supplier_sourcing(): void
    {
        $this->seedSales();
        $this->seedPurchases();

        $j = $this->gen(['group_by' => 'supplier', 'metrics' => ['qty', 'sales_value', 'discount', 'bill_count', 'aov', 'last_date']])->json();
        $r = $this->rowsBy($j);
        $this->assertSame(['Alpha Agro', 'Beta Bulk'], array_keys($r));
        $this->assertEquals([2, 1300.0, 15.0, 20.0], [(int) $r['Alpha Agro']['bill_count'], (float) $r['Alpha Agro']['total_sales'], (float) $r['Alpha Agro']['total_qty'], (float) $r['Alpha Agro']['total_disc']]); // cancelled P-3 excluded
        $this->assertSame('9000000001', $r['Alpha Agro']['group_subtext']);
        $this->assertSame('Pune', $r['Beta Bulk']['group_subtext']);
        $this->assertSame('₹ 650.00', $r['Alpha Agro']['formatted_aov']);
        $this->assertSame('20-Sep-2026 00:00', $r['Alpha Agro']['formatted_last_date']);
        $this->assertSame(['group_name', 'group_subtext', 'formatted_qty', 'formatted_sales', 'formatted_disc', 'bill_count', 'formatted_aov', 'formatted_last_date'], array_column($j['columns'], 'key'));
        $this->assertSame('Total Purchase Value (₹)', $j['columns'][3]['label']);
        $this->assertSame('₹ 1,700.00', $j['totals']['total_sales']);

        $this->assertSame(['Beta Bulk'], array_column($this->gen(['group_by' => 'supplier', 'supplier_id' => $this->sup2->id])->json('rows'), 'group_name'));
        $this->assertSame(['Beta Bulk'], array_column($this->gen(['group_by' => 'supplier', 'branch_id' => $this->b2->id])->json('rows'), 'group_name'));
        $this->assertCount(2, $this->gen(['group_by' => 'supplier', 'item_id' => $this->itemA->id])->json('rows'));
        $this->assertCount(0, $this->gen(['group_by' => 'supplier', 'item_id' => $this->itemB->id])->json('rows'));

        // item <-> supplier sourcing
        $j = $this->gen(['group_by' => 'item_supplier'])->json();
        $this->assertSame(2, $j['totals']['count']);
        $rows = collect($j['rows'])->keyBy('supplier_name');
        $one = $rows['Alpha Agro'];
        $this->assertEquals([15.0, 800.0, 2, 60.0, 55.0, 50.0, 60.0], [(float) $one['total_qty'], (float) $one['total_amount'], (int) $one['invoice_count'], (float) $one['last_cost_price'], (float) $one['avg_cost_price'], (float) $one['min_cost_price'], (float) $one['max_cost_price']]);
        $this->assertTrue($one['is_primary_supplier']);
        $this->assertFalse($rows['Beta Bulk']['is_primary_supplier']);
        $this->assertSame(['₹ 60.00', '₹ 800.00', '20-Sep-2026', '15.00'], [$one['formatted_last_cost'], $one['formatted_amount'], $one['formatted_last_date'], $one['formatted_qty']]);
        $this->assertSame('19.00', $j['totals']['total_qty']);
        $this->assertSame('₹ 1,020.00', $j['totals']['total_amount']);
        $this->assertSame(3, $j['totals']['total_invoices']);
        $this->assertSame(['Alpha Kibble (Alpha Agro)', 'Alpha Kibble (Beta Bulk)'], $j['chart']['labels']);

        $j = $this->gen(['group_by' => 'item_supplier', 'sort_by' => 'total_qty', 'sort_dir' => 'asc', 'limit' => 1])->json();
        $this->assertSame(['Beta Bulk'], array_column($j['rows'], 'supplier_name'));
        $this->assertSame(['Alpha Agro'], array_column($this->gen(['group_by' => 'item_supplier', 'supplier_id' => $this->sup1->id, 'item_id' => $this->itemA->id])->json('rows'), 'supplier_name'));
        $this->assertSame(['Beta Bulk'], array_column($this->gen(['group_by' => 'item_supplier', 'branch_id' => $this->b2->id])->json('rows'), 'supplier_name'));
        $this->assertCount(0, $this->gen(['group_by' => 'item_supplier', 'item_id' => $this->itemB->id])->json('rows'));
    }

    // ================================================================ Analytics Builder: export, drilldown, search, saved

    public function test_export_streams_an_excel_table_with_labels_and_rows(): void
    {
        $this->seedSales();
        $this->seedPurchases();

        $r = $this->get(route('reports.analytics-builder.export', self::RANGE + ['group_by' => 'item', 'metrics' => ['qty', 'sales_value']]))->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('Custom_Report_item_', $r->headers->get('Content-Disposition'));
        $html = $r->streamedContent();
        $this->assertStringContainsString('<th>Item Name</th><th>Item Code</th><th>Quantity Sold</th><th>Total Sales Value (₹)</th></tr>', $html);
        $this->assertStringContainsString('<td>Alpha Kibble</td><td>A1</td><td>3.00</td><td>₹ 336.00</td>', $html);
        $this->assertStringContainsString('<td>Bravo Toy</td><td>B1</td><td>1.00</td><td>₹ 118.00</td>', $html);
        $this->assertStringNotContainsString('₹ 900.00', $html);

        $html = $this->get(route('reports.analytics-builder.export', self::RANGE + ['group_by' => 'item_supplier', 'supplier_id' => $this->sup2->id, 'sort_dir' => 'asc']))->streamedContent();
        $this->assertStringContainsString('<td>A1</td><td>Alpha Kibble</td><td>Beta Bulk</td><td></td><td>4.00</td><td>₹ 55.00</td><td>₹ 220.00</td><td>1</td>', $html);
        $this->assertStringNotContainsString('Alpha Agro', $html);
    }

    public function test_drilldown_lists_underlying_documents_for_each_group(): void
    {
        $this->seedSales();
        $this->seedPurchases();
        $dd = fn (array $q) => $this->getJson(route('reports.analytics-builder.drilldown', array_merge(self::RANGE, $q)))->assertOk()->json();

        $j = $dd(['group_by' => 'item', 'group_id' => $this->itemA->id]);
        $this->assertSame('Sales Invoices for: Alpha Kibble', $j['title']);
        $this->assertSame(['S-2', 'S-1'], array_column($j['records'], 'bill_number'));   // cancelled S-3 excluded, newest first
        $this->assertSame(['total_qty' => '3.00', 'total_amount' => '₹ 336.00', 'count' => 2], $j['summary']);
        $this->assertSame('Yash Buyer', $j['records'][0]['customer_name']);
        $this->assertStringContainsString('/sales-bills/', $j['records'][0]['view_url']);
        $this->assertSame(['S-2'], array_column($dd(['group_by' => 'single_item', 'id' => $this->itemA->id, 'branch_id' => $this->b2->id])['records'], 'bill_number'));

        $j = $dd(['group_by' => 'item_supplier', 'group_id' => $this->itemA->id, 'sub_id' => $this->sup1->id]);
        $this->assertSame('Purchase Inwards for: Alpha Kibble ⇄ Alpha Agro', $j['title']);
        $this->assertSame(['P-4', 'P-1'], array_column($j['records'], 'invoice_number'));
        $this->assertSame(['total_qty' => '15.00', 'total_amount' => '₹ 800.00', 'count' => 2], $j['summary']);
        $this->assertSame(['P-1'], array_column($dd(['group_by' => 'item_supplier', 'group_id' => $this->itemA->id, 'sub_id' => $this->sup1->id, 'date_to' => '2026-09-10'])['records'], 'invoice_number'));
        $this->assertSame([], $dd(['group_by' => 'item_supplier', 'group_id' => $this->itemA->id, 'sub_id' => $this->sup1->id, 'branch_id' => $this->b2->id])['records']);

        $j = $dd(['group_by' => 'supplier', 'group_id' => $this->sup1->id]);
        $this->assertSame('Purchase Invoices for: Alpha Agro', $j['title']);
        $this->assertSame(['P-4', 'P-1'], array_column($j['records'], 'invoice_number'));
        $this->assertSame(['total_qty' => '15.00', 'total_amount' => '₹ 1,300.00', 'count' => 2], $j['summary']);
        $this->assertSame(['P-4', 'P-1'], array_column($dd(['group_by' => 'supplier', 'group_id' => $this->sup1->id, 'item_id' => $this->itemA->id])['records'], 'invoice_number'));
        $this->assertSame([], $dd(['group_by' => 'supplier', 'group_id' => $this->sup1->id, 'item_id' => $this->itemB->id])['records']);
        $this->assertSame([], $dd(['group_by' => 'supplier', 'group_id' => $this->sup1->id, 'branch_id' => $this->b2->id])['records']);

        $j = $dd(['group_by' => 'customer', 'group_id' => $this->custX->id]);
        $this->assertSame('Sales Bills for: Xena Buyer', $j['title']);
        $this->assertSame(['S-1'], array_column($j['records'], 'bill_number'));
        $this->assertSame('₹ 590.00', $j['summary']['total_amount']);
        $this->assertSame([], $dd(['group_by' => 'customer', 'group_id' => $this->custX->id, 'branch_id' => $this->b2->id])['records']);

        $j = $dd(['group_by' => 'payment_mode', 'group_id' => 'Cash']);
        $this->assertSame('Bills Paid via: Cash', $j['title']);
        $this->assertSame(['S-1'], array_column($j['records'], 'bill_number'));
        $this->assertSame([], $dd(['group_by' => 'payment_mode', 'group_id' => 'Cash', 'branch_id' => $this->b2->id])['records']);

        $j = $dd(['group_by' => 'category', 'group_id' => 1]);   // not drillable -> empty shell
        $this->assertSame('Transaction Breakdown', $j['title']);
        $this->assertSame([], $j['records']);
    }

    public function test_select2_search_endpoints(): void
    {
        $this->seedSales();
        $texts = fn ($route, $q) => collect($this->getJson(route($route, $q))->assertOk()->json('results'))->pluck('text')->all();

        $this->assertSame(['[A1] Alpha Kibble (₹120.00)'], $texts('reports.analytics-builder.search-items', ['q' => 'kibble']));
        $this->assertSame(['[B1] Bravo Toy (₹118.00)'], $texts('reports.analytics-builder.search-items', ['term' => 'B1']));
        $this->assertSame(['[A1] Alpha Kibble (₹120.00)'], $texts('reports.analytics-builder.search-items', ['q' => '8900000000011']));
        $this->assertCount(2, $texts('reports.analytics-builder.search-items', []));

        $this->assertSame(['Alpha Agro - Rajkot (9000000001)'], $texts('reports.analytics-builder.search-suppliers', ['q' => 'rajkot']));
        $this->assertSame(['Beta Bulk - Pune'], $texts('reports.analytics-builder.search-suppliers', ['term' => 'Beta']));
        $this->assertCount(2, $texts('reports.analytics-builder.search-suppliers', []));

        $this->assertSame(['Xena Buyer (9111111111)'], $texts('reports.analytics-builder.search-customers', ['q' => '9111']));
        $this->assertSame(['Yash Buyer (0792222222)'], $texts('reports.analytics-builder.search-customers', ['q' => '0792222']));
        $this->assertCount(2, $texts('reports.analytics-builder.search-customers', []));
    }

    public function test_index_prefills_initial_selection_and_lists_only_own_saved_reports(): void
    {
        $this->seedSales();
        $other = User::factory()->create();
        UserSavedReport::create(['user_id' => $this->owner->id, 'name' => 'My Sales', 'group_by' => 'item', 'metrics' => ['qty'], 'filters' => []]);
        UserSavedReport::create(['user_id' => $other->id, 'name' => 'Not Mine', 'group_by' => 'item', 'metrics' => ['qty'], 'filters' => []]);

        $r = $this->get(route('reports.analytics-builder', ['preset' => 'item_supplier', 'item_id' => $this->itemA->id, 'supplier_id' => $this->sup1->id, 'customer_id' => $this->custX->id]))->assertOk();
        $this->assertSame('item_supplier', $r->viewData('initialPreset'));
        $this->assertSame('Alpha Kibble', $r->viewData('initialItem')->name);
        $this->assertSame('Alpha Agro', $r->viewData('initialSupplier')->name);
        $this->assertSame('Xena Buyer', $r->viewData('initialCustomer')->name);
        $this->assertSame(['My Sales'], $r->viewData('savedReports')->pluck('name')->all());
        $this->assertNull($this->get(route('reports.analytics-builder'))->viewData('initialItem'));
    }

    public function test_save_and_delete_saved_reports_are_scoped_to_the_owner(): void
    {
        $r = $this->postJson(route('reports.analytics-builder.save'), ['name' => 'Weekly items', 'description' => 'top items', 'group_by' => 'item', 'metrics' => ['qty', 'margin'], 'filters' => ['branch_id' => 2]])->assertOk();
        $r->assertJson(['success' => true, 'message' => 'Report saved successfully!']);
        $saved = UserSavedReport::where('name', 'Weekly items')->first();
        $this->assertSame($this->owner->id, $saved->user_id);
        $this->assertSame(['qty', 'margin'], $saved->metrics);
        $this->assertSame(['branch_id' => 2], $saved->filters);

        $this->postJson(route('reports.analytics-builder.save'), ['name' => '', 'group_by' => 'nonsense', 'metrics' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['name', 'group_by', 'metrics']);
        $this->assertSame(1, UserSavedReport::count());

        $foreign = UserSavedReport::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'group_by' => 'item', 'metrics' => ['qty'], 'filters' => []]);
        $this->deleteJson(route('reports.analytics-builder.delete', $foreign->id))->assertOk();
        $this->assertNotNull(UserSavedReport::find($foreign->id), 'someone else\'s saved report must survive');
        $this->deleteJson(route('reports.analytics-builder.delete', $saved->id))->assertOk()->assertJson(['success' => true]);
        $this->assertNull(UserSavedReport::find($saved->id));
    }

    // ================================================================ Smart Analytics

    public function test_smart_analytics_index_defaults_and_initial_entities(): void
    {
        $this->seedSales();
        $r = $this->get(route('reports.smart-analytics', ['item_id' => $this->itemA->id, 'customer_id' => $this->custY->id, 'from_date' => '2026-09-01']))->assertOk();
        $this->assertSame('Alpha Kibble', $r->viewData('initialItem')->name);
        $this->assertSame('Yash Buyer', $r->viewData('initialCustomer')->name);
        $this->assertSame('2026-09-01', $r->viewData('fromDate'));
        $this->assertSame(now()->toDateString(), $r->viewData('toDate'));
        $this->assertNull($this->get(route('reports.smart-analytics'))->viewData('initialItem'));
    }

    public function test_item_analytics_summary_trend_bills_and_stock(): void
    {
        $this->seedSales();
        ItemStock::create(['item_id' => $this->itemA->id, 'branch_id' => $this->b1->id, 'quantity' => 7]);
        ItemStock::create(['item_id' => $this->itemA->id, 'branch_id' => $this->b2->id, 'quantity' => 5]);
        $q = ['item_id' => $this->itemA->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30'];

        $j = $this->getJson(route('reports.smart-analytics.item', $q))->assertOk()->json();
        $this->assertSame('success', $j['status']);
        $this->assertEquals(['id' => $this->itemA->id, 'name' => 'Alpha Kibble', 'item_code' => 'A1', 'ean_upc_code' => '8900000000011', 'mrp' => 130.0, 'sell_price' => 120.0, 'brand' => 'Acme'], $j['item']);
        $s = $j['summary'];
        $this->assertEquals(3.0, $s['total_qty']);
        $this->assertEquals(336.0, $s['total_net_amount']);
        $this->assertEquals(5.0, $s['total_discount']);
        $this->assertEquals(181.0, $s['gross_profit']); // 336 - (2*50 + 1*55)
        $this->assertEquals(53.9, $s['margin_percent']);
        $this->assertEquals(112.0, $s['avg_selling_rate']);
        $this->assertSame(2, $s['bills_count']);
        $this->assertEquals(12.0, $s['current_stock']);
        $this->assertSame(['2026-09-05', '2026-09-10'], array_column($j['daily_trend'], 'date'));
        $this->assertEquals([2.0, 236.0], [(float) $j['daily_trend'][0]['qty'], (float) $j['daily_trend'][0]['amount']]);
        $this->assertSame(['S-2', 'S-1'], array_column($j['bills'], 'bill_number'));
        $this->assertSame('Yash Buyer', $j['bills'][0]['customer_name']);

        $j = $this->getJson(route('reports.smart-analytics.item', $q + ['branch_id' => $this->b2->id]))->json();
        $this->assertEquals([1.0, 100.0, 100.0, 5.0, 1], [$j['summary']['total_qty'], $j['summary']['total_net_amount'], $j['summary']['avg_selling_rate'], $j['summary']['current_stock'], $j['summary']['bills_count']]);

        // no sales in window -> zero metrics and the list price is used as the average rate
        $j = $this->getJson(route('reports.smart-analytics.item', ['item_id' => $this->itemA->id, 'from_date' => '2025-01-01', 'to_date' => '2025-01-31']))->json();
        $this->assertEquals([0.0, 0.0, 120.0, 0], [$j['summary']['total_qty'], $j['summary']['margin_percent'], $j['summary']['avg_selling_rate'], $j['summary']['bills_count']]);
        $this->assertSame([], $j['bills']);

        $this->getJson(route('reports.smart-analytics.item', ['item_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('item_id');
        $this->getJson(route('reports.smart-analytics.item', []))->assertStatus(422);
    }

    public function test_customer_analytics_summary_top_items_and_recent_bills(): void
    {
        $this->seedSales();
        $extra = SalesBill::create(['bill_number' => 'S-5', 'bill_date' => '2026-09-20 15:45:00', 'customer_id' => $this->custX->id, 'branch_id' => $this->b1->id, 'sales_type' => 'Local', 'total' => 210, 'status' => 'Posted', 'total_qty' => 2]);
        SalesBillItem::create(['sales_bill_id' => $extra->id, 'item_id' => $this->itemB->id, 'qty' => 5, 'sell_price' => 42, 'net_amount' => 210, 'cost_at_sale' => 30]);
        $q = ['customer_id' => $this->custX->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30'];

        $j = $this->getJson(route('reports.smart-analytics.customer', $q))->assertOk()->json();
        $this->assertEquals(['id' => $this->custX->id, 'name' => 'Xena Buyer', 'mobile' => '9111111111', 'credit_balance' => 0.0], $j['customer']);
        $this->assertEquals([2, 800.0, 400.0], [$j['summary']['total_bills'], $j['summary']['total_spend'], $j['summary']['avg_bill_value']]); // S-1 + S-5, cancelled S-3 excluded
        $this->assertSame('20 Sep 2026, 03:45 PM', $j['summary']['last_visit']);
        $this->assertSame(['Bravo Toy', 'Alpha Kibble'], array_column($j['top_items'], 'name'));    // 6 units vs 2
        $this->assertEquals([6.0, 328.0], [(float) $j['top_items'][0]['total_qty'], (float) $j['top_items'][0]['total_amount']]);
        $this->assertSame(['S-5', 'S-1'], array_column($j['recent_bills'], 'bill_number'));

        $j = $this->getJson(route('reports.smart-analytics.customer', $q + ['branch_id' => $this->b2->id]))->json();
        $this->assertEquals([0, 0.0, 0.0, 'No visits in period'], [$j['summary']['total_bills'], $j['summary']['total_spend'], $j['summary']['avg_bill_value'], $j['summary']['last_visit']]);
        $this->getJson(route('reports.smart-analytics.customer', ['customer_id' => 0]))->assertStatus(422);
    }

    public function test_ranking_analytics_top_selling_slow_moving_and_limits(): void
    {
        $this->seedSales();
        $dead = Item::create(['name' => 'Dusty Leash', 'item_code' => 'D1', 'sell_price' => 30, 'status' => true]);
        Item::create(['name' => 'Retired Item', 'item_code' => 'R1', 'sell_price' => 30, 'status' => false]);
        ItemStock::create(['item_id' => $dead->id, 'branch_id' => $this->b1->id, 'quantity' => 9]);
        ItemStock::create(['item_id' => $dead->id, 'branch_id' => $this->b2->id, 'quantity' => 4]);
        $q = ['from_date' => '2026-09-01', 'to_date' => '2026-09-30'];
        $rank = fn (array $x) => $this->getJson(route('reports.smart-analytics.ranking', $q + $x))->assertOk()->json();

        $j = $rank([]);
        $this->assertSame('top_selling', $j['type']);
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], array_column($j['items'], 'name'));
        $this->assertEquals([3.0, 336.0, 2], [(float) $j['items'][0]['total_sold_qty'], (float) $j['items'][0]['total_revenue'], (int) $j['items'][0]['bills_count']]);

        $j = $rank(['metric' => 'revenue', 'limit' => 1]);
        $this->assertSame(['Alpha Kibble'], array_column($j['items'], 'name'));
        $b1 = $rank(['branch_id' => $this->b1->id, 'metric' => 'qty', 'limit' => 5])['items'];
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], array_column($b1, 'name'));
        $this->assertEquals([2.0, 1.0], [(float) $b1[0]['total_sold_qty'], (float) $b1[1]['total_sold_qty']]);
        $this->assertSame(['Alpha Kibble'], array_column($rank(['branch_id' => $this->b2->id])['items'], 'name'));
        $this->assertCount(2, $rank(['limit' => 500])['items']);

        $j = $rank(['type' => 'slow_moving']);
        $this->assertSame('slow_moving', $j['type']);
        $this->assertSame(['Dusty Leash'], array_column($j['items'], 'name'));   // sold items and inactive items are excluded
        $this->assertEquals([13.0, 0], [(float) $j['items'][0]['current_stock'], (int) $j['items'][0]['total_sold_qty']]);
        $j = $rank(['type' => 'slow_moving', 'branch_id' => $this->b2->id]);
        $this->assertSame(['Dusty Leash'], array_column($j['items'], 'name'));
        $this->assertEquals(4.0, (float) $j['items'][0]['current_stock']);
        // In August nothing sold at branch 2, so every item stocked there is slow-moving (sold-elsewhere items with no stock row at that branch are not listed)
        $j = $rank(['type' => 'slow_moving', 'from_date' => '2026-08-01', 'to_date' => '2026-08-31', 'branch_id' => $this->b2->id]);
        $this->assertSame(['Dusty Leash'], array_column($j['items'], 'name'));
        $this->assertEquals(4.0, (float) $j['items'][0]['current_stock']);
    }

    public function test_smart_analytics_search_endpoints_and_item_csv_export(): void
    {
        $this->seedSales();
        Item::create(['name' => 'Retired Kibble', 'item_code' => 'RK', 'sell_price' => 1, 'status' => false]);
        Customer::create(['name' => 'Inactive Ivan', 'mobile' => '9333333333', 'status' => false]);

        $names = fn ($route, $q) => collect($this->getJson(route($route, $q))->assertOk()->json())->pluck('name')->all();
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], $names('reports.smart-analytics.search-items', []));      // inactive hidden
        $this->assertSame(['Alpha Kibble'], $names('reports.smart-analytics.search-items', ['q' => 'kibble']));
        $this->assertSame(['Alpha Kibble'], $names('reports.smart-analytics.search-items', ['q' => '8900000000011']));
        $this->assertSame(['Bravo Toy'], $names('reports.smart-analytics.search-items', ['q' => 'b1']));
        $this->assertSame(['Xena Buyer', 'Yash Buyer'], $names('reports.smart-analytics.search-customers', []));
        $this->assertSame(['Xena Buyer'], $names('reports.smart-analytics.search-customers', ['q' => '9111']));
        $this->assertSame(['Yash Buyer'], $names('reports.smart-analytics.search-customers', ['q' => 'yash']));

        $r = $this->get(route('reports.smart-analytics.export-item', ['item_id' => $this->itemA->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30']))->assertOk();
        $this->assertStringContainsString('item_sales_A1_', $r->headers->get('Content-Disposition'));
        $html = $r->streamedContent();
        $this->assertStringContainsString('<strong>Product:</strong> Alpha Kibble | <strong>Code:</strong> A1', $html);
        $this->assertStringContainsString('<td>S-1</td><td>2026-09-05 00:00:00</td><td>Xena Buyer</td><td>2.000</td><td>118.00</td><td>5.00</td><td>236.00</td>', $html);
        $this->assertStringContainsString('<td>S-2</td>', $html);
        $this->assertStringNotContainsString('S-3', $html);   // cancelled
        $this->assertLessThan(strpos($html, 'S-2'), strpos($html, 'S-1'), 'oldest bill first');

        $html = $this->get(route('reports.smart-analytics.export-item', ['item_id' => $this->itemA->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30', 'branch_id' => $this->b2->id]))->streamedContent();
        $this->assertStringNotContainsString('S-1', $html);
        $this->assertStringContainsString('S-2', $html);
    }

    public function test_empty_metrics_fall_back_to_the_default_column_set(): void
    {
        $this->seedSales();
        $j = $this->gen(['group_by' => 'item', 'metrics' => []])->json();
        $this->assertSame(['qty', 'sales_value', 'margin', 'bill_count'], $j['metrics']);
        $this->assertSame(['group_name', 'group_subtext', 'formatted_qty', 'formatted_sales', 'formatted_profit', 'bill_count'], array_column($j['columns'], 'key'));
    }
}
