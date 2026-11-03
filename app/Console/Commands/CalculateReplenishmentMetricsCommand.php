<?php

namespace App\Console\Commands;

use App\Services\ReplenishmentCalculationService;
use Illuminate\Console\Command;

class CalculateReplenishmentMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'replenishment:calculate {--branch= : Calculate only for specific branch ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and cache smart inventory replenishment velocity, cover days, and suggested quantities';

    /**
     * Execute the console command.
     */
    public function handle(ReplenishmentCalculationService $service): int
    {
        $this->info('Starting Smart Inventory Replenishment calculations...');
        $startTime = microtime(true);

        $branchId = $this->option('branch');

        if ($branchId) {
            $this->info("Processing Branch ID: {$branchId}...");
            $count = $service->calculateForBranch((int) $branchId);
            $this->info("Processed {$count} items for Branch #{$branchId}.");
        } else {
            $results = $service->calculateAll();
            foreach ($results as $bId => $res) {
                $this->line(" - Branch #{$bId} ({$res['branch_name']}): {$res['items_processed']} items processed.");
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Smart Replenishment calculation finished in {$duration}s.");

        return Command::SUCCESS;
    }
}

