use App\Services\GST\Gstr1ReportService;

try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5J: GSTR-1 12-SECTION AUDIT ===\n";

    $today = date('Y-m-d');
    $service = new Gstr1ReportService($today, $today);
    $summary = $service->summary();

    // 1. HSN B2B
    echo "\n1. HSN B2B (REAL DATA):\n";
    echo "  Taxable: {$summary['hsnB2b']['taxable']} | Tax: {$summary['hsnB2b']['tax']} | Groups: " . count($summary['hsnB2b']['rows']) . "\n";
    foreach ($summary['hsnB2b']['rows'] as $r) {
        echo "    HSN={$r['hsn']} | Rate={$r['rate']}% | Qty={$r['qty']} | Taxable={$r['taxable']} | Tax=" . ($r['igst']+$r['cgst']+$r['sgst']) . "\n";
    }

    // 2. HSN B2C
    echo "\n2. HSN B2C (REAL DATA):\n";
    echo "  Taxable: {$summary['hsnB2c']['taxable']} | Tax: {$summary['hsnB2c']['tax']} | Groups: " . count($summary['hsnB2c']['rows']) . "\n";
    foreach ($summary['hsnB2c']['rows'] as $r) {
        echo "    HSN={$r['hsn']} | Rate={$r['rate']}% | Qty={$r['qty']} | Taxable={$r['taxable']} | Tax=" . ($r['igst']+$r['cgst']+$r['sgst']) . "\n";
    }

    // 3. B2B
    echo "\n3. B2B Invoices (REAL DATA):\n";
    echo "  Count: {$summary['b2b']['count']} | Taxable: {$summary['b2b']['taxable']} | Tax: {$summary['b2b']['tax']} | Total: {$summary['b2b']['total']}\n";
    foreach ($summary['b2b']['rows'] as $r) {
        echo "    Ref={$r['ref']} | Customer={$r['customer']} | GSTIN={$r['gstin']} | Taxable={$r['taxable']} | CGST={$r['cgst']} | SGST={$r['sgst']} | IGST={$r['igst']} | Total={$r['total']}\n";
    }

    // 4. B2CL
    echo "\n4. B2CL Invoices (REAL DATA >= 2.5L Interstate Unreg):\n";
    echo "  Count: {$summary['b2cl']['count']} | Taxable: {$summary['b2cl']['taxable']} | Tax: {$summary['b2cl']['tax']} | Total: {$summary['b2cl']['total']}\n";
    foreach ($summary['b2cl']['rows'] as $r) {
        echo "    Ref={$r['ref']} | POS={$r['pos']} | Taxable={$r['taxable']} | IGST={$r['igst']} | Total={$r['total']}\n";
    }

    // 5. Exports
    echo "\n5. Exports (UNSUPPORTED):\n";
    echo "  Supported: " . ($summary['export']['supported'] ? 'YES' : 'NO') . " | Limitation: {$summary['export']['limitation']}\n";

    // 6. B2CS
    echo "\n6. B2CS (REAL DATA < 2.5L or Intra Unreg):\n";
    echo "  Count: {$summary['b2cs']['count']} | Taxable: {$summary['b2cs']['taxable']} | Tax: {$summary['b2cs']['tax']}\n";
    foreach ($summary['b2cs']['rows'] as $r) {
        echo "    POS={$r['pos']} | Rate={$r['rate']}% | Taxable={$r['taxable']} | CGST={$r['cgst']} | SGST={$r['sgst']} | IGST={$r['igst']}\n";
    }

    // 7. CDNR
    echo "\n7. CDNR (REAL DATA - Registered Customer Credit Notes):\n";
    echo "  Count: {$summary['cdnr']['count']} | Taxable: {$summary['cdnr']['value']} | Tax: {$summary['cdnr']['tax']}\n";
    foreach ($summary['cdnr']['rows'] as $r) {
        echo "    Ref={$r['ref']} | OrigBill={$r['original_invoice']} | Customer={$r['customer']} | GSTIN={$r['gstin']} | Taxable={$r['taxable']} | CGST={$r['cgst']} | SGST={$r['sgst']} | Total={$r['total']}\n";
    }

    // 8. CDNUR
    echo "\n8. CDNUR (REAL DATA - Unregistered Customer Credit Notes):\n";
    echo "  Count: {$summary['cdnur']['count']} | Taxable: {$summary['cdnur']['value']} | Tax: {$summary['cdnur']['tax']}\n";
    foreach ($summary['cdnur']['rows'] as $r) {
        echo "    Ref={$r['ref']} | OrigBill={$r['original_invoice']} | Customer={$r['customer']} | Taxable={$r['taxable']} | CGST={$r['cgst']} | SGST={$r['sgst']} | Total={$r['total']}\n";
    }

    // 9. Nil Rated
    echo "\n9. Nil Rated / Exempt (PARTIAL DATA):\n";
    echo "  Combined Amount: {$summary['nil']['combined_amount']} | Supported Breakdown: " . ($summary['nil']['supported_breakdown'] ? 'YES' : 'NO') . "\n";
    echo "  Limitation: {$summary['nil']['limitation']}\n";

    // 10. Advance Received
    echo "\n10. Advance Received (UNSUPPORTED):\n";
    echo "  Supported: " . ($summary['advRec']['supported'] ? 'YES' : 'NO') . " | Limitation: {$summary['advRec']['limitation']}\n";

    // 11. Advance Adjusted
    echo "\n11. Advance Adjusted (UNSUPPORTED):\n";
    echo "  Supported: " . ($summary['advAdj']['supported'] ? 'YES' : 'NO') . " | Limitation: {$summary['advAdj']['limitation']}\n";

    // 12. Documents Issued
    echo "\n12. Documents Issued (REAL DATA):\n";
    echo "  Total Issued: {$summary['docs']['total_issued']} | Cancelled: {$summary['docs']['total_cancelled']}\n";
    foreach ($summary['docs']['rows'] as $r) {
        echo "    Series={$r['label']} | First={$r['first']} | Last={$r['last']} | Total={$r['total']} | Cancelled={$r['cancelled']} | Net={$r['net_issued']}\n";
    }

    echo "\n=== PHASE 5J COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
