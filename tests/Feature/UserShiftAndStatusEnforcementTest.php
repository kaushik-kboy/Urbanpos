<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserShiftAndStatusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Test Branch', 'code' => 'MTB-1']);

        $this->ownerUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->ownerUser->assignRole('Owner');
    }

    public function test_active_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login_and_gets_deactivated_error(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_logged_in_user_becomes_deactivated_is_immediately_logged_out(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        // Log in and verify access
        $this->actingAs($user);
        $this->get('/home')->assertOk();

        // Deactivate user in database
        $user->update(['is_active' => false]);

        // Next request should terminate session and redirect to login
        $response = $this->get('/home');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_outside_shift_hours_cannot_login(): void
    {
        // Freeze time to 14:00 (2:00 PM)
        Carbon::setTestNow(Carbon::createFromTime(14, 0, 0));

        // Shift is morning 08:00 - 12:00
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'time_in' => '08:00',
            'time_out' => '12:00',
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_user_inside_shift_hours_can_login(): void
    {
        // Freeze time to 10:00 AM
        Carbon::setTestNow(Carbon::createFromTime(10, 0, 0));

        // Shift is 08:00 - 17:00
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);

        Carbon::setTestNow();
    }

    public function test_user_session_automatically_expires_when_shift_time_passes(): void
    {
        // Start within shift at 16:59
        Carbon::setTestNow(Carbon::createFromTime(16, 59, 0));

        $user = User::factory()->create([
            'is_active' => true,
            'time_in' => '09:00',
            'time_out' => '17:00',
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('Cashier');

        $this->actingAs($user);
        $this->get('/home')->assertOk();

        // Advance time past 17:00 (e.g. 17:05)
        Carbon::setTestNow(Carbon::createFromTime(17, 5, 0));

        $response = $this->get('/home');
        $response->assertRedirect(route('login'));
        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_admin_owner_is_exempt_from_shift_restrictions(): void
    {
        // Freeze time outside shift at 23:00
        Carbon::setTestNow(Carbon::createFromTime(23, 0, 0));

        // Owner has daytime shift set, but should never be locked out
        $this->ownerUser->update([
            'time_in' => '09:00',
            'time_out' => '17:00',
        ]);

        $this->actingAs($this->ownerUser);
        $this->get('/home')->assertOk();

        Carbon::setTestNow();
    }

    public function test_owner_can_create_and_update_user_status_and_shift_timings(): void
    {
        // Create user with shift
        $response = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('master.users.store'), [
                'name' => 'New Staff Cashier',
                'email' => 'cashier.shift@example.com',
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
                'role' => 'Cashier',
                'branch_id' => $this->branch->id,
                'is_active' => 1,
                'time_in' => '09:00',
                'time_out' => '18:00',
            ]);

        $response->assertRedirect(route('master.users.index'));

        $created = User::where('email', 'cashier.shift@example.com')->firstOrFail();
        $this->assertTrue($created->is_active);
        $this->assertEquals('09:00', substr($created->time_in, 0, 5));
        $this->assertEquals('18:00', substr($created->time_out, 0, 5));

        // Update to deactivated and change shift
        $updateResponse = $this->actingAs($this->ownerUser)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->put(route('master.users.update', $created->id), [
                'name' => 'New Staff Cashier',
                'email' => 'cashier.shift@example.com',
                'role' => 'Cashier',
                'branch_id' => $this->branch->id,
                'is_active' => 0,
                'time_in' => '10:00',
                'time_out' => '19:00',
            ]);

        $updateResponse->assertRedirect(route('master.users.index'));

        $created->refresh();
        $this->assertFalse($created->is_active);
        $this->assertEquals('10:00', substr($created->time_in, 0, 5));
        $this->assertEquals('19:00', substr($created->time_out, 0, 5));
    }
}
