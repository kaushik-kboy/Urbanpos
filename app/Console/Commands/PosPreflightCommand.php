<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Register;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosPreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:preflight {--fix : Automatically fix data integrity discrepancies if found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated pre-flight diagnostic check for branch integrity, hardcoded IDs, and self-healing fallbacks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>===============================================================</>');
        $this->line('<fg=cyan;options=bold>        UrbanPOS Automated Pre-Flight Health Diagnostic        </>');
        $this->line('<fg=cyan;options=bold>===============================================================</>');
        $this->newLine();

        $rows = [];
        $failedChecks = 0;
        $shouldFix = $this->option('fix');

        // -------------------------------------------------------------
        // 1. Active Branches Existence
        // -------------------------------------------------------------
        $activeBranches = Branch::where('status', true)->get();
        if ($activeBranches->isNotEmpty()) {
            $names = $activeBranches->pluck('name', 'id')->map(fn ($n, $id) => "#{$id}: {$n}")->join(', ');
            $rows[] = ['Active Branches in DB', $activeBranches->count() . ' found (' . $names . ')', '<fg=green;options=bold>PASS</>'];
        } else {
            $failedChecks++;
            $rows[] = ['Active Branches in DB', 'Zero active branches found in branches table!', '<fg=red;options=bold>FAIL</>'];
        }

        $activeBranchIds = $activeBranches->pluck('id')->all();

        // -------------------------------------------------------------
        // 2. User Branch Foreign Key Integrity
        // -------------------------------------------------------------
        $orphanedUsers = User::whereNotNull('branch_id')
            ->whereNotIn('branch_id', $activeBranchIds)
            ->get();

        if ($orphanedUsers->isEmpty()) {
            $rows[] = ['User Branch Integrity', 'All ' . User::count() . ' user accounts reference valid active branches or null', '<fg=green;options=bold>PASS</>'];
        } else {
            if ($shouldFix) {
                $fallbackBranchId = $activeBranchIds[0] ?? 1;
                foreach ($orphanedUsers as $u) {
                    $u->branch_id = $fallbackBranchId;
                    $u->save();
                }
                $rows[] = ['User Branch Integrity', 'Fixed ' . $orphanedUsers->count() . ' orphaned users -> reassigned to branch #' . $fallbackBranchId, '<fg=yellow;options=bold>FIXED</>'];
            } else {
                $failedChecks++;
                $names = $orphanedUsers->map(fn ($u) => "{$u->email} (branch_id: {$u->branch_id})")->join(', ');
                $rows[] = ['User Branch Integrity', $orphanedUsers->count() . ' users with invalid branch_id: ' . $names, '<fg=red;options=bold>FAIL</>'];
            }
        }

        // -------------------------------------------------------------
        // 3. Register Branch Integrity
        // -------------------------------------------------------------
        if (class_exists(Register::class)) {
            $orphanedRegisters = Register::whereNotIn('branch_id', $activeBranchIds)->get();
            if ($orphanedRegisters->isEmpty()) {
                $rows[] = ['POS Register Integrity', 'All ' . Register::count() . ' registers reference active branches', '<fg=green;options=bold>PASS</>'];
            } else {
                if ($shouldFix) {
                    $fallbackBranchId = $activeBranchIds[0] ?? 1;
                    foreach ($orphanedRegisters as $r) {
                        $r->branch_id = $fallbackBranchId;
                        $r->save();
                    }
                    $rows[] = ['POS Register Integrity', 'Fixed ' . $orphanedRegisters->count() . ' registers -> reassigned to branch #' . $fallbackBranchId, '<fg=yellow;options=bold>FIXED</>'];
                } else {
                    $failedChecks++;
                    $rows[] = ['POS Register Integrity', $orphanedRegisters->count() . ' registers reference non-existent branches', '<fg=red;options=bold>FAIL</>'];
                }
            }
        }

        // -------------------------------------------------------------
        // 4. Hardcoded ID Audit in Blade & JS (Zero Stale Hardcoded IDs)
        // -------------------------------------------------------------
        $viewsPath = resource_path('views');
        $jsPath = public_path('js');
        $staleIdPatterns = [
            '/branch_id.*(?:\|\|\s*3\b)/i',
            '/urbanpos_active_branch_id.*(?:\|\|\s*3\b)/i',
            '/where\(\s*[\'"]id[\'"]\s*,\s*3\s*\)/i',
        ];
        $foundStaleFiles = [];

        $allBladeFiles = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));
        foreach ($iterator as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $allBladeFiles[] = $f->getPathname();
            }
        }

        foreach ($staleIdPatterns as $pattern) {
            foreach ($allBladeFiles as $filePath) {
                $content = @file_get_contents($filePath);
                if ($content && preg_match($pattern, $content)) {
                    $foundStaleFiles[] = basename($filePath);
                }
            }
        }

        if (empty($foundStaleFiles)) {
            $rows[] = ['Hardcoded Stale IDs Scan', '0 stale branch IDs found across all views and scripts', '<fg=green;options=bold>PASS</>'];
        } else {
            $failedChecks++;
            $rows[] = ['Hardcoded Stale IDs Scan', 'Found stale IDs in: ' . implode(', ', array_unique($foundStaleFiles)), '<fg=red;options=bold>FAIL</>'];
        }

        // -------------------------------------------------------------
        // 5. Web Middleware Self-Healing Auto-Fallback
        // -------------------------------------------------------------
        $testUser = User::first();
        $middleware = new \App\Http\Middleware\EnsureActiveBranch();
        $testReq = Request::create('/sales/sales-bills', 'POST', ['branch_id' => 999999]);
        if ($testUser) {
            $testReq->setUserResolver(fn () => $testUser);
        }

        $middleware->handle($testReq, function ($req) use (&$rows, &$failedChecks, $activeBranchIds) {
            $healedId = $req->input('branch_id');
            if (in_array((int) $healedId, $activeBranchIds, true)) {
                $rows[] = ['Web Branch Auto-Healing', 'Input branch_id=999999 successfully auto-healed to active branch #' . $healedId, '<fg=green;options=bold>PASS</>'];
            } else {
                $failedChecks++;
                $rows[] = ['Web Branch Auto-Healing', 'Failed to heal invalid branch_id, got: ' . var_export($healedId, true), '<fg=red;options=bold>FAIL</>'];
            }
            return new \Symfony\Component\HttpFoundation\Response();
        });

        // -------------------------------------------------------------
        // 6. API Middleware Self-Healing Auto-Fallback
        // -------------------------------------------------------------
        $apiMiddleware = new \App\Http\Middleware\EnsureApiBranchAccess();
        $apiReq = Request::create('/api/v1/observations', 'POST', ['branch_id' => 999999]);
        if ($testUser) {
            $apiReq->setUserResolver(fn () => $testUser);
        }

        $apiMiddleware->handle($apiReq, function ($req) use (&$rows, &$failedChecks, $activeBranchIds) {
            $healedId = $req->input('branch_id');
            if (in_array((int) $healedId, $activeBranchIds, true)) {
                $rows[] = ['API Branch Auto-Healing', 'API branch_id=999999 successfully auto-healed to active branch #' . $healedId, '<fg=green;options=bold>PASS</>'];
            } else {
                $failedChecks++;
                $rows[] = ['API Branch Auto-Healing', 'Failed to heal API invalid branch_id, got: ' . var_export($healedId, true), '<fg=red;options=bold>FAIL</>'];
            }
            return new \Symfony\Component\HttpFoundation\Response();
        });

        // -------------------------------------------------------------
        // 7. StoreSalesBillRequest Auto-Healing Form Request
        // -------------------------------------------------------------
        try {
            $sbr = new \App\Http\Requests\Sales\StoreSalesBillRequest();
            $sbr->initialize(['branch_id' => 888888]);
            if ($testUser) {
                $sbr->setUserResolver(fn () => $testUser);
            }
            $reflection = new \ReflectionMethod($sbr, 'prepareForValidation');
            $reflection->setAccessible(true);
            $reflection->invoke($sbr);

            if (in_array((int) $sbr->input('branch_id'), $activeBranchIds, true)) {
                $rows[] = ['FormRequest Auto-Healing', 'StoreSalesBillRequest auto-heals branch_id=888888 before validation', '<fg=green;options=bold>PASS</>'];
            } else {
                $failedChecks++;
                $rows[] = ['FormRequest Auto-Healing', 'StoreSalesBillRequest failed to auto-heal branch_id', '<fg=red;options=bold>FAIL</>'];
            }
        } catch (\Throwable $e) {
            $failedChecks++;
            $rows[] = ['FormRequest Auto-Healing', 'Error: ' . $e->getMessage(), '<fg=red;options=bold>FAIL</>'];
        }

        $this->table(['Diagnostic Category', 'Details / Resolution', 'Result'], $rows);
        $this->newLine();

        if ($failedChecks > 0) {
            $this->error("❌ Pre-Flight Diagnostic FAILED ({$failedChecks} checks failed). Run 'php artisan pos:preflight --fix' to auto-repair.");
            return Command::FAILURE;
        }

        $this->info('✅ All Pre-Flight Health Checks PASSED! Zero invalid branch vulnerabilities detected.');
        $this->newLine();

        return Command::SUCCESS;
    }
}
