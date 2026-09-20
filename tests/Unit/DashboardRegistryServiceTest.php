<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserDashboardPreference;
use App\Services\Dashboard\DashboardRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardRegistryService::class);
    }

    public function test_owner_can_access_all_registered_shortcuts(): void
    {
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($ownerRole);

        $accessible = $this->service->getAccessibleShortcuts($user);
        $allRegistered = config('dashboard.shortcuts', []);

        $this->assertCount(count($allRegistered), $accessible);
        $this->assertTrue($accessible->has('pos_bill'));
        $this->assertTrue($accessible->has('purchase_order'));
    }

    public function test_cashier_only_sees_permitted_shortcuts(): void
    {
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales-bills.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales-returns.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'till.open', 'guard_name' => 'web']);

        $cashierRole->syncPermissions(['sales-bills.create', 'sales-returns.create', 'till.open']);

        $user = User::factory()->create();
        $user->assignRole($cashierRole);

        $accessible = $this->service->getAccessibleShortcuts($user);

        // Should have pos_bill, till_session, sales_return
        $this->assertTrue($accessible->has('pos_bill'));
        $this->assertTrue($accessible->has('till_session'));
        $this->assertTrue($accessible->has('sales_return'));

        // Should NOT have purchase_order or purchase_invoice
        $this->assertFalse($accessible->has('purchase_order'));
        $this->assertFalse($accessible->has('purchase_invoice'));
    }

    public function test_user_shortcuts_fall_back_to_role_defaults(): void
    {
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales-bills.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales-returns.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'till.open', 'guard_name' => 'web']);
        $cashierRole->syncPermissions(['sales-bills.create', 'sales-returns.create', 'till.open']);

        $user = User::factory()->create();
        $user->assignRole($cashierRole);

        $active = $this->service->getActiveShortcutsForUser($user);
        $activeKeys = $active->pluck('key')->all();

        $this->assertEquals(['pos_bill', 'till_session', 'sales_return'], $activeKeys);
    }

    public function test_saved_preferences_override_role_defaults_and_strip_unauthorized_keys(): void
    {
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales-bills.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'till.open', 'guard_name' => 'web']);
        $cashierRole->syncPermissions(['sales-bills.create', 'till.open']);

        $user = User::factory()->create();
        $user->assignRole($cashierRole);

        // User tries to save a shortcut they have (pos_bill) + a forbidden one (purchase_order)
        UserDashboardPreference::setForUser($user->id, ['pos_bill', 'purchase_order']);

        $active = $this->service->getActiveShortcutsForUser($user);
        $activeKeys = $active->pluck('key')->all();

        // purchase_order must be filtered out because cashier does not have permission
        $this->assertEquals(['pos_bill'], $activeKeys);
    }
}
