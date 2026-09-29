echo "=== INVENTORY RECONCILIATION EQUATION ===\n";

$items = [
    111 => ['code' => 'P5-ITM-0GST',  'expected_b1' => 30.0, 'expected_b2' => 10.0],
    112 => ['code' => 'P5-ITM-5GST',  'expected_b1' => 62.0, 'expected_b2' => 0.0],
    113 => ['code' => 'P5-ITM-18GST', 'expected_b1' => 32.0, 'expected_b2' => 0.0],
];

foreach ($items as $itemId => $info) {
    // Branch 1
    $actualB1 = (float) (DB::table('item_stocks')->where('branch_id', 1)->where('item_id', $itemId)->value('quantity') ?? 0);
    $diffB1 = $actualB1 - $info['expected_b1'];
    $statusB1 = abs($diffB1) < 0.001 ? 'PASS' : 'FAIL';
    echo "Item {$info['code']} (id={$itemId}) Branch 1:\n";
    echo "  Expected: {$info['expected_b1']} | Actual: {$actualB1} | Diff: {$diffB1} | Status: {$statusB1}\n";

    // Branch 2
    $actualB2 = (float) (DB::table('item_stocks')->where('branch_id', 2)->where('item_id', $itemId)->value('quantity') ?? 0);
    $diffB2 = $actualB2 - $info['expected_b2'];
    $statusB2 = abs($diffB2) < 0.001 ? 'PASS' : 'FAIL';
    echo "Item {$info['code']} (id={$itemId}) Branch 2:\n";
    echo "  Expected: {$info['expected_b2']} | Actual: {$actualB2} | Diff: {$diffB2} | Status: {$statusB2}\n";

    // Stock Ledger Running Balance Check
    $lastLedgerB1 = DB::table('stock_ledger')->where('branch_id', 1)->where('item_id', $itemId)->latest('id')->first();
    echo "  Branch 1 Last Ledger Running Balance Qty: " . ($lastLedgerB1->running_balance_qty ?? 'N/A') . "\n";
    if ($lastLedgerB1 && abs($lastLedgerB1->running_balance_qty - $actualB1) < 0.001) {
        echo "  Branch 1 Ledger vs Stock Table: MATCH\n";
    } else {
        echo "  Branch 1 Ledger vs Stock Table: MISMATCH\n";
    }
}
