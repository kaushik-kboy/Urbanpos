<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

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
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user->assignRole($ownerRole);
    }

    public function test_system_health_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.system-health.index'));

        $response->assertOk();
        $response->assertSee('System Health');
        $response->assertSee('Run Live Diagnostics');
        $response->assertSee('MySQL Database');
        $response->assertSee('Host RAM');
        $response->assertSee('Storage');
        $response->assertSee('Regression Guard');
    }

    public function test_system_health_run_diagnostics_ajax_returns_healthy_json_with_memory_stats(): void
    {
        $response = $this->actingAs($this->user)->post(route('tools.system-health.run'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'status',
            'timestamp',
            'metrics' => [
                'overall_healthy',
                'database' => ['status', 'latency_ms'],
                'storage' => ['status', 'writable'],
                'server' => [
                    'php_version',
                    'laravel_version',
                    'memory_usage_mb',
                    'memory_stats' => [
                        'php_usage_mb',
                        'formatted_summary',
                    ],
                ],
            ],
        ]);
        $response->assertJson(['success' => true, 'status' => 'HEALTHY']);
    }

    public function test_pos_heartbeat_command_records_audit_log_entry(): void
    {
        $logPath = storage_path('logs/pos-health.log');
        $initialSize = File::exists($logPath) ? File::size($logPath) : 0;

        $exitCode = Artisan::call('pos:heartbeat');

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($logPath));
        $this->assertGreaterThan($initialSize, File::size($logPath));

        $content = File::get($logPath);
        $this->assertStringContainsString('"status":"HEALTHY"', $content);
    }

    public function test_clear_laravel_log_truncates_file_to_zero_bytes(): void
    {
        $logPath = storage_path('logs/laravel.log');
        File::put($logPath, "Simulated log line 1\nSimulated log line 2\n");
        $this->assertGreaterThan(0, File::size($logPath));

        $response = $this->actingAs($this->user)->postJson(route('tools.system-health.clear-log'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'log_size' => [
                'bytes' => 0,
                'formatted' => '0.00 MB',
            ],
        ]);

        $this->assertTrue(File::exists($logPath));
        $this->assertEquals(0, File::size($logPath));
    }
}
