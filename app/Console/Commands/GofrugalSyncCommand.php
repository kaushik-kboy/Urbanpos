<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class GofrugalSyncCommand extends Command
{
    protected $signature = 'gofrugal:sync 
                            {date? : Target date in YYYY-MM-DD format (default yesterday)} 
                            {--reports=all : Comma-separated report IDs to extract} 
                            {--modules=all : Comma-separated modules to import} 
                            {--skip-extract : Skip headless extraction and only run import on existing CSVs} 
                            {--dry-run : Simulate imports without database commit}';

    protected $description = 'Complete end-to-end extraction from GoFrugal TruePOS and synchronization into UrbanPOS';

    public function handle(): int
    {
        $targetDate = $this->argument('date') ?: Carbon::yesterday()->format('Y-m-d');
        $isDryRun = (bool) $this->option('dry-run');
        $skipExtract = (bool) $this->option('skip-extract');
        $reports = $this->option('reports') ?: 'all';
        $modules = $this->option('modules') ?: 'all';

        $this->info("==========================================================");
        $this->info("       GOFRUGAL TRUEPOS ➔ URBANPOS SYNC MASTER            ");
        $this->info(" Target Date:  {$targetDate}");
        $this->info(" Extraction:   " . ($skipExtract ? "SKIPPED" : "ENABLED (Headless Engine)"));
        $this->info(" Mode:         " . ($isDryRun ? "DRY-RUN (SIMULATION)" : "LIVE COMMIT"));
        $this->info("==========================================================");

        $outDir = storage_path("app/gofrugal_sync/{$targetDate}");

        // Step 1: Headless extraction from GoFrugal
        if (!$skipExtract) {
            $this->info("\n>>> STEP 1: EXTRACTING REPORTS FROM GOFRUGAL TRUEPOS <<<");
            $pythonScript = base_path('scripts/gofrugal_sync_engine.py');

            if (!file_exists($pythonScript)) {
                $this->error("Python sync engine not found at: {$pythonScript}");
                return 1;
            }

            $cmd = ['python', $pythonScript, '--date', $targetDate, '--out-dir', $outDir];
            if ($reports !== 'all') {
                $cmd[] = '--reports';
                $cmd[] = $reports;
            }

            $this->line("Running: " . implode(' ', $cmd));

            $process = new Process($cmd);
            $process->setTimeout(600); // 10 minutes timeout
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $this->error("Extraction process encountered errors or warnings.");
            } else {
                $this->info("[✓] Report extraction completed successfully!");
            }
        }

        // Step 2: Import into UrbanPOS database
        $this->info("\n>>> STEP 2: IMPORTING CSV DATA INTO URBANPOS DATABASE <<<");
        $exitCode = Artisan::call('gofrugal:import', [
            'date' => $targetDate,
            '--path' => $outDir,
            '--modules' => $modules,
            '--dry-run' => $isDryRun,
        ], $this->output);

        $this->info("\n==========================================================");
        $this->info("        GOFRUGAL ➔ URBANPOS SYNCHRONIZATION FINISHED      ");
        $this->info("==========================================================");

        return $exitCode;
    }
}
