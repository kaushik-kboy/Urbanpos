<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PosLatencyPingTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main POS Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_unauthenticated_user_cannot_access_ping_endpoint(): void
    {
        $response = $this->get(route('pos.ping'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_cashier_receives_fast_pong_json(): void
    {
        $response = $this->actingAs($this->user)->get(route('pos.ping'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'server_time',
        ]);
        $response->assertJson([
            'status' => 'pong',
        ]);
    }
}
