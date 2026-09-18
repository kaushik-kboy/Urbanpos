<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PosBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:backup {--type=scheduled : Trigger type (scheduled or manual)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform automated compressed database backup and prune old snapshots';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        $this->info('📦 Starting UrbanPOS database backup snapshot...');
        $trigger = $this->option('type') ?: 'scheduled';

        try {
            $result = $backupService->createBackup($trigger);
            $prunedCount = $backupService->pruneOldBackups(14);

            $this->newLine();
            $this->table(
                ['Attribute', 'Value'],
                [
                    ['Archive File', $result['filename']],
                    ['File Size', $result['size_human']],
                    ['Tables Backed Up', $result['tables_count']],
                    ['Total Records Dumped', $result['rows_count']],
                    ['Duration', $result['duration_ms'] . ' ms'],
                    ['Old Snapshots Pruned', $prunedCount],
                    ['Trigger', strtoupper($result['trigger'])],
                ]
            );

            // Append to pos-health.log
            $logPath = storage_path('logs/pos-health.log');
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'status' => 'BACKUP_SUCCESS',
                'details' => [
                    'backup' => [
                        'file' => $result['filename'],
                        'size' => $result['size_human'],
                        'tables' => $result['tables_count'],
                        'duration_ms' => $result['duration_ms'],
                        'trigger' => $result['trigger'],
                    ],
                ],
            ];
            @File::append($logPath, json_encode($logEntry) . PHP_EOL);

            $this->info('✅ Database snapshot created & verified successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Backup snapshot failed: ' . $e->getMessage());

            // Log failure
            $logPath = storage_path('logs/pos-health.log');
            $logEntry = [
                'timestamp' => now()->toIso8601String(),
                'status' => 'BACKUP_FAILED',
                'details' => ['error' => $e->getMessage()],
            ];
            @File::append($logPath, json_encode($logEntry) . PHP_EOL);

            return Command::FAILURE;
        }
    }
}
