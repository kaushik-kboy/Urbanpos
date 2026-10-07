<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ClosingStock;
use App\Models\DailySalesSummary;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class GofrugalSyncController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->input('date', Carbon::yesterday()->format('Y-m-d'));

        // Check storage files for this date
        $syncDir = storage_path("app/gofrugal_sync/{$selectedDate}");
        $availableFiles = [];
        if (is_dir($syncDir)) {
            foreach (scandir($syncDir) as $f) {
                if ($f !== '.' && $f !== '..' && str_ends_with(strtolower($f), '.csv')) {
                    $availableFiles[] = [
                        'name' => $f,
                        'size' => filesize($syncDir . DIRECTORY_SEPARATOR . $f),
                        'modified' => date('Y-m-d H:i:s', filemtime($syncDir . DIRECTORY_SEPARATOR . $f)),
                    ];
                }
            }
        }

        // Live database stats for this date
        $stats = [
            'closing_stocks_count' => ClosingStock::whereDate('as_on_date', $selectedDate)->count(),
            'closing_stocks_val' => ClosingStock::whereDate('as_on_date', $selectedDate)->sum('closing_stock_amount'),
            'daily_sales_bills' => DailySalesSummary::whereDate('summary_date', $selectedDate)->sum('total_bills'),
            'daily_sales_total' => DailySalesSummary::whereDate('summary_date', $selectedDate)->sum('bill_amount'),
            'detailed_bills_count' => SalesBill::whereDate('bill_date', $selectedDate)->count(),
            'purchases_count' => PurchaseInvoice::whereDate('invoice_date', $selectedDate)->count(),
            'purchases_amount' => PurchaseInvoice::whereDate('invoice_date', $selectedDate)->sum('total_amount'),
            'transfers_count' => StockTransfer::whereDate('transfer_date', $selectedDate)->count(),
            'transfers_val' => StockTransfer::whereDate('transfer_date', $selectedDate)->sum('total_cost_value'),
        ];

        return view('tools.gofrugal-sync', compact('selectedDate', 'availableFiles', 'stats'));
    }

    public function triggerSync(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->input('date');
        $skipExtract = $request->boolean('skip_extract', false);
        $modules = $request->input('modules', 'all');

        $outputBuffer = [];

        // 1. Extraction via Python Playwright if requested
        if (!$skipExtract) {
            $pythonScript = base_path('scripts/gofrugal_sync_engine.py');
            $outDir = storage_path("app/gofrugal_sync/{$date}");

            $cmd = ['python', $pythonScript, '--date', $date, '--out-dir', $outDir];
            $process = new Process($cmd);
            $process->setTimeout(600);
            $process->run();

            $outputBuffer[] = "=== EXTRACTOR OUTPUT ===";
            $outputBuffer[] = $process->getOutput();
            if ($process->getErrorOutput()) {
                $outputBuffer[] = "Errors/Warnings: " . $process->getErrorOutput();
            }
        }

        // 2. Import into UrbanPOS
        $outDir = storage_path("app/gofrugal_sync/{$date}");
        $exitCode = Artisan::call('gofrugal:import', [
            'date' => $date,
            '--path' => $outDir,
            '--modules' => $modules,
        ]);

        $outputBuffer[] = "=== IMPORTER OUTPUT ===";
        $outputBuffer[] = Artisan::output();

        return response()->json([
            'success' => $exitCode === 0,
            'date' => $date,
            'log' => implode("\n", $outputBuffer),
        ]);
    }
}
