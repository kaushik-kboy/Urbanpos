try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5B: MASTER DATA SETUP ===\n";

    // 1. ITEMS
    $itemsConfig = [
        [
            'item_code' => 'P5-ITM-0GST',
            'name' => 'P5 Fresh Milk 0% GST',
            'hsn_code' => '0401',
            'gst_tax_id' => 6, // 0%
            'cost_price' => 50.00,
            'sell_price' => 60.00,
            'mrp' => 60.00,
            'tax_inclusive' => 1,
            'status' => 1,
        ],
        [
            'item_code' => 'P5-ITM-5GST',
            'name' => 'P5 Organic Rice 5% GST',
            'hsn_code' => '1006',
            'gst_tax_id' => 7, // 5%
            'cost_price' => 100.00,
            'sell_price' => 120.00,
            'mrp' => 120.00,
            'tax_inclusive' => 1,
            'status' => 1,
        ],
        [
            'item_code' => 'P5-ITM-18GST',
            'name' => 'P5 Premium Biscuit 18% GST',
            'hsn_code' => '1905',
            'gst_tax_id' => 9, // 18%
            'cost_price' => 200.00,
            'sell_price' => 250.00,
            'mrp' => 250.00,
            'tax_inclusive' => 1,
            'status' => 1,
        ],
    ];

    foreach ($itemsConfig as $cfg) {
        $item = \App\Models\Item::firstOrNew(['item_code' => $cfg['item_code']]);
        foreach ($cfg as $k => $v) {
            $item->{$k} = $v;
        }
        $item->save();
        echo "Item: {$item->item_code} | id={$item->id} | name={$item->name} | GST tax id={$item->gst_tax_id} | HSN={$item->hsn_code}\n";
    }

    // 2. SUPPLIERS
    $suppliersConfig = [
        [
            'name' => 'P5 Local Supplier Pvt Ltd',
            'gst_no' => '24AABCS5678B1Z2',
            'state' => 'Gujarat',
            'purchase_type' => 'Local',
            'status' => 1,
            'city' => 'Ahmedabad',
            'country' => 'India',
        ],
        [
            'name' => 'P5 Interstate Supplies Ltd',
            'gst_no' => '27AABCS5678B1Z2',
            'state' => 'Maharashtra',
            'purchase_type' => 'Interstate',
            'status' => 1,
            'city' => 'Mumbai',
            'country' => 'India',
        ],
    ];

    foreach ($suppliersConfig as $cfg) {
        $supplier = \App\Models\Supplier::firstOrNew(['name' => $cfg['name']]);
        foreach ($cfg as $k => $v) {
            $supplier->{$k} = $v;
        }
        $supplier->save();
        echo "Supplier: {$supplier->name} | id={$supplier->id} | GSTIN={$supplier->gst_no} | state={$supplier->state}\n";
    }

    // 3. CUSTOMERS
    $customersConfig = [
        [
            'name' => 'P5 B2B Local Corp',
            'customer_code' => 'P5-CUST-B2B-LOC',
            'gst_no' => '24AABCP1234A1Z5',
            'state' => 'Gujarat',
            'sales_type' => 'Local',
            'customer_type' => 'TAX INVOICE',
            'status' => 1,
            'city' => 'Ahmedabad',
            'country' => 'India',
        ],
        [
            'name' => 'P5 B2B Interstate Ltd',
            'customer_code' => 'P5-CUST-B2B-INT',
            'gst_no' => '27AABCP1234A1Z5',
            'state' => 'Maharashtra',
            'sales_type' => 'Interstate',
            'customer_type' => 'TAX INVOICE',
            'status' => 1,
            'city' => 'Mumbai',
            'country' => 'India',
        ],
        [
            'name' => 'P5 Walk-in B2C Customer',
            'customer_code' => 'P5-CUST-B2C',
            'gst_no' => null,
            'state' => 'Gujarat',
            'sales_type' => 'Local',
            'customer_type' => 'RETAIL INVOICE',
            'status' => 1,
            'city' => 'Ahmedabad',
            'country' => 'India',
        ],
        [
            'name' => 'P5 Interstate B2C HighVal',
            'customer_code' => 'P5-CUST-B2C-INT',
            'gst_no' => null,
            'state' => 'Maharashtra',
            'sales_type' => 'Interstate',
            'customer_type' => 'RETAIL INVOICE',
            'status' => 1,
            'city' => 'Pune',
            'country' => 'India',
        ],
    ];

    foreach ($customersConfig as $cfg) {
        $cust = \App\Models\Customer::firstOrNew(['customer_code' => $cfg['customer_code']]);
        foreach ($cfg as $k => $v) {
            $cust->{$k} = $v;
        }
        $cust->save();
        echo "Customer: {$cust->name} | id={$cust->id} | code={$cust->customer_code} | GSTIN=" . ($cust->gst_no ?: 'NONE') . " | state={$cust->state}\n";
    }

    echo "=== MASTER DATA SETUP COMPLETE ===\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
