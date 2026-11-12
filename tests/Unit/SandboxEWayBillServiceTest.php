<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstSetting;
use App\Models\SalesBill;
use App\Services\GST\EWayBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SandboxEWayBillServiceTest extends TestCase
{
    use RefreshDatabase;

    private EWayBillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new EWayBillService();

        GstSetting::create([
            'company_name' => 'URBANPETS SERVICES PRIVATE LIMITED',
            'gstin' => '24AAECU0338G1ZN',
            'username' => 'API_Urban_API_01',
            'password' => 'Urban_API_01 ',
            'client_id' => 'key_live_test_123',
            'client_secret' => 'secret_live_test_456',
            'gsp_provider' => 'sandbox',
            'is_sandbox' => false,
            'auto_upload_enabled' => true,
            'auto_upload_threshold' => 50000,
            'auto_upload_scope' => 'both',
        ]);
    }

    private function makeBill(): SalesBill
    {
        $branch = Branch::create([
            'name' => 'Urban Pets | Satellite',
            'code' => 'SAT',
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
            'postal_code' => '380015',
            'gst_no' => '24AAECU0338G1ZN',
        ]);
        $customer = Customer::create([
            'name' => 'Test Buyer',
            'customer_code' => 'C-EWB-1',
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
            'postal_code' => '380015',
            'gst_no' => '24AAACR5055K1Z1',
        ]);

        return SalesBill::create([
            'bill_number' => 'SI01/2627/000999',
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'total_gst' => 9000,
            'total_cgst' => 4500,
            'total_sgst' => 4500,
            'total' => 59000,
            'transport_mode' => '1',
            'vehicle_no' => 'GJ01AB1234',
        ]);
    }

    public function test_sandbox_generate_cancel_vehicle_and_extend_eway_bill(): void
    {
        Http::fake([
            'https://api.sandbox.co.in/authenticate' => Http::response([
                'code' => 200,
                'data' => ['access_token' => 'base_jwt_token'],
            ], 200),
            'https://api.sandbox.co.in/gst/compliance/e-way-bill/tax-payer/authenticate*' => Http::response([
                'code' => 200,
                'data' => ['Status' => 1, 'access_token' => 'ewb_session_token'],
            ], 200),
            'https://api.sandbox.co.in/gst/compliance/e-way-bill/consignor/bill' => Http::response([
                'code' => 200,
                'data' => [
                    'status' => '1',
                    'data' => [
                        'ewayBillNo' => '291001071203',
                        'ewayBillDate' => '12/11/2026 02:30:00 PM',
                        'validUpto' => '15/11/2026 11:59:00 PM',
                    ],
                ],
            ], 200),
            'https://api.sandbox.co.in/gst/compliance/e-way-bill/consignor/bill/291001071203/vehicle' => Http::response([
                'code' => 200,
                'data' => ['status' => '1', 'data' => ['vehUpdDate' => '12/11/2026 03:00:00 PM']],
            ], 200),
            'https://api.sandbox.co.in/gst/compliance/e-way-bill/consignor/bill/291001071203/extend' => Http::response([
                'code' => 200,
                'data' => ['status' => '1', 'data' => ['validUpto' => '16/11/2026 11:59:00 PM']],
            ], 200),
            'https://api.sandbox.co.in/gst/compliance/e-way-bill/consignor/bill/291001071203/cancel' => Http::response([
                'code' => 200,
                'data' => ['status' => '1', 'data' => ['ewayBillNo' => '291001071203']],
            ], 200),
        ]);

        $bill = $this->makeBill();

        // 1. Generate
        $gen = $this->service->generateEwb($bill);
        $this->assertTrue($gen['success'], $gen['error'] ?? '');
        $this->assertSame('291001071203', $bill->fresh()->eway_bill_no);
        $this->assertSame('Generated', $bill->fresh()->eway_status);

        // 2. Update Part-B Vehicle (PUT)
        $veh = $this->service->updatePartB($bill->fresh(), 'GJ01XY9999', '1');
        $this->assertTrue($veh['success'], $veh['error'] ?? '');
        $this->assertSame('GJ01XY9999', $bill->fresh()->vehicle_no);

        // 3. Extend Validity
        $ext = $this->service->extendValidity($bill->fresh(), 'GJ01XY9999', 'Ahmedabad', 120);
        $this->assertTrue($ext['success'], $ext['error'] ?? '');
        $this->assertSame('2026-11-16 23:59:00', $bill->fresh()->eway_valid_until->format('Y-m-d H:i:s'));

        // 4. Cancel EWB
        $can = $this->service->cancelEwb($bill->fresh(), '2', 'Order changed');
        $this->assertTrue($can['success'], $can['error'] ?? '');
        $this->assertSame('Cancelled', $bill->fresh()->eway_status);
        $this->assertNull($bill->fresh()->eway_bill_no);
    }
}

