<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSalesTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creation_with_local_sales_type_succeeds_even_when_sales_types_seeded(): void
    {
        $branch = Branch::first() ?? Branch::create(['name' => 'Test Branch', 'code' => 'TB', 'status' => true]);
        $ownerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->assignRole($ownerRole);

        // Seed SalesType table with non-Local channels (e.g. Retail, Home, Spa)
        SalesType::firstOrCreate(['name' => 'Retail', 'code' => 'RETAIL', 'status' => true]);
        SalesType::firstOrCreate(['name' => 'Home', 'code' => 'HOME', 'status' => true]);

        $uniqueMobile = '98' . rand(10000000, 99999999);

        $payload = [
            'title' => 'Mr',
            'name' => 'Test Customer Local',
            'mobile' => $uniqueMobile,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 100000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 30,
            'branch_id' => $branch->id,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 1,
            'customer_type' => 'RETAIL INVOICE',
        ];

        $response = $this->actingAs($user)->post(route('master.customers.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'mobile' => $uniqueMobile,
            'sales_type' => 'Local',
        ]);
    }

    public function test_customer_creation_with_interstate_sales_type_succeeds(): void
    {
        $branch = Branch::first() ?? Branch::create(['name' => 'Test Branch', 'code' => 'TB', 'status' => true]);
        $ownerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->assignRole($ownerRole);

        $uniqueMobile = '97' . rand(10000000, 99999999);

        $payload = [
            'title' => 'Ms',
            'name' => 'Test Customer Interstate',
            'mobile' => $uniqueMobile,
            'sales_type' => 'Interstate',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 50000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 15,
            'branch_id' => $branch->id,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 1,
            'customer_type' => 'RETAIL INVOICE',
        ];

        $response = $this->actingAs($user)->post(route('master.customers.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'mobile' => $uniqueMobile,
            'sales_type' => 'Interstate',
        ]);
    }
}
