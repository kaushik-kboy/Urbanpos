<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $branch = Branch::create(['name' => 'Main Branch', 'status' => true]);
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('secret123'),
            'branch_id' => $branch->id,
        ]);
        $user->assignRole('Staff');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'branch_id', 'role']]);

        $this->assertSame('Staff', $response->json('user.role'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'staff2@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff2@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_protected_route_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->assignRole('Owner');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');

        $me = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $me->assertOk()->assertJsonPath('email', 'owner@example.com');

        $logout = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');
        $logout->assertOk();

        // Token should now be revoked in the database. (A further HTTP call reusing
        // the same in-process app instance would hit Laravel's RequestGuard caching
        // the resolved user on the guard object for the test's lifetime — not a real
        // app behavior — so we assert the revocation at the data layer instead.)
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
