use App\Services\GST\Gstr1ReportService;
use App\Exports\GstSalesTaxwiseExport;
use App\Exports\GstPurchaseSummaryInvoiceWiseExport;
use Illuminate\Support\Facades\DB;

try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5N: INDEPENDENT RECONCILIATION AUDIT ===\n";

    $tolerance = 0.01;
    $results = [];

    // 1. PURCHASE TOTALS
    // Expected:
    // PI 1 (id=30): 50@50(0%) + 50@100(5%)+tax(250) + 50@200(18%)+tax(1800) = 2500 + 5250 + 11800 = 19,550.00
    // PI 2 (id=31): 20@200(18%)+tax(720) + freight(300) = 4000 + 720 + 300 = 5,020.00
    // PI 3 (id=32): 30@100(5%)+tax(150) + 20@200(18%)+tax(720) = 3150 + 4720 = 7,870.00
    // Total Expected Purchases = 19550 + 5020 + 7870 = 32,440.00
    $expPurchTotal = 32440.00;
    $actPurchTotal = (float) DB::table('purchase_invoices')->whereIn('id', [30, 31, 32])->sum('total');
    $diffPurch = abs($actPurchTotal - $expPurchTotal);
    $results['Purchase totals'] = [
        'expected' => $expPurchTotal,
        'actual' => $actPurchTotal,
        'difference' => $diffPurch,
        'status' => $diffPurch <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 2. SALES TOTALS
    // Expected:
    // SB 1 (id=45): 4000.00
    // SB 2 (id=46): 2500.00
    // SB 3 (id=47): 1850.00
    // SB 4 (id=48): 4000.00
    // SB 5 (id=49): 260000.00
    // Total Expected Sales = 4000 + 2500 + 1850 + 4000 + 260000 = 272,350.00
    $expSalesTotal = 272350.00;
    $actSalesTotal = (float) DB::table('sales_bills')->whereIn('id', [45, 46, 47, 48, 49])->sum('total');
    $diffSales = abs($actSalesTotal - $expSalesTotal);
    $results['Sales totals'] = [
        'expected' => $expSalesTotal,
        'actual' => $actSalesTotal,
        'difference' => $diffSales,
        'status' => $diffSales <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 3. PURCHASE RETURN
    // Expected:
    // PR 1 (id=19): 5@200 + 18% tax (180) = 1180.00
    // PR 2 (id=20): 10@200 + 18% tax (360) = 2360.00
    // Total = 1180 + 2360 = 3,540.00
    $expPrTotal = 3540.00;
    $actPrTotal = (float) DB::table('purchase_returns')->whereIn('id', [19, 20])->sum('total');
    $diffPr = abs($actPrTotal - $expPrTotal);
    $results['Purchase Return'] = [
        'expected' => $expPrTotal,
        'actual' => $actPrTotal,
        'difference' => $diffPr,
        'status' => $diffPr <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 4. SALES RETURN
    // Expected:
    // SR 1 (id=18): 3@120 = 360.00
    // SR 2 (id=19): 4@120 = 480.00
    // SR 3 (id=20): 2@250 = 500.00
    // Total = 360 + 480 + 500 = 1,340.00
    $expSrTotal = 1340.00;
    $actSrTotal = (float) DB::table('sales_returns')->whereIn('id', [18, 19, 20])->sum('total');
    $diffSr = abs($actSrTotal - $expSrTotal);
    $results['Sales Return'] = [
        'expected' => $expSrTotal,
        'actual' => $actSrTotal,
        'difference' => $diffSr,
        'status' => $diffSr <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 5. STOCK
    // Expected Total units across all 3 items in both branches = 30 + 10 + 62 + 0 + 32 + 0 = 134 units
    $expStock = 134.00;
    $actStock = (float) DB::table('item_stocks')->whereIn('item_id', [111, 112, 113])->sum('quantity');
    $diffStock = abs($actStock - $expStock);
    $results['Stock'] = [
        'expected' => $expStock,
        'actual' => $actStock,
        'difference' => $diffStock,
        'status' => $diffStock <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 6. GST SALES
    // Expected Tax on 5 sales bills:
    // SB 1: 438.50
    // SB 2: 381.36
    // SB 3: 219.25
    // SB 4: 438.50
    // SB 5: 39661.02
    // Total Expected GST Sales = 438.50 + 381.36 + 219.25 + 438.50 + 39661.02 = 41,138.63
    $expGstSales = 41138.63;
    $actGstSales = (float) DB::table('sales_bills')->whereIn('id', [45, 46, 47, 48, 49])->sum('total_gst');
    $diffGstSales = abs($actGstSales - $expGstSales);
    $results['GST Sales'] = [
        'expected' => $expGstSales,
        'actual' => $actGstSales,
        'difference' => $diffGstSales,
        'status' => $diffGstSales <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 7. GST PURCHASE
    // Expected Tax on 3 purchase invoices:
    // PI 1: 2050.00
    // PI 2: 720.00
    // PI 3: 870.00
    // Total Expected GST Purchase = 2050 + 720 + 870 = 3,640.00
    $expGstPurch = 3640.00;
    $actGstPurch = (float) DB::table('purchase_invoices')->whereIn('id', [30, 31, 32])->sum('total_gst');
    $diffGstPurch = abs($actGstPurch - $expGstPurch);
    $results['GST Purchase'] = [
        'expected' => $expGstPurch,
        'actual' => $actGstPurch,
        'difference' => $diffGstPurch,
        'status' => $diffGstPurch <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 8. GST SALES EXCEL
    $today = date('Y-m-d');
    $salesExportRows = (new GstSalesTaxwiseExport($today, $today, 1))->query()->get();
    $actExcelSalesTotal = (float) $salesExportRows->sum('total_amount');
    $diffExcelSales = abs($actExcelSalesTotal - $expSalesTotal);
    $results['GST Sales Excel'] = [
        'expected' => $expSalesTotal,
        'actual' => $actExcelSalesTotal,
        'difference' => $diffExcelSales,
        'status' => $diffExcelSales <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 9. GST PURCHASE EXCEL
    $purchExportRows = (new GstPurchaseSummaryInvoiceWiseExport($today, $today, 1))->query()->get();
    $actExcelPurchTotal = (float) $purchExportRows->sum('total_amount');
    $diffExcelPurch = abs($actExcelPurchTotal - $expPurchTotal);
    $results['GST Purchase Excel'] = [
        'expected' => $expPurchTotal,
        'actual' => $actExcelPurchTotal,
        'difference' => $diffExcelPurch,
        'status' => $diffExcelPurch <= $tolerance ? 'PASS' : 'FAIL',
    ];

    // 10. GSTR-1
    // Total GSTR-1 Outward Tax = B2B (819.86) + B2CL (39661.02) + B2CS (657.75) = 41,138.63
    $gstr1 = new Gstr1ReportService($today, $today);
    $gstr1Summary = $gstr1->summary();
    $actGstr1Tax = $gstr1Summary['b2b']['tax'] + $gstr1Summary['b2cl']['tax'] + $gstr1Summary['b2cs']['tax'];
    $diffGstr1 = abs($actGstr1Tax - $expGstSales);
    $results['GSTR-1'] = [
        'expected' => $expGstSales,
        'actual' => $actGstr1Tax,
        'difference' => $diffGstr1,
        'status' => $diffGstr1 <= $tolerance ? 'PASS' : 'FAIL',
    ];

    echo "\n" . str_pad("Area", 22) . str_pad("Expected", 14) . str_pad("Actual", 14) . str_pad("Difference", 14) . "Status\n";
    echo str_repeat("-", 72) . "\n";
    foreach ($results as $area => $r) {
        echo str_pad($area, 22) . 
             str_pad(number_format($r['expected'], 2), 14) . 
             str_pad(number_format($r['actual'], 2), 14) . 
             str_pad(number_format($r['difference'], 2), 14) . 
             $r['status'] . "\n";
    }

    echo "\n=== PHASE 5N COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
