<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionValidityTest extends TestCase
{
    use RefreshDatabase;
    public function test_active_subscription_allows_normal_access(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/v1/license/verify*' => Http::response([
                'success' => true,
                'status' => 'active',
                'is_allowed_billing' => true,
            ], 200),
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_expired_subscription_displays_chandak_lockscreen(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/v1/license/verify*' => Http::response([
                'success' => true,
                'status' => 'expired',
                'is_allowed_billing' => false,
                'support_contact' => [
                    'phone' => '+91-9876543210',
                ]
            ], 200),
        ]);

        $user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->actingAs($user)->get('/master/items');
        $response->assertStatus(403);
        $response->assertSee('Subscription Expired');
        $response->assertSee('Chandak Infotech');
    }

    public function test_fail_open_allows_access_when_saas_hub_is_offline(): void
    {
        Cache::flush();

        // Simulate network timeout or offline SaaS Hub
        Http::fake([
            '*/api/v1/license/verify*' => Http::response([], 500),
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200); // Fail-open: Store still runs!
    }
}
