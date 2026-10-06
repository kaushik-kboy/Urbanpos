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

class RolePermissionsMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Burn User ID 1 so super-admin bypass doesn't mask permission checks
        User::factory()->create();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-1']);

        $this->ownerUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->ownerUser->assignRole('Owner');
    }

    public function test_get_permission_groups_returns_all_expected_groups_and_modules(): void
    {
        $groups = RoleController::getPermissionGroups();

        // Verify key modules are present
        $this->assertArrayHasKey('reports', $groups);
        $this->assertArrayHasKey('compliance', $groups);
        $this->assertArrayHasKey('tools', $groups);
        $this->assertArrayHasKey('system', $groups);
        $this->assertArrayHasKey('inventory', $groups);
        $this->assertArrayHasKey('masters', $groups);

        // Verify Reports modules
        $reportModules = array_keys($groups['reports']['modules']);
        $this->assertContains('reports-dashboard', $reportModules);
        $this->assertContains('reports-sales', $reportModules);
        $this->assertContains('reports-purchase', $reportModules);
        $this->assertContains('reports-inventory', $reportModules);
        $this->assertContains('reports-finance', $reportModules);
        $this->assertContains('reports-audit', $reportModules);
        $this->assertContains('reports-analytics-builder', $reportModules);
        $this->assertContains('reports-smart-analytics', $reportModules);

        // Verify Compliance modules
        $complianceModules = array_keys($groups['compliance']['modules']);
        $this->assertContains('eway-bills', $complianceModules);
        $this->assertContains('einvoices', $complianceModules);
        $this->assertContains('gst-returns', $complianceModules);

        // Verify Tools modules
        $toolsModules = array_keys($groups['tools']['modules']);
        $this->assertContains('whatsapp-settings', $toolsModules);
        $this->assertContains('document-sequences', $toolsModules);
        $this->assertContains('custom-fields', $toolsModules);
        $this->assertContains('form-validations', $toolsModules);
        $this->assertContains('receipt-designer', $toolsModules);
        $this->assertContains('bulk-updater', $toolsModules);

        // Verify System modules
        $systemModules = array_keys($groups['system']['modules']);
        $this->assertContains('database-backups', $systemModules);
        $this->assertContains('system-health', $systemModules);
        $this->assertContains('system-error-logs', $systemModules);

        // Verify Inventory new modules
        $inventoryModules = array_keys($groups['inventory']['modules']);
        $this->assertContains('kit-recipes', $inventoryModules);
        $this->assertContains('barcode-printing', $inventoryModules);
    }

    public function test_roles_create_and_edit_forms_render_all_permission_groups(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('master.roles.create'));

        $response->assertOk();
        $response->assertSee('Reports & Business Intelligence');
        $response->assertSee('GST, E-Way Bill & E-Invoice Compliance');
        $response->assertSee('Tools & Configuration Settings');
        $response->assertSee('System Maintenance & Diagnostic Logs');
        $response->assertSee('reports-dashboard.view');
        $response->assertSee('reports-analytics-builder');

        $cashierRole = Role::where('name', 'Cashier')->firstOrFail();
        $editResponse = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('master.roles.edit', $cashierRole->id));

        $editResponse->assertOk();
        $editResponse->assertSee('Reports & Business Intelligence');
        $editResponse->assertSee('Cashier');
    }

    public function test_owner_can_update_role_permissions_including_reports(): void
    {
        $testRole = Role::create(['name' => 'Custom Manager', 'guard_name' => 'web']);

        $newPermissions = [
            'reports-dashboard.view',
            'reports-sales.view',
            'reports-sales.export',
            'whatsapp-settings.edit',
            'eway-bills.create',
            'kit-recipes.create',
        ];

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->put(route('master.roles.update', $testRole->id), [
                'name' => 'Custom Manager',
                'permissions' => $newPermissions,
            ]);

        $response->assertRedirect(route('master.roles.index'));

        $testRole->refresh();
        foreach ($newPermissions as $perm) {
            $this->assertTrue(
                $testRole->hasPermissionTo($perm),
                "Role should have permission: {$perm}"
            );
        }

        $this->assertFalse($testRole->hasPermissionTo('users.create'));
    }

    public function test_cashier_role_has_only_scoped_permissions_by_default(): void
    {
        $cashierRole = Role::where('name', 'Cashier')->firstOrFail();

        $this->assertTrue($cashierRole->hasPermissionTo('sales-bills.create'));
        $this->assertTrue($cashierRole->hasPermissionTo('sales-returns.create'));
        $this->assertTrue($cashierRole->hasPermissionTo('till.open'));
        $this->assertTrue($cashierRole->hasPermissionTo('till.close'));
        $this->assertTrue($cashierRole->hasPermissionTo('customers.create'));

        $this->assertFalse($cashierRole->hasPermissionTo('purchase-invoices.create'));
        $this->assertFalse($cashierRole->hasPermissionTo('reports-dashboard.view'));
        $this->assertFalse($cashierRole->hasPermissionTo('database-backups.create'));
    }
}
