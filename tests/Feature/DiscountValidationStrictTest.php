<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiscountValidationStrictTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Supplier $supplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Test Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->customer = Customer::create(['name' => 'Test Customer', 'mobile' => '9888877777', 'status' => true]);
        $this->supplier = Supplier::create(['name' => 'Test Supplier', 'phone' => '9777766666', 'mobile' => '9777766666', 'state' => 'Maharashtra', 'status' => true]);
        $this->item = Item::create([
            'name' => 'Test Item',
            'item_code' => 'TST-01',
            'sell_price' => 100,
            'cost_price' => 80,
            'mrp' => 120,
            'gst_tax_id' => $gst->id,
            'status' => true,
            'allow_negative_stock' => true,
            'batch_expiry_details' => 'Not Required',
        ]);
    }

    public function test_sales_bill_rejects_discount_percent_greater_than_100()
    {
        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'bill_type' => 'Tax Invoice',
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct Delivery',
            'sales_type' => 'Local',
            'order_type' => 'Takeaway',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 100,
                    'disc_percent' => 105,
                    'disc_amount' => 105,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.disc_percent']);
    }

    public function test_sales_bill_rejects_discount_amount_exceeding_line_total()
    {
        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'bill_type' => 'Tax Invoice',
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct Delivery',
            'sales_type' => 'Local',
            'order_type' => 'Takeaway',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 100,
                    'disc_percent' => 50,
                    'disc_amount' => 120, // exceeds line total 100
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.disc_amount']);
    }

    public function test_purchase_order_rejects_discount_percent_greater_than_100()
    {
        $response = $this->post(route('purchase.purchase-orders.store'), [
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'cost_price' => 80,
                    'disc_percent' => 101,
                    'disc_amount' => 161.6,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.disc_percent']);
    }

    public function test_purchase_order_rejects_discount_amount_exceeding_cost()
    {
        $response = $this->post(route('purchase.purchase-orders.store'), [
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'cost_price' => 80,
                    'disc_percent' => 10,
                    'disc_amount' => 95, // exceeds cost 80
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.disc_amount']);
    }

    public function test_sales_return_rejects_discount_percent_greater_than_100()
    {
        $response = $this->post(route('sales.sales-returns.store'), [
            'return_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'return_type' => 'Local',
            'return_mode' => 'Cash',
            'sales_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 100,
                    'disc_percent' => 110,
                    'disc_amount' => 110,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.disc_percent']);
    }
}
