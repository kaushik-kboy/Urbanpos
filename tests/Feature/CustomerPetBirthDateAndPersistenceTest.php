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

    public function test_customer_pet_birth_date_serializes_as_exact_ymd_string(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'mobile' => '9876543210',
            'customer_type' => 'RETAIL INVOICE',
        ]);

        $pet = CustomerPet::create([
            'customer_id' => $customer->id,
            'name' => 'Bruno',
            'gender' => 'Male',
            'birth_date' => '2024-05-15',
        ]);

        $array = $pet->toArray();
        $this->assertEquals('2024-05-15', $array['birth_date']);

        $json = json_decode($pet->toJson(), true);
        $this->assertEquals('2024-05-15', $json['birth_date']);
    }

    public function test_sync_pets_saves_pet_when_only_birth_date_and_gender_provided(): void
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
                    'birth_date' => '2023-11-20',
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
            'birth_date' => '2023-11-20',
        ]);
    }
}
