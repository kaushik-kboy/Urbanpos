<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosTerminalUserAndSalesReturnItemSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat', 'status' => true]
        );

        $this->user = User::factory()->create([
            'name' => 'Rajesh Cashier',
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole('Owner');
    }

    public function test_pos_terminal_displays_logged_in_user_name_without_dropdown(): void
    {
        $response = $this->actingAs($this->user)->get(route('pos.terminal'));

        $response->assertStatus(200);
        // Logged-in user name is displayed
        $response->assertSee('Rajesh Cashier');
        // No <select id="posBillerSelect" exists
        $response->assertDontSee('<select id="posBillerSelect"', false);
        // Hidden input with id="posBillerSelect" and logged-in user's id is present
        $response->assertSee('<input type="hidden" id="posBillerSelect" value="' . $this->user->id . '">', false);
    }

    public function test_sales_return_form_renders_checklist_and_not_auto_selecting_items(): void
    {
        $response = $this->actingAs($this->user)->get(route('sales.sales-returns.create'));

        $response->assertStatus(200);
        // Checklist has Select All & Deselect All controls
        $response->assertSee('id="sr-btn-select-all-bill-items"', false);
        $response->assertSee('id="sr-btn-deselect-all-bill-items"', false);
        // Clear instruction that items are not selected by default
        $response->assertSee('Items are not selected by default');
    }
}
