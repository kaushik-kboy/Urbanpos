use App\Services\GST\Gstr1ReportService;
use App\Exports\GstSalesTaxwiseExport;
use App\Exports\GstPurchaseSummaryInvoiceWiseExport;

try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5K: PERIOD FILTERING AUDIT ===\n";

    // Test 1: Single-day range (Today: 2026-09-28)
    $today = date('Y-m-d');
    $gstr1Today = new Gstr1ReportService($today, $today);
    $b2bToday = $gstr1Today->b2b();
    $salesExportToday = (new GstSalesTaxwiseExport($today, $today, 1))->query()->count();
    $purchExportToday = (new GstPurchaseSummaryInvoiceWiseExport($today, $today, 1))->query()->count();

    echo "1. Single-Day Range (Today: {$today}):\n";
    echo "  GSTR-1 B2B count: {$b2bToday['count']} (Expected: 2)\n";
    echo "  Sales Export rows: {$salesExportToday} (Expected: 5)\n";
    echo "  Purchase Export rows: {$purchExportToday} (Expected: 3)\n";

    // Test 2: Previous period (e.g., 2026-08-01 to 2026-08-31)
    $prevFrom = '2026-08-01';
    $prevTo = '2026-08-31';
    $gstr1Prev = new Gstr1ReportService($prevFrom, $prevTo);
    $b2bPrev = $gstr1Prev->b2b();
    $salesExportPrev = (new GstSalesTaxwiseExport($prevFrom, $prevTo, 1))->query()->count();
    $purchExportPrev = (new GstPurchaseSummaryInvoiceWiseExport($prevFrom, $prevTo, 1))->query()->count();

    echo "\n2. Previous Period ({$prevFrom} to {$prevTo}):\n";
    echo "  GSTR-1 B2B count: {$b2bPrev['count']}\n";
    echo "  Sales Export rows: {$salesExportPrev}\n";
    echo "  Purchase Export rows: {$purchExportPrev}\n";

    // Verify Phase 5 documents do NOT leak into previous period
    $leakedP5Sales = (new GstSalesTaxwiseExport($prevFrom, $prevTo, 1))->query()
        ->whereIn('sales_bills.id', [45, 46, 47, 48, 49])
        ->count();
    $leakedP5Purch = (new GstPurchaseSummaryInvoiceWiseExport($prevFrom, $prevTo, 1))->query()
        ->whereIn('pi.id', [30, 31, 32])
        ->count();

    echo "  Leaked Phase 5 Sales Bills in previous period: {$leakedP5Sales} (Expected: 0)\n";
    echo "  Leaked Phase 5 Purchases in previous period: {$leakedP5Purch} (Expected: 0)\n";

    if ($leakedP5Sales === 0 && $leakedP5Purch === 0) {
        echo "CHECK PASS: Zero date leakage across periods.\n";
    } else {
        echo "CHECK FAIL: Leakage detected!\n";
    }

    echo "\n=== PHASE 5K COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
