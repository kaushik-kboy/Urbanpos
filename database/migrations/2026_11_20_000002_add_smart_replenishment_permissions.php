<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! class_exists(Permission::class) || ! class_exists(Role::class)) {
            return;
        }

        $actions = ['view', 'create', 'edit', 'sync', 'export'];
        $createdPermissions = [];

        foreach ($actions as $action) {
            $createdPermissions[] = Permission::firstOrCreate([
                'name' => "smart-replenishment.{$action}",
                'guard_name' => 'web',
            ]);
        }

        // Grant full access to system roles
        $targetRoles = Role::whereIn('name', ['Owner', 'Super Admin', 'Admin', 'Administrator', 'Manager'])->get();
        foreach ($targetRoles as $role) {
            $role->givePermissionTo($createdPermissions);
        }

        // Grant to any custom role that already held smart-replenishment.view
        $viewPerm = Permission::where('name', 'smart-replenishment.view')->first();
        if ($viewPerm) {
            $rolesWithView = Role::whereHas('permissions', function ($q) use ($viewPerm) {
                $q->where('permissions.id', $viewPerm->id);
            })->get();

            foreach ($rolesWithView as $role) {
                $role->givePermissionTo($createdPermissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down: do not delete system permissions to prevent accidental access cut
    }
};
