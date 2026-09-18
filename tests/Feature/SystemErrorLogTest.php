<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SystemErrorLog;
use App\Models\User;
use App\Services\System\ErrorLoggerService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class SystemErrorLogTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main POS Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_error_logger_service_captures_exception_and_stores_in_db(): void
    {
        $service = app(ErrorLoggerService::class);

        $request = Request::create('/sales/sales-bills/create', 'POST', [
            'bill_number' => 'SB-TEST-001',
            'password' => 'secret_password_123',
            'amount' => 1500.50,
        ]);

        $exception = new Exception('Simulated sales bill calculation failure in item row');

        $this->actingAs($this->user);
        $log = $service->capture($exception, $request);

        $this->assertNotNull($log);
        $this->assertEquals('SalesBill', $log->module);
        $this->assertEquals('Exception', $log->error_type);
        $this->assertEquals('Simulated sales bill calculation failure in item row', $log->message);
        $this->assertEquals('POST', $log->method);
        $this->assertEquals('Unresolved', $log->status);
        $this->assertEquals($this->user->id, $log->user_id);

        // Verify sensitive payload redaction
        $this->assertArrayHasKey('password', $log->request_data);
        $this->assertEquals('***REDACTED***', $log->request_data['password']);
        $this->assertEquals('SB-TEST-001', $log->request_data['bill_number']);
    }

    public function test_error_logger_service_detects_purchase_and_gst_modules(): void
    {
        $service = app(ErrorLoggerService::class);

        $reqPurchase = Request::create('/purchase/purchase-receipt-notes', 'GET');
        $this->assertEquals('PurchaseReceiptNote', $service->detectModule($reqPurchase->path(), new Exception()));

        $reqGst = Request::create('/tools/gst/gstr-1', 'GET');
        $this->assertEquals('GST', $service->detectModule($reqGst->path(), new Exception()));

        $reqInventory = Request::create('/inventory/stock-updates', 'POST');
        $this->assertEquals('Inventory', $service->detectModule($reqInventory->path(), new Exception()));
    }

    public function test_system_error_logs_dashboard_renders_successfully(): void
    {
        // Seed a sample log
        SystemErrorLog::create([
            'module' => 'SalesBill',
            'error_type' => 'QueryException',
            'message' => 'Test SQL syntax error on line 42',
            'file' => 'app/Http/Controllers/Sales/SalesBillController.php',
            'line' => 42,
            'url' => 'http://urbanpos.test/sales/sales-bills',
            'method' => 'POST',
            'status' => 'Unresolved',
        ]);

        $response = $this->actingAs($this->user)->get(route('tools.system-error-logs.index'));

        $response->assertOk();
        $response->assertSee('System Error');
        $response->assertSee('Test SQL syntax error on line 42');
        $response->assertSee('SalesBill');
        $response->assertSee('Unresolved');
    }

    public function test_system_error_logs_inspection_json_returns_details(): void
    {
        $log = SystemErrorLog::create([
            'module' => 'GST',
            'error_type' => 'GstApiException',
            'message' => 'NIC Portal timeout after 30s',
            'file' => 'app/Services/GST/EInvoiceService.php',
            'line' => 110,
            'url' => 'http://urbanpos.test/tools/einvoice/generate-irn',
            'method' => 'POST',
            'request_data' => ['doc_num' => 'INV-999'],
            'stack_trace' => '#0 dummy trace line',
            'status' => 'Unresolved',
        ]);

        $response = $this->actingAs($this->user)->get(route('tools.system-error-logs.show', $log->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'module' => 'GST',
                'message' => 'NIC Portal timeout after 30s',
                'status' => 'Unresolved',
            ],
        ]);
    }

    public function test_system_error_log_can_be_marked_as_resolved(): void
    {
        $log = SystemErrorLog::create([
            'module' => 'SalesBill',
            'error_type' => 'Exception',
            'message' => 'Temporary break',
            'status' => 'Unresolved',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('tools.system-error-logs.resolve', $log->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $log->refresh();
        $this->assertEquals('Resolved', $log->status);
        $this->assertNotNull($log->resolved_at);
        $this->assertEquals($this->user->id, $log->resolved_by);
    }

    public function test_system_error_logs_export_csv(): void
    {
        SystemErrorLog::create([
            'module' => 'Purchase',
            'error_type' => 'ValidationException',
            'message' => 'Supplier invoice number is required',
            'status' => 'Unresolved',
        ]);

        $response = $this->actingAs($this->user)->get(route('tools.system-error-logs.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }
}
