try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    $controller = app(\App\Http\Controllers\Sales\SalesBillController::class);

    echo "=== CREATING SALES 1: B2B INTRA-STATE ===\n";
    $s1Data = [
        'bill_date' => date('Y-m-d H:i:s'),
        'customer_id' => 18, // P5 B2B Local Corp
        'branch_id' => 1,
        'invoice_type' => 'Tax Invoice',
        'delivery_type' => 'Hand Delivery',
        'sales_type' => 'Local',
        'payment_type' => 'Cash',
        'round_off' => 0,
        'posting_key' => 'P5_SALE_1_' . time(),
        'items' => [
            [
                'item_id' => 111, // 0%
                'qty' => 5,
                'sell_price' => 60.00,
                'mrp' => 60.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 0,
            ],
            [
                'item_id' => 112, // 5%
                'qty' => 10,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ],
            [
                'item_id' => 113, // 18%
                'qty' => 10,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
        'payments' => [
            ['tender_type_id' => 1, 'amount' => 4000.00]
        ]
    ];
    $req1 = \Illuminate\Http\Request::create('/sales/sales-bills', 'POST', $s1Data);
    app()->instance('request', $req1);
    $controller->store($req1);
    $sb1 = \App\Models\SalesBill::where('posting_key', $s1Data['posting_key'])->first();
    echo "SB 1 Created: id={$sb1->id} | number={$sb1->bill_number} | total={$sb1->total} | total_gst={$sb1->total_gst} | CGST={$sb1->total_cgst} | SGST={$sb1->total_sgst} | IGST={$sb1->total_igst} | cust_gstin={$sb1->customer_gstin}\n";

    echo "\n=== CREATING SALES 2: B2B INTERSTATE ===\n";
    $s2Data = [
        'bill_date' => date('Y-m-d H:i:s'),
        'customer_id' => 19, // P5 B2B Interstate Ltd
        'branch_id' => 1,
        'invoice_type' => 'Tax Invoice',
        'delivery_type' => 'Hand Delivery',
        'sales_type' => 'Interstate',
        'payment_type' => 'Cash',
        'round_off' => 0,
        'posting_key' => 'P5_SALE_2_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 10,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
        'payments' => [
            ['tender_type_id' => 1, 'amount' => 2500.00]
        ]
    ];
    $req2 = \Illuminate\Http\Request::create('/sales/sales-bills', 'POST', $s2Data);
    app()->instance('request', $req2);
    $controller->store($req2);
    $sb2 = \App\Models\SalesBill::where('posting_key', $s2Data['posting_key'])->first();
    echo "SB 2 Created: id={$sb2->id} | number={$sb2->bill_number} | total={$sb2->total} | total_gst={$sb2->total_gst} | CGST={$sb2->total_cgst} | SGST={$sb2->total_sgst} | IGST={$sb2->total_igst} | cust_gstin={$sb2->customer_gstin}\n";

    echo "\n=== CREATING SALES 3: B2C UNREGISTERED LOCAL ===\n";
    $s3Data = [
        'bill_date' => date('Y-m-d H:i:s'),
        'customer_id' => 20, // P5 Walk-in B2C Customer
        'branch_id' => 1,
        'invoice_type' => 'Retail Invoice',
        'delivery_type' => 'Hand Delivery',
        'sales_type' => 'Local',
        'payment_type' => 'Cash',
        'round_off' => 0,
        'posting_key' => 'P5_SALE_3_' . time(),
        'items' => [
            [
                'item_id' => 112, // 5%
                'qty' => 5,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ],
            [
                'item_id' => 113, // 18%
                'qty' => 5,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
        'payments' => [
            ['tender_type_id' => 1, 'amount' => 1850.00]
        ]
    ];
    $req3 = \Illuminate\Http\Request::create('/sales/sales-bills', 'POST', $s3Data);
    app()->instance('request', $req3);
    $controller->store($req3);
    $sb3 = \App\Models\SalesBill::where('posting_key', $s3Data['posting_key'])->first();
    echo "SB 3 Created: id={$sb3->id} | number={$sb3->bill_number} | total={$sb3->total} | total_gst={$sb3->total_gst} | CGST={$sb3->total_cgst} | SGST={$sb3->total_sgst} | IGST={$sb3->total_igst} | cust_gstin=" . ($sb3->customer_gstin ?: 'NONE') . "\n";

    echo "\n=== CREATING SALES 4: MIXED GST UNREGISTERED (FOR RETURN TEST) ===\n";
    $s4Data = [
        'bill_date' => date('Y-m-d H:i:s'),
        'customer_id' => 20, // P5 Walk-in B2C Customer
        'branch_id' => 1,
        'invoice_type' => 'Retail Invoice',
        'delivery_type' => 'Hand Delivery',
        'sales_type' => 'Local',
        'payment_type' => 'Cash',
        'round_off' => 0,
        'posting_key' => 'P5_SALE_4_' . time(),
        'items' => [
            [
                'item_id' => 111, // 0%
                'qty' => 5,
                'sell_price' => 60.00,
                'mrp' => 60.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 0,
            ],
            [
                'item_id' => 112, // 5%
                'qty' => 10,
                'sell_price' => 120.00,
                'mrp' => 120.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5,
            ],
            [
                'item_id' => 113, // 18%
                'qty' => 10,
                'sell_price' => 250.00,
                'mrp' => 250.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
        'payments' => [
            ['tender_type_id' => 1, 'amount' => 4000.00]
        ]
    ];
    $req4 = \Illuminate\Http\Request::create('/sales/sales-bills', 'POST', $s4Data);
    app()->instance('request', $req4);
    $controller->store($req4);
    $sb4 = \App\Models\SalesBill::where('posting_key', $s4Data['posting_key'])->first();
    echo "SB 4 Created: id={$sb4->id} | number={$sb4->bill_number} | total={$sb4->total} | total_gst={$sb4->total_gst} | CGST={$sb4->total_cgst} | SGST={$sb4->total_sgst} | IGST={$sb4->total_igst} | cust_gstin=" . ($sb4->customer_gstin ?: 'NONE') . "\n";

    echo "\n=== CREATING SALES 5: B2CL SPECIAL TEST (UNREG + INTERSTATE >= 2.5L) ===\n";
    $s5Data = [
        'bill_date' => date('Y-m-d H:i:s'),
        'customer_id' => 21, // P5 Interstate B2C HighVal
        'branch_id' => 1,
        'invoice_type' => 'Retail Invoice',
        'delivery_type' => 'Hand Delivery',
        'sales_type' => 'Interstate',
        'payment_type' => 'Cash',
        'round_off' => 0,
        'posting_key' => 'P5_SALE_5_' . time(),
        'items' => [
            [
                'item_id' => 113, // 18%
                'qty' => 10,
                'sell_price' => 26000.00, // Total = 260,000 >= 250,000
                'mrp' => 26000.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18,
            ],
        ],
        'payments' => [
            ['tender_type_id' => 1, 'amount' => 260000.00]
        ]
    ];
    $req5 = \Illuminate\Http\Request::create('/sales/sales-bills', 'POST', $s5Data);
    app()->instance('request', $req5);
    $controller->store($req5);
    $sb5 = \App\Models\SalesBill::where('posting_key', $s5Data['posting_key'])->first();
    echo "SB 5 Created: id={$sb5->id} | number={$sb5->bill_number} | total={$sb5->total} | total_gst={$sb5->total_gst} | CGST={$sb5->total_cgst} | SGST={$sb5->total_sgst} | IGST={$sb5->total_igst} | cust_gstin=" . ($sb5->customer_gstin ?: 'NONE') . "\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
