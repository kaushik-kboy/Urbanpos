<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    /**
     * Display a listing of database backups.
     */
    public function index(): View
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            // Sort by latest first
            usort($files, fn ($a, $b) => $b->getMTime() <=> $a->getMTime());

            foreach ($files as $file) {
                $bytes = $file->getSize();
                $sizeStr = $bytes >= 1048576 
                    ? number_format($bytes / 1048576, 2) . ' MB' 
                    : number_format($bytes / 1024, 2) . ' KB';

                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size'     => $sizeStr,
                    'date'     => date('d-m-Y H:i:s', $file->getMTime()),
                    'mtime'    => $file->getMTime(),
                ];
            }
        }

        return view('tools.backups.index', [
            'backups' => $backups,
        ]);
    }

    /**
     * Trigger a new database backup.
     */
    public function create(): RedirectResponse
    {
        try {
            Artisan::call('db:backup');
            return redirect()->route('tools.backups.index')
                ->with('success', 'Database backup generated successfully!');
        } catch (\Throwable $e) {
            return redirect()->route('tools.backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Download a specific backup file.
     */
    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $cleanName = basename($filename);
        $filePath = storage_path('app/backups/' . $cleanName);

        if (! File::exists($filePath)) {
            return redirect()->route('tools.backups.index')
                ->with('error', 'Backup file not found.');
        }

        return response()->download($filePath, $cleanName, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    /**
     * Delete a specific backup file.
     */
    public function destroy(string $filename): RedirectResponse
    {
        $cleanName = basename($filename);
        $filePath = storage_path('app/backups/' . $cleanName);

        if (File::exists($filePath)) {
            File::delete($filePath);
            return redirect()->route('tools.backups.index')
                ->with('success', 'Backup deleted successfully.');
        }

        return redirect()->route('tools.backups.index')
            ->with('error', 'Backup file not found.');
    }
}
