try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5M: DATABASE INTEGRITY AUDIT ===\n";

    $violations = 0;

    // 1. Negative stock
    $negStock = DB::table('item_stocks')->where('quantity', '<', 0)->count();
    echo "1. Negative Stock: {$negStock} rows\n";
    if ($negStock > 0) $violations++;

    // 2. Orphan purchase invoice lines
    $orphanPil = DB::table('purchase_invoice_items')
        ->whereNotIn('purchase_invoice_id', DB::table('purchase_invoices')->pluck('id'))
        ->count();
    echo "2. Orphan Purchase Invoice Items: {$orphanPil}\n";
    if ($orphanPil > 0) $violations++;

    // 3. Orphan sales bill lines
    $orphanSbl = DB::table('sales_bill_items')
        ->whereNotIn('sales_bill_id', DB::table('sales_bills')->pluck('id'))
        ->count();
    echo "3. Orphan Sales Bill Items: {$orphanSbl}\n";
    if ($orphanSbl > 0) $violations++;

    // 4. Orphan return lines
    $orphanSrl = DB::table('sales_return_items')
        ->whereNotIn('sales_return_id', DB::table('sales_returns')->pluck('id'))
        ->count();
    $orphanPrl = DB::table('purchase_return_items')
        ->whereNotIn('purchase_return_id', DB::table('purchase_returns')->pluck('id'))
        ->count();
    echo "4. Orphan Sales Return Items: {$orphanSrl} | Orphan Purchase Return Items: {$orphanPrl}\n";
    if ($orphanSrl > 0 || $orphanPrl > 0) $violations++;

    // 5. Duplicate document numbers
    $dupSb = DB::table('sales_bills')->select('bill_number', DB::raw('count(*) as c'))->groupBy('bill_number')->having('c', '>', 1)->count();
    $dupPi = DB::table('purchase_invoices')->select('invoice_number', DB::raw('count(*) as c'))->groupBy('invoice_number')->having('c', '>', 1)->count();
    $dupSr = DB::table('sales_returns')->select('return_number', DB::raw('count(*) as c'))->groupBy('return_number')->having('c', '>', 1)->count();
    $dupPr = DB::table('purchase_returns')->select('return_number', DB::raw('count(*) as c'))->groupBy('return_number')->having('c', '>', 1)->count();
    echo "5. Duplicate Document Numbers: Sales Bills={$dupSb} | Purchase Invoices={$dupPi} | Sales Returns={$dupSr} | Purchase Returns={$dupPr}\n";
    if ($dupSb > 0 || $dupPi > 0 || $dupSr > 0 || $dupPr > 0) $violations++;

    // 6. Duplicate posting keys
    $dupKeys = DB::table('sales_bills')->whereNotNull('posting_key')->select('posting_key', DB::raw('count(*) as c'))->groupBy('posting_key')->having('c', '>', 1)->count();
    echo "6. Duplicate Posting Keys: {$dupKeys}\n";
    if ($dupKeys > 0) $violations++;

    // 7. Inconsistent document totals (lines sum vs header total) for Phase 5 bills
    $inconsistentTotals = 0;
    foreach ([45, 46, 47, 48, 49] as $sbId) {
        $sb = DB::table('sales_bills')->find($sbId);
        $linesTotal = DB::table('sales_bill_items')->where('sales_bill_id', $sbId)->sum('net_amount');
        if (abs($linesTotal - $sb->total) > 0.02) {
            echo "  Mismatch on SB {$sb->bill_number}: Header={$sb->total}, Lines={$linesTotal}\n";
            $inconsistentTotals++;
        }
    }
    echo "7. Inconsistent Totals on Phase 5 Sales Bills: {$inconsistentTotals}\n";
    if ($inconsistentTotals > 0) $violations++;

    // 8. Stock ledger running balance vs item stock table for Phase 5 items
    $ledgerMismatches = 0;
    foreach ([111, 112, 113] as $itId) {
        $stk = (float) DB::table('item_stocks')->where('branch_id', 1)->where('item_id', $itId)->value('quantity');
        $lastRunning = (float) DB::table('stock_ledger')->where('branch_id', 1)->where('item_id', $itId)->latest('id')->value('running_balance_qty');
        if (abs($stk - $lastRunning) > 0.001) {
            echo "  Mismatch on item {$itId}: item_stocks={$stk}, ledger={$lastRunning}\n";
            $ledgerMismatches++;
        }
    }
    echo "8. Stock Ledger Running Balance vs Item Stocks: {$ledgerMismatches}\n";
    if ($ledgerMismatches > 0) $violations++;

    echo "\nTotal Integrity Violations: {$violations}\n";
    if ($violations === 0) {
        echo "CHECK PASS: 0 unexpected integrity violations found across entire database.\n";
    } else {
        echo "CHECK FAIL: Violations detected!\n";
    }

    echo "\n=== PHASE 5M COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
