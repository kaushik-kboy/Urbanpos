<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesBill;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalActiveBranchEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch1;
    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create(['name' => 'Branch Alpha', 'code' => 'BA', 'status' => true]);
        $this->branch2 = Branch::create(['name' => 'Branch Beta', 'code' => 'BB', 'status' => true]);

        // User with null branch_id (Admin/Owner)
        $this->user = User::factory()->create([
            'branch_id' => null,
            'email' => 'admin@urbanpos.com',
        ]);
    }

    public function test_can_set_active_branch_via_post(): void
    {
        $response = $this->actingAs($this->user)->postJson('/active-branch', [
            'branch_id' => $this->branch2->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'branch_id' => $this->branch2->id,
        ]);

        $this->assertEquals($this->branch2->id, session('active_branch_id'));
    }

    public function test_sales_bills_index_defaults_to_active_branch(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'mobile' => '9876543210',
            'status' => true,
        ]);

        SalesBill::create([
            'branch_id' => $this->branch1->id,
            'customer_id' => $customer->id,
            'bill_number' => 'SB-ALPHA-001',
            'bill_date' => now(),
            'net_amount' => 100,
        ]);

        SalesBill::create([
            'branch_id' => $this->branch2->id,
            'customer_id' => $customer->id,
            'bill_number' => 'SB-BETA-001',
            'bill_date' => now(),
            'net_amount' => 150,
        ]);

        // Set active branch to branch2
        $response = $this->actingAs($this->user)
            ->withSession(['active_branch_id' => $this->branch2->id])
            ->get('/sales/sales-bills');

        $response->assertOk();
        $response->assertSee('SB-BETA-001');
        $response->assertDontSee('SB-ALPHA-001');
    }

    public function test_sales_orders_index_defaults_to_active_branch(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer 2',
            'mobile' => '9876543211',
            'status' => true,
        ]);

        SalesOrder::create([
            'branch_id' => $this->branch1->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-ALPHA-001',
            'order_date' => now(),
            'total_amount' => 100,
            'net_amount' => 100,
            'status' => 'Open',
        ]);

        SalesOrder::create([
            'branch_id' => $this->branch2->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-BETA-001',
            'order_date' => now(),
            'total_amount' => 150,
            'net_amount' => 150,
            'status' => 'Open',
        ]);

        // Set active branch to branch1
        $response = $this->actingAs($this->user)
            ->withSession(['active_branch_id' => $this->branch1->id])
            ->get('/sales/sales-orders');

        $response->assertOk();
        $response->assertSee('SO-ALPHA-001');
        $response->assertDontSee('SO-BETA-001');
    }

    public function test_sales_bills_create_form_renders_locked_active_branch(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_branch_id' => $this->branch2->id])
            ->get('/sales/sales-bills/create');

        $response->assertOk();
        $response->assertSee('name="branch_id" value="' . $this->branch2->id . '"', false);
        $response->assertSee('Controlled at Top Navbar');
    }
}
