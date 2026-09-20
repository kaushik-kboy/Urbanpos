<?php

namespace Tests\Feature;

use App\Models\SalesBill;
use App\Models\User;
use App\Models\WhatsAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_whatsapp_settings_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.whatsapp-settings.index'));

        $response->assertStatus(200);
        $response->assertSee('WhatsApp Integration & Template Settings');
        $response->assertSee('ChatOnClick API Credentials');
        $response->assertSee('Live WhatsApp Tester');
    }

    public function test_whatsapp_settings_can_be_updated_via_form(): void
    {
        $payload = [
            'api_url'           => 'https://chatonclick.com',
            'app_key'           => 'test-app-key-12345',
            'auth_key'          => 'test-auth-key-secret',
            'template_name'     => 'urban_custom_invoice',
            'template_lang'     => 'en',
            'header_title'      => 'URBAN PETS STORE',
            'footer_message'    => 'Thank you for choosing us!',
            'support_phone'     => '9876543210',
            'auto_send_on_bill' => '1',
            'is_active'         => '1',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('tools.whatsapp-settings.update'), $payload);

        $response->assertRedirect(route('tools.whatsapp-settings.index'));
        $response->assertSessionHas('status');

        $setting = WhatsAppSetting::current();
        $this->assertEquals('test-app-key-12345', $setting->app_key);
        $this->assertEquals('test-auth-key-secret', $setting->auth_key);
        $this->assertEquals('urban_custom_invoice', $setting->template_name);
        $this->assertEquals('URBAN PETS STORE', $setting->header_title);
        $this->assertEquals('Thank you for choosing us!', $setting->footer_message);
        $this->assertEquals('9876543210', $setting->support_phone);
        $this->assertTrue($setting->auto_send_on_bill);
        $this->assertTrue($setting->is_active);
    }

    public function test_whatsapp_test_dispatch_api_call(): void
    {
        Http::fake([
            'chatonclick.com/api/whatsapp/message' => Http::response([
                'success' => true,
                'data' => [
                    'mid' => 'test-mid-789456',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('tools.whatsapp-settings.test'), [
                'phone' => '9876543210',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'wamid'   => 'test-mid-789456',
        ]);
    }
}
