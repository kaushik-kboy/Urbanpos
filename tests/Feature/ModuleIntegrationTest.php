<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_integration_index_renders_successfully(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get(route('tools.module-integrations.index'));
        $response->assertOk();
    }
}
