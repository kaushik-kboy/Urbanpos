<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PosHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform scheduled 24/7 background system health audit and record heartbeat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timestamp = now()->toIso8601String();
        $isHealthy = true;
        $details = [];

        // 1. Database Connectivity & Ping
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);
            $details['database'] = [
                'status' => 'OK',
                'latency_ms' => $dbDuration,
            ];
        } catch (\Throwable $e) {
            $isHealthy = false;
            $details['database'] = [
                'status' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }

        // 2. Storage Permissions
        $storageWritable = is_writable(storage_path()) 
            && is_writable(storage_path('framework/views')) 
            && is_writable(base_path('bootstrap/cache'));

        $details['storage'] = [
            'status' => $storageWritable ? 'OK' : 'ERROR',
            'writable' => $storageWritable,
        ];
        if (!$storageWritable) {
            $isHealthy = false;
        }

        // 3. Disk Space Check
        $freeSpaceMb = @disk_free_space(base_path()) ? round(disk_free_space(base_path()) / 1024 / 1024, 1) : null;
        $details['disk'] = [
            'free_mb' => $freeSpaceMb,
        ];

        // 4. Record to Log File
        $logPath = storage_path('logs/pos-health.log');
        $logEntry = [
            'timestamp' => $timestamp,
            'status' => $isHealthy ? 'HEALTHY' : 'UNHEALTHY',
            'details' => $details,
        ];

        $logLine = json_encode($logEntry) . PHP_EOL;
        @File::append($logPath, $logLine);

        // Keep log file manageable (keep last 500 lines if oversized)
        if (@file_exists($logPath) && @filesize($logPath) > 500000) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($lines) > 200) {
                file_put_contents($logPath, implode(PHP_EOL, array_slice($lines, -200)) . PHP_EOL);
            }
        }

        if ($isHealthy) {
            $this->info("Heartbeat recorded: 100% HEALTHY (DB: {$details['database']['latency_ms']}ms)");
            return Command::SUCCESS;
        }

        $this->error('Heartbeat recorded: SYSTEM UNHEALTHY!');
        return Command::FAILURE;
    }
}
