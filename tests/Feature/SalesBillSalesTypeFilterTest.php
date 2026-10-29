<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBillSalesTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['id' => 2], [
            'name' => 'Satellite Branch',
            'code' => 'SAT',
            'status' => true,
        ]);

        $this->admin = User::firstOrCreate(['email' => 'admin@urbanpos.com'], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->admin->assignRole('Owner');
        session(['active_branch_id' => $this->branch->id]);
        $this->actingAs($this->admin);
    }

    public function test_sales_bills_index_filters_by_b2b_sales_type()
    {
        $customer = Customer::create([
            'name' => 'B2B Client',
            'mobile' => '9876543210',
            'status' => true,
        ]);

        SalesBill::create([
            'bill_number' => 'SB-B2B-001',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'invoice_type' => 'Retail Invoice',
            'sales_type' => 'B2B',
            'total' => 500.00,
        ]);

        SalesBill::create([
            'bill_number' => 'SB-B2C-002',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'invoice_type' => 'Retail Invoice',
            'sales_type' => 'Retail',
            'total' => 300.00,
        ]);

        // Filter by B2B
        $resB2b = $this->get(route('sales.sales-bills.index', ['sales_type' => 'B2B']));
        $resB2b->assertStatus(200);
        $resB2b->assertSee('SB-B2B-001');
        $resB2b->assertDontSee('SB-B2C-002');
        $resB2b->assertSee('Sales Type');

        // Filter by B2C
        $resB2c = $this->get(route('sales.sales-bills.index', ['sales_type' => 'B2C']));
        $resB2c->assertStatus(200);
        $resB2c->assertSee('SB-B2C-002');
        $resB2c->assertDontSee('SB-B2B-001');
    }
}
