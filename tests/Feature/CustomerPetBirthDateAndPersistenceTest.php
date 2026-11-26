<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPet;
use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPetBirthDateAndPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

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

        $this->user = User::firstOrCreate(['email' => 'admin@urbanpos.com'], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->user->assignRole('Owner');
        session(['active_branch_id' => $this->branch->id]);
        $this->actingAs($this->user);

        CustomerType::firstOrCreate(['name' => 'RETAIL INVOICE'], [
            'status' => true,
        ]);
    }

    public function test_customer_pet_birth_date_serializes_and_formats_both_dd_mm_yyyy_and_mm_yyyy(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'mobile' => '9876543210',
            'customer_type' => 'RETAIL INVOICE',
        ]);

        // Full date
        $petFull = CustomerPet::create([
            'customer_id' => $customer->id,
            'name' => 'Bruno',
            'gender' => 'Male',
            'birth_date' => '15-05-2024',
        ]);

        $this->assertEquals('15-05-2024', (string) $petFull->birth_date);
        $this->assertEquals('15-05-2024', optional($petFull->birth_date)->format('d-m-Y'));
        $jsonFull = json_decode($petFull->toJson(), true);
        $this->assertEquals('15-05-2024', $jsonFull['birth_date']);

        // Month and Year only
        $petMonthYear = CustomerPet::create([
            'customer_id' => $customer->id,
            'name' => 'Milo',
            'gender' => 'Male',
            'birth_date' => '10-2023',
        ]);

        $this->assertEquals('10-2023', (string) $petMonthYear->birth_date);
        $this->assertEquals('10-2023', optional($petMonthYear->birth_date)->format('d-m-Y'));
        $jsonMY = json_decode($petMonthYear->toJson(), true);
        $this->assertEquals('10-2023', $jsonMY['birth_date']);
    }

    public function test_sync_pets_saves_pet_when_only_month_year_birth_date_provided(): void
    {
        $customer = Customer::create([
            'name' => 'Jane Doe',
            'mobile' => '9876543211',
            'customer_type' => 'RETAIL INVOICE',
        ]);

        $response = $this->put(route('master.customers.update', $customer), [
            'name' => 'Jane Doe',
            'mobile' => '9876543211',
            'customer_type' => 'RETAIL INVOICE',
            'pets' => [
                [
                    'gender' => 'Female',
                    'birth_date' => '11-2023',
                    'name' => '',
                    'breed_id' => '',
                    'pet_type_id' => '',
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_pets', [
            'customer_id' => $customer->id,
            'gender' => 'Female',
            'birth_date' => '11-2023',
        ]);
    }

    public function test_customer_export_downloads_excel_file_with_filters(): void
    {
        $cust1 = Customer::create([
            'name' => 'Alice Pet Parent',
            'mobile' => '9876543220',
            'customer_type' => 'RETAIL INVOICE',
        ]);

        $cust2 = Customer::create([
            'name' => 'Bob No Pets',
            'mobile' => '9876543221',
            'customer_type' => 'RETAIL INVOICE',
        ]);

        CustomerPet::create([
            'customer_id' => $cust1->id,
            'name' => 'Tommy',
            'gender' => 'Male',
            'birth_date' => date('d-m-Y'),
        ]);

        // 1. Export all customers
        $resAll = $this->get(route('master.customers.export'));
        $resAll->assertOk();
        $this->assertStringContainsString('customers_', (string) $resAll->headers->get('content-disposition'));

        // 2. Export filtered customers
        $resFilter = $this->get(route('master.customers.export', ['filter' => 'today_birthdays']));
        $resFilter->assertOk();
        $this->assertStringContainsString('customers_today_birthdays_', (string) $resFilter->headers->get('content-disposition'));

        // 3. Export search filtered
        $resSearch = $this->get(route('master.customers.export', ['search' => 'Alice']));
        $resSearch->assertOk();
    }
}
