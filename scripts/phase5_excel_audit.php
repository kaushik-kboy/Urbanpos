use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GstSalesTaxwiseExport;
use App\Exports\GstPurchaseSummaryInvoiceWiseExport;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5H & 5I: REAL EXCEL GENERATION & AUDIT ===\n";

    $today = date('Y-m-d');
    $salesExportFile = \Illuminate\Support\Facades\Storage::disk('local')->path('p5_gst_sales_taxwise.xlsx');
    $purchaseExportFile = \Illuminate\Support\Facades\Storage::disk('local')->path('p5_gst_purchase_summary.xlsx');

    // Remove if exists
    if (file_exists($salesExportFile)) @unlink($salesExportFile);
    if (file_exists($purchaseExportFile)) @unlink($purchaseExportFile);

    // 1. Generate Real Excel for GST Sales Taxwise
    echo "\n--- 1. Generating GST Sales Taxwise Excel ---\n";
    Excel::store(new GstSalesTaxwiseExport($today, $today, 1), 'p5_gst_sales_taxwise.xlsx');
    echo "Saved to: {$salesExportFile} (size: " . filesize($salesExportFile) . " bytes)\n";

    $ssSales = IOFactory::load($salesExportFile);
    $sheetSales = $ssSales->getActiveSheet();
    $highestColSales = $sheetSales->getHighestColumn();
    $highestColIdxSales = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColSales);
    $highestRowSales = $sheetSales->getHighestRow();

    echo "Sales Sheet Title: " . $sheetSales->getTitle() . "\n";
    echo "Columns: {$highestColIdxSales} (Expected: 26)\n";
    echo "Rows: {$highestRowSales} (Header + Data rows)\n";

    // Verify 26 Headers
    $expectedSalesHeaders = [
        'Bill No', 'Bill Date', 'Customer Name', 'GST No.', 'State Name',
        'taxable_0_amount', 'taxable_5_amount', 'taxable_18_amount',
        'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
        'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
        'Total amount',
        'Inv Noble_0_amount', 'taxable_5_amount', 'taxable_18_amount',
        'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
        'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
        'Total amount', 'Inv No',
    ];

    $actualSalesHeaders = [];
    for ($c = 1; $c <= 26; $c++) {
        $actualSalesHeaders[] = $sheetSales->getCellByColumnAndRow($c, 1)->getValue();
    }

    $salesHeaderMismatch = false;
    for ($i = 0; $i < 26; $i++) {
        if ($actualSalesHeaders[$i] !== $expectedSalesHeaders[$i]) {
            echo "Header mismatch at col " . ($i + 1) . ": Expected '{$expectedSalesHeaders[$i]}', got '{$actualSalesHeaders[$i]}'\n";
            $salesHeaderMismatch = true;
        }
    }
    if (!$salesHeaderMismatch) {
        echo "CHECK PASS: All 26 Sales Excel column headers match the exact client spec in order.\n";
    }

    // Print all rows for Phase 5 bills
    echo "\nSales Excel Data Rows:\n";
    for ($r = 2; $r <= $highestRowSales; $r++) {
        $billNo = $sheetSales->getCellByColumnAndRow(1, $r)->getValue();
        $date = $sheetSales->getCellByColumnAndRow(2, $r)->getFormattedValue();
        $cust = $sheetSales->getCellByColumnAndRow(3, $r)->getValue();
        $gstin = $sheetSales->getCellByColumnAndRow(4, $r)->getValue();
        $tax0 = $sheetSales->getCellByColumnAndRow(6, $r)->getValue();
        $tax5 = $sheetSales->getCellByColumnAndRow(7, $r)->getValue();
        $tax18 = $sheetSales->getCellByColumnAndRow(8, $r)->getValue();
        $cgst5 = $sheetSales->getCellByColumnAndRow(11, $r)->getValue();
        $sgst5 = $sheetSales->getCellByColumnAndRow(10, $r)->getValue();
        $igst18 = $sheetSales->getCellByColumnAndRow(12, $r)->getValue();
        $cgst18 = $sheetSales->getCellByColumnAndRow(13, $r)->getValue();
        $sgst18 = $sheetSales->getCellByColumnAndRow(14, $r)->getValue();
        $tot = $sheetSales->getCellByColumnAndRow(15, $r)->getValue();
        $invNoEnd = $sheetSales->getCellByColumnAndRow(26, $r)->getValue();
        echo "  Row {$r}: Bill={$billNo} | Cust={$cust} | GSTIN={$gstin} | Taxable0={$tax0} | Taxable5={$tax5} | Taxable18={$tax18} | CGST18={$cgst18} | IGST18={$igst18} | Total={$tot} | Col26={$invNoEnd}\n";
    }


    // 2. Generate Real Excel for GST Purchase Summary
    echo "\n--- 2. Generating GST Purchase Summary Excel ---\n";
    Excel::store(new GstPurchaseSummaryInvoiceWiseExport($today, $today, 1), 'p5_gst_purchase_summary.xlsx');
    echo "Saved to: {$purchaseExportFile} (size: " . filesize($purchaseExportFile) . " bytes)\n";

    $ssPurch = IOFactory::load($purchaseExportFile);
    $sheetPurch = $ssPurch->getActiveSheet();
    $highestColPurch = $sheetPurch->getHighestColumn();
    $highestColIdxPurch = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColPurch);
    $highestRowPurch = $sheetPurch->getHighestRow();

    echo "Purchase Sheet Title: " . $sheetPurch->getTitle() . "\n";
    echo "Columns: {$highestColIdxPurch} (Expected: 16)\n";
    echo "Rows: {$highestRowPurch} (Header + Data rows)\n";

    // Verify 16 Headers
    $expectedPurchHeaders = [
        'Inv No', 'Inv date', 'Supplier name', 'GST No.', 'State Name',
        'Taxable amount', 'Purchase tax %',
        'SGST Perc', 'SGST TaxAmt',
        'CGST Perc', 'CGST TaxAmt',
        'IGST Perc', 'IGST TaxAmt',
        'Total amount', 'Freight charges', 'TCS Amt',
    ];

    $actualPurchHeaders = [];
    for ($c = 1; $c <= 16; $c++) {
        $actualPurchHeaders[] = $sheetPurch->getCellByColumnAndRow($c, 1)->getValue();
    }

    $purchHeaderMismatch = false;
    for ($i = 0; $i < 16; $i++) {
        if ($actualPurchHeaders[$i] !== $expectedPurchHeaders[$i]) {
            echo "Header mismatch at col " . ($i + 1) . ": Expected '{$expectedPurchHeaders[$i]}', got '{$actualPurchHeaders[$i]}'\n";
            $purchHeaderMismatch = true;
        }
    }
    if (!$purchHeaderMismatch) {
        echo "CHECK PASS: All 16 Purchase Excel column headers match the exact client spec in order.\n";
    }

    // Print all rows for Phase 5 purchases
    echo "\nPurchase Excel Data Rows:\n";
    for ($r = 2; $r <= $highestRowPurch; $r++) {
        $invNo = $sheetPurch->getCellByColumnAndRow(1, $r)->getValue();
        $date = $sheetPurch->getCellByColumnAndRow(2, $r)->getFormattedValue();
        $supp = $sheetPurch->getCellByColumnAndRow(3, $r)->getValue();
        $gstin = $sheetPurch->getCellByColumnAndRow(4, $r)->getValue();
        $taxable = $sheetPurch->getCellByColumnAndRow(6, $r)->getValue();
        $rates = $sheetPurch->getCellByColumnAndRow(7, $r)->getValue();
        $sgstAmt = $sheetPurch->getCellByColumnAndRow(9, $r)->getValue();
        $cgstAmt = $sheetPurch->getCellByColumnAndRow(11, $r)->getValue();
        $igstAmt = $sheetPurch->getCellByColumnAndRow(13, $r)->getValue();
        $tot = $sheetPurch->getCellByColumnAndRow(14, $r)->getValue();
        $freight = $sheetPurch->getCellByColumnAndRow(15, $r)->getValue();
        echo "  Row {$r}: Inv={$invNo} | Supp={$supp} | GSTIN={$gstin} | Taxable={$taxable} | Rates={$rates} | CGST={$cgstAmt} | SGST={$sgstAmt} | IGST={$igstAmt} | Freight={$freight} | Total={$tot}\n";
    }

    echo "\n=== PHASE 5H & 5I COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
