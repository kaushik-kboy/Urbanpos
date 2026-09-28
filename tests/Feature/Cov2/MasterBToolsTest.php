<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ReceiptSetting;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Backups (controller + service + SystemHealth), SystemHealth diagnostics and ReceiptDesigner.
 * storage_path() is redirected to a temp dir, so the real backups dir and laravel.log are never touched.
 */
class MasterBToolsTest extends TestCase
{
    use MasterBHelper, RefreshDatabase;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnSuperUser();
        $this->store = $this->useTempStorage();
        $this->actAs('Owner');
    }

    protected function tearDown(): void
    {
        $this->cleanTempDirs();
        parent::tearDown();
    }

    private function backupDir(): string
    {
        return $this->store.'/app/backups';
    }

    private function norm(string $p): string
    {
        return str_replace('\\', '/', $p);
    }

    private function seedBackup(string $name, string $content = 'data', ?int $mtime = null): string
    {
        $p = $this->backupDir().'/'.$name;
        File::put($p, $content);
        if ($mtime) {
            touch($p, $mtime);
        }

        return $p;
    }

    // ------------------------------------------------------------ DatabaseBackupController

    public function test_backup_index_lists_files_newest_first_with_size_formatting(): void
    {
        $this->seedBackup('old.sql.gz', str_repeat('a', 2048), time() - 5000);
        $this->seedBackup('new.sql.gz', 'tiny', time() - 10);

        $resp = $this->get(route('tools.backups.index'))->assertOk();
        $backups = $resp->viewData('backups');

        $this->assertSame(['new.sql.gz', 'old.sql.gz'], array_column($backups, 'filename'));
        $this->assertSame('2.00 KB', $backups[1]['size']);
        $this->assertSame('0.00 KB', $backups[0]['size']);
        $resp->assertSee('old.sql.gz');
    }

    public function test_backup_download_streams_file_and_blocks_missing_and_traversal_names(): void
    {
        $this->seedBackup('good.sql.gz', 'PAYLOAD-123');
        // a secret file OUTSIDE the backup dir that traversal would reach
        File::put($this->store.'/secret.txt', 'TOP-SECRET');

        $resp = $this->get(route('tools.backups.download', 'good.sql.gz'))->assertOk();
        $this->assertSame('PAYLOAD-123', file_get_contents($resp->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('good.sql.gz', $resp->headers->get('content-disposition'));

        $this->get(route('tools.backups.download', 'nope.sql.gz'))
            ->assertRedirect(route('tools.backups.index'))->assertSessionHas('error', 'Backup file not found.');

        // encoded traversal never reaches the controller (slash in a route segment => 404) and never leaks the file
        $this->get('/tools/backups/download/'.rawurlencode('../secret.txt'))->assertNotFound();
        $this->assertSame('TOP-SECRET', File::get($this->store.'/secret.txt'));
    }

    public function test_backup_destroy_deletes_only_inside_backup_dir_and_reports_missing(): void
    {
        $p = $this->seedBackup('del.sql.gz');
        File::put($this->store.'/precious.txt', 'keep');

        $this->delete(route('tools.backups.destroy', 'del.sql.gz'))
            ->assertRedirect(route('tools.backups.index'))->assertSessionHas('success', 'Backup deleted successfully.');
        $this->assertFileDoesNotExist($p);

        $this->delete(route('tools.backups.destroy', 'del.sql.gz'))->assertSessionHas('error', 'Backup file not found.');

        $this->delete('/tools/backups/'.rawurlencode('../precious.txt'))->assertNotFound();
        $this->assertFileExists($this->store.'/precious.txt');
    }

    public function test_backup_create_via_controller_writes_a_snapshot_and_guests_are_redirected(): void
    {
        $this->post(route('tools.backups.create'))
            ->assertRedirect(route('tools.backups.index'));
        $files = glob($this->backupDir().'/*.sql*');
        $this->assertNotEmpty($files, 'db:backup must write a file into the (temp) backup dir');

        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get(route('tools.backups.index'))->assertRedirect(route('login'));
        $this->delete(route('tools.backups.destroy', basename($files[0])))->assertRedirect(route('login'));
        $this->assertFileExists($files[0]);
    }

    // ------------------------------------------------------------ DatabaseBackupService

    public function test_service_creates_gzip_dump_with_real_table_data(): void
    {
        Branch::create(['name' => "O'Brien Branch"]);
        $svc = new DatabaseBackupService();

        $res = $svc->createBackup('unit');

        $this->assertTrue($res['success']);
        $this->assertSame($this->norm($this->backupDir().'/'.$res['filename']), $this->norm($res['path']));
        $this->assertMatchesRegularExpression('/^urbanpos_backup_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $res['filename']);
        $this->assertGreaterThan(0, $res['tables_count']);
        $this->assertGreaterThan(0, $res['rows_count']);
        $this->assertSame('unit', $res['trigger']);

        $sql = gzdecode(file_get_contents($res['path']));
        $this->assertStringContainsString('-- Trigger: UNIT', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `branches`;', $sql);
        $this->assertStringContainsString('INSERT INTO `branches`', $sql);
        $this->assertStringContainsString("O\\'Brien Branch", $sql, 'string values must be escaped');
        $this->assertStringContainsString('-- Dump completed successfully.', $sql);
        $this->assertFileExists($this->backupDir().'/.htaccess');
        $this->assertSame("Deny from all\n", File::get($this->backupDir().'/.htaccess'));
    }

    public function test_service_list_filters_by_prefix_and_extension_and_sorts_newest_name_first(): void
    {
        $this->seedBackup('urbanpos_backup_2026-01-01_000000.sql.gz', str_repeat('z', 1536));
        $this->seedBackup('urbanpos_backup_2026-03-01_000000.sql', 'x');
        $this->seedBackup('random.sql.gz');
        $this->seedBackup('urbanpos_backup_2026-02-01_000000.txt');

        $list = (new DatabaseBackupService())->listBackups();

        $this->assertSame(
            ['urbanpos_backup_2026-03-01_000000.sql', 'urbanpos_backup_2026-01-01_000000.sql.gz'],
            array_column($list, 'filename')
        );
        $this->assertSame('1.5 KB', $list[1]['size_human']);
        $this->assertSame('1 B', $list[0]['size_human']);
        $this->assertSame(1536, $list[1]['size_bytes']);
    }

    public function test_service_prune_removes_only_expired_backups(): void
    {
        $old = $this->seedBackup('urbanpos_backup_old.sql.gz', 'o', time() - 20 * 86400);
        $fresh = $this->seedBackup('urbanpos_backup_fresh.sql.gz', 'f', time() - 86400);
        $foreign = $this->seedBackup('unrelated.sql.gz', 'u', time() - 90 * 86400);

        $deleted = (new DatabaseBackupService())->pruneOldBackups(14);

        $this->assertSame(1, $deleted);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($fresh);
        $this->assertFileExists($foreign, 'files without the backup prefix are never pruned');
    }

    public function test_service_secure_path_rejects_traversal_and_missing_and_delete_follows(): void
    {
        $svc = new DatabaseBackupService();
        $p = $this->seedBackup('a.sql.gz');
        File::put($this->store.'/outside.txt', 'x');

        $this->assertSame($this->norm($p), $this->norm($svc->getSecureBackupPath('a.sql.gz')));
        $this->assertNull($svc->getSecureBackupPath('../outside.txt'));
        $this->assertNull($svc->getSecureBackupPath('sub/a.sql.gz'));
        $this->assertNull($svc->getSecureBackupPath('..\\outside.txt') ?? null);
        $this->assertNull($svc->getSecureBackupPath('missing.sql.gz'));

        $this->assertFalse($svc->deleteBackup('../outside.txt'));
        $this->assertFileExists($this->store.'/outside.txt');
        $this->assertTrue($svc->deleteBackup('a.sql.gz'));
        $this->assertFileDoesNotExist($p);
        $this->assertFalse($svc->deleteBackup('a.sql.gz'));
    }

    // ------------------------------------------------------------ SystemHealthController

    public function test_health_backup_endpoints_json_contract_and_traversal_safety(): void
    {
        $res = $this->postJson(route('tools.system-health.backup.create'))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('backup.trigger', 'manual_ui');
        $name = $res->json('backup.filename');
        $this->assertFileExists($this->backupDir().'/'.$name);

        $dl = $this->get(route('tools.system-health.backup.download', $name))->assertOk();
        $this->assertSame($this->norm($this->backupDir().'/'.$name), $this->norm($dl->baseResponse->getFile()->getPathname()));

        $this->get(route('tools.system-health.backup.download', 'ghost.sql.gz'))->assertNotFound();
        $this->get('/tools/system-health/backup/download/'.rawurlencode('../x'))->assertNotFound();

        File::put($this->store.'/keep.txt', 'k');
        $this->deleteJson('/tools/system-health/backup/'.rawurlencode('../keep.txt'))
            ->assertNotFound();
        $this->assertFileExists($this->store.'/keep.txt');

        $this->deleteJson(route('tools.system-health.backup.delete', $name))->assertOk()->assertJsonPath('success', true);
        $this->assertFileDoesNotExist($this->backupDir().'/'.$name);
        $this->deleteJson(route('tools.system-health.backup.delete', $name))->assertNotFound();
    }

    public function test_health_create_backup_failure_returns_500_json(): void
    {
        $this->app->bind(DatabaseBackupService::class, fn () => new class extends DatabaseBackupService {
            public function createBackup(string $trigger = 'manual'): array
            {
                throw new \RuntimeException('disk full');
            }
        });

        $this->postJson(route('tools.system-health.backup.create'))
            ->assertStatus(500)->assertJsonPath('success', false)->assertJsonPath('message', 'Backup failed: disk full');
    }

    public function test_run_diagnostics_reports_metrics_and_records_a_heartbeat(): void
    {
        $this->assertFileDoesNotExist($this->store.'/logs/pos-health.log');

        $res = $this->postJson(route('tools.system-health.run'))->assertOk();

        $res->assertJsonPath('success', true)
            ->assertJsonPath('status', 'HEALTHY')
            ->assertJsonPath('metrics.database.status', 'OK')
            ->assertJsonPath('metrics.storage.writable', true)
            ->assertJsonPath('metrics.server.php_version', PHP_VERSION)
            ->assertJsonPath('metrics.server.environment', 'testing')
            ->assertJsonPath('metrics.activity.today_bills_count', 0);
        $this->assertIsFloat($res->json('metrics.database.size_mb') + 0.0);
        $this->assertGreaterThan(0, $res->json('metrics.database.size_mb'));

        $line = json_decode(trim(File::get($this->store.'/logs/pos-health.log')), true);
        $this->assertSame('HEALTHY', $line['status']);
        $this->assertSame('OK', $line['details']['database']['status']);
    }

    public function test_run_diagnostics_flags_needs_attention_when_storage_is_not_writable(): void
    {
        // framework/views missing in the storage tree => is_writable() false
        File::deleteDirectory($this->store.'/framework/views');

        $this->postJson(route('tools.system-health.run'))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'NEEDS_ATTENTION')
            ->assertJsonPath('metrics.storage.status', 'ERROR')
            ->assertJsonPath('metrics.storage.writable', false);

        $line = json_decode(trim(File::get($this->store.'/logs/pos-health.log')), true);
        $this->assertSame('UNHEALTHY', $line['status']);
    }

    public function test_health_index_shows_recent_heartbeats_newest_first_backups_and_skips_corrupt_lines(): void
    {
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = json_encode(['timestamp' => "T$i", 'status' => 'HEALTHY', 'details' => []]);
        }
        $lines[] = 'not json at all';
        File::put($this->store.'/logs/pos-health.log', implode("\n", $lines)."\n\n");
        $this->seedBackup('urbanpos_backup_2026-05-05_010101.sql.gz', 'x');

        $resp = $this->get(route('tools.system-health.index'))->assertOk();

        $hb = $resp->viewData('recentHeartbeats');
        // last 15 lines = T7..T20 + the corrupt one; corrupt dropped => 14, newest first
        $this->assertCount(14, $hb);
        $this->assertSame('T20', $hb[0]['timestamp']);
        $this->assertSame('T7', $hb[13]['timestamp']);
        $this->assertSame('urbanpos_backup_2026-05-05_010101.sql.gz', $resp->viewData('backups')[0]['filename']);
        $this->assertTrue($resp->viewData('metrics')['overall_healthy']);
    }

    public function test_health_index_with_no_heartbeat_log_or_empty_log_has_no_entries(): void
    {
        $this->assertSame([], $this->get(route('tools.system-health.index'))->assertOk()->viewData('recentHeartbeats'));
        File::put($this->store.'/logs/pos-health.log', '');
        $this->assertSame([], $this->get(route('tools.system-health.index'))->viewData('recentHeartbeats'));
    }

    public function test_clear_laravel_log_truncates_the_temp_log_only_and_reports_size(): void
    {
        $log = $this->store.'/logs/laravel.log';
        File::put($log, str_repeat('E', 3000));

        $before = $this->postJson(route('tools.system-health.run'))->json('metrics.storage.laravel_log');
        $this->assertTrue($before['exists']);
        $this->assertSame(3000, $before['bytes']);
        $this->assertSame('2.9 KB', $before['formatted']);

        $this->postJson(route('tools.system-health.clear-log'))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('log_size.bytes', 0)
            ->assertJsonPath('log_size.formatted', '0.00 MB');
        $this->assertSame(0, filesize($log));
    }

    public function test_clear_laravel_log_with_no_file_is_a_safe_noop(): void
    {
        $this->postJson(route('tools.system-health.clear-log'))
            ->assertOk()->assertJsonPath('log_size.exists', false)->assertJsonPath('log_size.bytes', 0);
        $this->assertFileDoesNotExist($this->store.'/logs/laravel.log');
    }

    public function test_large_log_is_reported_in_mb(): void
    {
        $fp = fopen($this->store.'/logs/laravel.log', 'w');
        ftruncate($fp, 2 * 1048576 + 1);
        fclose($fp);

        $size = app(\App\Http\Controllers\Tools\SystemHealthController::class)->getLaravelLogSize();
        $this->assertSame('2 MB', $size['formatted']);
        $this->assertSame(2.0, $size['size_mb']);
    }

    public function test_server_memory_stats_shape_and_summary_consistency(): void
    {
        $m = app(\App\Http\Controllers\Tools\SystemHealthController::class)->getServerMemoryStats();
        $this->assertGreaterThan(0, $m['php_usage_mb']);
        if ($m['total_ram_gb'] === null) {
            $this->assertSame("{$m['php_usage_mb']} MB (PHP Process)", $m['formatted_summary']);
        } else {
            $this->assertGreaterThan(0, $m['total_ram_gb']);
            $this->assertLessThanOrEqual($m['total_ram_gb'], $m['used_ram_gb'] + 0.02);
            $this->assertSame("{$m['used_ram_gb']} GB / {$m['total_ram_gb']} GB ({$m['usage_percentage']}%)", $m['formatted_summary']);
        }
    }

    // ------------------------------------------------------------ ReceiptDesignerController

    private function receiptPayload(array $over = []): array
    {
        return array_merge([
            'store_name' => '  Urban Pets HQ  ',
            'paper_size' => '58mm',
            'font_size' => 'large',
            'gstin' => '27abcde1234f1z5',
            'tagline' => '',
            'phone' => ' 98765 ',
            'show_hsn_code' => 1,
        ], $over);
    }

    public function test_receipt_designer_index_defaults_unknown_doc_and_creates_default_settings(): void
    {
        $resp = $this->get(route('tools.receipt-designer.index', ['doc' => 'bogus_type']))->assertOk();
        $this->assertSame('sales_bill', $resp->viewData('docType'));
        $this->assertSame('sales_bill', $resp->viewData('settings')->document_type);
        $this->assertSame(1, ReceiptSetting::where('document_type', 'sales_bill')->count());

        foreach (['sales_return', 'stock_transfer', 'purchase_invoice', 'purchase_return'] as $doc) {
            $r = $this->get(route('tools.receipt-designer.index', ['doc' => $doc]))->assertOk();
            $this->assertSame($doc, $r->viewData('docType'));
            $this->assertSame($doc, $r->viewData('settings')->document_type);
        }
    }

    public function test_receipt_designer_index_prefills_branch_details_for_selected_branch(): void
    {
        $b = Branch::create(['name' => 'Kothrud', 'phone' => '020-111', 'city' => 'Pune', 'gst_no' => '27AAAAA0000A1Z5']);

        $resp = $this->get(route('tools.receipt-designer.index', ['branch_id' => $b->id]))->assertOk();
        $s = $resp->viewData('settings');
        $this->assertSame($b->id, $resp->viewData('selectedBranchId'));
        $this->assertSame('Kothrud', $s->store_name);
        $this->assertSame('020-111', $s->phone);
        $this->assertSame('27AAAAA0000A1Z5', $s->gstin);
        $this->assertStringContainsString('Pune', $s->header_address);
    }

    public function test_receipt_designer_update_saves_normalised_values_and_redirects(): void
    {
        $b = Branch::create(['name' => 'RB']);
        $this->post(route('tools.receipt-designer.update'), $this->receiptPayload(['document_type' => 'stock_transfer', 'branch_id' => $b->id]))
            ->assertRedirect(route('tools.receipt-designer.index', ['doc' => 'stock_transfer', 'branch_id' => $b->id]))
            ->assertSessionHas('success');

        $s = ReceiptSetting::where('document_type', 'stock_transfer')->where('branch_id', $b->id)->first();
        $this->assertSame('Urban Pets HQ', $s->store_name);
        $this->assertSame('27ABCDE1234F1Z5', $s->gstin);
        $this->assertNull($s->tagline);
        $this->assertSame('98765', $s->phone);
        $this->assertSame('58mm', $s->paper_size);
        $this->assertSame('large', $s->font_size);
        $this->assertTrue((bool) $s->show_hsn_code);
        $this->assertFalse((bool) $s->show_barcode);
        $this->assertSame(120, (int) $s->logo_width);
    }

    public function test_receipt_designer_update_json_and_unknown_type_falls_back_to_sales_bill(): void
    {
        $res = $this->postJson(route('tools.receipt-designer.update'), $this->receiptPayload(['document_type' => 'weird']))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('settings.store_name', 'Urban Pets HQ');
        $this->assertStringContainsString('All Branches', $res->json('message'));
        $this->assertSame('58mm', ReceiptSetting::where('document_type', 'sales_bill')->whereNull('branch_id')->value('paper_size'));
        $this->assertSame(0, ReceiptSetting::where('document_type', 'weird')->count());
    }

    public function test_receipt_designer_update_validation_rules(): void
    {
        $cases = [
            'store_name' => [['store_name' => ''], ['store_name' => str_repeat('s', 101)]],
            'paper_size' => [['paper_size' => 'letter'], ['paper_size' => '']],
            'font_size' => [['font_size' => 'huge']],
            'branch_id' => [['branch_id' => 999999]],
            'logo_width' => [['logo_width' => 39], ['logo_width' => 301]],
            'footer_policy' => [['footer_policy' => str_repeat('f', 2001)]],
            'custom_css' => [['custom_css' => str_repeat('c', 2001)]],
        ];
        foreach ($cases as $field => $variants) {
            foreach ($variants as $v) {
                $this->postJson(route('tools.receipt-designer.update'), $this->receiptPayload($v))
                    ->assertStatus(422)->assertJsonValidationErrors($field);
            }
        }
        $this->assertSame(0, ReceiptSetting::count());
    }

    public function test_receipt_designer_logo_upload_stores_file_and_forces_show_logo_and_rejects_non_images(): void
    {
        Storage::fake('public');

        $this->postJson(route('tools.receipt-designer.update'), $this->receiptPayload([
            'logo_file' => UploadedFile::fake()->image('logo.png', 100, 100),
        ]))->assertOk();

        $s = ReceiptSetting::where('document_type', 'sales_bill')->first();
        $this->assertTrue((bool) $s->show_logo, 'uploading a logo turns show_logo on even if not ticked');
        $this->assertStringStartsWith('/storage/receipt_logos/', $s->logo_path);
        Storage::disk('public')->assertExists('receipt_logos/'.basename($s->logo_path));

        $this->postJson(route('tools.receipt-designer.update'), $this->receiptPayload([
            'logo_file' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php'),
        ]))->assertStatus(422)->assertJsonValidationErrors('logo_file');
        $this->postJson(route('tools.receipt-designer.update'), $this->receiptPayload([
            'logo_file' => UploadedFile::fake()->image('big.png')->size(3000),
        ]))->assertStatus(422)->assertJsonValidationErrors('logo_file');
    }
}
