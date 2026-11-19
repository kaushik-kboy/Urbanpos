<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAddressEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);
    }

    public function test_customer_creation_with_multiple_addresses_and_google_map_links(): void
    {
        $payload = [
            'name' => 'Dr. Swati Test Customer',
            'mobile' => '9988776655',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 10000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 30,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 1,
            'customer_type' => 'RETAIL INVOICE',
            'phone' => '079-22334455',
            'email' => 'swati@example.com',
            'addresses' => [
                [
                    'id' => '',
                    '_delete' => '0',
                    'is_primary' => '1',
                    'address_type' => 'Home',
                    'address_line' => 'Flat 402, Shivalik Residency, Bodakdev',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'postal_code' => '380054',
                    'landmark' => 'Near Judges Bunglow',
                    'google_map_link' => 'https://maps.google.com/?q=23.037,72.512',
                ],
                [
                    'id' => '',
                    '_delete' => '0',
                    'is_primary' => '0',
                    'address_type' => 'Clinic',
                    'address_line' => 'Shop 12, Pet Care Clinic, Vastrapur',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'postal_code' => '380015',
                    'landmark' => 'Opp Vastrapur Lake',
                    'google_map_link' => 'https://maps.app.goo.gl/clinicXYZ123',
                ],
            ],
        ];

        $response = $this->post(route('master.customers.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('master.customers.index'));

        $customer = Customer::where('mobile', '9988776655')->first();
        $this->assertNotNull($customer);

        // Verify mirrored primary address
        $this->assertEquals('Flat 402, Shivalik Residency, Bodakdev', $customer->address1);
        $this->assertEquals('Ahmedabad', $customer->city);
        $this->assertEquals('Gujarat', $customer->state);
        $this->assertEquals('https://maps.google.com/?q=23.037,72.512', $customer->google_map_link);

        // Verify multiple addresses in customer_addresses table
        $addresses = $customer->addresses()->get();
        $this->assertCount(2, $addresses);

        $home = $addresses->where('address_type', 'Home')->first();
        $this->assertNotNull($home);
        $this->assertTrue((bool) $home->is_primary);
        $this->assertEquals('https://maps.google.com/?q=23.037,72.512', $home->google_map_link);

        $clinic = $addresses->where('address_type', 'Clinic')->first();
        $this->assertNotNull($clinic);
        $this->assertFalse((bool) $clinic->is_primary);
        $this->assertEquals('https://maps.app.goo.gl/clinicXYZ123', $clinic->google_map_link);

        // Verify POS customer invoices/history API returns google_map_link for each address
        $apiResp = $this->getJson(route('sales.sales-bills.customer-invoices', $customer->id));
        $apiResp->assertOk();
        $apiData = $apiResp->json();

        $this->assertArrayHasKey('addresses', $apiData);
        $this->assertCount(2, $apiData['addresses']);
        $this->assertEquals('https://maps.google.com/?q=23.037,72.512', $apiData['addresses'][0]['google_map_link']);
        $this->assertEquals('https://maps.app.goo.gl/clinicXYZ123', $apiData['addresses'][1]['google_map_link']);
    }
}
