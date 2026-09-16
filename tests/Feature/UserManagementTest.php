<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The Users & Roles management screen — closes the biggest practical gap left after
 * Phase 4: an Owner can now actually assign a role/branch through the app instead of
 * tinker. Confirms permission gating, correct password hashing (not double-hashed),
 * and the self-delete/last-Owner safety guards.
 */
class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_manager_is_blocked_from_creating_users(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $response = $this->post(route('master.users.store'), [
            'name' => 'New Person', 'email' => 'newperson@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'Cashier',
        ]);
        $response->assertForbidden();
    }

    public function test_owner_can_create_user_with_role_and_branch_and_password_is_correctly_hashed(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $branch = Branch::create(['name' => 'User Mgmt Test Branch']);

        $response = $this->post(route('master.users.store'), [
            'name' => 'New Cashier', 'email' => 'newcashier@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'branch_id' => $branch->id, 'role' => 'Cashier',
        ]);
        $response->assertRedirect(route('master.users.index'));

        $created = User::where('email', 'newcashier@test.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('Cashier'));
        $this->assertEquals($branch->id, $created->branch_id);

        // Not double-hashed: Auth::attempt must succeed with the plain-text password.
        Auth::logout();
        $this->assertTrue(Auth::attempt(['email' => 'newcashier@test.com', 'password' => 'password123']));
    }

    public function test_editing_a_user_with_blank_password_keeps_the_current_one(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $existing = User::factory()->create(['email' => 'keeppass@test.com', 'password' => 'originalpass']);
        $existing->assignRole('Cashier');

        $response = $this->put(route('master.users.update', $existing), [
            'name' => $existing->name, 'email' => 'keeppass@test.com',
            'password' => '', 'password_confirmation' => '',
            'role' => 'Manager',
        ]);
        $response->assertRedirect(route('master.users.index'));

        $existing->refresh();
        $this->assertTrue($existing->hasRole('Manager'));
        Auth::logout();
        $this->assertTrue(Auth::attempt(['email' => 'keeppass@test.com', 'password' => 'originalpass']), 'Blank password on edit must not overwrite the existing one.');
    }

    public function test_owner_cannot_delete_own_account(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $response = $this->from(route('master.users.index'))->delete(route('master.users.destroy', $owner));
        $response->assertSessionHasErrors('user');
        $this->assertNotNull($owner->fresh());
    }

    public function test_two_owners_allows_deleting_one_of_them(): void
    {
        $ownerA = User::factory()->create();
        $ownerA->assignRole('Owner');
        $ownerB = User::factory()->create();
        $ownerB->assignRole('Owner');
        $this->actingAs($ownerA);

        $this->delete(route('master.users.destroy', $ownerB))->assertRedirect(route('master.users.index'));
        $this->assertNull(User::find($ownerB->id));
    }

    /**
     * The "last Owner" guard is unreachable through the normal HTTP+permission path:
     * only an Owner may delete users, the self-delete guard fires first for a lone Owner
     * deleting themselves, and any OTHER Owner acting means at least two Owners exist at
     * check time — so the target can never actually be "the last one" from an allowed
     * actor's perspective. The guard is still worth keeping as a safety net against
     * future permission-model changes (e.g. Manager someday gaining users.cancel), so
     * it's tested directly against the controller instead of pretending it's reachable
     * via a route.
     */
    public function test_last_owner_guard_blocks_at_the_controller_level(): void
    {
        User::role('Owner')->get()->each(fn ($u) => $u->removeRole('Owner'));
        $lastOwner = User::factory()->create();
        $lastOwner->assignRole('Owner');
        $this->assertEquals(1, User::role('Owner')->count());

        // Distinct from the target and deliberately NOT an Owner — this direct call bypasses
        // the permission middleware entirely, so the actor's role has no bearing on whether
        // the controller method is reached; only $lastOwner's role/count matters to the guard.
        $anotherActor = User::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Http\Controllers\Master\UserController::class)->destroy(
            \Illuminate\Http\Request::create('/', 'DELETE')->setUserResolver(fn () => $anotherActor),
            $lastOwner,
        );
    }
}
