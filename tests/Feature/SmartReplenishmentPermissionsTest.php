<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\RoleController;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmartReplenishmentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;
    private User $managerUser;
    private User $staffUser;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['replenishment.enabled' => true]);

        // Burn User ID 1 so super-admin bypass doesn't mask permission checks
        User::factory()->create();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-1']);

        $this->ownerUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->ownerUser->assignRole('Owner');

        $this->managerUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->managerUser->assignRole('Manager');

        $this->staffUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->staffUser->assignRole('Cashier');
    }

    public function test_permission_groups_includes_smart_replenishment(): void
    {
        $groups = RoleController::getPermissionGroups();

        $this->assertArrayHasKey('replenishment', $groups);
        $this->assertEquals('Smart Replenishment Studio', $groups['replenishment']['label']);
        $this->assertArrayHasKey('smart-replenishment', $groups['replenishment']['modules']);

        $actions = $groups['replenishment']['modules']['smart-replenishment']['actions'];
        $this->assertArrayHasKey('view', $actions);
        $this->assertArrayHasKey('create', $actions);
        $this->assertArrayHasKey('edit', $actions);
        $this->assertArrayHasKey('sync', $actions);
        $this->assertArrayHasKey('export', $actions);
    }

    public function test_roles_create_and_edit_forms_render_smart_replenishment_checkboxes(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('master.roles.create'));

        $response->assertOk();
        $response->assertSee('Smart Replenishment Studio');
        $response->assertSee('smart-replenishment.view');
        $response->assertSee('smart-replenishment.create');
        $response->assertSee('smart-replenishment.edit');
        $response->assertSee('smart-replenishment.sync');
        $response->assertSee('smart-replenishment.export');

        $managerRole = Role::where('name', 'Manager')->firstOrFail();
        $editResponse = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('master.roles.edit', $managerRole->id));

        $editResponse->assertOk();
        $editResponse->assertSee('Smart Replenishment Studio');
    }

    public function test_manager_has_replenishment_permissions_by_default(): void
    {
        $managerRole = Role::where('name', 'Manager')->firstOrFail();

        $this->assertTrue($managerRole->hasPermissionTo('smart-replenishment.view'));
        $this->assertTrue($managerRole->hasPermissionTo('smart-replenishment.create'));
        $this->assertTrue($managerRole->hasPermissionTo('smart-replenishment.edit'));
        $this->assertTrue($managerRole->hasPermissionTo('smart-replenishment.sync'));
        $this->assertTrue($managerRole->hasPermissionTo('smart-replenishment.export'));
    }

    public function test_owner_can_grant_and_revoke_replenishment_permissions_on_custom_role(): void
    {
        $customRole = Role::create(['name' => 'Inventory Analyst', 'guard_name' => 'web']);

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->put(route('master.roles.update', $customRole->id), [
                'name' => 'Inventory Analyst',
                'permissions' => [
                    'smart-replenishment.view',
                    'smart-replenishment.export',
                ],
            ]);

        $response->assertRedirect(route('master.roles.index'));

        $customRole->refresh();
        $this->assertTrue($customRole->hasPermissionTo('smart-replenishment.view'));
        $this->assertTrue($customRole->hasPermissionTo('smart-replenishment.export'));
        $this->assertFalse($customRole->hasPermissionTo('smart-replenishment.create'));
        $this->assertFalse($customRole->hasPermissionTo('smart-replenishment.edit'));
    }

    public function test_user_without_replenishment_permission_gets_forbidden(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('inventory.replenishment.index'));

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_replenishment_studio(): void
    {
        $analystRole = Role::create(['name' => 'Analyst', 'guard_name' => 'web']);
        $analystRole->givePermissionTo(Permission::findByName('smart-replenishment.view', 'web'));

        $analystUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $analystUser->assignRole('Analyst');

        $response = $this->actingAs($analystUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('inventory.replenishment.index'));

        $response->assertOk();
    }
}
