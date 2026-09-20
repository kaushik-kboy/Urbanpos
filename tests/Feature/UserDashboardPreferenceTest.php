<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDashboardPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDashboardPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($ownerRole);
    }

    public function test_home_page_renders_with_customizer_button_and_shortcuts(): void
    {
        $response = $this->actingAs($this->user)->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Customize Dashboard');
        $response->assertSee('dashboardCustomizerModal');
        $response->assertSee('dashboard-shortcut-grid');
        $response->assertSee('New POS Bill');
    }

    public function test_user_can_save_custom_shortcuts_and_widgets(): void
    {
        $payload = [
            'shortcuts' => ['pos_bill', 'sales_quotation', 'quick_customer'],
            'widgets'   => ['kpi_sales', 'revenue_trend'],
        ];

        $response = $this->actingAs($this->user)->post(route('user.dashboard-preferences.save'), $payload);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('user_dashboard_preferences', [
            'user_id' => $this->user->id,
        ]);

        $pref = UserDashboardPreference::getForUser($this->user->id);
        $this->assertEquals(['pos_bill', 'sales_quotation', 'quick_customer'], $pref->shortcuts);
        $this->assertEquals(['kpi_sales', 'revenue_trend'], $pref->visible_widgets);
    }

    public function test_ajax_request_returns_json_response(): void
    {
        $payload = [
            'shortcuts' => ['pos_bill', 'delivery_note'],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('user.dashboard-preferences.save'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'shortcuts_count' => 2,
        ]);
    }

    public function test_user_can_reset_dashboard_to_defaults(): void
    {
        UserDashboardPreference::setForUser($this->user->id, ['pos_bill']);
        $this->assertDatabaseHas('user_dashboard_preferences', ['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post(route('user.dashboard-preferences.reset'));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseMissing('user_dashboard_preferences', ['user_id' => $this->user->id]);
    }
}
