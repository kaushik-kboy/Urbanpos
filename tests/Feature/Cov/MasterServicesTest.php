<?php

namespace Tests\Feature\Cov;

use App\Helpers\DateHelper;
use App\Models\Customer;
use App\Models\FormFieldValidation;
use App\Models\SalesBill;
use App\Models\User;
use App\Models\WhatsAppSetting;
use App\Services\DynamicValidationService;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MasterServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FormFieldValidation::query()->delete(); // migrations seed default configs; start from a clean slate
        Cache::flush();
    }

    // ------------------------------------------------------------------ DateHelper

    #[\PHPUnit\Framework\Attributes\DataProvider('dateProvider')]
    public function test_date_normalize_cases(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, DateHelper::normalize($raw));
    }

    public static function dateProvider(): array
    {
        $y = date('Y');

        return [
            'null' => [null, null],
            'empty' => ['', null],
            'whitespace only' => ['   ', null],
            'iso passthrough' => ['2026-04-10', '2026-04-10'],
            'iso impossible date' => ['2026-02-30', null],
            'iso leap day valid' => ['2028-02-29', '2028-02-29'],
            'iso non-leap invalid' => ['2027-02-29', null],
            'dd-mm-yyyy' => ['10-04-2026', '2026-04-10'],
            'dd/mm/yyyy' => ['10/04/2026', '2026-04-10'],
            'dd.mm.yyyy' => ['10.04.2026', '2026-04-10'],
            'yyyy/mm/dd' => ['2026/04/10', '2026-04-10'],
            'single digit parts' => ['1-4-2026', '2026-04-01'],
            'two digit year' => ['10-04-26', '2026-04-10'],
            'day 31 in 30-day month' => ['31-04-2026', null],
            'year out of range' => ['10-04-1800', null],
            'dd-mm current year' => ['10-04', "$y-04-10"],
            'ddmmyyyy digits' => ['10042026', '2026-04-10'],
            'yyyymmdd digits' => ['20260410', '2026-04-10'],
            'ddmmyy digits' => ['100426', '2026-04-10'],
            'ddmm digits' => ['1004', "$y-04-10"],
            'ddmm invalid month' => ['1013', null],
            'text month fallback' => ['10 Apr 2026', '2026-04-10'],
            'garbage' => ['not-a-date', null],
        ];
    }

    public function test_date_format_defaults_custom_and_blank(): void
    {
        $this->assertSame('10-04-2026', DateHelper::format('2026-04-10'));
        $this->assertSame('2026/04/10', DateHelper::format('2026-04-10', 'Y/m/d'));
        $this->assertSame('', DateHelper::format(null));
        $this->assertSame('', DateHelper::format(''));
        $this->assertSame('%%%bad', DateHelper::format('%%%bad'));
    }

    // ------------------------------------------------------------------ DynamicValidationService

    private function cfg(array $over = []): FormFieldValidation
    {
        return FormFieldValidation::create(array_merge([
            'module_key' => 'customers',
            'field_name' => 'email',
            'field_label' => 'Email',
            'section' => 'General',
            'field_type' => 'text',
            'is_required' => false,
            'is_readonly' => false,
            'block_future_date' => false,
            'is_unique' => false,
            'sort_order' => 1,
        ], $over));
    }

    private function apply(string $module, array $rules, array &$messages = [], $ignoreId = null): array
    {
        Cache::flush();
        app(DynamicValidationService::class)->applyTo($module, $rules, $messages, $ignoreId);

        return $rules;
    }

    public function test_required_toggle_replaces_nullable_and_optional_toggle_replaces_required(): void
    {
        $this->cfg(['field_name' => 'email', 'is_required' => true]);
        $this->cfg(['field_name' => 'city', 'is_required' => false, 'sort_order' => 2]);

        $rules = $this->apply('customers', ['email' => ['nullable', 'email'], 'city' => ['required', 'string']]);

        $this->assertSame(['required', 'email'], $rules['email']);
        $this->assertContains('nullable', $rules['city']);
        $this->assertNotContains('required', $rules['city']);
        $this->assertTrue(Validator::make(['email' => ''], $rules)->fails());
    }

    public function test_protected_identity_fields_never_lose_required(): void
    {
        $this->cfg(['field_name' => 'name', 'is_required' => false]);
        $rules = $this->apply('customers', ['name' => ['required', 'string']]);
        $this->assertContains('required', $rules['name']);
        $this->assertNotContains('nullable', $rules['name']);
    }

    public function test_block_future_date_adds_today_limit_and_allowed_future_strips_existing_limit(): void
    {
        $this->cfg(['field_name' => 'bill_date', 'module_key' => 'sales_bills', 'field_type' => 'date', 'block_future_date' => true]);
        $this->cfg(['field_name' => 'due_date', 'module_key' => 'sales_bills', 'field_type' => 'date', 'block_future_date' => false, 'sort_order' => 2]);

        $rules = $this->apply('sales_bills', [
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $this->assertContains('before_or_equal:'.date('Y-m-d'), $rules['bill_date']);
        $future = Carbon::now()->addDays(3)->format('Y-m-d');
        $this->assertTrue(Validator::make(['bill_date' => $future], ['bill_date' => $rules['bill_date']])->fails());
        $this->assertTrue(Validator::make(['bill_date' => date('Y-m-d')], ['bill_date' => $rules['bill_date']])->passes());
        // admin allows future dates -> stale limit is stripped
        $this->assertNotContains('before_or_equal:today', $rules['due_date']);
    }

    public function test_unique_toggle_uses_module_table_and_ignores_current_record_on_update(): void
    {
        $this->cfg(['field_name' => 'email', 'is_unique' => true]);
        $existing = Customer::create(['name' => 'A', 'email' => 'dup@example.com', 'mobile' => '9111111111', 'sales_type' => 'Local', 'customer_type' => 'RETAIL INVOICE', 'payment_mode' => 'Cash Only', 'gst_type' => 'Un Register']);

        $rules = $this->apply('customers', ['email' => ['nullable', 'email']]);
        $this->assertTrue(Validator::make(['email' => 'dup@example.com'], $rules)->fails());
        $this->assertTrue(Validator::make(['email' => 'free@example.com'], $rules)->passes());

        $msgs = [];
        $rulesUpdate = $this->apply('customers', ['email' => ['nullable', 'email']], $msgs, $existing->id);
        $this->assertTrue(Validator::make(['email' => 'dup@example.com'], $rulesUpdate)->passes());
    }

    public function test_unique_toggle_is_skipped_on_create_for_auto_generated_document_numbers(): void
    {
        $this->cfg(['module_key' => 'sales_bills', 'field_name' => 'bill_number', 'is_unique' => true]);
        $rules = $this->apply('sales_bills', ['bill_number' => ['required', 'string']]);
        foreach ($rules['bill_number'] as $r) {
            $this->assertNotInstanceOf(\Illuminate\Validation\Rules\Unique::class, $r);
        }
    }

    public function test_custom_error_message_is_wired_to_all_rule_keys(): void
    {
        $this->cfg(['field_name' => 'email', 'is_required' => true, 'custom_error_message' => '  Email is mandatory here  ']);
        $messages = [];
        $rules = $this->apply('customers', ['email' => ['nullable', 'email']], $messages);

        $this->assertSame('Email is mandatory here', $messages['email.required']);
        $this->assertArrayHasKey('email.unique', $messages);
        $v = Validator::make(['email' => ''], $rules, $messages);
        $this->assertSame('Email is mandatory here', $v->errors()->first('email'));
    }

    public function test_required_field_absent_from_base_rules_is_injected_but_line_item_fields_are_not(): void
    {
        $this->cfg(['field_name' => 'remarks', 'is_required' => true]);
        $this->cfg(['field_name' => 'qty', 'is_required' => true, 'sort_order' => 2]);
        $this->cfg(['field_name' => 'ignored_optional', 'is_required' => false, 'sort_order' => 3]);

        $rules = $this->apply('customers', ['name' => ['required']]);
        $this->assertSame(['required'], $rules['remarks']);
        $this->assertArrayNotHasKey('qty', $rules);
        $this->assertArrayNotHasKey('ignored_optional', $rules);
    }

    public function test_alias_and_line_item_wildcard_rule_targeting(): void
    {
        $this->cfg(['module_key' => 'suppliers', 'field_name' => 'gstin', 'is_required' => true]);
        $rules = $this->apply('suppliers', ['gst_no' => ['nullable', 'string']]);
        $this->assertContains('required', $rules['gst_no']);
        $this->assertArrayNotHasKey('gstin', $rules);

        $this->cfg(['module_key' => 'sales_bills', 'field_name' => 'batch_no', 'is_required' => true]);
        $rules = $this->apply('sales_bills', ['items.*.batch_no' => ['nullable']]);
        $this->assertContains('required', $rules['items.*.batch_no']);
    }

    public function test_alias_pair_optional_config_does_not_clobber_strict_alias(): void
    {
        $this->cfg(['module_key' => 'suppliers', 'field_name' => 'gstin', 'is_required' => false]);
        $this->cfg(['module_key' => 'suppliers', 'field_name' => 'gst_no', 'is_required' => true, 'sort_order' => 2]);
        $rules = $this->apply('suppliers', ['gst_no' => ['nullable', 'string']]);
        $this->assertContains('required', $rules['gst_no']);
    }

    public function test_unique_ignore_id_is_inferred_from_route_model_on_put_requests(): void
    {
        $this->cfg(['field_name' => 'email', 'is_unique' => true]);
        $c = Customer::create(['name' => 'A', 'email' => 'dup@example.com', 'mobile' => '9111111112', 'sales_type' => 'Local', 'customer_type' => 'RETAIL INVOICE', 'payment_mode' => 'Cash Only', 'gst_type' => 'Un Register']);

        $request = \Illuminate\Http\Request::create('/x/'.$c->id, 'PUT');
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('PUT', '/x/{customer}', []))->bind($request));
        $request->route()->setParameter('customer', $c);
        app()->instance('request', $request);

        $rules = $this->apply('customers', ['email' => ['nullable', 'email']]);
        $this->assertTrue(Validator::make(['email' => 'dup@example.com'], $rules)->passes());
    }

    public function test_flag_helpers_and_cache_clear(): void
    {
        $this->cfg(['field_name' => 'email', 'is_required' => true, 'is_readonly' => true, 'block_future_date' => true]);
        Cache::flush();
        $svc = app(DynamicValidationService::class);

        $this->assertTrue($svc->isFieldRequired('customers', 'email'));
        $this->assertTrue($svc->isFieldReadonly('customers', 'email'));
        $this->assertTrue($svc->isFutureDateBlocked('customers', 'email'));
        $this->assertFalse($svc->isFieldRequired('customers', 'unknown'));
        $this->assertTrue($svc->isFieldRequired('customers', 'unknown', true));

        // config is cached: a later DB change is invisible until clearCache
        FormFieldValidation::where('field_name', 'email')->update(['is_required' => false]);
        $this->assertTrue($svc->isFieldRequired('customers', 'email'));
        $svc->clearCache('customers');
        $this->assertFalse($svc->isFieldRequired('customers', 'email'));

        $svc->clearCache(); // all-modules branch must not throw and must also purge
        $this->assertFalse($svc->isFieldReadonly('customers', 'missing'));
    }

    // ------------------------------------------------------------------ WhatsApp service

    /** Http::fake() stubs stack (first match wins); swap in a fresh factory so re-faking overrides. */
    private function fakeHttp(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake($stubs);
    }

    private function bill(): SalesBill
    {
        $bill = new SalesBill();
        $bill->forceFill(['bill_number' => 'INV-77', 'total' => 1234.5, 'bill_date' => Carbon::parse('2026-04-10 15:30')]);
        $bill->id = 55;
        $bill->setRelation('items', collect([1, 2, 3]));
        $bill->setRelation('payments', collect());
        $bill->setRelation('customer', new Customer(['name' => 'Asha', 'mobile' => '9876543210']));
        $bill->setRelation('branch', null);

        return $bill;
    }

    public function test_phone_sanitising_variants(): void
    {
        $svc = new ChatOnClickWhatsAppService();
        $this->assertSame('919876543210', $svc->sanitizePhone('98765 43210'));
        $this->assertSame('919876543210', $svc->sanitizePhone('09876543210'));
        $this->assertSame('919876543210', $svc->sanitizePhone('+91-9876543210'));
        $this->assertSame('14155552671', $svc->sanitizePhone('+1 415 555 2671'));
        $this->assertNull($svc->sanitizePhone('12345'));
        $this->assertNull($svc->sanitizePhone(''));
        $this->assertNull($svc->sanitizePhone(null));
        $this->assertNull($svc->sanitizePhone('1234567890123456'));
    }

    public function test_receipt_hash_is_stable_bill_specific_and_verifiable(): void
    {
        $svc = new ChatOnClickWhatsAppService();
        $bill = $this->bill();
        $hash = $svc->generateReceiptHash($bill);

        $this->assertSame(16, strlen($hash));
        $this->assertSame($hash, $svc->generateReceiptHash($bill));
        $this->assertTrue($svc->verifyReceiptHash($bill, $hash));
        $this->assertFalse($svc->verifyReceiptHash($bill, 'deadbeefdeadbeef'));

        $other = $this->bill();
        $other->bill_number = 'INV-78';
        $this->assertNotSame($hash, $svc->generateReceiptHash($other));
        $this->assertStringContainsString($hash, $svc->getPublicReceiptUrl($bill));
    }

    public function test_public_receipt_url_uses_app_url_when_not_localhost(): void
    {
        config(['app.url' => 'https://pos.example.com/']);
        $svc = new ChatOnClickWhatsAppService();
        $bill = $this->bill();
        $this->assertSame('https://pos.example.com/receipt/v/55/'.$svc->generateReceiptHash($bill), $svc->getPublicReceiptUrl($bill));
    }

    public function test_invoice_message_contains_bill_facts(): void
    {
        WhatsAppSetting::current()->update(['header_title' => 'Shop X', 'support_phone' => '9000011111', 'footer_message' => 'Bye']);
        $msg = (new ChatOnClickWhatsAppService())->formatInvoiceMessage($this->bill());

        foreach (['SHOP X', 'Asha', 'INV-77', '10-Apr-2026 03:30 PM', '1,234.50', '9000011111', 'Bye', 'Total Items:* 3'] as $needle) {
            $this->assertStringContainsString($needle, $msg);
        }
    }

    public function test_send_text_message_success_failure_and_guard_paths(): void
    {
        WhatsAppSetting::current()->update(['app_key' => 'AK', 'auth_key' => 'UK', 'api_url' => 'https://chat.test']);

        $this->fakeHttp(['chat.test/api/whatsapp/message' => Http::response(['success' => true, 'data' => ['mid' => 'M1']])]);
        $ok = (new ChatOnClickWhatsAppService())->sendTextMessage('9876543210', 'hi');
        $this->assertTrue($ok['success']);
        $this->assertSame('M1', $ok['wamid']);
        $this->assertSame('919876543210', $ok['phone']);
        Http::assertSent(fn ($r) => $r->url() === 'https://chat.test/api/whatsapp/message'
            && str_contains($r->body(), '919876543210') && str_contains($r->body(), 'AK'));

        $this->assertFalse((new ChatOnClickWhatsAppService())->sendTextMessage('12', 'hi')['success']);

        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['status' => 'failed']])]);
        $failed = (new ChatOnClickWhatsAppService())->sendTextMessage('9876543210', 'hi');
        $this->assertFalse($failed['success']);
        $this->assertStringContainsString('delivery failed', $failed['error']);

        $this->fakeHttp(['chat.test/*' => Http::response(['error' => 'Bad auth'], 401)]);
        $this->assertSame('Bad auth', (new ChatOnClickWhatsAppService())->sendTextMessage('9876543210', 'hi')['error']);

        $this->fakeHttp(['chat.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $conn = (new ChatOnClickWhatsAppService())->sendTextMessage('9876543210', 'hi');
        $this->assertStringContainsString('Connection error', $conn['error']);

        WhatsAppSetting::current()->update(['app_key' => '', 'auth_key' => '']);
        config(['services.chatonclick.appkey' => null, 'services.chatonclick.authkey' => null]);
        $noKeys = (new ChatOnClickWhatsAppService())->sendTextMessage('9876543210', 'hi');
        $this->assertStringContainsString('not configured', $noKeys['error']);
    }

    public function test_sales_bill_invoice_direct_message_mode(): void
    {
        WhatsAppSetting::current()->update(['app_key' => 'AK', 'auth_key' => 'UK', 'api_url' => 'https://chat.test', 'template_name' => '']);
        config(['services.chatonclick.template_name' => null]);
        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['mid' => 'W9']])]);

        $res = (new ChatOnClickWhatsAppService())->sendSalesBillInvoice($this->bill());
        $this->assertTrue($res['success']);
        $this->assertSame('W9', $res['wamid']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'name="message"') && str_contains($r->body(), 'INV-77'));
    }

    public function test_sales_bill_invoice_template_mode_sends_eight_variables_and_button_url(): void
    {
        WhatsAppSetting::current()->update(['app_key' => 'AK', 'auth_key' => 'UK', 'api_url' => 'https://chat.test', 'template_name' => 'urban_tax_invoice']);
        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['mid' => 'T1']])]);

        $res = (new ChatOnClickWhatsAppService())->sendSalesBillInvoice($this->bill());
        $this->assertTrue($res['success']);
        Http::assertSent(function ($r) {
            $b = $r->body();

            return substr_count($b, 'name="variables[]"') === 8
                && str_contains($b, 'name="template_name"') && str_contains($b, 'urban_tax_invoice')
                && str_contains($b, 'name="button_url"') && str_contains($b, '/receipt/v/55/');
        });

        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['status' => 'failed']])]);
        $failed = (new ChatOnClickWhatsAppService())->sendSalesBillInvoice($this->bill());
        $this->assertFalse($failed['success']);
        $this->assertStringContainsString("Template 'urban_tax_invoice' failed", $failed['error']);
    }

    public function test_sales_bill_invoice_guards_disabled_missing_phone_and_http_errors(): void
    {
        WhatsAppSetting::current()->update(['app_key' => 'AK', 'auth_key' => 'UK', 'api_url' => 'https://chat.test', 'template_name' => '', 'is_active' => false]);
        config(['services.chatonclick.template_name' => null]);
        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['mid' => 'F1']])]);

        $svc = new ChatOnClickWhatsAppService();
        $disabled = $svc->sendSalesBillInvoice($this->bill());
        $this->assertFalse($disabled['success']);
        $this->assertStringContainsString('disabled', $disabled['error']);
        Http::assertNothingSent();

        // force overrides the disabled switch
        $this->assertTrue($svc->sendSalesBillInvoice($this->bill(), null, true)['success']);

        WhatsAppSetting::current()->update(['is_active' => true]);
        $noPhoneBill = $this->bill();
        $noPhoneBill->setRelation('customer', new Customer(['name' => 'NoPhone']));
        $this->assertStringContainsString('no valid 10-digit', $svc->sendSalesBillInvoice($noPhoneBill)['error']);
        // override phone rescues it
        $this->assertTrue($svc->sendSalesBillInvoice($noPhoneBill, '9123456789')['success']);

        $this->fakeHttp(['chat.test/*' => Http::response(['message' => 'quota exceeded'], 500)]);
        $this->assertSame('quota exceeded', $svc->sendSalesBillInvoice($this->bill())['error']);

        $this->fakeHttp(['chat.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('dns')]);
        $this->assertStringContainsString('Network error', $svc->sendSalesBillInvoice($this->bill())['error']);

        WhatsAppSetting::current()->update(['app_key' => '', 'auth_key' => '']);
        config(['services.chatonclick.appkey' => null, 'services.chatonclick.authkey' => null]);
        $this->assertStringContainsString('not configured', $svc->sendSalesBillInvoice($this->bill())['error']);
    }

    public function test_send_test_message_dispatches_through_text_endpoint(): void
    {
        WhatsAppSetting::current()->update(['app_key' => 'AK', 'auth_key' => 'UK', 'api_url' => 'https://chat.test']);
        $this->fakeHttp(['chat.test/*' => Http::response(['success' => true, 'data' => ['mid' => 'TT']])]);

        $res = (new ChatOnClickWhatsAppService())->sendTestMessage('9876543210');
        $this->assertTrue($res['success']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'WhatsApp Integration Test Successful'));
    }
}
