<?php

namespace Tests\Feature\Cov;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerPet;
use App\Models\DamageStock;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\PetType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesBillPayment;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReportController: every filter, the numbers each report computes and the CSV exports.
 */
class ReportsCoreTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b1;
    private Branch $b2;
    private User $owner;
    /** Owners are scoped to their first branch by default; these tests want every branch unless they say otherwise. */
    private array $sessionDefaults = ['active_branch_id' => 'all'];

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->owner = User::factory()->create(['name' => 'Rita Reporter']);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->b1 = Branch::create(['name' => 'Main Store', 'state' => 'Gujarat', 'status' => true]);
        $this->b2 = Branch::create(['name' => 'Second Store', 'state' => 'Gujarat', 'status' => true]);
    }

    public function get($uri, array $headers = [])
    {
        $this->withSession($this->sessionDefaults);

        return parent::get($uri, $headers);
    }

    private function bill(string $no, string $date, Branch $b, float $total, string $status = 'Posted', ?Customer $c = null, array $extra = []): SalesBill
    {
        $c ??= Customer::create(['name' => 'Cust ' . $no, 'status' => true]);
        return SalesBill::create($extra + [
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $b->id,
            'sales_type' => 'Local', 'total' => $total, 'status' => $status, 'total_qty' => 1,
        ]);
    }

    private function item(string $name, array $extra = []): Item
    {
        return Item::create($extra + ['name' => $name, 'item_code' => strtoupper(substr($name, 0, 3)) . rand(100, 999), 'sell_price' => 100, 'cost_price' => 60, 'mrp' => 120, 'status' => true]);
    }

    private function csv($response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'CSV must start with a UTF-8 BOM for Excel');
        $body = substr($body, 3);
        return array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', $body)), 'strlen')));
    }

    private function range(): array
    {
        return ['from' => '2026-09-01', 'to' => '2026-09-30'];
    }

    // ---------------------------------------------------------------- sales

    public function test_index_page_renders(): void
    {
        $this->get(route('reports.index'))->assertOk();
    }

    public function test_sales_summary_groups_by_day_and_branch_excluding_cancelled(): void
    {
        $this->bill('SS-1', '2026-09-05', $this->b1, 100, 'Posted', null, ['disc_amount' => 5, 'total_gst' => 10]);
        $this->bill('SS-2', '2026-09-05', $this->b1, 200, 'Posted', null, ['total_gst' => 20]);
        $this->bill('SS-3', '2026-09-05', $this->b2, 50);
        $this->bill('SS-CAN', '2026-09-06', $this->b1, 300, 'Cancelled');
        $this->bill('SS-OUT', '2026-08-30', $this->b1, 999);

        $r = $this->get(route('reports.sales-summary', $this->range()))->assertOk();
        $rows = $r->viewData('rows');
        $this->assertCount(2, $rows);
        $main = $rows->firstWhere('branch_id', $this->b1->id);
        $this->assertSame([2, 300.0, 5.0, 30.0], [(int) $main->bill_count, (float) $main->total_amount, (float) $main->total_disc, (float) $main->total_gst]);
        $this->assertEquals(50.0, (float) $rows->firstWhere('branch_id', $this->b2->id)->total_amount);

        $rows = $this->get(route('reports.sales-summary', $this->range() + ['branch_id' => $this->b2->id]))->viewData('rows');
        $this->assertCount(1, $rows);
        // branch_id=all / 0 mean "every branch"
        $this->assertCount(2, $this->get(route('reports.sales-summary', $this->range() + ['branch_id' => 'all']))->viewData('rows'));
        $this->assertCount(2, $this->get(route('reports.sales-summary', $this->range() + ['branch_id' => '0']))->viewData('rows'));
    }

    public function test_active_branch_session_scopes_reports_when_no_branch_param(): void
    {
        $this->bill('AB-1', '2026-09-05', $this->b1, 100);
        $this->bill('AB-2', '2026-09-05', $this->b2, 40);

        $this->sessionDefaults = ['active_branch_id' => $this->b2->id];
        $rows = $this->get(route('reports.sales-summary', $this->range()))->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertEquals(40.0, (float) $rows[0]->total_amount);

        $this->sessionDefaults = ['active_branch_id' => 'all'];
        $rows = $this->get(route('reports.sales-summary', $this->range()))->viewData('rows');
        $this->assertCount(2, $rows);
    }

    public function test_billwise_sales_filters(): void
    {
        $ramesh = Customer::create(['name' => 'Ramesh', 'mobile' => '9876500001', 'phone' => '0792000001', 'status' => true]);
        $sita = Customer::create(['name' => 'Sita', 'mobile' => '9876500002', 'status' => true]);
        $this->bill('BW-1', '2026-09-05', $this->b1, 100, 'Posted', $ramesh, ['invoice_type' => 'Tax Invoice']);
        $this->bill('BW-2', '2026-09-06', $this->b2, 200, 'Posted', $sita);
        $this->bill('BW-OLD', '2026-01-06', $this->b1, 999, 'Posted', $ramesh);

        $names = fn ($r) => $r->viewData('bills')->pluck('bill_number')->all();
        $this->assertSame(['BW-1', 'BW-2'], $names($this->get(route('reports.billwise-sales', $this->range()))->assertOk()));
        $this->assertSame(['BW-2'], $names($this->get(route('reports.billwise-sales', $this->range() + ['branch_id' => $this->b2->id]))));
        $this->assertSame(['BW-1'], $names($this->get(route('reports.billwise-sales', $this->range() + ['customer_id' => $ramesh->id]))));
        $this->assertSame(['BW-1'], $names($this->get(route('reports.billwise-sales', $this->range() + ['invoice_type' => 'Tax Invoice']))));
        $this->assertSame(['BW-1'], $names($this->get(route('reports.billwise-sales', $this->range() + ['search' => '0792000001']))));
        $this->assertSame(['BW-2'], $names($this->get(route('reports.billwise-sales', $this->range() + ['search' => 'sita']))));
        $this->assertSame(['BW-2'], $names($this->get(route('reports.billwise-sales', $this->range() + ['search' => 'BW-2']))));

        // A selected customer outside the first-30 dropdown is still offered as an option.
        foreach (range(1, 31) as $i) {
            Customer::create(['name' => sprintf('A%02d Filler', $i), 'status' => true]);
        }
        $zed = Customer::create(['name' => 'Zzz Selected', 'mobile' => '9000000000', 'status' => true]);
        $r = $this->get(route('reports.billwise-sales', $this->range() + ['customer_id' => $zed->id]));
        $this->assertSame('Zzz Selected (9000000000)', $r->viewData('customers')[$zed->id]);
        $this->assertCount(30 + 1, $r->viewData('customers'));
    }

    public function test_sales_return_summary_filters(): void
    {
        $c1 = Customer::create(['name' => 'Return Rita', 'status' => true]);
        $c2 = Customer::create(['name' => 'Return Ravi', 'status' => true]);
        $bill = $this->bill('RB-1', '2026-09-01', $this->b1, 500, 'Posted', $c1);
        $mk = fn ($no, $d, $c, $b, $mode, $extra = []) => SalesReturn::create($extra + ['return_number' => $no, 'return_date' => $d, 'customer_id' => $c->id, 'branch_id' => $b->id, 'return_mode' => $mode, 'total' => 100]);
        $mk('RT-1', '2026-09-10', $c1, $this->b1, 'Cash', ['sales_bill_id' => $bill->id]);
        $mk('RT-2', '2026-09-11', $c2, $this->b2, 'Credit Note');
        $mk('RT-OLD', '2026-01-11', $c2, $this->b2, 'Cash');

        $names = fn ($r) => $r->viewData('returns')->pluck('return_number')->all();
        $this->assertSame(['RT-1', 'RT-2'], $names($this->get(route('reports.sales-return-summary', $this->range()))->assertOk()));
        $this->assertSame(['RT-2'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['branch_id' => $this->b2->id]))));
        $this->assertSame(['RT-1'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['customer_id' => $c1->id]))));
        $this->assertSame(['RT-2'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['return_mode' => 'Credit Note']))));
        $this->assertSame(['RT-1'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['search' => 'RB-1']))));
        $this->assertSame(['RT-2'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['search' => 'Ravi']))));
        $this->assertSame(['RT-1'], $names($this->get(route('reports.sales-return-summary', $this->range() + ['search' => 'RT-1']))));
        $this->assertEqualsCanonicalizing(['Cash', 'Credit Note'], $this->get(route('reports.sales-return-summary'))->viewData('returnModes')->all());
    }

    // ---------------------------------------------------------------- purchase

    public function test_purchase_detail_filters(): void
    {
        $s1 = Supplier::create(['name' => 'Alpha Agro', 'status' => true]);
        $s2 = Supplier::create(['name' => 'Beta Bulk', 'status' => true]);
        $mk = fn ($no, $d, $s, $b, $type, $extra = []) => PurchaseInvoice::create($extra + ['invoice_number' => $no, 'invoice_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $b->id, 'purchase_type' => $type, 'total' => 100, 'status' => 'Posted']);
        $mk('PD-1', '2026-09-05', $s1, $this->b1, 'Local', ['supplier_inv_no' => 'VB-77']);
        $mk('PD-2', '2026-09-06', $s2, $this->b2, 'Interstate');
        $mk('PD-OLD', '2026-01-06', $s1, $this->b1, 'Local');

        $names = fn ($r) => $r->viewData('invoices')->pluck('invoice_number')->all();
        $this->assertSame(['PD-1', 'PD-2'], $names($this->get(route('reports.purchase-detail', $this->range()))->assertOk()));
        $this->assertSame(['PD-2'], $names($this->get(route('reports.purchase-detail', $this->range() + ['branch_id' => $this->b2->id]))));
        $this->assertSame(['PD-1'], $names($this->get(route('reports.purchase-detail', $this->range() + ['supplier_id' => $s1->id]))));
        $this->assertSame(['PD-2'], $names($this->get(route('reports.purchase-detail', $this->range() + ['purchase_type' => 'Interstate']))));
        $this->assertSame(['PD-1'], $names($this->get(route('reports.purchase-detail', $this->range() + ['search' => 'VB-77']))));
        $this->assertSame(['PD-2'], $names($this->get(route('reports.purchase-detail', $this->range() + ['search' => 'beta']))));
        $this->assertSame(['PD-1'], $names($this->get(route('reports.purchase-detail', $this->range() + ['search' => 'PD-1']))));
    }

    public function test_gst_purchase_summary_groups_by_hsn_and_rate_and_skips_cancelled(): void
    {
        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        $food = $this->item('Food', ['hsn_code' => '23091000']);
        $noHsn = $this->item('Misc', ['hsn_code' => '']);
        $mkInv = fn ($no, $b, $status = 'Posted') => PurchaseInvoice::create(['invoice_number' => $no, 'invoice_date' => '2026-09-10', 'supplier_id' => $sup->id, 'branch_id' => $b->id, 'total' => 1, 'status' => $status]);
        $line = fn ($inv, $item, $rate, $net, $gst, $c, $sg, $i) => PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'qty' => 1, 'cost_price' => 1, 'sell_price' => 1, 'mrp' => 1,
            'gst_percent' => $rate, 'net_amount' => $net, 'gst_tax_amount' => $gst, 'cgst_amount' => $c, 'sgst_amount' => $sg, 'igst_amount' => $i,
        ]);
        $i1 = $mkInv('GP-1', $this->b1);
        $line($i1, $food, 18, 118, 18, 9, 9, 0);
        $line($i1, $food, 18, 236, 36, 18, 18, 0);
        $line($i1, $noHsn, 5, 105, 5, 0, 0, 5);
        $i2 = $mkInv('GP-2', $this->b2);
        $line($i2, $food, 18, 118, 18, 0, 0, 18);
        $line($mkInv('GP-CAN', $this->b1, 'Cancelled'), $food, 18, 1180, 180, 90, 90, 0);

        $rows = $this->get(route('reports.gst-purchase-summary', $this->range()))->assertOk()->viewData('rows');
        $this->assertCount(2, $rows);
        $food18 = $rows->first(fn ($r) => $r->hsn_code === '23091000');
        $this->assertEquals(400.0, (float) $food18->taxable_amount);   // 100 + 200 + 100
        $this->assertEquals([27.0, 27.0, 18.0, 72.0], [(float) $food18->cgst_amount, (float) $food18->sgst_amount, (float) $food18->igst_amount, (float) $food18->gst_amount]);
        $na = $rows->first(fn ($r) => $r->hsn_code === 'N/A');
        $this->assertEquals([5.0, 100.0, 5.0], [(float) $na->gst_percent, (float) $na->taxable_amount, (float) $na->igst_amount]);

        $rows = $this->get(route('reports.gst-purchase-summary', $this->range() + ['branch_id' => $this->b2->id]))->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertEquals(100.0, (float) $rows[0]->taxable_amount);
    }

    public function test_purchase_order_summary_filters_and_csv(): void
    {
        $s1 = Supplier::create(['name' => 'PO Alpha', 'status' => true]);
        $s2 = Supplier::create(['name' => 'PO Beta', 'status' => true]);
        $mk = fn ($no, $d, $s, $b, $st, $extra = []) => PurchaseOrder::create($extra + ['po_number' => $no, 'po_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $b->id, 'status' => $st]);
        $mk('PO-1', '2026-09-05', $s1, $this->b1, 'Open', ['total_qty' => 10, 'freight' => 25, 'total_gst' => 90, 'total' => 590]);
        $mk('PO-2', '2026-09-06', $s2, $this->b2, 'Closed');
        $mk('PO-OLD', '2026-01-06', $s1, $this->b1, 'Open');

        $names = fn ($r) => $r->viewData('purchaseOrders')->pluck('po_number')->all();
        $this->assertSame(['PO-2', 'PO-1'], $names($this->get(route('reports.purchase-order-summary', $this->range()))->assertOk()));
        $this->assertSame(['PO-2'], $names($this->get(route('reports.purchase-order-summary', $this->range() + ['branch_id' => $this->b2->id]))));
        $this->assertSame(['PO-1'], $names($this->get(route('reports.purchase-order-summary', $this->range() + ['supplier_id' => $s1->id]))));
        $this->assertSame(['PO-1'], $names($this->get(route('reports.purchase-order-summary', $this->range() + ['status' => 'Open']))));
        $this->assertSame(['PO-2'], $names($this->get(route('reports.purchase-order-summary', $this->range() + ['search' => 'Beta']))));
        $this->assertSame(['PO-1'], $names($this->get(route('reports.purchase-order-summary', $this->range() + ['search' => 'PO-1']))));

        $rows = $this->csv($this->get(route('reports.purchase-order-summary', $this->range() + ['export' => 'csv', 'status' => 'Open'])));
        $this->assertSame(['PO Number', 'PO Date', 'Supplier', 'Branch', 'Total Qty', 'Freight', 'Total GST', 'Total Amount', 'Status'], $rows[0]);
        $this->assertSame(['PO-1', '05-09-2026', 'PO Alpha', 'Main Store', '10.00', '25.00', '90.00', '590.00', 'Open'], $rows[1]);
        $this->assertCount(2, $rows);
    }

    // ---------------------------------------------------------------- stock

    public function test_current_stock_filters_and_totals(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $catVal = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $a = $this->item('Alpha', ['item_code' => 'AL1', 'brand_id' => $brand->id, 'category_value_id' => $catVal->id, 'cost_price' => 10, 'sell_price' => 20]);
        $b = $this->item('Bravo', ['item_code' => 'BR1', 'ean_upc_code' => 'EAN-BRAVO', 'cost_price' => 5, 'sell_price' => 8]);
        ItemStock::create(['item_id' => $a->id, 'branch_id' => $this->b1->id, 'quantity' => 4]);
        ItemStock::create(['item_id' => $a->id, 'branch_id' => $this->b2->id, 'quantity' => 6]);
        ItemStock::create(['item_id' => $b->id, 'branch_id' => $this->b1->id, 'quantity' => 10]);
        ItemStock::create(['item_id' => $b->id, 'branch_id' => $this->b2->id, 'quantity' => 0]);

        $r = $this->get(route('reports.current-stock'))->assertOk();
        $this->assertSame(3, $r->viewData('rows')->total());   // zero-qty excluded
        $t = $r->viewData('totals');
        $this->assertEquals([20.0, 10 * 4 + 6 * 10 + 5 * 10, 20 * 4 + 20 * 6 + 8 * 10], [(float) $t->qty, (float) $t->cost_value, (float) $t->sell_value]);

        $r = $this->get(route('reports.current-stock', ['branch_id' => $this->b1->id]));
        $this->assertSame(2, $r->viewData('rows')->total());
        $this->assertEquals(14.0, (float) $r->viewData('totals')->qty);

        $this->assertSame(2, $this->get(route('reports.current-stock', ['search' => 'AL1']))->viewData('rows')->total());
        $this->assertSame(1, $this->get(route('reports.current-stock', ['search' => 'EAN-BRAVO']))->viewData('rows')->total());
        $this->assertSame(2, $this->get(route('reports.current-stock', ['brand_id' => $brand->id]))->viewData('rows')->total());
        $r = $this->get(route('reports.current-stock', ['category_value_id' => $catVal->id, 'branch_id' => $this->b2->id]));
        $this->assertSame(1, $r->viewData('rows')->total());
        $this->assertEquals(120.0, (float) $r->viewData('totals')->sell_value);
        $this->assertSame(['Food'], $r->viewData('categories')->values()->all());
    }

    public function test_reorder_report_thresholds_and_status_filters(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $catVal = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $sup = Supplier::create(['name' => 'Refill Co', 'status' => true]);
        $mk = fn ($name, $qty, $b, $extra = []) => ItemStock::create(['item_id' => $this->item($name, $extra + ['item_code' => strtoupper($name)])->id, 'branch_id' => $b->id, 'quantity' => $qty, 'sell_price' => 50]);
        $mk('out', 0, $this->b1, ['supplier_id' => $sup->id]);
        $mk('neg', -3, $this->b1);
        $mk('low', 4, $this->b1, ['brand_id' => $brand->id, 'category_value_id' => $catVal->id]);
        $mk('mid', 8, $this->b1);
        $mk('plenty', 50, $this->b1);
        $mk('lowb2', 2, $this->b2);

        $r = $this->get(route('reports.reorder-report'))->assertOk();
        $this->assertSame(['neg', 'out', 'lowb2', 'low'], $r->viewData('rows')->pluck('item_name')->map(fn ($n) => strtolower($n))->all());
        $this->assertSame(['out_of_stock' => 2, 'low_stock' => 2, 'total_skus' => 4], $r->viewData('kpi'));
        $this->assertSame('Refill Co', $r->viewData('rows')->firstWhere('item_code', 'OUT')->supplier_name);

        $r = $this->get(route('reports.reorder-report', ['threshold' => 10]));
        $this->assertSame(5, $r->viewData('rows')->count());
        $r = $this->get(route('reports.reorder-report', ['stock_status' => 'out']));
        $this->assertSame(['Out of Stock'], $r->viewData('rows')->pluck('status')->unique()->values()->all());
        $this->assertSame(2, $r->viewData('rows')->count());
        $r = $this->get(route('reports.reorder-report', ['stock_status' => 'low']));
        $this->assertSame(2, $r->viewData('rows')->count());
        $this->assertSame(['Low Stock'], $r->viewData('rows')->pluck('status')->unique()->values()->all());
        $this->assertSame(1, $this->get(route('reports.reorder-report', ['brand_id' => $brand->id]))->viewData('rows')->count());
        $this->assertSame(1, $this->get(route('reports.reorder-report', ['category_value_id' => $catVal->id]))->viewData('rows')->count());
        $this->assertSame(1, $this->get(route('reports.reorder-report', ['search' => 'lowb2']))->viewData('rows')->count());
        $this->assertSame(1, $this->get(route('reports.reorder-report', ['branch_id' => $this->b2->id]))->viewData('rows')->count());
        $this->assertSame(0, $this->get(route('reports.reorder-report', ['threshold' => -4, 'stock_status' => 'low']))->viewData('rows')->count());
    }

    public function test_stock_transfer_summary_filters_and_csv(): void
    {
        $mk = fn ($no, $d, $from, $to, $st, $extra = []) => StockTransfer::create($extra + ['transfer_number' => $no, 'transfer_date' => $d, 'from_branch_id' => $from->id, 'to_branch_id' => $to->id, 'status' => $st]);
        $mk('ST-1', '2026-09-05', $this->b1, $this->b2, 'Received', ['total_qty' => 7.5, 'dispatched_at' => '2026-09-05 10:00:00', 'received_at' => '2026-09-06 12:30:00']);
        $mk('ST-2', '2026-09-06', $this->b2, $this->b1, 'Dispatched');
        $mk('ST-OLD', '2026-01-06', $this->b1, $this->b2, 'Dispatched');

        $names = fn ($r) => $r->viewData('transfers')->pluck('transfer_number')->all();
        $this->assertSame(['ST-2', 'ST-1'], $names($this->get(route('reports.stock-transfer-summary', $this->range()))->assertOk()));
        $this->assertSame(['ST-1'], $names($this->get(route('reports.stock-transfer-summary', $this->range() + ['branch_id' => $this->b1->id]))));
        $this->assertSame(['ST-1'], $names($this->get(route('reports.stock-transfer-summary', $this->range() + ['to_branch_id' => $this->b2->id]))));
        $this->assertSame(['ST-2'], $names($this->get(route('reports.stock-transfer-summary', $this->range() + ['status' => 'Dispatched']))));
        $this->assertSame(['ST-1'], $names($this->get(route('reports.stock-transfer-summary', $this->range() + ['search' => 'ST-1']))));

        $rows = $this->csv($this->get(route('reports.stock-transfer-summary', $this->range() + ['export' => 'csv'])));
        $this->assertCount(3, $rows);
        $this->assertSame(['ST-2', '06-09-2026', 'Second Store', 'Main Store', '0.00', '-', '-', 'Dispatched'], $rows[1]);
        $this->assertSame(['ST-1', '05-09-2026', 'Main Store', 'Second Store', '7.50', '05-09-2026 10:00', '06-09-2026 12:30', 'Received'], $rows[2]);
    }

    public function test_damage_stock_summary_filters_and_csv(): void
    {
        $mk = fn ($no, $d, $b, $extra = []) => DamageStock::create($extra + ['damage_number' => $no, 'branch_id' => $b->id, 'entry_date' => $d, 'status' => 'Posted']);
        $mk('DS-1', '2026-09-05', $this->b1, ['wastage_type' => 'Theft', 'total_qty' => 3, 'total_cost' => 150.5, 'remarks' => 'Shelf 4']);
        $mk('DS-2', '2026-09-06', $this->b2);
        $mk('DS-OLD', '2026-01-06', $this->b1);

        $names = fn ($r) => $r->viewData('damageStocks')->pluck('damage_number')->all();
        $this->assertSame(['DS-2', 'DS-1'], $names($this->get(route('reports.damage-stock-summary', $this->range()))->assertOk()));
        $this->assertSame(['DS-2'], $names($this->get(route('reports.damage-stock-summary', $this->range() + ['branch_id' => $this->b2->id]))));
        $this->assertSame(['DS-1'], $names($this->get(route('reports.damage-stock-summary', $this->range() + ['search' => 'DS-1']))));

        $rows = $this->csv($this->get(route('reports.damage-stock-summary', $this->range() + ['export' => 'csv'])));
        $this->assertSame(['DS-2', '06-09-2026', 'Second Store', 'Damage', '0.00', '0.00', '-', 'Posted'], $rows[1]);
        $this->assertSame(['DS-1', '05-09-2026', 'Main Store', 'Theft', '3.00', '150.50', 'Shelf 4', 'Posted'], $rows[2]);
    }

    // ---------------------------------------------------------------- masters (csv + filters)

    public function test_customer_master_filters_and_csv_export(): void
    {
        $gold = CustomerCategory::create(['name' => 'Gold', 'status' => true]);
        $basic = CustomerCategory::create(['name' => 'Basic', 'status' => true]);
        Customer::create(['name' => 'Asha Patel', 'customer_code' => 'C001', 'phone' => '9111100001', 'city' => 'Surat', 'gst_no' => '24AAAAA1111A1Z1', 'customer_category_id' => $gold->id, 'branch_id' => $this->b1->id, 'credit_balance' => 1250.5, 'status' => true]);
        Customer::create(['name' => 'Bimal Shah', 'customer_code' => 'C002', 'phone' => '9111100002', 'customer_category_id' => $basic->id, 'branch_id' => $this->b2->id, 'status' => false]);

        $names = fn ($r) => $r->viewData('customers')->pluck('name')->all();
        $this->assertSame(['Asha Patel', 'Bimal Shah'], $names($this->get(route('reports.customer-master', ['branch_id' => 'all']))->assertOk()));
        $this->assertSame(['Asha Patel'], $names($this->get(route('reports.customer-master', ['category_id' => $gold->id]))));
        $this->assertSame(['Bimal Shah'], $names($this->get(route('reports.customer-master', ['branch_id' => $this->b2->id]))));
        $this->assertSame(['Asha Patel'], $names($this->get(route('reports.customer-master', ['status' => 1]))));
        $this->assertSame(['Bimal Shah'], $names($this->get(route('reports.customer-master', ['status' => 0]))));
        $this->assertSame(['Bimal Shah'], $names($this->get(route('reports.customer-master', ['search' => '9111100002']))));
        $this->assertSame(['Asha Patel'], $names($this->get(route('reports.customer-master', ['search' => 'C001']))));

        $rows = $this->csv($this->get(route('reports.customer-master', ['export' => 'csv', 'branch_id' => 'all'])));
        $this->assertSame(['Code', 'Customer Name', 'Mobile / Phone', 'City', 'GST No', 'Category', 'Branch', 'Credit Balance', 'Status'], $rows[0]);
        $this->assertSame(['C001', 'Asha Patel', '9111100001', 'Surat', '24AAAAA1111A1Z1', 'Gold', 'Main Store', '1,250.50', 'Active'], $rows[1]);
        $this->assertSame(['C002', 'Bimal Shah', '9111100002', '', '', 'Basic', 'Second Store', '0.00', 'Inactive'], $rows[2]);

        $rows = $this->csv($this->get(route('reports.customer-master', ['export' => 'csv', 'category_id' => $basic->id, 'branch_id' => 'all'])));
        $this->assertCount(2, $rows);
    }

    public function test_customer_pet_details_filters_and_csv_export(): void
    {
        $dog = PetType::create(['name' => 'Dog', 'status' => true]);
        $cat = PetType::create(['name' => 'Cat', 'status' => true]);
        $lab = Breed::create(['pet_type_id' => $dog->id, 'name' => 'Labrador', 'status' => true]);
        $pers = Breed::create(['pet_type_id' => $cat->id, 'name' => 'Persian', 'status' => true]);
        $owner1 = Customer::create(['name' => 'Dog Dad', 'customer_code' => 'D1', 'phone' => '9000000010', 'status' => true]);
        $owner2 = Customer::create(['name' => 'Cat Mom', 'customer_code' => 'K1', 'phone' => '9000000020', 'status' => true]);
        Customer::create(['name' => 'No Pets', 'status' => true]);
        CustomerPet::create(['customer_id' => $owner1->id, 'pet_type_id' => $dog->id, 'breed_id' => $lab->id, 'name' => 'Bruno', 'gender' => 'Male', 'age' => '3', 'birth_date' => '2023-05-01']);
        CustomerPet::create(['customer_id' => $owner1->id, 'pet_type_id' => $dog->id, 'name' => 'Coco']);
        CustomerPet::create(['customer_id' => $owner2->id, 'pet_type_id' => $cat->id, 'breed_id' => $pers->id, 'name' => 'Misty', 'gender' => 'Female']);

        $names = fn ($r) => $r->viewData('customers')->pluck('name')->all();
        $this->assertSame(['Cat Mom', 'Dog Dad'], $names($this->get(route('reports.customer-pet-details'))->assertOk()));
        $this->assertSame(['Dog Dad'], $names($this->get(route('reports.customer-pet-details', ['pet_type_id' => $dog->id]))));
        $this->assertSame(['Cat Mom'], $names($this->get(route('reports.customer-pet-details', ['breed_id' => $pers->id]))));
        $this->assertSame(['Dog Dad'], $names($this->get(route('reports.customer-pet-details', ['search' => 'bruno']))));
        $this->assertSame(['Cat Mom'], $names($this->get(route('reports.customer-pet-details', ['search' => 'K1']))));

        $rows = $this->csv($this->get(route('reports.customer-pet-details', ['export' => 'csv'])));
        $this->assertSame(['Customer Code', 'Customer Name', 'Pet Name', 'Pet Type', 'Breed', 'Gender', 'Age', 'Birth Date'], $rows[0]);
        $this->assertCount(4, $rows); // header + 3 pets (one line per pet)
        $this->assertSame(['K1', 'Cat Mom', 'Misty', 'Cat', 'Persian', 'Female', '-', '-'], $rows[1]);
        $this->assertSame(['D1', 'Dog Dad', 'Bruno', 'Dog', 'Labrador', 'Male', '3', '01-05-2023'], $rows[2]);
        $this->assertSame(['D1', 'Dog Dad', 'Coco', 'Dog', '-', '-', '-', '-'], $rows[3]);
    }

    public function test_item_master_filters_and_csv_export(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $catVal = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $tax = GstTax::create(['description' => 'GST 18', 'percentage' => 18, 'status' => true]);
        $this->item('Alpha Kibble', ['item_code' => 'AK1', 'alias' => 'kibbly', 'brand_id' => $brand->id, 'category_value_id' => $catVal->id, 'gst_tax_id' => $tax->id, 'hsn_code' => '2309', 'cost_price' => 40, 'sell_price' => 70.5, 'mrp' => 80]);
        $this->item('Bravo Toy', ['item_code' => 'BT1', 'ean_upc_code' => '8901234567890', 'status' => false, 'cost_price' => 5, 'sell_price' => 9, 'mrp' => 10]);

        $names = fn ($r) => $r->viewData('items')->pluck('name')->all();
        $this->assertSame(['Alpha Kibble', 'Bravo Toy'], $names($this->get(route('reports.item-master'))->assertOk()));
        $this->assertSame(['Alpha Kibble'], $names($this->get(route('reports.item-master', ['brand_id' => $brand->id]))));
        $this->assertSame(['Alpha Kibble'], $names($this->get(route('reports.item-master', ['category_value_id' => $catVal->id]))));
        $this->assertSame(['Bravo Toy'], $names($this->get(route('reports.item-master', ['status' => 0]))));
        $this->assertSame(['Alpha Kibble'], $names($this->get(route('reports.item-master', ['search' => 'kibbly']))));
        $this->assertSame(['Bravo Toy'], $names($this->get(route('reports.item-master', ['search' => '8901234567890']))));

        $rows = $this->csv($this->get(route('reports.item-master', ['export' => 'csv'])));
        $this->assertSame(['Code', 'Barcode', 'Item Name', 'Brand', 'Category', 'UOM', 'HSN', 'Tax %', 'Cost Price', 'Sell Price', 'MRP', 'Status'], $rows[0]);
        $this->assertSame('AK1', $rows[1][0]);
        $this->assertSame(['Alpha Kibble', 'Acme', 'Food'], [$rows[1][2], $rows[1][3], $rows[1][4]]);
        $this->assertSame(['2309', '18.00%', '40.00', '70.50', '80.00', 'Active'], [$rows[1][6], $rows[1][7], $rows[1][8], $rows[1][9], $rows[1][10], $rows[1][11]]);
        $this->assertSame(['BT1', '8901234567890', 'Bravo Toy', '-', '-', '-', '-', '-', '5.00', '9.00', '10.00', 'Inactive'], $rows[2]);
    }

    public function test_supplier_master_filters_and_csv_export(): void
    {
        Supplier::create(['name' => 'Alpha Agro', 'mobile' => '9000000001', 'phone' => '0792111111', 'email' => 'a@agro.test', 'gst_no' => '24AAAAA0000A1Z5', 'city' => 'Rajkot', 'state' => 'Gujarat', 'address' => '1 Farm Rd', 'credit_limit' => 5000, 'credit_balance' => 750.25, 'status' => true]);
        Supplier::create(['name' => 'Beta Bulk', 'state' => 'Maharashtra', 'status' => false]);
        Supplier::create(['name' => 'Gamma Goods', 'status' => true]);

        $names = fn ($r) => $r->viewData('suppliers')->pluck('name')->all();
        $this->assertSame(['Alpha Agro', 'Beta Bulk', 'Gamma Goods'], $names($this->get(route('reports.supplier-master'))->assertOk()));
        $this->assertSame(['Beta Bulk'], $names($this->get(route('reports.supplier-master', ['state' => 'Maharashtra']))));
        $this->assertSame(['Beta Bulk'], $names($this->get(route('reports.supplier-master', ['status' => 0]))));
        $this->assertSame(['Alpha Agro'], $names($this->get(route('reports.supplier-master', ['search' => 'rajkot']))));
        $this->assertSame(['Alpha Agro'], $names($this->get(route('reports.supplier-master', ['search' => 'a@agro']))));
        $this->assertEqualsCanonicalizing(['Gujarat', 'Maharashtra'], $this->get(route('reports.supplier-master'))->viewData('states')->all());

        $rows = $this->csv($this->get(route('reports.supplier-master', ['export' => 'csv'])));
        $this->assertSame(['Alpha Agro', '9000000001', '0792111111', 'a@agro.test', '24AAAAA0000A1Z5', 'Rajkot, Gujarat', '1 Farm Rd', '5,000.00', '750.25', 'Active'], $rows[1]);
        $this->assertSame(['Beta Bulk', '-', '-', '-', '-', 'Maharashtra', '-', '0.00', '0.00', 'Inactive'], $rows[2]);
        $this->assertSame('-', $rows[3][5]);
    }

    // ---------------------------------------------------------------- tender / EOD / audit

    public function test_tender_summary_groups_by_tender_and_excludes_cancelled_bills(): void
    {
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $card = TenderType::create(['name' => 'HDFC Card', 'type' => 'Card', 'status' => true]);
        $pay = fn ($bill, $t, $amt) => SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $t->id, 'amount' => $amt]);
        $b1 = $this->bill('TS-1', '2026-09-05', $this->b1, 300);
        $pay($b1, $cash, 100);
        $pay($b1, $card, 200);
        $b2 = $this->bill('TS-2', '2026-09-06', $this->b2, 50);
        $pay($b2, $cash, 50);
        $pay($this->bill('TS-CAN', '2026-09-06', $this->b1, 900, 'Cancelled'), $cash, 900);
        $pay($this->bill('TS-OLD', '2026-01-06', $this->b1, 700), $cash, 700);

        $r = $this->get(route('reports.tender-summary', $this->range()))->assertOk();
        $rows = $r->viewData('rows');
        $this->assertCount(2, $rows);
        $c = $rows->firstWhere('tender_name', 'Cash');
        $this->assertSame([2, 150.0, 'Cash'], [$c->count, $c->total_amount, $c->type]);
        $this->assertSame(200.0, $rows->firstWhere('tender_name', 'HDFC Card')->total_amount);
        $this->assertSame(350.0, $r->viewData('totalCollected'));

        $r = $this->get(route('reports.tender-summary', $this->range() + ['branch_id' => $this->b2->id]));
        $this->assertSame(50.0, $r->viewData('totalCollected'));
        $r = $this->get(route('reports.tender-summary', $this->range() + ['tender_type_id' => $card->id]));
        $this->assertSame(200.0, $r->viewData('totalCollected'));
        $this->assertCount(1, $r->viewData('rows'));
    }

    public function test_eod_totals_payments_unattributed_and_till_variance(): void
    {
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $upi = TenderType::create(['name' => 'UPI', 'type' => 'Wallet', 'status' => true]);
        $reg = Register::create(['branch_id' => $this->b1->id, 'name' => 'Counter 1', 'status' => 'Active']);
        $till = TillSession::create(['register_id' => $reg->id, 'branch_id' => $this->b1->id, 'user_id' => $this->owner->id, 'opening_cash' => 500, 'opened_at' => '2026-09-05 09:00:00', 'status' => 'Closed', 'variance' => -20.5]);
        $till2 = TillSession::create(['register_id' => $reg->id, 'branch_id' => $this->b1->id, 'user_id' => $this->owner->id, 'opening_cash' => 500, 'opened_at' => '2026-09-06 09:00:00', 'status' => 'Open']);

        $a = $this->bill('EO-1', '2026-09-05', $this->b1, 1000, 'Posted', null, ['disc_amount' => 20, 'total_gst' => 100, 'till_session_id' => $till->id]);
        SalesBillPayment::create(['sales_bill_id' => $a->id, 'tender_type_id' => $cash->id, 'amount' => 600]);
        SalesBillPayment::create(['sales_bill_id' => $a->id, 'tender_type_id' => $upi->id, 'amount' => 400]);
        $this->bill('EO-2', '2026-09-05', $this->b1, 250, 'Posted', null, ['disc_amount' => 5, 'total_gst' => 25, 'till_session_id' => $till2->id]); // no payments
        $this->bill('EO-CAN', '2026-09-05', $this->b1, 5000, 'Cancelled', null, ['till_session_id' => $till->id]);
        $this->bill('EO-B2', '2026-09-05', $this->b2, 77);
        $c = Customer::create(['name' => 'Ret', 'status' => true]);
        SalesReturn::create(['return_number' => 'EOR-1', 'return_date' => '2026-09-05', 'customer_id' => $c->id, 'branch_id' => $this->b1->id, 'total' => 118]);
        SalesReturn::create(['return_number' => 'EOR-C', 'return_date' => '2026-09-05', 'customer_id' => $c->id, 'branch_id' => $this->b1->id, 'total' => 999, 'status' => 'Cancelled']);

        $r = $this->get(route('reports.eod', $this->range() + ['branch_id' => $this->b1->id]))->assertOk();
        $s = $r->viewData('summary');
        $this->assertSame(2, $s['sales_count']);
        $this->assertSame(1250.0, $s['sales_total']);
        $this->assertSame(25.0, $s['discount_total']);
        $this->assertSame(125.0, $s['gst_total']);
        $this->assertSame([1, 118.0], [$s['returns_count'], $s['returns_total']]);
        $this->assertSame(['Cash' => 600.0, 'Wallet' => 400.0], $s['payment_totals']->map(fn ($v) => (float) $v)->all());
        $this->assertSame(250.0, $s['unattributed_total']);
        $this->assertSame(-20.5, $s['till_variance_total']);
        $this->assertCount(2, $r->viewData('tillSessions'));

        $r = $this->get(route('reports.eod', $this->range() + ['branch_id' => $this->b1->id, 'till_session_id' => $till->id]));
        $s = $r->viewData('summary');
        $this->assertSame(1000.0, $s['sales_total']);
        $this->assertSame(0.0, $s['unattributed_total']);
        $this->assertCount(1, $r->viewData('tillSessions'));

        $s = $this->get(route('reports.eod', $this->range()))->viewData('summary');
        $this->assertSame(1327.0, $s['sales_total']);
    }

    public function test_audit_logs_filters_and_csv(): void
    {
        $other = User::factory()->create(['name' => 'Other Person']);
        $mk = fn ($user, $action, $type, $id, $reason, $ip) => AuditLog::create(['user_id' => $user?->id, 'action' => $action, 'auditable_type' => $type, 'auditable_id' => $id, 'reason' => $reason, 'ip_address' => $ip]);
        $mk($this->owner, 'updated', 'App\\Models\\Item', 5, 'Price fix', '10.1.1.1');
        $mk($other, 'deleted', 'App\\Models\\SalesBill', 9, 'Duplicate', '10.2.2.2');
        $old = $mk(null, 'created', 'App\\Models\\Customer', 1, null, null);
        $old->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $today = now()->format('Y-m-d');
        $q = ['from' => $today, 'to' => $today];
        $names = fn ($r) => $r->viewData('logs')->pluck('action')->sort()->values()->all();
        $this->assertSame(['deleted', 'updated'], $names($this->get(route('reports.audit-logs', $q))->assertOk()));
        $this->assertSame(['updated'], $names($this->get(route('reports.audit-logs', $q + ['user_id' => $this->owner->id]))));
        $this->assertSame(['deleted'], $names($this->get(route('reports.audit-logs', $q + ['action' => 'deleted']))));
        $this->assertSame(['deleted'], $names($this->get(route('reports.audit-logs', $q + ['search' => 'SalesBill']))));
        $this->assertSame(['updated'], $names($this->get(route('reports.audit-logs', $q + ['search' => 'Price fix']))));
        $this->assertSame(['deleted'], $names($this->get(route('reports.audit-logs', $q + ['search' => '10.2.2']))));
        $this->assertSame(['created'], $names($this->get(route('reports.audit-logs', ['from' => '2026-01-01', 'to' => '2026-01-02']))));

        $rows = $this->csv($this->get(route('reports.audit-logs', ['from' => '2026-01-01', 'to' => $today, 'export' => 'csv'])));
        $this->assertSame(['Date & Time', 'User', 'Action', 'Entity', 'Entity ID', 'Reason / Description', 'IP Address'], $rows[0]);
        $this->assertCount(4, $rows);
        $sys = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'CREATED');
        $this->assertSame(['01-01-2026 10:00:00', 'System', 'CREATED', 'Customer', '1', '-', '-'], $sys);
        $upd = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'UPDATED');
        $this->assertSame(['Rita Reporter', 'UPDATED', 'Item', '5', 'Price fix', '10.1.1.1'], array_slice($upd, 1));
    }

    // ---------------------------------------------------------------- margin

    public function test_sales_margin_itemwise_numbers_and_filters_exclude_cancelled(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $catVal = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $food = $this->item('Food', ['item_code' => 'FD1', 'brand_id' => $brand->id, 'category_value_id' => $catVal->id]);
        $toy = $this->item('Toy', ['item_code' => 'TY1']);
        $cust = Customer::create(['name' => 'Margin Mo', 'status' => true]);
        $line = fn ($bill, $item, $qty, $net, $cost) => SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => $net / $qty, 'net_amount' => $net, 'cost_at_sale' => $cost]);
        $b = $this->bill('MG-1', '2026-09-05', $this->b1, 500, 'Posted', $cust);
        $line($b, $food, 2, 200, 60);   // margin 80
        $line($b, $toy, 1, 100, 100);   // margin 0
        $b2 = $this->bill('MG-2', '2026-09-06', $this->b2, 300);
        $line($b2, $toy, 3, 300, 50);   // margin 150
        $line($this->bill('MG-CAN', '2026-09-06', $this->b1, 999, 'Cancelled'), $food, 1, 999, 1);
        $line($this->bill('MG-OUT', '2026-01-06', $this->b1, 999), $food, 1, 999, 1);

        $r = $this->get(route('reports.sales-margin-itemwise', $this->range()))->assertOk();
        $t = $r->viewData('totals');
        $this->assertEquals([600.0, 370.0, 230.0], [$t['sell_total'], $t['cog_total'], $t['gross_margin']]);
        $this->assertEqualsWithDelta(38.333, $t['margin_pct'], 0.01);
        $this->assertCount(3, $r->viewData('lines'));
        $foodLine = $r->viewData('lines')->firstWhere('item_code', 'FD1');
        $this->assertEquals([80.0, 40.0, 'Acme', 'Food', 'Margin Mo'], [$foodLine->gross_margin, $foodLine->margin_pct, $foodLine->brand_name, $foodLine->category_name, $foodLine->customer_name]);

        $this->assertCount(1, $this->get(route('reports.sales-margin-itemwise', $this->range() + ['branch_id' => $this->b2->id]))->viewData('lines'));
        $this->assertCount(1, $this->get(route('reports.sales-margin-itemwise', $this->range() + ['brand_id' => $brand->id]))->viewData('lines'));
        $this->assertCount(1, $this->get(route('reports.sales-margin-itemwise', $this->range() + ['category_value_id' => $catVal->id]))->viewData('lines'));
        $this->assertCount(2, $this->get(route('reports.sales-margin-itemwise', $this->range() + ['customer_id' => $cust->id]))->viewData('lines'));
        $this->assertCount(2, $this->get(route('reports.sales-margin-itemwise', $this->range() + ['search' => 'TY1']))->viewData('lines'));
        $this->assertSame('Margin Mo', $this->get(route('reports.sales-margin-itemwise', $this->range() + ['customer_id' => $cust->id]))->viewData('customers')[$cust->id]);
    }

    public function test_sales_margin_categorywise_groups_and_sorts_by_margin(): void
    {
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $catVal = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $food = $this->item('Food', ['category_value_id' => $catVal->id]);
        $misc = $this->item('Misc');
        $line = fn ($bill, $item, $qty, $net, $cost) => SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => $net / $qty, 'net_amount' => $net, 'cost_at_sale' => $cost]);
        $b = $this->bill('CM-1', '2026-09-05', $this->b1, 500);
        $line($b, $food, 2, 200, 60);
        $line($b, $misc, 1, 100, 10);
        $line($this->bill('CM-CAN', '2026-09-05', $this->b1, 500, 'Cancelled'), $food, 1, 5000, 1);

        $r = $this->get(route('reports.sales-margin-category', $this->range()))->assertOk();
        $g = $r->viewData('grouped');
        $this->assertSame(['Uncategorised', 'Food'], $g->pluck('category_name')->all()); // 90 margin beats 80
        $this->assertEquals([90.0, 100.0, 90.0], [$g[0]->gross_margin, $g[0]->sell_total, $g[0]->margin_pct]);
        $this->assertEquals([2.0, 80.0, 40.0], [(float) $g[1]->qty, $g[1]->gross_margin, $g[1]->margin_pct]);
        $this->assertEquals([300.0, 170.0], [$r->viewData('totals')['sell_total'], $r->viewData('totals')['gross_margin']]);
        $this->assertSame(['Uncategorised', 'Food'], $r->viewData('chartLabels')->all());
    }

    // ---------------------------------------------------------------- quotations / orders

    public function test_quotation_order_summary_counts_open_documents_and_filters(): void
    {
        $c1 = Customer::create(['name' => 'Quote Quinn', 'status' => true]);
        $c2 = Customer::create(['name' => 'Order Omar', 'status' => true]);
        $mkQ = fn ($no, $d, $c, $b, $st, $total = 100) => SalesQuotation::create(['quotation_number' => $no, 'quotation_date' => $d, 'valid_until' => '2026-10-30', 'customer_id' => $c->id, 'branch_id' => $b->id, 'status' => $st, 'total' => $total]);
        $mkO = fn ($no, $d, $c, $b, $st, $total = 100) => SalesOrder::create(['order_number' => $no, 'order_date' => $d, 'customer_id' => $c->id, 'branch_id' => $b->id, 'status' => $st, 'total' => $total]);
        $mkQ('Q-1', '2026-09-03', $c1, $this->b1, 'Draft');
        $mkQ('Q-2', '2026-09-04', $c1, $this->b1, 'Converted');
        $mkQ('Q-OLD', '2026-01-04', $c1, $this->b1, 'Draft');
        $mkO('O-1', '2026-09-05', $c2, $this->b2, 'Open');
        $mkO('O-2', '2026-09-06', $c2, $this->b2, 'Partially Fulfilled');
        $mkO('O-3', '2026-09-07', $c2, $this->b1, 'Cancelled');

        $r = $this->get(route('reports.quotation-order-summary', $this->range()))->assertOk();
        $this->assertSame(['O-3', 'O-2', 'O-1', 'Q-2', 'Q-1'], $r->viewData('rows')->pluck('number')->all());
        $this->assertSame(['total_quotations' => 2, 'total_orders' => 3, 'open' => 3, 'converted' => 1, 'cancelled' => 1], $r->viewData('summary'));
        $this->assertContains('Open', $r->viewData('statuses'));

        $r = $this->get(route('reports.quotation-order-summary', $this->range() + ['type' => 'order']));
        $this->assertSame(['total_quotations' => 0, 'total_orders' => 3, 'open' => 2, 'converted' => 0, 'cancelled' => 1], $r->viewData('summary'));
        $r = $this->get(route('reports.quotation-order-summary', $this->range() + ['type' => 'quotation', 'status' => 'Converted']));
        $this->assertSame(['Q-2'], $r->viewData('rows')->pluck('number')->all());
        $r = $this->get(route('reports.quotation-order-summary', $this->range() + ['branch_id' => $this->b2->id]));
        $this->assertSame(['O-2', 'O-1'], $r->viewData('rows')->pluck('number')->all());
        $r = $this->get(route('reports.quotation-order-summary', $this->range() + ['customer_id' => $c1->id]));
        $this->assertSame(['Q-2', 'Q-1'], $r->viewData('rows')->pluck('number')->all());
        $this->assertSame('Quote Quinn', $r->viewData('customers')[$c1->id]);
    }

    public function test_selected_customer_outside_first_30_stays_selectable_in_filtered_reports(): void
    {
        foreach (range(1, 31) as $i) {
            Customer::create(['name' => sprintf('A%02d Filler', $i), 'status' => true]);
        }
        $zed = Customer::create(['name' => 'Zzz Selected', 'mobile' => '9000000000', 'status' => true]);

        $r = $this->get(route('reports.sales-return-summary', ['customer_id' => $zed->id]));
        $this->assertSame('Zzz Selected (9000000000)', $r->viewData('customers')[$zed->id]);
        $r = $this->get(route('reports.sales-margin-itemwise', ['customer_id' => $zed->id]));
        $this->assertSame('Zzz Selected', $r->viewData('customers')[$zed->id]);
        $r = $this->get(route('reports.quotation-order-summary', ['customer_id' => $zed->id]));
        $this->assertSame('Zzz Selected', $r->viewData('customers')[$zed->id]);
        $this->assertCount(31, $r->viewData('customers'));
        // an unknown id must not crash or add a phantom option
        $r = $this->get(route('reports.quotation-order-summary', ['customer_id' => 999999]));
        $this->assertCount(30, $r->viewData('customers'));
    }
}
