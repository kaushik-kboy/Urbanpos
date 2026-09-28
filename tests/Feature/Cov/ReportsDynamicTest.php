<?php

namespace Tests\Feature\Cov;

use App\Models\AuditLog;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\ClosingStock;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the DynamicReportService through the generic report route (reports.view)
 * with seeded data, asserting rows, filters and KPI totals.
 */
class ReportsDynamicTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b1;
    private Branch $b2;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->owner = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'olivia@example.test']);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->b1 = Branch::create(['name' => 'Main Store', 'state' => 'Gujarat', 'erp_code' => 'MS01', 'gst_no' => '24AAAAA0000A1Z5', 'status' => true]);
        $this->b2 = Branch::create(['name' => 'Second Store', 'state' => 'Gujarat', 'status' => true]);
    }

    private function rpt(string $slug, array $q = [])
    {
        return $this->get(route('reports.view', ['module' => $slug] + $q))->assertOk();
    }

    /** Rows as plain text, one string per row. */
    private function texts($r): array
    {
        return collect($r->viewData('rows')->items())
            ->map(fn ($x) => html_entity_decode(trim(preg_replace('/\s+/', ' ', strip_tags(implode(' | ', $x['cells']))))))
            ->all();
    }

    private function kpi($r, string $label)
    {
        return collect($r->viewData('kpis'))->firstWhere('label', $label)['value'] ?? null;
    }

    private function item(string $name, array $extra = []): Item
    {
        return Item::create($extra + ['name' => $name, 'item_code' => strtoupper(substr($name, 0, 3)) . rand(100, 999), 'sell_price' => 100, 'cost_price' => 60, 'mrp' => 120, 'status' => true]);
    }

    private function bill(string $no, string $date, Branch $b, float $total, string $status = 'Posted', ?Customer $c = null, array $extra = []): SalesBill
    {
        $c ??= Customer::create(['name' => 'Cust ' . $no, 'mobile' => '90000' . rand(10000, 99999), 'status' => true]);
        return SalesBill::create($extra + [
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $b->id,
            'sales_type' => 'Local', 'total' => $total, 'status' => $status, 'total_qty' => 2, 'total_gst' => $total * 0.1, 'disc_amount' => 5,
        ]);
    }

    // ---------------------------------------------------------------- unknown / dispatch

    public function test_unknown_module_falls_back_to_sales_feed_within_range_and_branch(): void
    {
        $this->bill('U-IN', '2026-09-10', $this->b1, 500);
        $this->bill('U-OUT', '2026-07-10', $this->b1, 800);
        $this->bill('U-B2', '2026-09-10', $this->b2, 900);

        $r = $this->rpt('zzz-unknown-report', ['from' => '2026-09-01', 'to' => '2026-09-30', 'branch_id' => $this->b1->id]);
        $this->assertSame('Zzz Unknown Report Report', $r->viewData('title'));
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('U-IN', $rows[0]);
        $this->assertStringContainsString('₹ 500.00', $rows[0]);
        $this->assertSame(1, $this->kpi($r, 'Records in Period'));

        $r = $this->rpt('zzz-unknown-report', ['from' => '2026-01-01', 'to' => '2026-12-31']);
        $this->assertSame(3, $this->kpi($r, 'Records in Period'));
    }

    // ---------------------------------------------------------------- masters

    public function test_brand_master_lists_brands_with_search_and_kpis(): void
    {
        Brand::create(['name' => 'Royal Canin', 'prefix' => 'RC', 'status' => true]);
        Brand::create(['name' => 'Pedigree', 'prefix' => 'PD', 'status' => false]);

        $r = $this->rpt('brand-master');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Pedigree', $rows[0]); // ordered by name
        $this->assertStringContainsString('Inactive', $rows[0]);
        $this->assertStringContainsString('Active', $rows[1]);
        $this->assertSame(2, $this->kpi($r, 'Total Brands'));
        $this->assertSame(1, $this->kpi($r, 'Active Brands'));

        $rows = $this->texts($this->rpt('brand-master', ['search' => 'royal']));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Royal Canin', $rows[0]);
    }

    public function test_tax_master_shows_split_rates_and_supports_search(): void
    {
        GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        GstTax::create(['description' => 'GST 5%', 'percentage' => 5, 'status' => true]);

        $r = $this->rpt('tax-master');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('GST 5%', $rows[0]);       // ordered by rate ascending
        $this->assertStringContainsString('2.50% | 2.50% | 5.00%', $rows[0]);
        $this->assertStringContainsString('GST 18%', $rows[1]);
        $this->assertStringContainsString('9.00% | 9.00% | 18.00%', $rows[1]);
        $this->assertSame(2, $this->kpi($r, 'Configured Tax Slabs'));

        $rows = $this->texts($this->rpt('tax-master', ['search' => 'GST 18']));
        $this->assertCount(1, $rows);
    }

    public function test_area_master_names_branch_and_search(): void
    {
        Area::create(['name' => 'Adajan', 'branch_id' => $this->b1->id, 'status' => true]);
        Area::create(['name' => 'Vesu', 'status' => false]);

        $r = $this->rpt('area');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Adajan | - | Main Store', $rows[0]);
        $this->assertStringContainsString('Vesu | - | All Branches', $rows[1]);
        $this->assertSame(2, $this->kpi($r, 'Total Defined Areas'));
        $this->assertCount(1, $this->texts($this->rpt('area', ['search' => 'vesu'])));
    }

    public function test_branch_master_counts_bills_and_stock_batches(): void
    {
        $it = $this->item('Kibble');
        ItemStock::create(['item_id' => $it->id, 'branch_id' => $this->b1->id, 'quantity' => 5]);
        $this->bill('BM-1', '2026-09-10', $this->b1, 100);
        $this->bill('BM-2', '2026-09-11', $this->b1, 100);

        $r = $this->rpt('branch-master');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $main = collect($rows)->first(fn ($t) => str_contains($t, 'Main Store'));
        $this->assertStringContainsString('MS01', $main);
        $this->assertStringContainsString('24AAAAA0000A1Z5', $main);
        $this->assertMatchesRegularExpression('/\| 2 \| 1 \| Active$/', $main); // 2 bills, 1 stock batch
        $this->assertSame(2, $this->kpi($r, 'Operational Branches'));
    }

    public function test_price_list_margin_and_search(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'status' => true]);
        $this->item('Widget', ['item_code' => 'W1', 'brand_id' => $brand->id, 'mrp' => 200, 'sell_price' => 150, 'cost_price' => 90]);
        $this->item('Gadget', ['item_code' => 'G1', 'mrp' => 0, 'sell_price' => 50, 'cost_price' => 10]);

        $r = $this->rpt('price-list');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $gadget = $rows[0];
        $widget = $rows[1];
        $this->assertStringContainsString('₹ 150.00', $widget);
        $this->assertStringContainsString('Acme', $widget);
        $this->assertStringEndsWith('40.0%', $widget);   // (150-90)/150
        $this->assertStringEndsWith('0.0%', $gadget);    // mrp 0 => margin suppressed
        $this->assertSame(2, $this->kpi($r, 'Total Priced SKUs'));
        $this->assertSame('₹ 100.00', $this->kpi($r, 'Avg Sell Price'));

        $this->assertCount(1, $this->texts($this->rpt('price-list', ['search' => 'W1'])));
    }

    public function test_supplier_vs_items_only_mapped_items(): void
    {
        $s = Supplier::create(['name' => 'Acme Traders', 'status' => true]);
        $this->item('Mapped', ['supplier_id' => $s->id, 'cost_price' => 40, 'sell_price' => 70, 'mrp' => 80]);
        $this->item('Unmapped');

        $r = $this->rpt('supplier-vs-items');
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Acme Traders', $rows[0]);
        $this->assertStringContainsString('₹ 40.00 | ₹ 70.00 | ₹ 80.00', $rows[0]);
        $this->assertSame(1, $this->kpi($r, 'Mapped SKUs'));
        $this->assertCount(1, $this->texts($this->rpt('supplier-vs-items', ['search' => 'Acme'])));
        $this->assertCount(0, $this->texts($this->rpt('supplier-vs-items', ['search' => 'nothing-here'])));
    }

    public function test_employee_master_and_user_login_summary_show_roles_and_active_status(): void
    {
        $this->owner->update(['branch_id' => $this->b1->id]);

        foreach (['employee-master', 'user-login-summary'] as $slug) {
            $rows = $this->texts($this->rpt($slug, ['search' => 'olivia']));
            $this->assertCount(1, $rows, $slug);
            $this->assertStringContainsString('Olivia Owner', $rows[0]);
            $this->assertStringContainsString('olivia@example.test', $rows[0]);
            $this->assertStringContainsString('Owner', $rows[0]);
            $this->assertStringContainsString('Main Store', $rows[0]);
            $this->assertStringContainsString('Active', $rows[0]);
            $this->assertStringNotContainsString('Inactive', $rows[0]);
            $this->assertStringNotContainsString('Disabled', $rows[0]);
        }
        $this->assertSame(2, $this->kpi($this->rpt('employee-master'), 'Registered Staff'));
    }

    public function test_generic_master_fallback_lists_items(): void
    {
        $this->item('Alpha Item', ['item_code' => 'A1', 'sell_price' => 33, 'product_type' => 'Service']);

        $r = $this->rpt('kit-mapping');
        $this->assertSame('Kit Mapping Report', $r->viewData('title'));
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('A1 | Alpha Item | - | Service | ₹ 33.00 | Active', $rows[0]);
        $this->assertCount(0, $this->texts($this->rpt('kit-mapping', ['search' => 'zzz'])));
    }

    // ---------------------------------------------------------------- purchase

    public function test_purchase_summary_filters_dates_branch_search_and_totals(): void
    {
        $s1 = Supplier::create(['name' => 'Alpha Supplies', 'status' => true]);
        $s2 = Supplier::create(['name' => 'Beta Wholesale', 'status' => true]);
        $mk = fn ($no, $d, $s, $b, $tot, $gst, $qty) => PurchaseInvoice::create([
            'invoice_number' => $no, 'invoice_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $b->id,
            'total' => $tot, 'total_gst' => $gst, 'total_qty' => $qty, 'status' => 'Posted', 'supplier_inv_no' => 'V-' . $no,
        ]);
        $mk('PI-1', '2026-09-10', $s1, $this->b1, 1180, 180, 10);
        $mk('PI-2', '2026-09-12', $s2, $this->b2, 590, 90, 5);
        $mk('PI-OLD', '2026-05-01', $s1, $this->b1, 9999, 0, 99);

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $r = $this->rpt('purchase-summary', $q);
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('PI-2', $rows[0]); // date desc
        $this->assertStringContainsString('₹ 1,000.00 | ₹ 180.00 | ₹ 1,180.00', $rows[1]); // taxable = total - gst
        $this->assertSame('₹ 1,770.00', $this->kpi($r, 'Total Purchase Value'));
        $this->assertSame('15', $this->kpi($r, 'Total Qty Received'));
        $this->assertSame(2, $this->kpi($r, 'Invoices Count'));

        $r = $this->rpt('purchase-register-summary', $q + ['branch_id' => $this->b2->id]);
        $this->assertCount(1, $this->texts($r));
        $this->assertSame('₹ 590.00', $this->kpi($r, 'Total Purchase Value'));

        $this->assertCount(1, $this->texts($this->rpt('purchase-summary', $q + ['search' => 'Beta'])));
        $this->assertCount(1, $this->texts($this->rpt('purchase-summary', $q + ['search' => 'V-PI-1'])));
    }

    public function test_purchase_order_details_and_pending_cancelled(): void
    {
        $sup = Supplier::create(['name' => 'PO Supplier', 'status' => true]);
        $item = $this->item('POItem', ['item_code' => 'POI1']);
        $po = PurchaseOrder::create(['po_number' => 'PO-1', 'po_date' => '2026-09-10', 'supplier_id' => $sup->id, 'branch_id' => $this->b1->id, 'total' => 500, 'total_qty' => 10, 'status' => 'Open']);
        DB::table('purchase_order_items')->insert(['purchase_order_id' => $po->id, 'item_id' => $item->id, 'qty' => 10, 'cost_price' => 50, 'gst_tax_amount' => 90, 'net_amount' => 590, 'created_at' => now(), 'updated_at' => now()]);
        PurchaseOrder::create(['po_number' => 'PO-2', 'po_date' => '2026-09-11', 'supplier_id' => $sup->id, 'branch_id' => $this->b2->id, 'total' => 300, 'total_qty' => 3, 'status' => 'Cancelled', 'cancellation_reason' => 'Vendor out of stock']);
        PurchaseOrder::create(['po_number' => 'PO-3', 'po_date' => '2026-09-11', 'supplier_id' => $sup->id, 'branch_id' => $this->b1->id, 'total' => 100, 'total_qty' => 1, 'status' => 'Closed']);

        $r = $this->rpt('purchase-order-details', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('PO-1', $rows[0]);
        $this->assertStringContainsString('POI1 | POItem | 10 | ₹ 50.00 | ₹ 90.00 | ₹ 590.00', $rows[0]);
        $this->assertSame(1, $this->kpi($r, 'Total Line Items'));
        $this->assertCount(0, $this->texts($this->rpt('purchase-order-details', ['from' => '2026-09-01', 'to' => '2026-09-30', 'branch_id' => $this->b2->id])));
        $this->assertCount(1, $this->texts($this->rpt('purchase-order-details', ['from' => '2026-09-01', 'to' => '2026-09-30', 'search' => 'POItem'])));

        $r = $this->rpt('purchase-pending-cancelled');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows); // Open + Cancelled, not Closed
        $this->assertSame(2, $this->kpi($r, 'Pending / Cancelled Orders'));
        $all = implode("\n", $rows);
        $this->assertStringContainsString('Vendor out of stock', $all);
        $this->assertStringNotContainsString('PO-3', $all);
        $this->assertCount(1, $this->texts($this->rpt('purchase-pending-cancelled', ['branch_id' => $this->b2->id])));
    }

    public function test_supplierwise_purchase_summary_aggregates_per_supplier(): void
    {
        $a = Supplier::create(['name' => 'Agg A', 'status' => true]);
        $b = Supplier::create(['name' => 'Agg B', 'status' => true]);
        $mk = fn ($no, $d, $s, $br, $tot, $gst, $qty) => PurchaseInvoice::create(['invoice_number' => $no, 'invoice_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $br->id, 'total' => $tot, 'total_gst' => $gst, 'total_qty' => $qty, 'status' => 'Posted']);
        $mk('AG-1', '2026-09-01', $a, $this->b1, 118, 18, 1);
        $mk('AG-2', '2026-09-02', $a, $this->b1, 236, 36, 2);
        $mk('AG-3', '2026-09-03', $b, $this->b1, 1000, 0, 4);
        $mk('AG-4', '2026-09-03', $a, $this->b2, 5000, 0, 9);   // other branch
        $mk('AG-5', '2025-01-03', $a, $this->b1, 7000, 0, 9);   // out of range

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30', 'branch_id' => $this->b1->id];
        $rows = $this->texts($this->rpt('supplierwise-purchase-summary', $q));
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Agg B | 1 | 4 | ₹ 1,000.00 | ₹ 0.00 | ₹ 1,000.00', $rows[0]); // ordered by total desc
        $this->assertStringContainsString('Agg A | 2 | 3 | ₹ 300.00 | ₹ 54.00 | ₹ 354.00', $rows[1]);
        $this->assertCount(1, $this->texts($this->rpt('supplierwise-purchase-summary', $q + ['search' => 'Agg A'])));
    }

    public function test_purchase_fallback_report_lists_invoices(): void
    {
        $s = Supplier::create(['name' => 'FB Sup', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'FB-1', 'invoice_date' => '2026-09-05', 'supplier_id' => $s->id, 'branch_id' => $this->b1->id, 'total' => 250, 'total_qty' => 2, 'status' => 'Posted']);
        $r = $this->rpt('gin-summary', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertSame('Gin Summary Report', $r->viewData('title'));
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('FB-1', $rows[0]);
        $this->assertStringContainsString('₹ 250.00', $rows[0]);
    }

    // ---------------------------------------------------------------- audit

    public function test_audit_reports_map_action_type_and_reason(): void
    {
        $mk = fn ($action, $type, $reason) => AuditLog::create(['user_id' => $this->owner->id, 'action' => $action, 'auditable_type' => $type, 'auditable_id' => 1, 'reason' => $reason, 'ip_address' => '10.0.0.5']);
        $mk('updated', 'App\\Models\\GstTax', 'Rate changed 12 to 18');
        $mk('deleted', 'App\\Models\\SalesBill', 'Duplicate bill');

        $rows = $this->texts($this->rpt('gst-tax-change-audit'));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Olivia Owner | updated | GstTax | Rate changed 12 to 18 | 10.0.0.5', $rows[0]);

        $r = $this->rpt('audit-detail-report');
        $this->assertCount(2, $this->texts($r));
        $this->assertSame(2, $this->kpi($r, 'Total Audit Logs'));
        $rows = $this->texts($this->rpt('audit-detail-report', ['search' => 'Duplicate']));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('SalesBill', $rows[0]);
        $this->assertCount(1, $this->texts($this->rpt('foot-fall-details', ['search' => 'deleted'])));
    }

    // ---------------------------------------------------------------- sales

    public function test_monthly_sales_summary_storewise_excludes_cancelled_and_groups(): void
    {
        $this->bill('M-1', '2026-09-05', $this->b1, 1000);
        $this->bill('M-2', '2026-09-15', $this->b1, 500);
        $this->bill('M-CAN', '2026-09-16', $this->b1, 7000, 'Cancelled');
        $this->bill('M-AUG', '2026-08-15', $this->b1, 300);
        $this->bill('M-B2', '2026-09-15', $this->b2, 200);

        $r = $this->rpt('monthly-sales-summary-storewise');
        $rows = $this->texts($r);
        $this->assertCount(3, $rows);
        $sep = collect($rows)->first(fn ($t) => str_contains($t, 'September 2026') && str_contains($t, 'Main Store'));
        $this->assertStringContainsString('| 2 | 4 |', $sep);            // 2 bills, qty 2+2
        $this->assertStringContainsString('₹ 10.00 | ₹ 150.00 | ₹ 1,500.00', $sep); // disc 5+5, gst 100+50, total
        $this->assertSame('₹ 2,000.00', $this->kpi($r, 'Total Sales'));
        $this->assertSame(4, $this->kpi($r, 'Total Bills Processed'));

        $r = $this->rpt('monthly-sales-summary-storewise', ['branch_id' => $this->b2->id]);
        $this->assertCount(1, $this->texts($r));
        $this->assertSame('₹ 200.00', $this->kpi($r, 'Total Sales'));
    }

    public function test_daily_sales_billwise_range_branch_search_and_period_total(): void
    {
        $cust = Customer::create(['name' => 'Ramesh Kumar', 'mobile' => '9876500001', 'status' => true]);
        $this->bill('D-1', '2026-09-10', $this->b1, 1000, 'Posted', $cust);
        $this->bill('D-2', '2026-09-11', $this->b2, 400);
        $this->bill('D-CAN', '2026-09-11', $this->b1, 5000, 'Cancelled');
        $this->bill('D-OLD', '2026-06-11', $this->b1, 800);

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $r = $this->rpt('daily-sales-billwise', $q);
        $this->assertCount(3, $this->texts($r));     // register lists cancelled bills too
        $this->assertSame('₹ 1,400.00', $this->kpi($r, 'Period Sales Total')); // ...but they do not count as sales
        $this->assertSame(3, $this->kpi($r, 'Total Bills'));

        $r = $this->rpt('daily-sales-timefilter', $q + ['branch_id' => $this->b2->id]);
        $this->assertSame('₹ 400.00', $this->kpi($r, 'Period Sales Total'));

        $rows = $this->texts($this->rpt('offline-sales-bill-details', $q + ['search' => '98765']));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('D-1 | 10 Sep 2026', $rows[0]);
        $this->assertStringContainsString('Ramesh Kumar | 9876500001 | Main Store | 2 | ₹ 5.00 | ₹ 100.00 | ₹ 1,000.00', $rows[0]);
    }

    public function test_customerwise_itemwise_sales_lines(): void
    {
        $item = $this->item('Chew Toy', ['item_code' => 'CT1']);
        $cust = Customer::create(['name' => 'Meera Shah', 'mobile' => '9000011111', 'status' => true]);
        $b = $this->bill('CI-1', '2026-09-10', $this->b1, 236, 'Posted', $cust);
        SalesBillItem::create(['sales_bill_id' => $b->id, 'item_id' => $item->id, 'qty' => 2, 'sell_price' => 100, 'net_amount' => 236]);
        $old = $this->bill('CI-OLD', '2026-01-10', $this->b1, 50, 'Posted', $cust);
        SalesBillItem::create(['sales_bill_id' => $old->id, 'item_id' => $item->id, 'qty' => 1, 'sell_price' => 50, 'net_amount' => 50]);

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $rows = $this->texts($this->rpt('customerwise-itemwise-sales', $q));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Meera Shah | 9000011111 | CI-1 | CT1 | Chew Toy | 2 | ₹ 100.00 | ₹ 236.00', $rows[0]);
        $this->assertCount(1, $this->texts($this->rpt('itemwise-customerwise-sales', $q + ['search' => 'Meera'])));
        $this->assertCount(0, $this->texts($this->rpt('itemwise-customerwise-sales', $q + ['branch_id' => $this->b2->id])));
    }

    public function test_sales_order_delivery_note_and_return_reports(): void
    {
        $cust = Customer::create(['name' => 'Order Cust', 'status' => true]);
        SalesOrder::create(['order_number' => 'SO-1', 'order_date' => '2026-09-10', 'customer_id' => $cust->id, 'branch_id' => $this->b1->id, 'total' => 1180, 'total_gst' => 180, 'advance_amount' => 300, 'status' => 'Open']);
        SalesOrder::create(['order_number' => 'SO-2', 'order_date' => '2026-09-10', 'customer_id' => $cust->id, 'branch_id' => $this->b2->id, 'total' => 50, 'status' => 'Open']);

        $r = $this->rpt('sales-order-summary', ['branch_id' => $this->b1->id]);
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('SO-1 | 10 Sep 2026 | Order Cust | Main Store | ₹ 180.00 | ₹ 1,180.00 | ₹ 300.00 | Open', $rows[0]);
        $this->assertSame(1, $this->kpi($r, 'Orders Count'));
        $this->assertCount(2, $this->texts($this->rpt('quotation-details')));
        $this->assertCount(1, $this->texts($this->rpt('sales-order-summary', ['search' => 'SO-2'])));

        SalesDeliveryNote::create(['delivery_number' => 'DN-1', 'delivery_date' => '2026-09-12', 'customer_id' => $cust->id, 'branch_id' => $this->b1->id, 'total_ordered_qty' => 10, 'total_dispatched_qty' => 8, 'total_amount' => 800, 'status' => 'Dispatched']);
        $r = $this->rpt('sales-deliverynote-summary');
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('DN-1 | 12 Sep 2026 | Order Cust | Main Store | 10 | 8 | ₹ 800.00 | Dispatched', $rows[0]);
        $this->assertCount(0, $this->texts($this->rpt('sales-deliverynote-detail', ['branch_id' => $this->b2->id])));

        $bill = $this->bill('RB-1', '2026-09-01', $this->b1, 300, 'Posted', $cust);
        SalesReturn::create(['return_number' => 'SR-1', 'return_date' => '2026-09-13', 'customer_id' => $cust->id, 'branch_id' => $this->b1->id, 'sales_bill_id' => $bill->id, 'total' => 118, 'total_gst' => 18, 'return_mode' => 'Cash']);
        foreach (['sale-return-customerwise', 'sales-return-itemwise', 'sale-return-datewise', 'sale-return-monthwise'] as $slug) {
            $r = $this->rpt($slug, ['search' => 'SR-1']);
            $rows = $this->texts($r);
            $this->assertCount(1, $rows, $slug);
            $this->assertStringContainsString('SR-1 | 13 Sep 2026 | Order Cust | RB-1 | Main Store | ₹ 18.00 | ₹ 118.00 | Cash', $rows[0]);
        }
        $this->assertCount(0, $this->texts($this->rpt('sale-return-datewise', ['branch_id' => $this->b2->id])));
    }

    public function test_sales_fallback_and_monthly_transaction_summary(): void
    {
        $this->bill('F-1', '2026-09-10', $this->b1, 1000);
        $this->bill('F-2', '2026-08-10', $this->b1, 250);
        $this->bill('F-CAN', '2026-08-11', $this->b1, 9000, 'Cancelled');

        $r = $this->rpt('margin-summary', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertSame('Margin Summary Report', $r->viewData('title'));
        $this->assertSame(1, $this->kpi($r, 'Total Transactions'));

        $r = $this->rpt('monthly-transaction-summary');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('September 2026 | 1 | ₹ 1,000.00', $rows[0]);
        $this->assertStringContainsString('August 2026 | 1 | ₹ 250.00', $rows[1]); // cancelled bill excluded
        $this->assertSame('₹ 1,250.00', $this->kpi($r, 'Total Sales Revenue'));
    }

    // ---------------------------------------------------------------- inventory

    public function test_closing_stock_branch_filter_search_and_kpis(): void
    {
        $mk = fn ($code, $name, $store, $branch, $qty, $amt, $extra = []) => ClosingStock::create($extra + [
            'item_code' => $code, 'item_name' => $name, 'store_name' => $store, 'store_id' => (string) $branch, 'branch_id' => $branch,
            'closing_stock' => $qty, 'closing_stock_amount' => $amt, 'net_cost' => $qty ? $amt / $qty : 0, 'status' => 'Active', 'batch_no' => 'B1',
        ]);
        $mk('CS1', 'Dog Food', 'MAIN STORE', $this->b1->id, 10, 500);
        $mk('CS2', 'Cat Food', 'MAIN STORE', $this->b1->id, -2, -80, ['status' => 'Blocked']);
        $mk('CS3', 'Bird Seed', 'SECOND STORE', $this->b2->id, 7, 140, ['brand_name' => 'Avian']);

        $r = $this->rpt('closing-stock');
        $this->assertCount(3, $this->texts($r));
        $this->assertSame('15 Units', $this->kpi($r, 'Total Closing Stock'));
        $this->assertSame('₹ 560.00', $this->kpi($r, 'Total Valuation (Net Cost)'));
        $this->assertSame('3', $this->kpi($r, 'Total Catalog Batches'));
        $this->assertSame('2', $this->kpi($r, 'In-Stock Batches'));

        $r = $this->rpt('closing-stock', ['branch_id' => $this->b1->id]);
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Dog Food', $rows[0]); // closing_stock desc within store
        $this->assertStringContainsString('Blocked', $rows[1]);
        $this->assertSame('8 Units', $this->kpi($r, 'Total Closing Stock'));
        $this->assertSame('₹ 420.00', $this->kpi($r, 'Total Valuation (Net Cost)'));
        $this->assertSame('1', $this->kpi($r, 'In-Stock Batches'));

        $rows = $this->texts($this->rpt('closing-stock', ['search' => 'avian']));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Bird Seed', $rows[0]);
    }

    public function test_itemwise_stock_statement_and_category_summary(): void
    {
        $it = $this->item('Shampoo', ['item_code' => 'SH1', 'cost_price' => 40, 'sell_price' => 90]);
        $it2 = $this->item('Collar', ['item_code' => 'CO1', 'cost_price' => 10, 'sell_price' => 30]);
        ItemStock::create(['item_id' => $it->id, 'branch_id' => $this->b1->id, 'quantity' => 5, 'cost_price' => 40, 'sell_price' => 90]);
        ItemStock::create(['item_id' => $it2->id, 'branch_id' => $this->b1->id, 'quantity' => 0, 'cost_price' => 10]);
        ItemStock::create(['item_id' => $it2->id, 'branch_id' => $this->b2->id, 'quantity' => 3, 'cost_price' => 10, 'sell_price' => 30]);

        $r = $this->rpt('itemwise-stock-statement');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows); // zero stock excluded
        $this->assertStringContainsString('SH1 | Shampoo', $rows[0]);
        $this->assertStringContainsString('₹ 200.00', $rows[0]); // 5 x 40
        $this->assertSame('₹ 230.00', $this->kpi($r, 'Total Inventory Value')); // 200 + 3x10
        $this->assertSame(2, $this->kpi($r, 'In-Stock Batches'));

        $r = $this->rpt('itemwise-stock-sales-detail', ['branch_id' => $this->b2->id, 'search' => 'collar']);
        $this->assertCount(1, $this->texts($r));
        $this->assertSame('₹ 30.00', $this->kpi($r, 'Total Inventory Value'));

        $r = $this->rpt('categorywise-storewise-current-stock');
        $rows = $this->texts($r);
        $this->assertCount(2, $rows);
        $main = collect($rows)->first(fn ($t) => str_contains($t, 'Main Store'));
        $this->assertStringContainsString('General Goods | 1 | 5 | ₹ 200.00 | ₹ 450.00', $main);
        $this->assertCount(1, $this->texts($this->rpt('categorywise-storewise-current-stock', ['branch_id' => $this->b2->id])));

        $r = $this->rpt('stock-slow-moving', ['branch_id' => $this->b1->id]); // generic inventory fallback
        $this->assertSame('Stock Slow Moving Report', $r->viewData('title'));
        $this->assertSame(1, $this->kpi($r, 'Active SKUs'));
    }

    public function test_stock_update_damage_opening_and_transfer_reports(): void
    {
        $it = $this->item('Leash', ['item_code' => 'LE1']);
        $now = now();

        $suId = DB::table('stock_updates')->insertGetId(['update_number' => 'SU-1', 'branch_id' => $this->b1->id, 'entry_date' => '2026-09-10', 'remarks' => 'Cycle count', 'status' => 'Approved', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('stock_update_items')->insert(['stock_update_id' => $suId, 'item_id' => $it->id, 'physical_qty' => 7, 'system_qty_at_entry' => 10, 'delta_qty' => -3, 'created_at' => $now, 'updated_at' => $now]);
        $r = $this->rpt('stock-update-detail');
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('SU-1 | 10 Sep 2026 | Main Store | LE1 | Leash | 10 | 7 | -3 | Cycle count | Approved', $rows[0]);
        $this->assertSame(1, $this->kpi($r, 'Total Audited Items'));
        $this->assertCount(0, $this->texts($this->rpt('stock-update-detail', ['branch_id' => $this->b2->id])));

        $dsId = DB::table('damage_stocks')->insertGetId(['damage_number' => 'DM-1', 'branch_id' => $this->b1->id, 'entry_date' => '2026-09-11', 'total_cost' => 90, 'remarks' => 'Leaked', 'status' => 'Posted', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('damage_stock_items')->insert(['damage_stock_id' => $dsId, 'item_id' => $it->id, 'qty' => 3, 'cost_price' => 30, 'created_at' => $now, 'updated_at' => $now]);
        $r = $this->rpt('wastage-damage-stock-detail');
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('DM-1 | 11 Sep 2026 | Main Store | LE1 | Leash | 3 | ₹ 30.00 | ₹ 90.00 | Leaked | Posted', $rows[0]);
        $this->assertSame('₹ 90.00', $this->kpi($r, 'Total Write-off Loss'));
        $this->assertCount(1, $this->texts($this->rpt('wastage-damage-stock-detail', ['search' => 'DM-1'])));

        $osId = DB::table('opening_stocks')->insertGetId(['entry_number' => 'OS-1', 'branch_id' => $this->b2->id, 'entry_date' => '2026-04-01', 'status' => 'Posted', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('opening_stock_items')->insert(['opening_stock_id' => $osId, 'item_id' => $it->id, 'qty' => 20, 'cost_price' => 25, 'sell_price' => 50, 'created_at' => $now, 'updated_at' => $now]);
        $r = $this->rpt('opening-stock-detail', ['branch_id' => $this->b2->id]);
        $rows = $this->texts($r);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('OS-1 | 01 Apr 2026 | Second Store | LE1 | Leash | 20 | ₹ 25.00 | ₹ 50.00 | ₹ 500.00', $rows[0]);
        $this->assertCount(0, $this->texts($this->rpt('opening-stock-detail', ['branch_id' => $this->b1->id])));

        StockTransfer::create(['transfer_number' => 'TR-1', 'transfer_date' => '2026-09-14', 'from_branch_id' => $this->b1->id, 'to_branch_id' => $this->b2->id, 'total_qty' => 12, 'total_value' => 480, 'status' => 'Dispatched', 'remarks' => 'Restock']);
        foreach (['stock-transferin-summary', 'stock-transferout-detail', 'stock-transferin-detail'] as $slug) {
            $r = $this->rpt($slug);
            $rows = $this->texts($r);
            $this->assertCount(1, $rows, $slug);
            $this->assertStringContainsString('TR-1 | 14 Sep 2026 | Main Store | Second Store | 12 | ₹ 480.00 | Dispatched | Restock', $rows[0]);
            $this->assertSame(1, $this->kpi($r, 'Total Transfers'));
        }
        $this->assertCount(0, $this->texts($this->rpt('stock-transferin-summary', ['search' => 'nope'])));
    }
}
