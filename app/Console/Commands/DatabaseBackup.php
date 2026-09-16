<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--tag=pre_import}';
    protected $description = 'Create a full SQL snapshot backup of the current database';

    public function handle(): int
    {
        $tag = $this->option('tag') ?: 'pre_import';
        $backupDir = storage_path('backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'backup_' . $tag . '_' . date('Y_m_d_His') . '.sql';
        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        $this->info("Starting database backup to: {$filename}");

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', '3306');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Try mysqldump first if available
        $mysqldumpPath = 'mysqldump';
        if (PHP_OS_FAMILY === 'Windows' && File::exists('C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe')) {
            $mysqldumpPath = '"C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe"';
        }

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s %s > %s',
            $mysqldumpPath,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        $dumpSuccess = false;
        if (function_exists('exec')) {
            @exec($cmd, $output, $returnVar);
            if (($returnVar ?? 1) === 0 && File::exists($filepath) && File::size($filepath) > 1024) {
                $dumpSuccess = true;
            }
        }

        if ($dumpSuccess) {
            $sizeMb = round(File::size($filepath) / 1024 / 1024, 2);
            $this->info("Backup completed successfully via mysqldump: {$filename} ({$sizeMb} MB)");
            return 0;
        }

        // Pure PHP Table Dump Fallback
        $this->warn("Running native PHP table export fallback...");
        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $database;

        $fp = fopen($filepath, 'w');
        fwrite($fp, "-- UrbanPOS Database Snapshot\n");
        fwrite($fp, "-- Created at: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $t) {
            $table = $t->$tableKey ?? current((array)$t);
            $this->line("Dumping table: {$table}");

            // Create table statement
            $createRes = DB::select("SHOW CREATE TABLE `{$table}`");
            $createSql = $createRes[0]->{'Create Table'} ?? '';
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fp, $createSql . ";\n\n");

            // Dump data in chunks
            $rows = DB::table($table)->cursor();
            $batch = [];
            foreach ($rows as $row) {
                $cols = [];
                $vals = [];
                foreach ((array)$row as $col => $val) {
                    $cols[] = "`{$col}`";
                    if (is_null($val)) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = "'" . addslashes((string)$val) . "'";
                    }
                }
                $batch[] = "(" . implode(', ', $vals) . ")";
                if (count($batch) >= 100) {
                    fwrite($fp, "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                fwrite($fp, "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);

        $sizeMb = round(File::size($filepath) / 1024 / 1024, 2);
        $this->info("Native PHP export completed: {$filename} ({$sizeMb} MB)");
        return 0;
    }
}
