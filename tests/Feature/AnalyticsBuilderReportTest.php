<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserSavedReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsBuilderReportTest extends TestCase
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

    public function test_analytics_builder_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.analytics-builder'));

        $response->assertOk();
        $response->assertSee('Custom Report Studio & Analytics Builder', false);
        $response->assertSee('Item ⇄ Supplier Sourcing', false);
        $response->assertSee('Single Item Monthly Sales', false);
        $response->assertSee('Save as My Report', false);
    }

    public function test_group_by_item_supplier_sourcing_traces_which_supplier_supplied_item(): void
    {
        $supplierA = Supplier::create([
            'name' => 'National Wholesalers Ltd',
            'city' => 'Mumbai',
            'mobile' => '9876543210',
            'status' => true,
        ]);

        $supplierB = Supplier::create([
            'name' => 'Metro Distributors',
            'city' => 'Pune',
            'mobile' => '9123456780',
            'status' => true,
        ]);

        $item1 = Item::create([
            'name' => 'Dog Food 5kg',
            'item_code' => 'DOG-5KG',
            'sell_price' => 500.00,
            'cost_price' => 380.00,
            'supplier_id' => $supplierA->id, // Primary supplier
            'status' => true,
        ]);

        // Purchase Invoice from Supplier A
        $invA = PurchaseInvoice::create([
            'invoice_number' => 'PINV-001',
            'invoice_date' => Carbon::parse('2026-08-10'),
            'supplier_id' => $supplierA->id,
            'branch_id' => $this->branch->id,
            'total_qty' => 100,
            'total' => 35000.00,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invA->id,
            'item_id' => $item1->id,
            'qty' => 100,
            'cost_price' => 350.00,
            'sell_price' => 500.00,
            'mrp' => 500.00,
            'net_amount' => 35000.00,
        ]);

        // Purchase Invoice from Supplier B (Purchased later at different cost)
        $invB = PurchaseInvoice::create([
            'invoice_number' => 'PINV-002',
            'invoice_date' => Carbon::parse('2026-09-05'),
            'supplier_id' => $supplierB->id,
            'branch_id' => $this->branch->id,
            'total_qty' => 50,
            'total' => 19000.00,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invB->id,
            'item_id' => $item1->id,
            'qty' => 50,
            'cost_price' => 380.00,
            'sell_price' => 500.00,
            'mrp' => 500.00,
            'net_amount' => 19000.00,
        ]);

        // Run report grouped by item_supplier
        $response = $this->actingAs($this->user)->postJson(route('reports.analytics-builder.generate'), [
            'group_by' => 'item_supplier',
            'date_preset' => 'all_time',
            'item_id' => $item1->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('group_by', 'item_supplier');

        $rows = $response->json('rows');
        $this->assertCount(2, $rows);

        // Verify both suppliers are traced
        $supplierNames = collect($rows)->pluck('supplier_name')->all();
        $this->assertContains('National Wholesalers Ltd', $supplierNames);
        $this->assertContains('Metro Distributors', $supplierNames);

        // Check primary source flag
        $supplierARow = collect($rows)->firstWhere('supplier_name', 'National Wholesalers Ltd');
        $this->assertTrue($supplierARow['is_primary_supplier']);
        $this->assertEquals(100, $supplierARow['total_qty']);

        $supplierBRow = collect($rows)->firstWhere('supplier_name', 'Metro Distributors');
        $this->assertFalse($supplierBRow['is_primary_supplier']);
        $this->assertEquals(50, $supplierBRow['total_qty']);
    }

    public function test_single_item_monthly_sales_drilldown_calculates_accurately(): void
    {
        $customer = Customer::create([
            'name' => 'Rahul Sharma',
            'mobile' => '9988776655',
            'status' => true,
        ]);

        $item = Item::create([
            'name' => 'Royal Canin Adult 3kg',
            'item_code' => 'RC-3KG',
            'sell_price' => 1200.00,
            'cost_price' => 900.00,
            'mrp' => 1200.00,
            'status' => true,
        ]);

        $billDate = Carbon::now()->startOfMonth()->addDays(2);

        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-001',
            'bill_date' => $billDate,
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Tax Invoice',
            'total_qty' => 3,
            'total' => 3600.00,
            'disc_amount' => 100.00,
            'status' => 'Completed',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 3,
            'unit_rate' => 1200.00,
            'disc_amount' => 100.00,
            'net_amount' => 3500.00,
            'cost_at_sale' => 900.00,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('reports.analytics-builder.generate'), [
            'group_by' => 'item',
            'date_preset' => 'this_month',
            'item_id' => $item->id,
            'metrics' => ['qty', 'sales_value', 'margin', 'bill_count'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $rows = $response->json('rows');
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertEquals('Royal Canin Adult 3kg', $row['group_name']);
        $this->assertEquals(3, (float) $row['total_qty']);
        $this->assertEquals(3500.00, (float) $row['total_sales']);
        $this->assertEquals(1, $row['bill_count']);
    }

    public function test_save_and_delete_custom_report_configuration(): void
    {
        $saveData = [
            'name' => 'Monthly High Margin Items',
            'description' => 'Items with highest profit this month',
            'group_by' => 'item',
            'metrics' => ['qty', 'sales_value', 'margin'],
            'filters' => [
                'date_preset' => 'this_month',
                'limit' => '20',
                'sort_dir' => 'desc',
            ],
        ];

        // 1. Save Report
        $saveResponse = $this->actingAs($this->user)->postJson(route('reports.analytics-builder.save'), $saveData);
        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('success', true);

        $reportId = $saveResponse->json('report.id');
        $this->assertDatabaseHas('user_saved_reports', [
            'id' => $reportId,
            'user_id' => $this->user->id,
            'name' => 'Monthly High Margin Items',
        ]);

        // 2. Delete Report
        $deleteResponse = $this->actingAs($this->user)->deleteJson(route('reports.analytics-builder.delete', ['id' => $reportId]));
        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_saved_reports', ['id' => $reportId]);
    }

    public function test_csv_export_returns_stream_download(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.analytics-builder.export', [
            'group_by' => 'item',
            'date_preset' => 'this_month',
        ]));

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type') ?? '';
        $this->assertTrue(str_contains($contentType, 'application/vnd.ms-excel') || str_contains($contentType, 'text/csv'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_item_drilldown_returns_individual_sales_bill_records(): void
    {
        $customer = Customer::create([
            'name' => 'Amit Verma',
            'mobile' => '9820011223',
            'status' => true,
        ]);

        $item = Item::create([
            'name' => 'Whiskas Wet Cat Food',
            'item_code' => 'CAT-WET',
            'sell_price' => 50.00,
            'cost_price' => 35.00,
            'mrp' => 50.00,
            'status' => true,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-DRILL-001',
            'bill_date' => Carbon::now()->startOfMonth()->addDays(1),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'total_qty' => 5,
            'total' => 250.00,
            'status' => 'Completed',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 5,
            'unit_rate' => 50.00,
            'net_amount' => 250.00,
            'cost_at_sale' => 35.00,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.analytics-builder.drilldown', [
            'group_by' => 'item',
            'group_id' => $item->id,
            'date_preset' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $records = $response->json('records');
        $this->assertCount(1, $records);
        $this->assertEquals('SB-DRILL-001', $records[0]['bill_number']);
        $this->assertEquals('Amit Verma', $records[0]['customer_name']);
        $this->assertStringContainsString('/sales/sales-bills/' . $bill->id, $records[0]['view_url']);
    }

    public function test_item_supplier_drilldown_returns_purchase_invoice_records(): void
    {
        $supplier = Supplier::create([
            'name' => 'Alpha Agro Supplies',
            'city' => 'Nashik',
            'mobile' => '9988112233',
            'status' => true,
        ]);

        $item = Item::create([
            'name' => 'Organic Bird Seed 1kg',
            'item_code' => 'BIRD-SEED',
            'sell_price' => 150.00,
            'cost_price' => 90.00,
            'status' => true,
        ]);

        $inv = PurchaseInvoice::create([
            'invoice_number' => 'PINV-ALPHA-99',
            'invoice_date' => Carbon::now()->startOfMonth()->addDays(3),
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'total_qty' => 20,
            'total' => 1800.00,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $inv->id,
            'item_id' => $item->id,
            'qty' => 20,
            'cost_price' => 90.00,
            'sell_price' => 150.00,
            'mrp' => 150.00,
            'net_amount' => 1800.00,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.analytics-builder.drilldown', [
            'group_by' => 'item_supplier',
            'group_id' => $item->id,
            'sub_id' => $supplier->id,
            'date_preset' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $records = $response->json('records');
        $this->assertCount(1, $records);
        $this->assertEquals('PINV-ALPHA-99', $records[0]['invoice_number']);
        $this->assertEquals(20, (float) $records[0]['qty']);
        $this->assertStringContainsString('/purchase/purchase-invoices/' . $inv->id, $records[0]['view_url']);
    }

    public function test_customer_master_exports_full_dataset_to_csv(): void
    {
        Customer::create([
            'customer_code' => 'CUST001',
            'name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'branch_id' => $this->branch->id,
            'status' => true,
        ]);
        Customer::create([
            'customer_code' => 'CUST002',
            'name' => 'Pooja Verma',
            'phone' => '9123456780',
            'city' => 'Pune',
            'branch_id' => $this->branch->id,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.customer-master', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="customer-master-report.csv"');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('Rahul Sharma', $content);
        $this->assertStringContainsString('9876543210', $content);
        $this->assertStringContainsString('Pooja Verma', $content);
        $this->assertStringContainsString('CUST001', $content);
    }

    public function test_drilldown_works_with_id_param_and_item_grouping(): void
    {
        $item = Item::create([
            'name' => 'Pedigree Adult 3kg',
            'item_code' => 'PED3KG',
            'sell_price' => 500,
            'status' => 1,
        ]);

        $customer = Customer::create([
            'customer_code' => 'CUST003',
            'name' => 'Suresh Patel',
            'phone' => '9988776655',
            'branch_id' => $this->branch->id,
            'status' => true,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'BILL-ITEM-001',
            'bill_date' => Carbon::now(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'total_qty' => 2,
            'total' => 1000,
            'status' => 'Paid',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 2,
            'sell_price' => 500,
            'mrp' => 500,
            'net_amount' => 1000,
        ]);

        // Sending 'id' instead of 'group_id' to verify backwards compatibility with front-end
        $response = $this->actingAs($this->user)->getJson(route('reports.analytics-builder.drilldown', [
            'group_by' => 'item',
            'id' => $item->id,
            'date_preset' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $records = $response->json('records');
        $this->assertCount(1, $records);
        $this->assertEquals('BILL-ITEM-001', $records[0]['bill_number']);
        $this->assertStringContainsString('/sales/sales-bills/' . $bill->id, $records[0]['view_url']);
    }
}
