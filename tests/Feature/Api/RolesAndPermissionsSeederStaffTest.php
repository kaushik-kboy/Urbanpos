<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies the additive Staff role + new stock-verification/barcode-mapping permissions
 * run cleanly against the sqlite in-memory test DB (never against real MySQL — see task
 * constraints). Does not touch any existing role/permission assertion.
 */
class RolesAndPermissionsSeederStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_staff_role_with_only_submit_and_mapping_request_permissions(): void
    {
        (new RolesAndPermissionsSeeder())->run();

        $staff = Role::where('name', 'Staff')->first();
        $this->assertNotNull($staff, 'Staff role was not created by the seeder.');

        $permissionNames = $staff->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame(
            ['barcode-mapping.request', 'stock-verification.submit'],
            $permissionNames
        );
    }

    public function test_seeder_grants_manager_and_owner_review_and_assign_task_permissions(): void
    {
        (new RolesAndPermissionsSeeder())->run();

        $manager = Role::where('name', 'Manager')->first();
        $owner = Role::where('name', 'Owner')->first();

        $managerPermissionNames = $manager->permissions->pluck('name')->all();
        $ownerPermissionNames = $owner->permissions->pluck('name')->all();

        foreach ([
            'stock-verification.submit',
            'stock-verification.review',
            'stock-verification.assign-task',
            'barcode-mapping.request',
            'barcode-mapping.review',
        ] as $expected) {
            $this->assertContains($expected, $managerPermissionNames, "Manager missing {$expected}");
            $this->assertContains($expected, $ownerPermissionNames, "Owner missing {$expected}");
        }
    }
}
