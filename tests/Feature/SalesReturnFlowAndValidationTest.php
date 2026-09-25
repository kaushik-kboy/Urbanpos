<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReturnFlowAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $customerA;
    private Customer $customerB;
    private Item $productA;
    private Item $productB;
    private Item $productC;
    private SalesBill $billA;
    private SalesBill $billB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->owner = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->owner->assignRole('Owner');

        $this->customerA = Customer::create([
            'name' => 'ABC Customer',
            'mobile' => '9898000001',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $this->customerB = Customer::create([
            'name' => 'XYZ Customer',
            'mobile' => '9898000002',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $this->productA = Item::create([
            'name' => 'Product A',
            'item_code' => 'PROD-A',
            'sell_price' => 100,
            'mrp' => 120,
            'gst_tax_id' => $gst->id,
            'status' => true,
        ]);

        $this->productB = Item::create([
            'name' => 'Product B',
            'item_code' => 'PROD-B',
            'sell_price' => 200,
            'mrp' => 250,
            'gst_tax_id' => $gst->id,
            'status' => true,
        ]);

        $this->productC = Item::create([
            'name' => 'Product C',
            'item_code' => 'PROD-C',
            'sell_price' => 300,
            'mrp' => 350,
            'gst_tax_id' => $gst->id,
            'status' => true,
        ]);

        // Customer A's Bill: INV-001 with Product A (qty 2), Product B (qty 3), Product C (qty 5)
        $this->billA = SalesBill::create([
            'bill_number' => 'INV-001',
            'bill_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customerA->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash',
            'total' => 2300,
            'status' => 'Completed',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $this->billA->id,
            'item_id' => $this->productA->id,
            'qty' => 2,
            'sell_price' => 100,
            'net_amount' => 200,
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $this->billA->id,
            'item_id' => $this->productB->id,
            'qty' => 3,
            'sell_price' => 200,
            'net_amount' => 600,
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $this->billA->id,
            'item_id' => $this->productC->id,
            'qty' => 5,
            'sell_price' => 300,
            'net_amount' => 1500,
        ]);

        // Customer B's Bill: INV-002
        $this->billB = SalesBill::create([
            'bill_number' => 'INV-002',
            'bill_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customerB->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash',
            'total' => 500,
            'status' => 'Completed',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $this->billB->id,
            'item_id' => $this->productA->id,
            'qty' => 5,
            'sell_price' => 100,
            'net_amount' => 500,
        ]);
    }

    public function test_customer_bills_endpoint_only_returns_selected_customers_bills(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/sales/sales-returns/customer-bills/{$this->customerA->id}");

        $response->assertOk();
        $bills = $response->json();

        $this->assertCount(1, $bills);
        $this->assertEquals($this->billA->id, $bills[0]['id']);
        $this->assertEquals('INV-001', $bills[0]['bill_number']);

        // Assert Customer B's bill is NOT returned
        $billIds = collect($bills)->pluck('id')->all();
        $this->assertNotContains($this->billB->id, $billIds);
    }

    public function test_bill_items_endpoint_returns_only_items_of_selected_bill_with_remaining_qty(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/sales/sales-returns/bill-items/{$this->billA->id}");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('INV-001', $data['bill_number']);
        $this->assertCount(3, $data['items']);

        $items = collect($data['items'])->keyBy('item_id');

        $this->assertEquals(2, $items[$this->productA->id]['remaining_qty']);
        $this->assertEquals(3, $items[$this->productB->id]['remaining_qty']);
        $this->assertEquals(5, $items[$this->productC->id]['remaining_qty']);
    }

    public function test_bill_items_endpoint_considers_already_returned_quantities(): void
    {
        // Return 1 of Product A earlier
        $sr = SalesReturn::create([
            'return_number' => 'SR-0001',
            'return_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customerA->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $this->billA->id,
            'sales_type' => 'Local',
            'return_mode' => 'Cash',
            'total' => 100,
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $sr->id,
            'item_id' => $this->productA->id,
            'qty' => 1,
            'sell_price' => 100,
            'net_amount' => 100,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/sales/sales-returns/bill-items/{$this->billA->id}");

        $response->assertOk();
        $items = collect($response->json('items'))->keyBy('item_id');

        // Product A original 2, returned 1 => remaining 1
        $this->assertEquals(1, $items[$this->productA->id]['remaining_qty']);
        $this->assertEquals(1, $items[$this->productA->id]['already_returned_qty']);

        // Product B unaffected => remaining 3
        $this->assertEquals(3, $items[$this->productB->id]['remaining_qty']);
    }

    public function test_sales_return_store_allows_returning_only_selected_item_with_valid_quantity(): void
    {
        // Return ONLY Product B with qty 3
        $payload = [
            'return_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customerA->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $this->billA->id,
            'sales_type' => 'Local',
            'return_mode' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->productB->id,
                    'qty' => 3,
                    'sell_price' => 200,
                    'mrp' => 250,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)
            ->post(route('sales.sales-returns.store'), $payload);

        $response->assertRedirect(route('sales.sales-returns.index'));

        $this->assertDatabaseHas('sales_returns', [
            'customer_id' => $this->customerA->id,
            'sales_bill_id' => $this->billA->id,
        ]);

        $createdReturn = SalesReturn::where('sales_bill_id', $this->billA->id)->first();
        $this->assertCount(1, $createdReturn->items);
        $this->assertEquals($this->productB->id, $createdReturn->items[0]->item_id);
    }

    public function test_sales_return_store_rejects_quantity_greater_than_remaining(): void
    {
        // Try returning Product B with qty 4 (when original bill only has 3)
        $payload = [
            'return_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customerA->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $this->billA->id,
            'sales_type' => 'Local',
            'return_mode' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->productB->id,
                    'qty' => 4,
                    'sell_price' => 200,
                    'mrp' => 250,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)
            ->post(route('sales.sales-returns.store'), $payload);

        $response->assertSessionHasErrors('items');
    }

    public function test_sales_return_create_page_renders_with_picker_and_inline_error_containers(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('sales.sales-returns.create'));

        $response->assertOk();
        $response->assertSee('sr-bill-item-select');
        $response->assertSee('sr-bill-item-error');
        $response->assertSee('sr-items-table');
        $response->assertSee('Select Item from Sales Bill to Return');
    }
}
