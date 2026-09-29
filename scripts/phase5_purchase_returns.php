try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    $controller = app(\App\Http\Controllers\Purchase\PurchaseReturnController::class);

    echo "=== PHASE 5F: PURCHASE RETURNS ===\n";

    // Use PI 2 (id=31, supplier 29, Item 113: 20 units)
    $pi2 = \App\Models\PurchaseInvoice::find(31);
    echo "Using Purchase Invoice: {$pi2->invoice_number} (id={$pi2->id}, supplier={$pi2->supplier_id})\n";

    // Step 1: Partial Return 1 (Item 113: 5 units)
    echo "\n--- Step 1: Partial Return 1 (5 units of Item 113) ---\n";
    $pr1Data = [
        'return_date' => date('Y-m-d'),
        'supplier_id' => $pi2->supplier_id,
        'branch_id' => 1,
        'purchase_invoice_id' => $pi2->id,
        'purchase_type' => 'Interstate',
        'round_off' => 0,
        'posting_key' => 'P5_PR_1_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 5,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ]
        ]
    ];
    $req1 = \Illuminate\Http\Request::create('/purchase/purchase-returns', 'POST', $pr1Data);
    app()->instance('request', $req1);
    $controller->store($req1);
    $pr1 = \App\Models\PurchaseReturn::where('posting_key', $pr1Data['posting_key'])->first();
    echo "PR 1 Created: id={$pr1->id} | number={$pr1->return_number} | total={$pr1->total} | total_gst={$pr1->total_gst} | IGST={$pr1->total_igst}\n";

    // Step 2: Partial Return 2 (Item 113: 10 units)
    echo "\n--- Step 2: Partial Return 2 (10 units of Item 113) ---\n";
    $pr2Data = [
        'return_date' => date('Y-m-d'),
        'supplier_id' => $pi2->supplier_id,
        'branch_id' => 1,
        'purchase_invoice_id' => $pi2->id,
        'purchase_type' => 'Interstate',
        'round_off' => 0,
        'posting_key' => 'P5_PR_2_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 10,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ]
        ]
    ];
    $req2 = \Illuminate\Http\Request::create('/purchase/purchase-returns', 'POST', $pr2Data);
    app()->instance('request', $req2);
    $controller->store($req2);
    $pr2 = \App\Models\PurchaseReturn::where('posting_key', $pr2Data['posting_key'])->first();
    echo "PR 2 Created: id={$pr2->id} | number={$pr2->return_number} | total={$pr2->total} | total_gst={$pr2->total_gst} | IGST={$pr2->total_igst}\n";

    // Step 3: Over-return Attempt (Total bought was 20, returned so far = 5 + 10 = 15, remaining = 5. Attempting 10 units!)
    echo "\n--- Step 3: Over-return Attempt (Requesting 10 units when remaining is 5) ---\n";
    $overData = [
        'return_date' => date('Y-m-d'),
        'supplier_id' => $pi2->supplier_id,
        'branch_id' => 1,
        'purchase_invoice_id' => $pi2->id,
        'purchase_type' => 'Interstate',
        'round_off' => 0,
        'posting_key' => 'P5_PR_OVER_' . time(),
        'items' => [
            [
                'item_id' => 113,
                'qty' => 10,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ]
        ]
    ];
    $reqOver = \Illuminate\Http\Request::create('/purchase/purchase-returns', 'POST', $overData);
    app()->instance('request', $reqOver);
    $overRejected = false;
    try {
        $controller->store($reqOver);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        $overRejected = true;
        echo "SUCCESS: Purchase Over-return correctly REJECTED by system validation!\n";
        echo "Validation error message: " . json_encode($ve->errors()) . "\n";
    }

    if (!$overRejected) {
        echo "FAILURE: Purchase Over-return was NOT rejected!\n";
    }

    echo "\n=== PHASE 5F COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
