<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Branch $branch;
    private string $testBackupPath;

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

        // Create a dummy backup file in storage/app/backups to test list, download, and delete without full dump each time
        $backupDir = storage_path('app/backups');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);
        }
        $this->testBackupPath = $backupDir . '/urbanpos_backup_test_sample.sql.gz';
        if (extension_loaded('zlib')) {
            $gz = gzopen($this->testBackupPath, 'wb9');
            gzwrite($gz, "-- Sample test backup content\nSELECT 1;\n");
            gzclose($gz);
        } else {
            file_put_contents($this->testBackupPath, "-- Sample test backup content\nSELECT 1;\n");
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBackupPath)) {
            File::delete($this->testBackupPath);
        }
        parent::tearDown();
    }

    public function test_service_lists_backups_and_prevents_path_traversal(): void
    {
        $service = new DatabaseBackupService();
        $backups = $service->listBackups();

        $this->assertIsArray($backups);
        $found = false;
        foreach ($backups as $b) {
            if ($b['filename'] === 'urbanpos_backup_test_sample.sql.gz') {
                $found = true;
                $this->assertGreaterThan(0, $b['size_bytes']);
                $this->assertNotEmpty($b['size_human']);
                break;
            }
        }
        $this->assertTrue($found, 'Sample backup was not found in listBackups.');

        // Path traversal protection check
        $this->assertNull($service->getSecureBackupPath('../../.env'));
        $this->assertNull($service->getSecureBackupPath('..\\..\\config.php'));
        $this->assertNull($service->getSecureBackupPath('non_existent_file.sql.gz'));
        $this->assertNotNull($service->getSecureBackupPath('urbanpos_backup_test_sample.sql.gz'));
    }

    public function test_system_health_page_renders_backup_section(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.system-health.index'));

        $response->assertOk();
        $response->assertSee('Database Snapshots');
        $response->assertSee('Disaster Recovery');
        $response->assertSee('Create Instant Snapshot');
        $response->assertSee('urbanpos_backup_test_sample.sql.gz');
    }

    public function test_download_backup_endpoint_works_and_secures_against_traversal(): void
    {
        // Valid download
        $response = $this->actingAs($this->user)->get(route('tools.system-health.backup.download', 'urbanpos_backup_test_sample.sql.gz'));
        $response->assertOk();
        $this->assertStringContainsString('urbanpos_backup_test_sample.sql.gz', $response->headers->get('content-disposition'));

        // Path traversal attempt should 404
        $malicious = $this->actingAs($this->user)->get('/tools/system-health/backup/download/..%2F..%2F.env');
        $this->assertTrue(in_array($malicious->status(), [404, 400]));
    }

    public function test_delete_backup_endpoint_deletes_file_safely(): void
    {
        $deleteTarget = storage_path('app/backups/urbanpos_backup_to_delete.sql.gz');
        file_put_contents($deleteTarget, "test");
        $this->assertFileExists($deleteTarget);

        $response = $this->actingAs($this->user)->delete(route('tools.system-health.backup.delete', 'urbanpos_backup_to_delete.sql.gz'));
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertFileDoesNotExist($deleteTarget);
    }

    public function test_artisan_pos_backup_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('pos:backup', ['--type' => 'manual']);
        $this->assertSame(0, $exitCode);
    }
}
