<?php

namespace Tests\Unit;

use App\Models\SalesBill;
use App\Models\Customer;
use App\Models\Branch;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatOnClickWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatOnClickWhatsAppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChatOnClickWhatsAppService();
    }

    public function test_phone_sanitization(): void
    {
        // 10 digits
        $this->assertEquals('919876543210', $this->service->sanitizePhone('9876543210'));
        $this->assertEquals('919876543210', $this->service->sanitizePhone('+91 98765 43210'));
        $this->assertEquals('919876543210', $this->service->sanitizePhone('98765-43210'));

        // 11 digits with leading 0
        $this->assertEquals('919876543210', $this->service->sanitizePhone('09876543210'));

        // 12 digits with 91
        $this->assertEquals('919876543210', $this->service->sanitizePhone('919876543210'));

        // Invalid
        $this->assertNull($this->service->sanitizePhone(''));
        $this->assertNull($this->service->sanitizePhone('12345'));
    }

    public function test_receipt_hash_generation_and_verification(): void
    {
        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB-1']);
        $customer = Customer::create(['name' => 'Test Customer', 'customer_code' => 'CUST-001']);
        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-001',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Store Pickup',
            'sales_type' => 'Local',
            'total' => 500,
        ]);

        $hash = $this->service->generateReceiptHash($bill);
        $this->assertNotEmpty($hash);
        $this->assertTrue($this->service->verifyReceiptHash($bill, $hash));
        $this->assertFalse($this->service->verifyReceiptHash($bill, 'invalid_hash_123'));

        $publicUrl = $this->service->getPublicReceiptUrl($bill);
        $this->assertStringContainsString('/receipt/v/' . $bill->id . '/' . $hash, $publicUrl);
    }

    public function test_whatsapp_message_formatting(): void
    {
        $branch = Branch::create(['name' => 'Motera Branch', 'code' => 'MOT-1', 'phone' => '9638455255']);
        $customer = Customer::create(['name' => 'Rahul Sharma', 'customer_code' => 'CUST-002']);
        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-002',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Store Pickup',
            'sales_type' => 'Local',
            'total' => 1250.50,
            'payment_type' => 'Cash',
        ]);

        $message = $this->service->formatInvoiceMessage($bill);

        $this->assertStringContainsString('URBAN PETS', $message);
        $this->assertStringContainsString('Rahul Sharma', $message);
        $this->assertStringContainsString('SB-TEST-002', $message);
        $this->assertStringContainsString('1,250.50', $message);
        $this->assertStringContainsString('Motera Branch', $message);
        $this->assertStringContainsString('/receipt/v/', $message);
    }

    public function test_send_sales_bill_invoice_http_dispatch(): void
    {
        Http::fake([
            'https://chatonclick.com/api/whatsapp/message' => Http::response([
                'success' => true,
                'data' => [
                    'mid' => 'wamid.TEST123456789',
                    'status' => 'sent',
                    'message_type' => 'text',
                ],
            ], 200),
        ]);

        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB-2']);
        $customer = Customer::create(['name' => 'Test Client', 'customer_code' => 'CUST-003', 'phone' => '9876543210']);
        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-003',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Store Pickup',
            'sales_type' => 'Local',
            'total' => 999.00,
            'payment_type' => 'UPI',
        ]);

        $result = $this->service->sendSalesBillInvoice($bill);

        $this->assertTrue($result['success']);
        $this->assertEquals('919876543210', $result['phone']);
        $this->assertEquals('wamid.TEST123456789', $result['wamid']);
    }

    public function test_public_receipt_route_accessible_without_auth(): void
    {
        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB-3']);
        $customer = Customer::create(['name' => 'Public Guest', 'customer_code' => 'CUST-004']);
        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-004',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Store Pickup',
            'sales_type' => 'Local',
            'total' => 350.00,
        ]);

        $validHash = $this->service->generateReceiptHash($bill);

        // 1. Valid hash without login => 200 OK
        $response = $this->get(route('sales-bills.public-receipt', ['salesBill' => $bill, 'hash' => $validHash]));
        $response->assertStatus(200);
        $response->assertSee('SB-TEST-004');
        $response->assertSee('Verified Digital Bill');

        // 2. Invalid hash without login => 403 Forbidden
        $badResponse = $this->get(route('sales-bills.public-receipt', ['salesBill' => $bill, 'hash' => 'bad_token']));
        $badResponse->assertStatus(403);
    }
}

