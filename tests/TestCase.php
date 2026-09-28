<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard safety net: the automated suite must never touch the real/dev database.
     * Only in-memory sqlite or a database whose name ends in "_test" is allowed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $conn = config('database.default');
        $db = (string) config("database.connections.$conn.database");

        if ($db !== ':memory:' && ! str_ends_with($db, '_test')) {
            $this->fail("Refusing to run tests against non-test database '$db'. Use phpunit.xml (sqlite) or phpunit.mysql.xml (urban_pos_test).");
        }

        // Baseline reference data (roles/permissions) that many suites assume exists.
        // Runs inside the per-test transaction, so it is rolled back automatically.
        if (\Illuminate\Support\Facades\Schema::hasTable('roles')
            && \Illuminate\Support\Facades\DB::table('roles')->doesntExist()) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
