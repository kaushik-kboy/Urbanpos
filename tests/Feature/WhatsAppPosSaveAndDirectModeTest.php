<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\User;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppPosSaveAndDirectModeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customerWithMobile;
    private Customer $customerNoMobile;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::first() ?? Branch::create(['name' => 'Motera Store', 'code' => 'MOT', 'state' => 'Gujarat']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
        \App\Models\TenderType::firstOrCreate(['id' => 1], ['name' => 'Cash', 'type' => 'Cash', 'status' => true]);

        $this->customerWithMobile = Customer::create([
            'name' => 'Ramesh Sharma',
            'mobile' => '9876543210',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $this->customerNoMobile = Customer::create([
            'name' => 'Walkin Guest',
            'mobile' => null,
            'phone' => null,
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $this->item = Item::first() ?? Item::create([
            'name' => 'Test Pet Food',
            'code' => 'TEST-PF-1',
            'mrp' => 200,
            'sell_price' => 190,
            'status' => 1,
        ]);

        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch->id],
            ['quantity' => 100]
        );
    }

    public function test_pos_save_and_whatsapp_returns_json_with_whatsapp_status(): void
    {
        Http::fake([
            'https://chatonclick.com/api/whatsapp/message' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'sent',
                    'mid' => 'wamid_test_123',
                ],
            ], 200),
        ]);

        WhatsAppSetting::updateOrCreate(
            ['branch_id' => $this->branch->id],
            [
                'api_url' => 'https://chatonclick.com',
                'app_key' => 'test-app-key',
                'auth_key' => 'test-auth-key',
                'template_name' => null, // Direct Mode
                'is_active' => true,
                'auto_send_on_bill' => false,
            ]
        );

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customerWithMobile->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Retail',
            'save_action' => 'whatsapp',
            'send_whatsapp' => 1,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 190,
                    'mrp' => 200,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                ],
            ],
            'payments' => [
                ['tender_type_id' => 1, 'amount' => 190],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('sales.sales-bills.store'), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('whatsapp_sent', true)
            ->assertJsonStructure(['id', 'bill_number', 'whatsapp_sent', 'whatsapp_notice']);

        $this->assertStringContainsString('WhatsApp invoice sent to +919876543210', $response->json('message'));

        // Direct Message was dispatched with message content and receipt link
        Http::assertSent(function ($request) {
            $body = (string) $request->body();
            return str_contains($body, '919876543210')
                && str_contains($body, 'Receipt')
                && str_contains($body, 'receipt/v/');
        });
    }

    public function test_known_unapproved_templates_bypass_template_attempt_and_send_direct_message(): void
    {
        Http::fake([
            'https://chatonclick.com/api/whatsapp/message' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'sent',
                    'mid' => 'wamid_direct_bypass_456',
                ],
            ], 200),
        ]);

        // Branch has sales_invoice_motera set
        WhatsAppSetting::updateOrCreate(
            ['branch_id' => $this->branch->id],
            [
                'api_url' => 'https://chatonclick.com',
                'app_key' => 'test-app-key',
                'auth_key' => 'test-auth-key',
                'template_name' => 'sales_invoice_motera', // Known unapproved template
                'is_active' => true,
            ]
        );

        $service = app(ChatOnClickWhatsAppService::class);
        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-009',
            'bill_date' => now(),
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customerWithMobile->id,
            'total' => 190,
            'invoice_type' => 'Retail Invoice',
            'payment_type' => 'Cash',
            'status' => 'Posted',
        ]);

        $result = $service->sendSalesBillInvoice($bill);

        $this->assertTrue($result['success']);
        $this->assertSame('919876543210', $result['phone']);

        // Verify only 1 HTTP request was made directly (no template rejection retry delay)
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            // Direct message should contain message parameter, not template_name
            $body = (string) $request->body();
            return str_contains($body, 'name="message"');
        });
    }
}
