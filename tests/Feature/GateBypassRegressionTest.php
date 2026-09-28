<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * BUG-001 (critical): Gate::before granted a blanket bypass to the Manager role and to
 * anyone whose name/email merely contained "admin"/"owner", nullifying all role gating.
 */
class GateBypassRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // id 1 has a hard-coded super-user bypass; burn it so the users under test are not id 1.
        User::factory()->create();
    }

    private function userWithRole(string $role, array $attrs = []): User
    {
        $u = User::factory()->create($attrs);
        $u->assignRole($role);

        return $u;
    }

    public function test_manager_does_not_bypass_owner_only_permissions(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->assertFalse(Gate::forUser($manager)->allows('users.create'));
        $this->assertFalse(Gate::forUser($manager)->allows('financial-years.lock'));
        $this->assertTrue(Gate::forUser($manager)->allows('sales-bills.create'));
    }

    public function test_name_or_email_containing_admin_or_owner_grants_nothing(): void
    {
        $cashier = $this->userWithRole('Cashier', ['name' => 'Radmin Kumar', 'email' => 'shop.owner@example.com']);

        $this->assertFalse(Gate::forUser($cashier)->allows('users.create'));
        $this->assertFalse(Gate::forUser($cashier)->allows('financial-years.lock'));
    }

    public function test_owner_still_has_full_access(): void
    {
        $owner = $this->userWithRole('Owner');

        $this->assertTrue(Gate::forUser($owner)->allows('users.create'));
        $this->assertTrue(Gate::forUser($owner)->allows('financial-years.lock'));
    }

    public function test_manager_cannot_create_a_user_via_http(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->post(route('master.users.store'), [
            'name' => 'Escalation', 'email' => 'esc@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'Owner',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'esc@test.com']);
    }
}
