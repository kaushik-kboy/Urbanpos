<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\DailySalesSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportDailySalesSummary extends Command
{
    protected $signature = 'import:daily-sales-summary {file? : Specific XLS or CSV file} {--force : Overwrite existing entries}';
    protected $description = 'Import TruePOS 110158 Daily Sales Summary files into daily_sales_summaries';

    public function handle(): int
    {
        $target = $this->argument('file');
        $filesToProcess = [];

        if ($target && is_file($target)) {
            $filesToProcess[] = $target;
        } elseif ($target && is_dir($target)) {
            $filesToProcess = array_merge(
                glob(rtrim($target, '/\\') . '/*.xls') ?: [],
                glob(rtrim($target, '/\\') . '/*.xlsx') ?: [],
                glob(rtrim($target, '/\\') . '/*.csv') ?: []
            );
        } else {
            $dir = base_path('data_files/daily_sales_summary');
            if (is_dir($dir)) {
                $filesToProcess = array_merge(
                    glob($dir . '/*.xls') ?: [],
                    glob($dir . '/*.xlsx') ?: [],
                    glob($dir . '/*.csv') ?: []
                );
            }
        }

        if (empty($filesToProcess)) {
            $this->error("No Daily Sales Summary files found to process.");
            return 1;
        }

        $this->info("==========================================================");
        $this->info("  STARTING TRUEPOS DAILY SALES SUMMARY IMPORT (110158)");
        $this->info("==========================================================");
        $this->info("Found " . count($filesToProcess) . " file(s) to process:");

        $branches = Branch::all();
        $cleanNum = fn($v) => (float) str_replace(',', '', trim((string)$v));

        $totalImported = 0;

        foreach ($filesToProcess as $filePath) {
            $this->info("\nProcessing: " . basename($filePath) . "...");

            $reader = IOFactory::createReaderForFile($filePath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();

            $headerColMap = [];
            $headerRowIdx = null;

            for ($r = 1; $r <= min(15, $highestRow); $r++) {
                $rowCells = [];
                $colIndex = 1;
                while (true) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $val = trim((string)$sheet->getCell($colLetter . $r)->getFormattedValue());
                    $rowCells[$colLetter] = $val;
                    if ($colLetter === $highestCol || $colIndex > 60) break;
                    $colIndex++;
                }

                if (in_array('Store', $rowCells) && (in_array('Bill amount', $rowCells) || in_array('Date', $rowCells))) {
                    $headerRowIdx = $r;
                    foreach ($rowCells as $cLetter => $hName) {
                        if ($hName !== '') {
                            $headerColMap[$hName] = $cLetter;
                        }
                    }
                    break;
                }
            }

            if (! $headerRowIdx) {
                $this->error("Header row not found in " . basename($filePath));
                continue;
            }

            for ($r = $headerRowIdx + 1; $r <= $highestRow; $r++) {
                $get = fn($colName) => isset($headerColMap[$colName])
                    ? trim((string)$sheet->getCell($headerColMap[$colName] . $r)->getFormattedValue())
                    : '';

                $storeName = $get('Store');
                $dateRaw = $get('Date');
                $billAmount = $cleanNum($get('Bill amount'));
                $totalBills = (int) $cleanNum($get('Total bills'));

                if ($storeName === '' || ($billAmount == 0 && $totalBills == 0)) {
                    continue;
                }

                // Parse Date (06-10-2026 -> 2026-10-06)
                $summaryDate = null;
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateRaw, $m)) {
                    $summaryDate = "{$m[3]}-{$m[2]}-{$m[1]}";
                } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateRaw)) {
                    $summaryDate = $dateRaw;
                } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $dateRaw, $m)) {
                    $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
                    $summaryDate = sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
                } else {
                    $summaryDate = date('Y-m-d');
                }

                // Resolve Branch
                $branch = null;
                $upperStore = strtoupper($storeName);
                if (str_contains($upperStore, 'MOTERA')) {
                    $branch = $branches->first(fn($b) => str_contains(strtoupper($b->name), 'MOTERA'));
                } elseif (str_contains($upperStore, 'SERVICES') || str_contains($upperStore, 'SATELLITE') || str_contains($upperStore, 'URBANPETS')) {
                    $branch = $branches->first(fn($b) => str_contains(strtoupper($b->name), 'SERVICES') || str_contains(strtoupper($b->name), 'SATELLITE'));
                }
                if (! $branch) {
                    $branch = $branches->firstWhere('name', $storeName);
                }

                $branchId = $branch ? $branch->id : null;
                $storeId = match (true) {
                    str_contains($upperStore, 'MOTERA') => '32772',
                    default => '225',
                };

                DailySalesSummary::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'summary_date' => $summaryDate,
                    ],
                    [
                        'store_id' => $storeId,
                        'store_name' => $storeName,
                        'bill_amount' => $billAmount,
                        'tax' => $cleanNum($get('Tax')),
                        'disc_amount' => $cleanNum($get('Disc. Amount')),
                        'scheme_amount' => $cleanNum($get('Scheme amount')),
                        'cash' => $cleanNum($get('Cash')),
                        'card' => $cleanNum($get('Card')),
                        'cheque' => $cleanNum($get('Cheque')),
                        'coupon' => $cleanNum($get('Coupon')),
                        'wallet_amt' => $cleanNum($get('Wallet Amt')),
                        'credit' => $cleanNum($get('Credit')),
                        'due' => $cleanNum($get('Due')),
                        'compliment' => $cleanNum($get('Compliment')),
                        'approval' => $cleanNum($get('Approval')),
                        'advance_adjusted' => $cleanNum($get('Advance adjusted')),
                        'rounded_off' => $cleanNum($get('Rounded off')),
                        'profit' => $cleanNum($get('Profit')),
                        'item_discount' => $cleanNum($get('Item discount')),
                        'bill_discount' => $cleanNum($get('Bill discount')),
                        'freight' => $cleanNum($get('Freight')),
                        'total_bills' => $totalBills,
                        'gst_tax_amt' => $cleanNum($get('GST TaxAmt')),
                        'sgst_tax_amt' => $cleanNum($get('SGST TaxAmt')),
                        'cgst_tax_amt' => $cleanNum($get('CGST TaxAmt')),
                        'igst_tax_amt' => $cleanNum($get('IGST TaxAmt')),
                        'gst_cess_amt' => $cleanNum($get('GST Cess Amt')),
                        'redeemed_point' => $cleanNum($get('Redeemed Point')),
                    ]
                );

                $this->info("  -> Saved: {$storeName} | Date: {$summaryDate} | Bills: {$totalBills} | Amount: ₹ " . number_format($billAmount, 2));
                $totalImported++;
            }
        }

        $this->info("\n==========================================================");
        $this->info("  TOTAL DAILY SALES SUMMARIES IMPORTED: {$totalImported}");
        $this->info("==========================================================");

        return 0;
    }
}
