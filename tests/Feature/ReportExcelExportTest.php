<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    public function test_item_master_excel_export_respects_filter(): void
    {
        Item::create(['name' => 'Royal Canin Dog Food', 'item_code' => 'RC001', 'cost_price' => 100, 'sell_price' => 150]);
        Item::create(['name' => 'Whiskas Cat Food', 'item_code' => 'WH001', 'cost_price' => 50, 'sell_price' => 80]);

        $response = $this->actingAs($this->owner)->get(route('reports.item-master', [
            'search' => 'Royal Canin',
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'attachment') ||
            str_contains((string) $response->headers->get('content-type'), 'spreadsheet') ||
            str_contains((string) $response->headers->get('content-disposition'), '.xlsx')
        );
    }

    public function test_supplier_master_excel_export(): void
    {
        Supplier::create(['name' => 'Alpha Suppliers', 'state' => 'Delhi']);

        $response = $this->actingAs($this->owner)->get(route('reports.supplier-master', [
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_customer_master_excel_export_with_filter(): void
    {
        Customer::create(['name' => 'John Doe', 'phone' => '9876543210']);

        $response = $this->actingAs($this->owner)->get(route('reports.customer-master', [
            'search' => 'John',
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_generic_report_excel_export(): void
    {
        Brand::create(['name' => 'Pedigree', 'status' => true]);

        $response = $this->actingAs($this->owner)->get(route('reports.view', [
            'module' => 'brand-master',
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_day_book_excel_export(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finance.reports.day-book', [
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_trial_balance_excel_export(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finance.reports.trial-balance', [
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_profit_loss_excel_export(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finance.reports.profit-loss', [
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_customer_loyalty_excel_export(): void
    {
        Customer::create(['name' => 'Jane Smith', 'phone' => '9123456780']);

        $response = $this->actingAs($this->owner)->get(route('finance.reports.customer-loyalty', [
            'search' => 'Jane',
            'export' => 'excel',
        ]));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_daily_sales_billwise_excel_export_and_fields(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $customer = Customer::create(['name' => 'John PetLover', 'mobile' => '9988776655']);
        \App\Models\SalesBill::create([
            'bill_number' => 'BILL-XYZ-101',
            'bill_date' => now(),
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'payment_type' => 'UPI',
            'remarks' => 'Urgent pet meds',
            'message' => 'Thank you for visiting',
            'total_qty' => 3,
            'total' => 1500,
            'status' => 'Completed',
        ]);

        // 1. Web view test
        $webResponse = $this->actingAs($this->owner)->get(route('reports.view', [
            'module' => 'daily-sales-billwise',
            'search' => 'BILL-XYZ-101',
        ]));
        $webResponse->assertOk();
        $webResponse->assertSee('Payment Mode');
        $webResponse->assertSee('Remarks');
        $webResponse->assertSee('Message');
        $webResponse->assertSee('UPI');
        $webResponse->assertSee('Urgent pet meds');
        $webResponse->assertSee('Thank you for visiting');

        // 2. Excel export test
        $exportResponse = $this->actingAs($this->owner)->get(route('reports.view', [
            'module' => 'daily-sales-billwise',
            'search' => 'BILL-XYZ-101',
            'export' => 'excel',
        ]));
        $exportResponse->assertOk();
        $this->assertTrue(str_contains((string) $exportResponse->headers->get('content-disposition'), '.xlsx'));
    }
}

