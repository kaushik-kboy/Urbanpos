<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseBackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        $this->ensureDirectoryExists();
    }

    /**
     * Ensure backup directory exists and is secured against direct web access.
     */
    private function ensureDirectoryExists(): void
    {
        if (!File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true, true);
        }

        $htaccessPath = $this->backupDir . '/.htaccess';
        if (!File::exists($htaccessPath)) {
            File::put($htaccessPath, "Deny from all\n");
        }
    }

    /**
     * Create a compressed SQL backup snapshot.
     */
    public function createBackup(string $trigger = 'manual'): array
    {
        $startTime = microtime(true);
        $this->ensureDirectoryExists();

        $useGzip = extension_loaded('zlib');
        $extension = $useGzip ? 'sql.gz' : 'sql';
        $filename = 'urbanpos_backup_' . date('Y-m-d_His') . '.' . $extension;
        $filepath = $this->backupDir . '/' . $filename;

        $handle = $useGzip ? gzopen($filepath, 'wb9') : fopen($filepath, 'wb');
        if (!$handle) {
            throw new \RuntimeException("Unable to open backup file for writing: {$filepath}");
        }

        $write = function (string $text) use ($handle, $useGzip) {
            if ($useGzip) {
                gzwrite($handle, $text);
            } else {
                fwrite($handle, $text);
            }
        };

        // 1. SQL Dump Header
        $write("-- ============================================================\n");
        $write("-- UrbanPOS Autonomous Database Backup Snapshot\n");
        $write("-- Date: " . date('Y-m-d H:i:s T') . "\n");
        $write("-- Trigger: " . strtoupper($trigger) . "\n");
        $write("-- Host: " . config('database.connections.mysql.host') . "\n");
        $write("-- Database: " . config('database.connections.mysql.database') . "\n");
        $write("-- ============================================================\n\n");
        $write("SET FOREIGN_KEY_CHECKS = 0;\n");
        $write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        $write("SET AUTOCOMMIT = 0;\n");
        $write("START TRANSACTION;\n");
        $write("SET NAMES utf8mb4;\n\n");

        // 2. Fetch all database tables
        $tablesRaw = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $dbName = config('database.connections.mysql.database');
        $tableKey = 'Tables_in_' . $dbName;
        $tables = [];

        foreach ($tablesRaw as $tr) {
            $prop = (array) $tr;
            $tableName = reset($prop);
            if ($tableName) {
                $tables[] = $tableName;
            }
        }

        $tableCount = count($tables);
        $totalRowsDumped = 0;

        // 3. Dump each table's schema and records
        foreach ($tables as $table) {
            $write("-- ------------------------------------------------------------\n");
            $write("-- Table structure for `{$table}`\n");
            $write("-- ------------------------------------------------------------\n");
            $write("DROP TABLE IF EXISTS `{$table}`;\n");

            $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
            if (!empty($createTable)) {
                $createArr = (array) $createTable[0];
                $createStatement = $createArr['Create Table'] ?? '';
                if ($createStatement) {
                    $write($createStatement . ";\n\n");
                }
            }

            // Dump rows in chunks
            $query = DB::table($table);
            $count = $query->count();
            if ($count === 0) {
                continue;
            }

            $write("-- Dumping data for table `{$table}` ({$count} records)\n");

            // Fetch columns to quote safely
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $quotedColumns = array_map(fn ($c) => "`{$c}`", $columns);
            $colList = implode(', ', $quotedColumns);

            // Fetch rows via cursor to keep memory minimal
            $rowsChunk = [];
            foreach ($query->cursor() as $row) {
                $rowArr = (array) $row;
                $escapedValues = [];
                foreach ($columns as $col) {
                    $val = $rowArr[$col] ?? null;
                    if (is_null($val)) {
                        $escapedValues[] = 'NULL';
                    } elseif (is_numeric($val)) {
                        $escapedValues[] = (string) $val;
                    } else {
                        // String escape using PDO quote or addslashes
                        $escapedValues[] = DB::getPdo()->quote((string) $val);
                    }
                }
                $rowsChunk[] = '(' . implode(', ', $escapedValues) . ')';
                $totalRowsDumped++;

                if (count($rowsChunk) >= 200) {
                    $write("INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $rowsChunk) . ";\n");
                    $rowsChunk = [];
                }
            }

            if (!empty($rowsChunk)) {
                $write("INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $rowsChunk) . ";\n");
            }
            $write("\n");
        }

        // 4. SQL Dump Footer
        $write("COMMIT;\n");
        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        $write("-- Dump completed successfully.\n");

        if ($useGzip) {
            gzclose($handle);
        } else {
            fclose($handle);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $fileSizeBytes = File::size($filepath);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'size_bytes' => $fileSizeBytes,
            'size_human' => $this->formatBytes($fileSizeBytes),
            'tables_count' => $tableCount,
            'rows_count' => $totalRowsDumped,
            'duration_ms' => $durationMs,
            'trigger' => $trigger,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * List all available backup archives.
     */
    public function listBackups(): array
    {
        $this->ensureDirectoryExists();
        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, 'urbanpos_backup_') && (str_ends_with($name, '.sql.gz') || str_ends_with($name, '.sql'))) {
                $size = $file->getSize();
                $mtime = $file->getMTime();
                $backups[] = [
                    'filename' => $name,
                    'size_bytes' => $size,
                    'size_human' => $this->formatBytes($size),
                    'timestamp' => date('Y-m-d H:i:s', $mtime),
                    'age_days' => round((time() - $mtime) / 86400, 1),
                ];
            }
        }

        // Sort newest first
        usort($backups, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

        return $backups;
    }

    /**
     * Remove backups older than N days.
     */
    public function pruneOldBackups(int $keepDays = 14): int
    {
        $this->ensureDirectoryExists();
        $files = File::files($this->backupDir);
        $threshold = time() - ($keepDays * 86400);
        $deletedCount = 0;

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, 'urbanpos_backup_') && $file->getMTime() < $threshold) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Securely resolve backup file path preventing directory traversal.
     */
    public function getSecureBackupPath(string $filename): ?string
    {
        // Enforce exact basename matching (no / or ..)
        if (basename($filename) !== $filename) {
            return null;
        }

        $filepath = $this->backupDir . '/' . $filename;
        if (!File::exists($filepath)) {
            return null;
        }

        return $filepath;
    }

    /**
     * Delete a specific backup file safely.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getSecureBackupPath($filename);
        if (!$path) {
            return false;
        }

        return File::delete($path);
    }

    /**
     * Format bytes to readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
