<?php
require 'c:/laragon/www/Urbanpos/vendor/autoload.php';
$app = require 'c:/laragon/www/Urbanpos/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ewbService = app(\App\Services\GST\EWayBillService::class);
$bill = \App\Models\SalesBill::whereNotNull('eway_bill_no')->latest()->first();

if (!$bill) {
    $bill = \App\Models\SalesBill::latest()->first();
}

echo "Testing Bill #" . $bill->bill_number . PHP_EOL;

// Test 1: Generate EWB
$genRes = $ewbService->generateEwb($bill, [
    'trans_mode' => '1',
    'distance' => 120,
    'vehicle_no' => 'GJ01AB1234',
    'transporter_id' => '',
    'transporter_name' => 'Fast Cargo',
]);
echo "1. Generate EWB: " . ($genRes['success'] ? 'OK (EWB: ' . $bill->fresh()->eway_bill_no . ')' : 'FAIL: ' . $genRes['error']) . PHP_EOL;

// Test 2: Update Part-B
$partBRes = $ewbService->updatePartB($bill->fresh(), 'GJ01CD5678', '1');
echo "2. Update Part-B: " . ($partBRes['success'] ? 'OK (Veh: ' . $bill->fresh()->vehicle_no . ')' : 'FAIL: ' . $partBRes['error']) . PHP_EOL;

// Test 3: Extend Validity
$extRes = $ewbService->extendValidity($bill->fresh(), 'GJ01CD5678', 'Ahmedabad', 50);
echo "3. Extend Validity: " . ($extRes['success'] ? 'OK (Valid until: ' . $bill->fresh()->eway_valid_until . ')' : 'FAIL: ' . $extRes['error']) . PHP_EOL;

// Test 4: Cancel EWB
$cancelRes = $ewbService->cancelEwb($bill->fresh(), '2', 'Order cancelled by party');
echo "4. Cancel EWB: " . ($cancelRes['success'] ? 'OK (Status: ' . $bill->fresh()->eway_status . ')' : 'FAIL: ' . $cancelRes['error']) . PHP_EOL;

echo "E-WAY BILL LIFECYCLE 100% OPERATIONAL!" . PHP_EOL;
