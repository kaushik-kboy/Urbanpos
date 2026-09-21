<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--clean : Clean up old backups exceeding retention days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a compressed database backup and clean old backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup process...');

        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            $targetGz = $backupDir . "/backup-{$timestamp}.sql.gz";
            if ($dbPath !== ':memory:' && File::exists($dbPath)) {
                $data = file_get_contents($dbPath);
                file_put_contents($targetGz, gzencode($data, 9));
                $this->info("SQLite database backed up to: {$targetGz}");
            } else {
                $this->fallbackExport($targetGz);
            }
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $host     = config("database.connections.{$connection}.host");
            $port     = config("database.connections.{$connection}.port", '3306');
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");

            $targetSql  = $backupDir . "/backup-{$timestamp}.sql";
            $targetGz   = $targetSql . '.gz';
            $dumpBin = $this->getMysqldumpBinary();

            if ($dumpBin) {
                $pwdFlag = ($password !== '' && ! is_null($password)) ? '--password=' . escapeshellarg($password) : '';
                $cmd = sprintf(
                    '%s --user=%s %s --host=%s --port=%s --skip-comments --quick %s > %s 2>&1',
                    $dumpBin,
                    escapeshellarg($username),
                    $pwdFlag,
                    escapeshellarg($host),
                    escapeshellarg($port),
                    escapeshellarg($database),
                    escapeshellarg($targetSql)
                );

                exec($cmd, $output, $resultCode);

                if ($resultCode === 0 && File::exists($targetSql) && filesize($targetSql) > 0) {
                    $sqlContent = file_get_contents($targetSql);
                    file_put_contents($targetGz, gzencode($sqlContent, 9));
                    File::delete($targetSql);
                    $this->info("MySQL database backup compressed to: {$targetGz}");
                } else {
                    $this->warn('mysqldump execution failed, using internal table exporter fallback...');
                    if (File::exists($targetSql)) {
                        File::delete($targetSql);
                    }
                    $this->fallbackExport($targetGz);
                }
            } else {
                $this->info('mysqldump binary not found in PATH, using high-speed internal table exporter...');
                $this->fallbackExport($targetGz);
            }
        }

        // Clean old backups older than 14 days
        $this->cleanOldBackups($backupDir, 14);

        $this->info('Database backup process completed successfully.');
        return 0;
    }

    /**
     * Fallback database exporter using Laravel DB connection.
     */
    protected function fallbackExport(string $targetGz): void
    {
        $tables = \Illuminate\Support\Facades\Schema::getTableListing();
        $output = "-- UrbanPOS Automated Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n";

        foreach ($tables as $table) {
            $rows = DB::table($table)->get();
            $output .= "\n-- Table: `{$table}`\n";
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $cols = implode('`, `', array_keys($rowArray));
                $vals = implode(', ', array_map(function ($val) {
                    return is_null($val) ? 'NULL' : "'" . addslashes((string) $val) . "'";
                }, array_values($rowArray)));
                $output .= "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
            }
        }
        $output .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($targetGz, gzencode($output, 9));
        $this->info("Fallback backup exported to: {$targetGz}");
    }

    /**
     * Clean old backups exceeding retention days.
     */
    protected function cleanOldBackups(string $dir, int $retentionDays): void
    {
        $cutoff = time() - ($retentionDays * 86400);
        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * Locate mysqldump binary on system.
     */
    protected function getMysqldumpBinary(): ?string
    {
        // Check Windows Laragon paths
        $laragonPaths = glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe');
        if (! empty($laragonPaths) && file_exists($laragonPaths[0])) {
            return '"' . str_replace('/', '\\', $laragonPaths[0]) . '"';
        }

        // Check common Linux / standard PATH
        if (PHP_OS_FAMILY !== 'Windows') {
            if (file_exists('/usr/bin/mysqldump')) {
                return '/usr/bin/mysqldump';
            }
            if (file_exists('/usr/local/bin/mysqldump')) {
                return '/usr/local/bin/mysqldump';
            }
            return 'mysqldump';
        }

        return null;
    }
}
