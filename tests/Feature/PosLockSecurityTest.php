<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosLockSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_unlock_pos_with_default_pin_0000(): void
    {
        $user = User::factory()->create([
            'pos_pin' => null,
        ]);

        $response = $this->actingAs($user)->postJson(route('pos.verify-pin'), [
            'pin' => '0000',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Verify pos_pin was set to 0000
        $user->refresh();
        $this->assertTrue(Hash::check('0000', $user->pos_pin));
    }

    public function test_user_can_unlock_with_configured_pin(): void
    {
        $user = User::factory()->create([
            'pos_pin' => Hash::make('1234'),
        ]);

        $response = $this->actingAs($user)->postJson(route('pos.verify-pin'), [
            'pin' => '1234',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_invalid_pin_is_rejected(): void
    {
        $user = User::factory()->create([
            'pos_pin' => Hash::make('1234'),
        ]);

        $response = $this->actingAs($user)->postJson(route('pos.verify-pin'), [
            'pin' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_user_can_update_their_pos_pin(): void
    {
        $user = User::factory()->create([
            'pos_pin' => Hash::make('1111'),
        ]);

        $response = $this->actingAs($user)->postJson(route('pos.update-pin'), [
            'current_pin' => '1111',
            'new_pin'     => '5555',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('5555', $user->pos_pin));
    }
}
