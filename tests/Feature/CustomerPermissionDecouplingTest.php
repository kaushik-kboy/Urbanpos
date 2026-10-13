<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerType;
use App\Models\SalesType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerPermissionDecouplingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $manager;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Main Outlet',
            'code' => 'MAIN',
            'status' => true,
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Test Cashier',
            'email' => 'cashier@test.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole('Cashier');

        $this->manager = User::factory()->create([
            'name' => 'Test Manager',
            'email' => 'manager@test.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->manager->assignRole('Manager');

        CustomerType::firstOrCreate(['name' => 'RETAIL INVOICE', 'status' => true]);
        SalesType::firstOrCreate(['name' => 'Local', 'status' => true]);
        CustomerCategory::firstOrCreate(['name' => 'General', 'status' => true]);
    }

    public function test_cashier_can_quick_create_customer_during_billing(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson(route('sales.sales-bills.quick-customer'), [
                'name' => 'Walkin Jane',
                'mobile' => '9876543210',
                'customer_type' => 'RETAIL INVOICE',
                'sales_type' => 'Local',
                'branch_id' => $this->branch->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.name', 'Walkin Jane')
            ->assertJsonPath('customer.mobile', '9876543210');

        $this->assertDatabaseHas('customers', [
            'name' => 'Walkin Jane',
            'mobile' => '9876543210',
        ]);
    }

    public function test_cashier_can_search_customers_at_billing_counter(): void
    {
        $customer = Customer::create([
            'name' => 'Amit Sharma',
            'mobile' => '9988776655',
            'customer_type' => 'RETAIL INVOICE',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'status' => 1,
            'sms_consent' => 1,
            'gst_type' => 'Un Register',
            'branch_id' => $this->branch->id,
        ]);

        // Search with exact/partial hit
        $res = $this->actingAs($this->cashier)
            ->getJson(route('sales.sales-bills.customer-search', ['q' => '9988776655']));

        $res->assertOk();
        $this->assertCount(1, $res->json('results'));
        $this->assertEquals($customer->id, $res->json('results.0.id'));

        // Blank search must return 0 results (privacy protection)
        $emptyRes = $this->actingAs($this->cashier)
            ->getJson(route('sales.sales-bills.customer-search', ['q' => '']));
        $emptyRes->assertOk();
        $this->assertCount(0, $emptyRes->json('results'));
    }

    public function test_cashier_can_fetch_and_update_customer_for_inline_billing(): void
    {
        $customer = Customer::create([
            'name' => 'Rahul Verma',
            'mobile' => '9123456789',
            'customer_type' => 'RETAIL INVOICE',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'status' => 1,
            'sms_consent' => 1,
            'gst_type' => 'Un Register',
            'branch_id' => $this->branch->id,
        ]);

        $showRes = $this->actingAs($this->cashier)
            ->getJson(route('sales.sales-bills.quick-customer-show', $customer));
        $showRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.name', 'Rahul Verma');

        $updateRes = $this->actingAs($this->cashier)
            ->putJson(route('sales.sales-bills.quick-customer-update', $customer), [
                'name' => 'Rahul Verma Renamed',
                'mobile' => '9123456789',
                'customer_type' => 'RETAIL INVOICE',
                'sales_type' => 'Local',
                'payment_mode' => 'Cash Only',
                'credit_limit' => 0,
                'credit_balance' => 0,
                'monthly_credit_balance' => 0,
                'credit_days' => 0,
                'status' => 1,
                'sms_consent' => 1,
                'gst_type' => 'Un Register',
                'branch_id' => $this->branch->id,
            ]);

        $updateRes->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Rahul Verma Renamed',
        ]);
    }

    public function test_cashier_is_forbidden_from_customer_master_directory_and_store(): void
    {
        // 1. Direct index browsing forbidden
        $this->actingAs($this->cashier)
            ->get(route('master.customers.index'))
            ->assertForbidden();

        // 2. Direct create form forbidden
        $this->actingAs($this->cashier)
            ->get(route('master.customers.create'))
            ->assertForbidden();

        // 3. Direct master store endpoint forbidden
        $this->actingAs($this->cashier)
            ->post(route('master.customers.store'), [
                'name' => 'Hacker Input',
                'mobile' => '9000000000',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_access_customer_master_directory(): void
    {
        $this->actingAs($this->manager)
            ->get(route('master.customers.index'))
            ->assertOk();
    }
}
