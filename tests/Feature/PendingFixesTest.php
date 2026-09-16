<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Post-Phase-7 cleanup pass: bulk-import commands now require --force (they bypass the
 * Stock Ledger), and changing an EXISTING Customer/Supplier's credit terms is Owner-only
 * — setting them when first creating one stays open to Manager, same shape as the
 * items.create-vs-edit split from the permission-gating pass.
 */
class PendingFixesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function validCustomerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Credit Guard Customer',
            'sales_type' => 'Local',
            'payment_mode' => 'Both Cash and Credit',
            'credit_limit' => 500,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 30,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 0,
            'mobile' => '9876543210',
            'customer_type' => 'RETAIL INVOICE',
        ], $overrides);
    }

    private function validSupplierPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Credit Guard Supplier',
            'currency' => 'INR',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'credit_limit' => 500,
            'credit_balance' => 0,
            'credit_days' => 30,
            'status' => 1,
            'gst_type' => 'Un Register',
            'mail_type' => 'None',
        ], $overrides);
    }

    public function test_bulk_import_command_without_force_is_blocked(): void
    {
        $exitCode = Artisan::call('import:all-masters');
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('bypassing', Artisan::output());
    }

    public function test_manager_can_set_credit_terms_when_creating_a_customer_but_not_change_them_later(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $created = $this->post(route('master.customers.store'), $this->validCustomerPayload());
        $created->assertRedirect(route('master.customers.index'));
        $customer = Customer::where('name', 'Credit Guard Customer')->firstOrFail();
        $this->assertEquals(500, (float) $customer->credit_limit);

        // Editing an unrelated field while resubmitting the same credit values must pass.
        $unrelatedEdit = $this->put(route('master.customers.update', $customer), $this->validCustomerPayload(['name' => 'Credit Guard Customer Renamed']));
        $unrelatedEdit->assertRedirect(route('master.customers.index'));
        $this->assertEquals('Credit Guard Customer Renamed', $customer->fresh()->name);

        // Actually changing credit_limit must be blocked for Manager.
        $blockedEdit = $this->put(route('master.customers.update', $customer), $this->validCustomerPayload(['name' => 'Credit Guard Customer Renamed', 'credit_limit' => 9999]));
        $blockedEdit->assertSessionHasErrors('credit_limit');
        $this->assertEquals(500, (float) $customer->fresh()->credit_limit);
    }

    public function test_owner_can_change_an_existing_customers_credit_terms(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $customer = Customer::create($this->validCustomerPayload());

        $response = $this->put(route('master.customers.update', $customer), $this->validCustomerPayload(['credit_limit' => 9999]));
        $response->assertRedirect(route('master.customers.index'));
        $this->assertEquals(9999, (float) $customer->fresh()->credit_limit);
    }

    public function test_manager_cannot_change_an_existing_suppliers_credit_terms(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $supplier = Supplier::create($this->validSupplierPayload());

        $response = $this->put(route('master.suppliers.update', $supplier), $this->validSupplierPayload(['credit_days' => 90]));
        $response->assertSessionHasErrors('credit_days');
        $this->assertEquals(30, (int) $supplier->fresh()->credit_days);
    }
}
