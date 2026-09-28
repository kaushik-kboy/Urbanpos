<?php

namespace Tests\Feature\Cov;

use App\Models\CustomFieldDefinition;
use App\Models\SystemErrorLog;
use App\Models\User;
use App\Services\System\ErrorLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tools controllers not fully covered elsewhere: custom fields, system error hub,
 * and the ErrorLoggerService (module detection, redaction, de-duplication).
 */
class MasterToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1
        $this->user = User::factory()->create(['name' => 'Tools User']);
        $this->user->assignRole('Manager');
        $this->actingAs($this->user);
    }

    // ------------------------------------------------------------------ custom fields

    private function field(array $over = []): CustomFieldDefinition
    {
        return CustomFieldDefinition::create(array_merge([
            'module' => 'Customer', 'field_name' => 'Blood Group', 'field_key' => 'blood_group',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 0, 'status' => true,
        ], $over));
    }

    public function test_custom_field_store_parses_select_options_and_generates_unique_keys(): void
    {
        $this->post(route('tools.custom-fields.store'), [
            'module' => 'Customer', 'field_name' => 'Coat Type', 'field_type' => 'select',
            'options' => "Short, Long\r\n Curly ,,", 'is_required' => 1, 'sort_order' => 3,
        ])->assertRedirect(route('tools.custom-fields.index', ['tab' => 'Customer']));

        $f = CustomFieldDefinition::where('field_name', 'Coat Type')->first();
        $this->assertSame(['Short', 'Long', 'Curly'], $f->options);
        $this->assertTrue($f->is_required);
        $this->assertSame(3, $f->sort_order);
        $this->assertTrue($f->status, 'status defaults to active when omitted');
        $this->assertSame('coat_type', $f->field_key);

        // same name again in the same module gets a suffixed key rather than a collision
        $this->post(route('tools.custom-fields.store'), ['module' => 'Customer', 'field_name' => 'Coat Type', 'field_type' => 'text']);
        $this->assertSame(['coat_type', 'coat_type_1'], CustomFieldDefinition::where('field_name', 'Coat Type')->orderBy('id')->pluck('field_key')->all());

        // options are only kept for select fields
        $this->assertNull(CustomFieldDefinition::where('field_key', 'coat_type_1')->first()->options);
    }

    public function test_custom_field_store_validation_and_json_response(): void
    {
        $this->post(route('tools.custom-fields.store'), ['module' => 'Nope', 'field_name' => '', 'field_type' => 'blob'])
            ->assertSessionHasErrors(['module', 'field_name', 'field_type']);
        $this->assertSame(0, CustomFieldDefinition::count());

        $this->postJson(route('tools.custom-fields.store'), ['module' => 'Item', 'field_name' => 'Origin', 'field_type' => 'text', 'status' => 0])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('field.field_key', 'origin');
        $this->assertFalse(CustomFieldDefinition::where('field_key', 'origin')->first()->status);
    }

    public function test_custom_field_update_toggle_and_delete(): void
    {
        $f = $this->field(['field_type' => 'select', 'options' => ['A', 'B']]);

        $this->put(route('tools.custom-fields.update', $f), ['field_name' => 'Renamed', 'field_type' => 'text'])
            ->assertRedirect(route('tools.custom-fields.index', ['tab' => 'Customer']));
        $f->refresh();
        $this->assertSame('Renamed', $f->field_name);
        $this->assertNull($f->options, 'options cleared when type is no longer select');
        $this->assertFalse($f->status, 'unchecked status on update deactivates');
        $this->assertSame('blood_group', $f->field_key, 'key is stable across renames');

        $this->putJson(route('tools.custom-fields.update', $f), ['field_name' => 'X', 'field_type' => 'number', 'status' => 1])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($f->fresh()->status);

        $this->putJson(route('tools.custom-fields.update', $f), ['field_name' => '', 'field_type' => 'number'])->assertStatus(422);

        $this->postJson(route('tools.custom-fields.toggle', $f))->assertOk()->assertJsonPath('status', false);
        $this->assertFalse($f->fresh()->status);
        $this->post(route('tools.custom-fields.toggle', $f))->assertRedirect();
        $this->assertTrue($f->fresh()->status);

        $this->deleteJson(route('tools.custom-fields.destroy', $f))->assertOk()->assertJsonPath('success', true);
        $this->assertNull(CustomFieldDefinition::find($f->id));

        $g = $this->field(['field_key' => 'g']);
        $this->delete(route('tools.custom-fields.destroy', $g))->assertRedirect(route('tools.custom-fields.index', ['tab' => 'Customer']));
        $this->assertNull(CustomFieldDefinition::find($g->id));
    }

    public function test_custom_field_index_picks_tab_with_fallback_and_lists_definitions(): void
    {
        $this->field(['module' => 'Supplier', 'field_name' => 'Supplier Only Field', 'field_key' => 'so']);

        $this->get(route('tools.custom-fields.index', ['tab' => 'Supplier']))->assertOk()
            ->assertViewHas('activeModule', 'Supplier')->assertViewHas('activeCategory', 'Masters')->assertSee('Supplier Only Field');
        $this->get(route('tools.custom-fields.index', ['tab' => 'PurchaseOrder']))->assertViewHas('activeCategory', 'Purchase');
        $this->get(route('tools.custom-fields.index', ['tab' => 'garbage']))->assertViewHas('activeModule', 'Customer');
    }

    // ------------------------------------------------------------------ system error hub

    private function log(array $over = []): SystemErrorLog
    {
        static $n = 0;
        $n++;

        return SystemErrorLog::create(array_merge([
            'module' => 'SalesBill', 'error_type' => 'RuntimeException', 'message' => "boom $n",
            'error_hash' => md5("h$n"), 'occurrence_count' => 1, 'last_seen_at' => now(),
            'file' => 'app/X.php', 'line' => $n, 'url' => 'http://t/x', 'method' => 'POST', 'status' => 'Unresolved',
        ], $over));
    }

    private function logIds(array $q): array
    {
        return $this->get(route('tools.system-error-logs.index', $q))->assertOk()->viewData('logs')->pluck('id')->all();
    }

    public function test_error_hub_filters_by_module_status_search_and_dates(): void
    {
        $a = $this->log(['module' => 'SalesBill', 'message' => 'needle in haystack']);
        $b = $this->log(['module' => 'GST', 'status' => 'Resolved']);
        $old = $this->log(['module' => 'GST']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->assertSame([$a->id], $this->logIds(['module' => 'SalesBill']));
        $this->assertSame([$b->id], $this->logIds(['status' => 'Resolved']));
        $this->assertSame([$a->id], $this->logIds(['search' => 'needle']));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->logIds(['date_preset' => 'today']));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->logIds(['date_preset' => 'last_7_days']));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->logIds(['date_preset' => 'this_month']));
        $this->assertSame([], $this->logIds(['date_preset' => 'yesterday']));
        $this->assertSame([$old->id], $this->logIds(['to_date' => now()->subDays(30)->toDateString()]));
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $old->id], $this->logIds(['module' => 'all', 'status' => 'all']));

        $res = $this->get(route('tools.system-error-logs.index'));
        $this->assertSame(3, $res->viewData('totalErrors'));
        $this->assertSame(2, $res->viewData('unresolvedCount'));
        $this->assertSame(2, $res->viewData('todayCount'));
    }

    public function test_error_hub_show_returns_json_with_fallbacks_and_404_for_missing(): void
    {
        $log = $this->log(['request_data' => ['a' => 1]]);
        $this->getJson(route('tools.system-error-logs.show', $log->id))->assertOk()
            ->assertJsonPath('log.user_name', 'Guest / System')->assertJsonPath('log.branch_name', 'N/A')
            ->assertJsonPath('log.ip_address', 'N/A')->assertJsonPath('log.resolver', null);
        $this->getJson(route('tools.system-error-logs.show', 999999))->assertStatus(404);
    }

    public function test_error_hub_resolve_records_who_and_when_json_or_redirect(): void
    {
        $a = $this->log();
        $b = $this->log();

        $this->postJson(route('tools.system-error-logs.resolve', $a->id))->assertOk()->assertJsonPath('success', true);
        $a->refresh();
        $this->assertSame('Resolved', $a->status);
        $this->assertSame($this->user->id, $a->resolved_by);
        $this->assertNotNull($a->resolved_at);

        $this->from('/back')->post(route('tools.system-error-logs.resolve', $b->id))->assertRedirect('/back')->assertSessionHas('success');
        $this->assertSame('Resolved', $b->fresh()->status);

        $this->getJson(route('tools.system-error-logs.show', $a->id))->assertJsonPath('log.resolver', 'Tools User');
    }

    public function test_error_hub_clear_old_only_deletes_old_resolved_rows(): void
    {
        $oldResolved = $this->log(['status' => 'Resolved']);
        $oldResolved->forceFill(['created_at' => now()->subDays(45)])->save();
        $oldUnresolved = $this->log();
        $oldUnresolved->forceFill(['created_at' => now()->subDays(45)])->save();
        $newResolved = $this->log(['status' => 'Resolved']);

        $this->from('/back')->post(route('tools.system-error-logs.clear-old'), ['days' => 30])
            ->assertRedirect('/back')->assertSessionHas('success', 'Cleared 1 resolved error records older than 30 days.');

        $this->assertNull(SystemErrorLog::find($oldResolved->id));
        $this->assertNotNull(SystemErrorLog::find($oldUnresolved->id));
        $this->assertNotNull(SystemErrorLog::find($newResolved->id));
    }

    public function test_error_hub_csv_export_respects_filters(): void
    {
        $this->log(['module' => 'GST', 'message' => 'gst failure, with comma']);
        $this->log(['module' => 'SalesBill', 'message' => 'sales failure']);

        $res = $this->get(route('tools.system-error-logs.export', ['module' => 'GST', 'status' => 'Unresolved', 'from_date' => now()->toDateString(), 'to_date' => now()->toDateString()]));
        $res->assertOk();
        $this->assertStringContainsString('attachment; filename=system_error_logs_', $res->headers->get('content-disposition'));
        $csv = $res->streamedContent();
        $this->assertStringContainsString('ID,Module,"Error Type",Message', $csv);
        $this->assertStringContainsString('"gst failure, with comma"', $csv);
        $this->assertStringNotContainsString('sales failure', $csv);
    }

    public function test_client_js_errors_are_logged_with_truncation_module_detection_and_defaults(): void
    {
        $this->postJson(route('tools.client-error-logs'), [
            'message' => str_repeat('m', 1500), 'file' => 'pos.js', 'line' => '77',
            'url' => 'http://pos.test/sales/sales-bills/create?x=1', 'stack' => 'at foo()', 'extra' => ['cart' => 3],
        ])->assertOk()->assertJson(['success' => true]);

        $log = SystemErrorLog::first();
        $this->assertSame(1000, strlen($log->message));
        $this->assertSame('SalesBill', $log->module);
        $this->assertSame('ClientJavaScriptError', $log->error_type);
        $this->assertSame('BROWSER', $log->method);
        $this->assertSame(77, $log->line);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame(['cart' => 3], $log->request_data);

        $this->postJson(route('tools.client-error-logs'), [])->assertOk();
        $default = SystemErrorLog::latest('id')->first();
        $this->assertSame('Client JavaScript Error', $default->message);
        $this->assertSame('Client Browser JS Trace', $default->stack_trace);
        $this->assertNull($default->request_data);
    }

    // ------------------------------------------------------------------ ErrorLoggerService

    public static function moduleProvider(): array
    {
        return [
            ['sales/sales-bills/12/edit', 'SalesBill'], ['sales/sales-orders', 'SalesOrder'], ['sales/sales-quotations/1', 'SalesQuotation'],
            ['sales/sales-returns', 'SalesReturn'], ['sales/sales-delivery-notes', 'SalesDeliveryNote'], ['sales/other', 'Sales'],
            ['purchase/purchase-receipt-notes', 'PurchaseReceiptNote'], ['purchase/purchase-invoices', 'PurchaseInvoice'],
            ['purchase/purchase-orders', 'PurchaseOrder'], ['purchase/purchase-returns', 'PurchaseReturn'],
            ['purchase/purchase-indents', 'PurchaseIndent'], ['purchase/aux/x', 'Purchase'], ['inventory/stock-updates', 'Inventory'],
            ['tools/gst-thing', 'GST'], ['tools/einvoice/generate-irn', 'GST'], ['tools/backups', 'Tools'],
            ['master/items', 'Master'], ['finance/vouchers', 'Finance'], ['reports/x', 'Reports'], ['pos/ping', 'POSTerminal'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('moduleProvider')]
    public function test_detect_module_from_path(string $path, string $expected): void
    {
        $this->assertSame($expected, (new ErrorLoggerService())->detectModule($path, new \Exception('x')));
    }

    public function test_detect_module_falls_back_to_the_throwing_file_name_when_path_is_unknown(): void
    {
        $svc = new ErrorLoggerService();
        // Exception created in this test file, whose path contains "Master" => file-name fallback.
        $this->assertSame('Master', $svc->detectModule('/unknown/', new \Exception('x')));

        $anon = new class('x') extends \Exception {
            public function __construct(string $m)
            {
                parent::__construct($m);
                $this->file = '/srv/app/Http/Controllers/Inventory/Whatever.php';
            }
        };
        $this->assertSame('Inventory', $svc->detectModule('/unknown/', $anon));

        $none = new class('x') extends \Exception {
            public function __construct(string $m)
            {
                parent::__construct($m);
                $this->file = '/srv/app/lib/util.php';
            }
        };
        $this->assertSame('General', $svc->detectModule('/unknown/', $none));
    }

    public function test_capture_skips_routine_exceptions_and_redacts_secrets_and_deduplicates(): void
    {
        $svc = new ErrorLoggerService();

        $this->assertNull($svc->capture(\Illuminate\Validation\ValidationException::withMessages(['a' => 'b'])));
        $this->assertNull($svc->capture(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException()));
        $this->assertSame(0, SystemErrorLog::count());

        $req = Request::create('/finance/vouchers', 'POST', [
            'amount' => 10, 'password' => 'hunter2', 'nested' => ['api_key' => 'k', 'ok' => 'fine'], '_token' => 'csrf',
        ]);
        $e = new \RuntimeException('ledger exploded');

        $first = $svc->capture($e, $req);
        $this->assertSame('Finance', $first->module);
        $this->assertSame('Unresolved', $first->status);
        $this->assertSame(1, $first->occurrence_count);
        $this->assertSame('***REDACTED***', $first->request_data['password']);
        $this->assertSame('***REDACTED***', $first->request_data['_token']);
        $this->assertSame('***REDACTED***', $first->request_data['nested']['api_key']);
        $this->assertSame('fine', $first->request_data['nested']['ok']);
        $this->assertSame(10, $first->request_data['amount']);
        $this->assertSame($this->user->id, $first->user_id);

        // identical exception (same class/file/line/message) => same row, count bumped
        $again = $svc->capture($e, Request::create('/finance/vouchers?retry=1', 'POST', ['amount' => 11]));
        $this->assertSame($first->id, $again->id);
        $this->assertSame(2, $again->fresh()->occurrence_count);
        $this->assertSame(1, SystemErrorLog::count());

        // once resolved, the same error opens a fresh row
        $first->update(['status' => 'Resolved']);
        $third = $svc->capture($e, $req);
        $this->assertNotSame($first->id, $third->id);
        $this->assertSame(2, SystemErrorLog::count());
    }

    public function test_capture_records_query_string_for_get_requests_and_truncates_url(): void
    {
        $svc = new ErrorLoggerService();
        $req = Request::create('/reports/sales?token=abc&from=2026-01-01', 'GET');
        $log = $svc->capture(new \LogicException('report broke'), $req);

        $this->assertSame('Reports', $log->module);
        $this->assertSame('GET', $log->method);
        $this->assertSame('***REDACTED***', $log->request_data['_query']['token']);
        $this->assertSame('2026-01-01', $log->request_data['_query']['from']);
    }
}
