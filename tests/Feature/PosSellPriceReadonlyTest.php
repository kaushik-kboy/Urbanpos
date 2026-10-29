<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSellPriceReadonlyTest extends TestCase
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

    public function test_pos_view_renders_sell_price_readonly_true_when_configured()
    {
        FormFieldValidation::updateOrCreate(
            ['module_key' => 'sales_bills', 'field_name' => 'sell_price'],
            [
                'field_label' => 'Sell Price',
                'field_type' => 'number',
                'is_readonly' => true,
                'custom_error_message' => 'Selling price cannot exceed MRP.',
            ]
        );

        $response = $this->get(route('pos.terminal'));

        $response->assertStatus(200);
        $response->assertSee('window.IS_SELL_PRICE_READONLY = true;', false);
    }
}
