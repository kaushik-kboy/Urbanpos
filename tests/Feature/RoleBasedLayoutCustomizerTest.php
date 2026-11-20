<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\RoleFormPreference;
use App\Models\RoleTablePreference;
use App\Models\User;
use App\Models\UserFormPreference;
use App\Models\UserTablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleBasedLayoutCustomizerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;
    protected User $staffUser;
    protected Role $adminRole;
    protected Role $cashierRole;
    protected Role $staffRole;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Outlet', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $this->adminRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        $this->staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($this->adminRole);

        $this->cashierUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashierUser->assignRole($this->cashierRole);

        $this->staffUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->staffUser->assignRole($this->staffRole);
    }

    public function test_role_preference_tables_exist_with_proper_columns(): void
    {
        $this->assertTrue(Schema::hasTable('role_table_preferences'));
        $this->assertTrue(Schema::hasColumns('role_table_preferences', ['id', 'role_id', 'table_key', 'preferences']));

        $this->assertTrue(Schema::hasTable('role_form_preferences'));
        $this->assertTrue(Schema::hasColumns('role_form_preferences', ['id', 'role_id', 'form_key', 'preferences']));
    }

    public function test_cashier_without_permission_cannot_save_table_layout_gets_403(): void
    {
        $response = $this->actingAs($this->cashierUser)
            ->postJson(route('tools.table-preferences.store'), [
                'table_key' => 'sales_bills_index',
                'preferences' => [
                    ['key' => 'bill_no', 'order' => 1, 'visible' => true],
                    ['key' => 'net_amount', 'order' => 2, 'visible' => false],
                ],
            ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error']);
    }

    public function test_cashier_without_permission_cannot_save_form_layout_gets_403(): void
    {
        $response = $this->actingAs($this->cashierUser)
            ->postJson(route('tools.form-preferences.store'), [
                'form_key' => 'sales_bills.header',
                'preferences' => [
                    ['field' => 'customer_id', 'order' => 1, 'grid_col' => 'col-md-6', 'visible' => true],
                ],
            ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error']);
    }

    public function test_admin_can_set_standard_table_layout_for_cashier_role(): void
    {
        $cashierColumns = [
            ['key' => 'bill_no', 'order' => 1, 'visible' => true],
            ['key' => 'date', 'order' => 2, 'visible' => true],
            ['key' => 'net_amount', 'order' => 3, 'visible' => true],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('tools.table-preferences.store'), [
                'table_key' => 'sales_bills_index',
                'target_type' => 'role',
                'target_role_id' => $this->cashierRole->id,
                'sync_users' => true,
                'preferences' => $cashierColumns,
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        // Verify stored in RoleTablePreference
        $savedRolePref = RoleTablePreference::getForRole($this->cashierRole->id, 'sales_bills_index');
        $this->assertEquals($cashierColumns, $savedRolePref);

        // Cashier user resolving preferences automatically inherits Admin's role layout
        $resolvedCashierPref = UserTablePreference::getResolvedForUser($this->cashierUser, 'sales_bills_index');
        $this->assertEquals($cashierColumns, $resolvedCashierPref);

        // Staff user does not inherit Cashier's role layout
        $resolvedStaffPref = UserTablePreference::getResolvedForUser($this->staffUser, 'sales_bills_index');
        $this->assertNull($resolvedStaffPref);
    }

    public function test_admin_can_set_standard_form_layout_for_cashier_role(): void
    {
        $cashierFormFields = [
            ['field' => 'customer_id', 'order' => 1, 'grid_col' => 'col-md-4', 'visible' => true],
            ['field' => 'sales_type_id', 'order' => 2, 'grid_col' => 'col-md-4', 'visible' => true],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('tools.form-preferences.store'), [
                'form_key' => 'sales_bills.header',
                'target_type' => 'role',
                'target_role_id' => $this->cashierRole->id,
                'sync_users' => true,
                'preferences' => $cashierFormFields,
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        // Cashier user resolving preferences automatically inherits Admin's form layout
        $resolvedCashierPref = UserFormPreference::getResolvedForUser($this->cashierUser, 'sales_bills.header');
        $this->assertEquals($cashierFormFields, $resolvedCashierPref);
    }

    public function test_admin_sync_users_wipes_conflicting_individual_user_overrides(): void
    {
        // Seed an old individual user preference for the cashier
        UserTablePreference::create([
            'user_id' => $this->cashierUser->id,
            'table_key' => 'sales_bills_index',
            'preferences' => [
                ['key' => 'old_messed_up_field', 'order' => 99, 'visible' => false],
            ],
        ]);

        $this->assertNotNull(UserTablePreference::where('user_id', $this->cashierUser->id)->first());

        // Admin saves role layout with sync_users = true
        $cleanRoleColumns = [
            ['key' => 'item_code', 'order' => 1, 'visible' => true],
            ['key' => 'item_name', 'order' => 2, 'visible' => true],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('tools.table-preferences.store'), [
                'table_key' => 'sales_bills_index',
                'target_type' => 'role',
                'target_role_id' => $this->cashierRole->id,
                'sync_users' => true,
                'preferences' => $cleanRoleColumns,
            ]);

        $response->assertOk();

        // Old individual preference row for cashier should be purged
        $this->assertNull(UserTablePreference::where('user_id', $this->cashierUser->id)->where('table_key', 'sales_bills_index')->first());

        // Cashier resolves clean role layout
        $resolved = UserTablePreference::getResolvedForUser($this->cashierUser, 'sales_bills_index');
        $this->assertEquals($cleanRoleColumns, $resolved);
    }

    public function test_admin_can_set_global_layout_for_all_roles(): void
    {
        $globalColumns = [
            ['key' => 'col_a', 'order' => 1, 'visible' => true],
            ['key' => 'col_b', 'order' => 2, 'visible' => true],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('tools.table-preferences.store'), [
                'table_key' => 'universal_catalog_table',
                'target_type' => 'all_roles',
                'sync_users' => true,
                'preferences' => $globalColumns,
            ]);

        $response->assertOk();

        // Both Cashier and Staff inherit the layout
        $this->assertEquals($globalColumns, UserTablePreference::getResolvedForUser($this->cashierUser, 'universal_catalog_table'));
        $this->assertEquals($globalColumns, UserTablePreference::getResolvedForUser($this->staffUser, 'universal_catalog_table'));
    }

    public function test_cashier_does_not_see_customizer_trigger_buttons_in_rendered_view(): void
    {
        $rendered = view('components.table-column-customizer', [
            'tableKey' => 'test_table_key',
            'tableId' => 'test-table-id',
        ])->render();

        // When not logged in or non-admin, trigger button is not rendered
        $this->assertStringNotContainsString('btn-table-customizer-trigger', $rendered);
        $this->assertStringNotContainsString('modal fade table-customizer-modal', $rendered);
        // But the data wrapper is still rendered so JS can apply Admin column ordering
        $this->assertStringContainsString('table-customizer-wrapper', $rendered);
    }
}
