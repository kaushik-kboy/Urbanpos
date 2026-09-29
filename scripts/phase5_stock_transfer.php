try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    $controller = app(\App\Http\Controllers\Inventory\StockTransferController::class);

    echo "=== PHASE 5G: STOCK TRANSFER ===\n";

    $itemId = 111; // P5 Fresh Milk 0%
    $fromBranch = 1;
    $toBranch = 2;

    $stkFromBefore = (float) (DB::table('item_stocks')->where('branch_id', $fromBranch)->where('item_id', $itemId)->value('quantity') ?? 0);
    $stkToBefore   = (float) (DB::table('item_stocks')->where('branch_id', $toBranch)->where('item_id', $itemId)->value('quantity') ?? 0);

    echo "BEFORE DISPATCH:\n";
    echo "  Branch {$fromBranch} Stock: {$stkFromBefore}\n";
    echo "  Branch {$toBranch} Stock: {$stkToBefore}\n";

    // Step 1: Dispatch 10 units from Branch 1 to Branch 2
    echo "\n--- Step 1: Dispatch Transfer Out (10 units) ---\n";
    $transferData = [
        'transfer_date' => date('Y-m-d'),
        'from_branch_id' => $fromBranch,
        'to_branch_id' => $toBranch,
        'remarks' => 'Phase 5 Transfer Test',
        'posting_key' => 'P5_TRANSFER_' . time(),
        'items' => [
            [
                'item_id' => $itemId,
                'qty' => 10,
            ]
        ]
    ];

    $reqDispatch = \Illuminate\Http\Request::create('/inventory/stock-transfers', 'POST', $transferData);
    app()->instance('request', $reqDispatch);
    $controller->store($reqDispatch);

    $st = \App\Models\StockTransfer::where('posting_key', $transferData['posting_key'])->first();
    echo "Transfer Created: id={$st->id} | number={$st->transfer_number} | status={$st->status}\n";

    $stkFromDispatched = (float) (DB::table('item_stocks')->where('branch_id', $fromBranch)->where('item_id', $itemId)->value('quantity') ?? 0);
    $stkToDispatched   = (float) (DB::table('item_stocks')->where('branch_id', $toBranch)->where('item_id', $itemId)->value('quantity') ?? 0);

    echo "AFTER DISPATCH (Awaiting Receipt):\n";
    echo "  Branch {$fromBranch} Stock: {$stkFromDispatched} (Expected: " . ($stkFromBefore - 10) . ")\n";
    echo "  Branch {$toBranch} Stock: {$stkToDispatched} (Expected: {$stkToBefore} - NOT yet increased)\n";

    if ($stkFromDispatched == ($stkFromBefore - 10) && $stkToDispatched == $stkToBefore) {
        echo "CHECK PASS: Source decreased by 10, destination unchanged before receipt.\n";
    } else {
        echo "CHECK FAIL: Stock mismatch during in-transit state!\n";
    }

    // Step 2: Receive 10 units at Branch 2
    echo "\n--- Step 2: Receive Transfer In at Destination ---\n";
    $line = $st->items->first();
    $receiveData = [
        'items' => [
            [
                'id' => $line->id,
                'received_qty' => 10,
            ]
        ],
        'remarks' => 'Received verified by Phase 5 script',
    ];

    $reqReceive = \Illuminate\Http\Request::create("/inventory/stock-transfers/{$st->id}/receive", 'POST', $receiveData);
    app()->instance('request', $reqReceive);
    $controller->receive($reqReceive, $st);

    $st->refresh();
    echo "Transfer Status after receipt: {$st->status}\n";

    $stkFromFinal = (float) (DB::table('item_stocks')->where('branch_id', $fromBranch)->where('item_id', $itemId)->value('quantity') ?? 0);
    $stkToFinal   = (float) (DB::table('item_stocks')->where('branch_id', $toBranch)->where('item_id', $itemId)->value('quantity') ?? 0);

    echo "AFTER RECEIPT:\n";
    echo "  Branch {$fromBranch} Stock: {$stkFromFinal} (Expected: " . ($stkFromBefore - 10) . ")\n";
    echo "  Branch {$toBranch} Stock: {$stkToFinal} (Expected: " . ($stkToBefore + 10) . ")\n";

    if ($stkFromFinal == ($stkFromBefore - 10) && $stkToFinal == ($stkToBefore + 10)) {
        echo "CHECK PASS: Destination increased by 10 after receipt.\n";
    } else {
        echo "CHECK FAIL: Stock mismatch after receipt!\n";
    }

    echo "\n=== PHASE 5G COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
