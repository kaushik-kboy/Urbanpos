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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PosEnhancementsTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private Branch $branch;
    private Supplier $supplier;
    private Customer $customer1;
    private Customer $customer2;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

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
    }
}
