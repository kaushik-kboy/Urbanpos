<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Supplier $supplier;
    private Customer $customer1;
    private Customer $customer2;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->owner = User::factory()->create();

        $this->branch = Branch::firstOrCreate(['name' => 'Main Test Branch'], ['status' => true]);
        $this->supplier = Supplier::firstOrCreate(['name' => 'Test Supplier'], ['status' => true]);
        $this->customer1 = Customer::firstOrCreate(['name' => 'Alice Test', 'mobile' => '9876543210'], ['status' => true]);
        $this->customer2 = Customer::firstOrCreate(['name' => 'Bob Test', 'mobile' => '9123456789'], ['status' => true]);

        $tax = GstTax::firstOrCreate(['percentage' => 18], ['description' => 'GST 18%']);
        $this->item = Item::firstOrCreate(['name' => 'Test POS Item'], [
            'status' => true,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'gst_tax_id' => $tax->id,
            'item_code' => 'TESTITEM001',
        ]);
    }

    public function test_purchase_invoice_edit_renders_fast_with_only_its_items(): void
    {
        $pi = PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-001',
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'total' => 590,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi->id,
            'item_id' => $this->item->id,
            'qty' => 5,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'gst_percent' => 18,
            'net_amount' => 590,
        ]);

        $response = $this->actingAs($this->owner)->get(route('purchase.purchase-invoices.edit', $pi));
        $response->assertOk();
        $response->assertSee('TESTITEM001');
        $response->assertSee('Saving...');
    }

    public function test_sales_return_customer_bills_endpoint_returns_only_bills_for_that_customer(): void
    {
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-TEST-001',
            'customer_id' => $this->customer1->id,
            'branch_id' => $this->branch->id,
            'bill_date' => now()->toDateString(),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'total' => 1000,
            'status' => 'Posted',
        ]);

        $bill2 = SalesBill::create([
            'bill_number' => 'SB-TEST-002',
            'customer_id' => $this->customer2->id,
            'branch_id' => $this->branch->id,
            'bill_date' => now()->toDateString(),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'total' => 2500,
            'status' => 'Posted',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.customer-bills', $this->customer1));
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['bill_number' => 'SB-TEST-001']);
        $response->assertJsonMissing(['bill_number' => 'SB-TEST-002']);
    }

    public function test_sales_bill_customer_search_returns_mobile_number(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-search', ['q' => '9876543210']));
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $this->customer1->id,
            'mobile' => '9876543210',
        ]);
    }

    public function test_purchase_order_create_renders_with_item_code_column_and_search_modal(): void
    {
        $response = $this->actingAs($this->owner)->get(route('purchase.purchase-orders.create'));
        $response->assertOk();
        $response->assertSee('Code / Barcode');
        $response->assertSee('po-item-search-modal');
        $response->assertSee('po-item-code');
        $response->assertSee('Stock');
    }

    public function test_quick_customer_creation_via_ajax(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('master.customers.store'), [
            'name' => 'Quick Test Customer',
            'mobile' => '9123456780',
            'customer_type' => 'RETAIL INVOICE',
            'sales_type' => 'Local',
            'gst_type' => 'Un Register',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'status' => 1,
            'sms_consent' => 1,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'customer' => [
                'name' => 'Quick Test Customer',
                'mobile' => '9123456780',
            ],
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Quick Test Customer',
            'mobile' => '9123456780',
        ]);
    }

    public function test_customer_mobile_validation_requires_exact_10_digits(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('master.customers.store'), [
            'name' => 'Invalid Mobile Cust',
            'mobile' => '12345', // only 5 digits
            'customer_type' => 'RETAIL INVOICE',
            'sales_type' => 'Local',
            'gst_type' => 'Un Register',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'status' => 1,
            'sms_consent' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mobile']);
    }

    public function test_purchase_invoice_from_order_conversion(): void
    {
        $supplier = \App\Models\Supplier::create([
            'name' => 'PO Test Supplier',
            'status' => true,
            'mail_type' => 'None',
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO99999',
            'po_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'total' => 500,
            'status' => 'Open',
        ]);

        $po->items()->create([
            'item_id' => $this->item->id,
            'qty' => 10,
            'cost_price' => 50,
            'sell_price' => 60,
            'mrp' => 60,
            'net_amount' => 500,
        ]);

        $response = $this->actingAs($this->owner)->get(route('purchase.purchase-invoices.create', ['from_order' => $po->id]));
        $response->assertOk();
        $response->assertSee('PO99999');
        $response->assertSee('Converting directly from Purchase Order');
    }

    public function test_pos_terminal_renders_customer_card_and_modal(): void
    {
        $response = $this->actingAs($this->owner)->get(route('pos.terminal'));
        $response->assertOk();
        $response->assertSee('posCustomerCard');
        $response->assertSee('posCustomerInvoicesModal');
        $response->assertSee('posEditCustomerBtn');
        $response->assertSee('posViewCustomerInvoicesBtn');
        $response->assertSee('Customer invoices');
    }

    public function test_customer_search_returns_edit_url_and_mobile(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-search', ['q' => '9876543210']));
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $this->customer1->id,
            'name' => 'Alice Test',
            'mobile' => '9876543210',
            'edit_url' => url("master/customers/{$this->customer1->id}/edit"),
        ]);
    }

    public function test_customer_invoices_returns_bills_with_items_count_and_edit_url(): void
    {
        $bill = SalesBill::create([
            'bill_number' => 'INV/2627/000860',
            'bill_date' => now(),
            'customer_id' => $this->customer1->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'sales_type' => 'Local',
            'total' => 22.00,
            'status' => 'Completed',
        ]);

        $bill->items()->create([
            'item_id' => $this->item->id,
            'qty' => 1,
            'sell_price' => 22,
            'mrp' => 22,
            'cost_at_sale' => 10,
            'net_amount' => 22,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-invoices', $this->customer1));
        $response->assertOk();
        $response->assertJson([
            'customer_id' => $this->customer1->id,
            'customer_name' => 'Alice Test',
            'customer_mobile' => '9876543210',
            'customer_edit_url' => url("master/customers/{$this->customer1->id}/edit"),
            'total_invoices' => 1,
        ]);

        $json = $response->json();
        $this->assertCount(1, $json['invoices']);
        $this->assertEquals('INV/2627/000860', $json['invoices'][0]['bill_number']);
        $this->assertEquals(1, $json['invoices'][0]['items']);
        $this->assertEquals('22.00', $json['invoices'][0]['total']);
        $this->assertArrayHasKey('view_url', $json['invoices'][0]);
        $this->assertArrayHasKey('edit_url', $json['invoices'][0]);
        $this->assertArrayHasKey('print_url', $json['invoices'][0]);
    }

    public function test_pos_terminal_renders_customer_modal_with_all_tabs(): void
    {
        $response = $this->actingAs($this->owner)->get(route('pos.terminal'));
        $response->assertOk();
        $response->assertSee('pos-tab-general');
        $response->assertSee('pos-tab-contact');
        $response->assertSee('pos-tab-others');
        $response->assertSee('pos-tab-pets');
        $response->assertSee('pos-pet-row-template');
        $response->assertSee('pos-add-pet-detail');
    }

    public function test_customer_edit_returns_json_with_pets(): void
    {
        $this->customer1->pets()->create([
            'name' => 'Bruno',
            'gender' => 'Male',
            'age' => '2 yrs',
            'remarks' => 'Golden retriever',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('master.customers.edit', $this->customer1));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'customer' => [
                'id' => $this->customer1->id,
                'name' => 'Alice Test',
            ],
        ]);
        $json = $response->json();
        $this->assertNotEmpty($json['pets']);
        $this->assertEquals('Bruno', $json['pets'][0]['name']);
    }

    public function test_customer_update_via_ajax_with_pets(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('master.customers.update', $this->customer1), [
            '_method' => 'PUT',
            'title' => 'Ms',
            'name' => 'Alice Updated',
            'mobile' => '9876543210',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 1000000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 1000,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 1,
            'customer_type' => 'RETAIL INVOICE',
            'pets' => [
                [
                    'name' => 'Milo',
                    'gender' => 'Male',
                    'age' => '1 yr',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'customer' => [
                'id' => $this->customer1->id,
                'name' => 'Alice Updated',
            ]
        ]);

        $this->customer1->refresh();
        $this->assertEquals('Alice Updated', $this->customer1->name);
        $this->assertEquals(1, $this->customer1->pets()->count());
        $this->assertEquals('Milo', $this->customer1->pets()->first()->name);
    }
}
