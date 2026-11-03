<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ReplenishmentSalesRule;
use App\Models\User;
use Database\Seeders\ReplenishmentRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmartReplenishmentUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['replenishment.enabled' => true]);
        $this->seed(ReplenishmentRulesSeeder::class);

        // Ensure permissions and roles
        $permission = Permission::firstOrCreate(['name' => 'smart-replenishment.view', 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permission);

        $this->branch = Branch::create([
            'name' => 'Urban Pets - Motera Test',
            'status' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole($ownerRole);
    }

    public function test_dashboard_index_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.index'));
        $response->assertStatus(200);
        $response->assertSee('Smart Replenishment Studio');
        $response->assertSee('Stock Replenishment Analysis');
    }

    public function test_ajax_data_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('inventory.replenishment.data', [
            'branch_id' => $this->branch->id,
            'status' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'per_page',
        ]);
    }

    public function test_sync_trigger_endpoint_executes_successfully(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.sync'), [
            'branch_id' => $this->branch->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_settings_update_modifies_rules(): void
    {
        $rule = ReplenishmentSalesRule::first();
        $this->assertNotNull($rule);

        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.settings.update'), [
            'sales_rules' => [
                $rule->id => 35,
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(35, $rule->fresh()->target_cover_days);
    }

    public function test_transfers_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.transfers'));
        $response->assertStatus(200);
        $response->assertSee('Smart Inter-Branch Matchmaker');
    }

    public function test_supplier_orders_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.supplier-orders'));
        $response->assertStatus(200);
        $response->assertSee('Supplier-wise Order Recommendations');
    }

    public function test_feature_flag_disabled_returns_404(): void
    {
        config(['replenishment.enabled' => false]);

        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.index'));
        $response->assertStatus(404);
    }
}
