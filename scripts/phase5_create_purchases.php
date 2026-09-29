try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    $controller = app(\App\Http\Controllers\Purchase\PurchaseInvoiceController::class);

    echo "=== CREATING PURCHASE 1: LOCAL MIXED GST ===\n";
    $p1Data = [
        'invoice_date' => date('Y-m-d'),
        'supplier_id' => 28, // P5 Local Supplier
        'branch_id' => 1,
        'purchase_type' => 'Local',
        'c_form' => 'No Forms',
        'supplier_inv_no' => 'P5-SINV-001-' . time(),
        'supplier_inv_date' => date('Y-m-d'),
        'supplier_inv_amount' => 19550.00,
        'freight' => 0,
        'round_off' => 0,
        'tcs_amount' => 0,
        'posting_key' => 'P5_PURCHASE_1_' . time(),
        'items' => [
            [
                'item_id' => 111, // 0%
                'qty' => 50,
                'cost_price' => 50.00,
                'sell_price' => 60.00,
                'mrp' => 60.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 0,
            ],
            [
                'item_id' => 112, // 5%
                'qty' => 50,
                'cost_price' => 100.00,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ],
            [
                'item_id' => 113, // 18%
                'qty' => 50,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
    ];

    $req1 = \Illuminate\Http\Request::create('/purchase/purchase-invoices', 'POST', $p1Data);
    app()->instance('request', $req1);
    $res1 = $controller->store($req1);
    $pi1 = \App\Models\PurchaseInvoice::where('posting_key', $p1Data['posting_key'])->first();
    if ($pi1) {
        echo "PI 1 Created: id={$pi1->id} | number={$pi1->invoice_number} | total={$pi1->total} | total_gst={$pi1->total_gst} | CGST={$pi1->total_cgst} | SGST={$pi1->total_sgst} | IGST={$pi1->total_igst}\n";
    } else {
        echo "PI 1 FAILED TO PERSIST\n";
    }

    echo "\n=== CREATING PURCHASE 2: INTERSTATE + FREIGHT ===\n";
    $p2Data = [
        'invoice_date' => date('Y-m-d'),
        'supplier_id' => 29, // P5 Interstate Supplies Ltd
        'branch_id' => 1,
        'purchase_type' => 'Interstate',
        'c_form' => 'No Forms',
        'supplier_inv_no' => 'P5-SINV-002-' . time(),
        'supplier_inv_date' => date('Y-m-d'),
        'supplier_inv_amount' => 5020.00,
        'freight' => 300.00,
        'round_off' => 0,
        'tcs_amount' => 0,
        'posting_key' => 'P5_PURCHASE_2_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 20,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
    ];

    $req2 = \Illuminate\Http\Request::create('/purchase/purchase-invoices', 'POST', $p2Data);
    app()->instance('request', $req2);
    $res2 = $controller->store($req2);
    $pi2 = \App\Models\PurchaseInvoice::where('posting_key', $p2Data['posting_key'])->first();
    if ($pi2) {
        echo "PI 2 Created: id={$pi2->id} | number={$pi2->invoice_number} | total={$pi2->total} | total_gst={$pi2->total_gst} | CGST={$pi2->total_cgst} | SGST={$pi2->total_sgst} | IGST={$pi2->total_igst} | freight={$pi2->freight}\n";
    } else {
        echo "PI 2 FAILED TO PERSIST\n";
    }

    echo "\n=== CREATING PURCHASE 3: MULTIPLE GST RATES ===\n";
    $p3Data = [
        'invoice_date' => date('Y-m-d'),
        'supplier_id' => 28, // P5 Local Supplier
        'branch_id' => 1,
        'purchase_type' => 'Local',
        'c_form' => 'No Forms',
        'supplier_inv_no' => 'P5-SINV-003-' . time(),
        'supplier_inv_date' => date('Y-m-d'),
        'supplier_inv_amount' => 7870.00,
        'freight' => 0,
        'round_off' => 0,
        'tcs_amount' => 0,
        'posting_key' => 'P5_PURCHASE_3_' . time(),
        'items' => [
            [
                'item_id' => 112, // 5%
                'qty' => 30,
                'cost_price' => 100.00,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ],
            [
                'item_id' => 113, // 18%
                'qty' => 20,
                'cost_price' => 200.00,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
    ];

    $req3 = \Illuminate\Http\Request::create('/purchase/purchase-invoices', 'POST', $p3Data);
    app()->instance('request', $req3);
    $res3 = $controller->store($req3);
    $pi3 = \App\Models\PurchaseInvoice::where('posting_key', $p3Data['posting_key'])->first();
    if ($pi3) {
        echo "PI 3 Created: id={$pi3->id} | number={$pi3->invoice_number} | total={$pi3->total} | total_gst={$pi3->total_gst} | CGST={$pi3->total_cgst} | SGST={$pi3->total_sgst} | IGST={$pi3->total_igst}\n";
    } else {
        echo "PI 3 FAILED TO PERSIST\n";
    }

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
