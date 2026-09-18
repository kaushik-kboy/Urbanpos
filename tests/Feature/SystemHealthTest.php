<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemHealthTest extends TestCase
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

    public function test_system_health_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('tools.system-health.index'));

        $response->assertOk();
        $response->assertSee('System Health &amp; 24/7 Monitor', false);
        $response->assertSee('Run Live Diagnostics');
        $response->assertSee('MySQL Database');
        $response->assertSee('Storage Permissions');
        $response->assertSee('Regression Guard');
    }

    public function test_system_health_run_diagnostics_ajax_returns_healthy_json(): void
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
                'server' => ['php_version', 'laravel_version'],
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
}
