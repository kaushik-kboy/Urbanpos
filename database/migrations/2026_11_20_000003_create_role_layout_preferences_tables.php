<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        if (! Schema::hasTable('role_table_preferences')) {
            Schema::create('role_table_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->string('table_key', 100)->index();
                $table->json('preferences');
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->unique(['role_id', 'table_key']);
            });
        }

        if (! Schema::hasTable('role_form_preferences')) {
            Schema::create('role_form_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->string('form_key', 100)->index();
                $table->json('preferences');
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->unique(['role_id', 'form_key']);
            });
        }

        // Register layout customization permissions
        if (class_exists(Permission::class) && class_exists(Role::class)) {
            $perms = [
                'layout-customizer.customize',
                'layout.customize',
            ];

            $created = [];
            foreach ($perms as $permName) {
                $created[] = Permission::firstOrCreate([
                    'name' => $permName,
                    'guard_name' => 'web',
                ]);
            }

            $adminRoles = Role::whereIn('name', ['Owner', 'Super Admin', 'Admin', 'Administrator', 'Manager'])->get();
            foreach ($adminRoles as $role) {
                $role->givePermissionTo($created);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_table_preferences');
        Schema::dropIfExists('role_form_preferences');

        if (class_exists(Permission::class)) {
            Permission::whereIn('name', ['layout-customizer.customize', 'layout.customize'])->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
