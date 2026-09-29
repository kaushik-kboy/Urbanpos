try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    $controller = app(\App\Http\Controllers\Sales\SalesReturnController::class);

    echo "=== PHASE 5E: SALES RETURNS ===\n";

    // Original bill: SB 4 (id=48, B2C Walk-in)
    $sb4 = \App\Models\SalesBill::find(48);
    echo "Using Bill: {$sb4->bill_number} (id={$sb4->id})\n";

    // Step 1: Partial Return 1 (Item 112: 3 units)
    echo "\n--- Step 1: Partial Return 1 (3 units of Item 112) ---\n";
    $sr1Data = [
        'return_date' => date('Y-m-d'),
        'customer_id' => $sb4->customer_id,
        'branch_id' => 1,
        'sales_bill_id' => $sb4->id,
        'return_mode' => 'Credit Note',
        'sales_type' => 'Local',
        'round_off' => 0,
        'posting_key' => 'P5_SR_1_' . time(),
        'items' => [
            [
                'item_id' => 112, // 5%
                'qty' => 3,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ]
        ]
    ];
    $req1 = \Illuminate\Http\Request::create('/sales/sales-returns', 'POST', $sr1Data);
    app()->instance('request', $req1);
    $controller->store($req1);
    $sr1 = \App\Models\SalesReturn::where('posting_key', $sr1Data['posting_key'])->first();
    echo "SR 1 Created: id={$sr1->id} | number={$sr1->return_number} | total={$sr1->total} | total_gst={$sr1->total_gst} | CGST={$sr1->total_cgst} | SGST={$sr1->total_sgst}\n";

    // Step 2: Partial Return 2 (Item 112: 4 units)
    echo "\n--- Step 2: Partial Return 2 (4 units of Item 112) ---\n";
    $sr2Data = [
        'return_date' => date('Y-m-d'),
        'customer_id' => $sb4->customer_id,
        'branch_id' => 1,
        'sales_bill_id' => $sb4->id,
        'return_mode' => 'Credit Note',
        'sales_type' => 'Local',
        'round_off' => 0,
        'posting_key' => 'P5_SR_2_' . time(),
        'items' => [
            [
                'item_id' => 112, // 5%
                'qty' => 4,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ]
        ]
    ];
    $req2 = \Illuminate\Http\Request::create('/sales/sales-returns', 'POST', $sr2Data);
    app()->instance('request', $req2);
    $controller->store($req2);
    $sr2 = \App\Models\SalesReturn::where('posting_key', $sr2Data['posting_key'])->first();
    echo "SR 2 Created: id={$sr2->id} | number={$sr2->return_number} | total={$sr2->total} | total_gst={$sr2->total_gst} | CGST={$sr2->total_cgst} | SGST={$sr2->total_sgst}\n";

    // Step 3: Over-return Attempt (Total sold was 10, returned so far = 3 + 4 = 7, remaining = 3. Requesting 5 units!)
    echo "\n--- Step 3: Over-return Attempt (Requesting 5 units when remaining is 3) ---\n";
    $overData = [
        'return_date' => date('Y-m-d'),
        'customer_id' => $sb4->customer_id,
        'branch_id' => 1,
        'sales_bill_id' => $sb4->id,
        'return_mode' => 'Credit Note',
        'sales_type' => 'Local',
        'round_off' => 0,
        'posting_key' => 'P5_SR_OVER_' . time(),
        'items' => [
            [
                'item_id' => 112,
                'qty' => 5,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ]
        ]
    ];
    $reqOver = \Illuminate\Http\Request::create('/sales/sales-returns', 'POST', $overData);
    app()->instance('request', $reqOver);
    $overRejected = false;
    try {
        $controller->store($reqOver);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        $overRejected = true;
        echo "SUCCESS: Over-return correctly REJECTED by system validation!\n";
        echo "Validation error message: " . json_encode($ve->errors()) . "\n";
    }

    if (!$overRejected) {
        echo "FAILURE: Over-return was NOT rejected!\n";
    }

    // Step 4: Registered Customer Return for CDNR test (SB 1, id=45, P5 B2B Local Corp)
    $sb1 = \App\Models\SalesBill::find(45);
    echo "\n--- Step 4: Registered Customer Return for CDNR (SB 1: {$sb1->bill_number}) ---\n";
    $srB2bData = [
        'return_date' => date('Y-m-d'),
        'customer_id' => $sb1->customer_id,
        'branch_id' => 1,
        'sales_bill_id' => $sb1->id,
        'return_mode' => 'Credit Note',
        'sales_type' => 'Local',
        'round_off' => 0,
        'posting_key' => 'P5_SR_B2B_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 2,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ]
        ]
    ];
    $reqB2b = \Illuminate\Http\Request::create('/sales/sales-returns', 'POST', $srB2bData);
    app()->instance('request', $reqB2b);
    $controller->store($reqB2b);
    $srB2b = \App\Models\SalesReturn::where('posting_key', $srB2bData['posting_key'])->first();
    echo "SR B2B (CDNR) Created: id={$srB2b->id} | number={$srB2b->return_number} | total={$srB2b->total} | total_gst={$srB2b->total_gst} | CGST={$srB2b->total_cgst} | SGST={$srB2b->total_sgst} | cust_gstin={$srB2b->customer_gstin}\n";

    echo "\n=== PHASE 5E COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
