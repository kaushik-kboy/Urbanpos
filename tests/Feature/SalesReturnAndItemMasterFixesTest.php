<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReturnAndItemMasterFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB1', 'state' => 'Gujarat']);
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole('Owner');
    }

    public function test_item_master_defaults_to_tax_inclusive(): void
    {
        $item = new Item([
            'name' => 'Test Pet Food',
            'sell_price' => 150.00,
        ]);
        $item->save();

        $this->assertTrue($item->tax_inclusive);
    }

    public function test_sales_return_edit_loads_customer_properly(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            Customer::create([
                'name' => "Customer {$i}",
                'mobile' => "90000000{$i}",
                'status' => true,
            ]);
        }

        $specialCustomer = Customer::create([
            'id' => 571,
            'name' => 'VIP Customer 571',
            'mobile' => '9876543210',
            'sales_type' => 'Local',
            'status' => true,
        ]);

        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-571',
            'return_date' => now()->toDateString(),
            'customer_id' => $specialCustomer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'status' => 'Draft',
            'total_amount' => 100.0,
            'net_amount' => 100.0,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sales.sales-returns.edit', $salesReturn));

        $response->assertOk();
        $response->assertSee('VIP Customer 571');
        $response->assertSee('9876543210');
    }

    public function test_sales_bill_items_endpoint_resolves_sales_type_from_customer_if_bill_type_is_retail(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'sales_type' => 'Local',
            'status' => true,
        ]);

        $salesBill = SalesBill::create([
            'bill_number' => 'SB-1001',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Retail', // Non-GST tax type
            'status' => 'Completed',
            'subtotal' => 100.0,
            'total' => 100.0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('sales.sales-returns.bill-items', $salesBill));

        $response->assertOk();
        $this->assertEquals('Local', $response->json('sales_type'));
    }
}
