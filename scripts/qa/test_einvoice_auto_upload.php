<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstSetting;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use App\Services\GST\EInvoiceService;
use Illuminate\Support\Facades\Auth;

echo "========================================================\n";
echo "E-INVOICE AUTOMATIC UPLOAD & TFA DASHBOARD QA TEST SUITE\n";
echo "========================================================\n";

// 1. Verify GstSetting Configuration
echo "\n[Test 1] Checking GstSetting configuration...\n";
$settings = GstSetting::current();
assert($settings->auto_upload_enabled === true, "auto_upload_enabled should be true");
assert($settings->gstin === '24AAECU0338G1ZN', "GSTIN should match GoFrugal: 24AAECU0338G1ZN");
assert($settings->username === 'API_Urbanpets1', "Username should match GoFrugal: API_Urbanpets1");
assert($settings->auto_upload_scope === 'both', "auto_upload_scope should be 'both'");
echo "✓ GstSetting verified: Auto-Upload ENABLED, GSTIN {$settings->gstin}, Scope '{$settings->auto_upload_scope}', User {$settings->username}\n";

// 2. Test EInvoiceService Eligibility
echo "\n[Test 2] Testing EInvoiceService eligibility logic...\n";
$service = app(EInvoiceService::class);

$b2bBill = new SalesBill(['total' => 1200.00, 'customer_gstin' => '24AAECU9999Z1Z5']);
assert($service->isEligibleForAutoUpload($b2bBill), "B2B bill should be eligible for auto-upload");

$largeB2cBill = new SalesBill(['total' => 55000.00]);
assert($service->isEligibleForAutoUpload($largeB2cBill), "Large B2C bill >= 50k should be eligible for auto-upload");

$smallB2cBill = new SalesBill(['total' => 450.00]);
assert(!$service->isEligibleForAutoUpload($smallB2cBill), "Small B2C bill < 50k should NOT be eligible under 'both' scope");
echo "✓ Eligibility logic verified: B2B=YES, B2C >= 50k=YES, B2C < 50k=NO\n";

// 3. Test IRN Generation & Persistence on SalesBill
echo "\n[Test 3] Testing IRN generation on SalesBill...\n";
$branch = Branch::first();
$customer = Customer::whereNotNull('gst_no')->where('gst_no', '!=', '')->first();
if (!$customer) {
    $customer = Customer::create([
        'name' => 'QA B2B Customer Pvt Ltd',
        'gst_no' => '24ABCDE1234F1Z5',
        'city' => 'Ahmedabad',
        'state' => 'Gujarat',
        'postal_code' => '380015',
    ]);
}

$bill = SalesBill::create([
    'bill_number' => 'EINV-TEST-' . time(),
    'bill_date' => now(),
    'branch_id' => $branch?->id ?: 1,
    'customer_id' => $customer->id,
    'customer_gstin' => $customer->gst_no,
    'total' => 15000.00,
    'total_taxable' => 12711.86,
    'total_gst' => 2288.14,
    'total_cgst' => 1144.07,
    'total_sgst' => 1144.07,
    'total_igst' => 0.00,
    'total_qty' => 5,
    'status' => 'Posted',
]);

$uploadResult = $service->uploadToGovernment($bill);
assert($uploadResult['success'] === true, "IRN upload should succeed: " . ($uploadResult['error'] ?? ''));

$bill->refresh();
assert(!empty($bill->irn), "Bill should have 64-char IRN");
assert(strlen($bill->irn) === 64, "IRN should be exactly 64 hex characters, got: " . strlen($bill->irn));
assert(!empty($bill->ack_no), "Bill should have Government Ack No");
assert(!empty($bill->ack_date), "Bill should have Ack Date");
assert($bill->einvoice_status === 'Completed', "Bill status should be 'Completed'");
echo "✓ IRN Generated: {$bill->irn}\n";
echo "✓ Ack No: {$bill->ack_no} | Ack Date: {$bill->ack_date}\n";

// 4. Test Receipt View Rendering with Government E-Invoice QR & IRN
echo "\n[Test 4] Testing receipt view rendering with Government QR Code...\n";
$receiptHtml = view('sales.sales-bills.receipt', ['salesBill' => $bill])->render();
assert(str_contains($receiptHtml, 'GOVERNMENT OF INDIA - E-INVOICE'), "Receipt must contain Govt E-Invoice header");
assert(str_contains($receiptHtml, $bill->irn), "Receipt must contain the generated IRN hash");
assert(str_contains($receiptHtml, $bill->ack_no), "Receipt must contain the Government Ack No");
assert(str_contains($receiptHtml, 'GST Portal Digitally Signed QR Code'), "Receipt must contain signed QR code caption");
echo "✓ Receipt View verified: Displays Government E-Invoice banner, IRN hash, Ack No, and Signed QR Code.\n";

// 5. Test Cancellation within 24 Hours
echo "\n[Test 5] Testing Government IRN Cancellation...\n";
$cancelResult = $service->cancelIrn($bill, '3', 'Order cancelled by test runner');
assert($cancelResult['success'] === true, "Cancellation should succeed");
$bill->refresh();
assert($bill->einvoice_status === 'Cancelled', "Status should be 'Cancelled'");
assert(str_contains($bill->einvoice_error, 'IRN Cancelled on Portal'), "Error field should record cancellation reason");

$cancelledReceiptHtml = view('sales.sales-bills.receipt', ['salesBill' => $bill])->render();
assert(str_contains($cancelledReceiptHtml, '[IRN CANCELLED ON GOVERNMENT PORTAL]'), "Receipt should clearly show cancellation status");
echo "✓ IRN Cancellation verified: Bill marked Cancelled, logged reason, receipt updated.\n";

// 6. Test Routes via HTTP request / Request dispatch
echo "\n[Test 6] Testing URL route accessibility...\n";
$admin = User::first();
Auth::login($admin);

$request1 = \Illuminate\Http\Request::create('/einvoice/dashboard', 'GET');
$response1 = $app->handle($request1);
echo "✓ Route /einvoice/dashboard HTTP Status: " . $response1->getStatusCode() . "\n";
assert($response1->getStatusCode() === 200, "/einvoice/dashboard should return HTTP 200");

$request2 = \Illuminate\Http\Request::create('/tfa/einvoice/dashboard', 'GET');
$response2 = $app->handle($request2);
echo "✓ Route /tfa/einvoice/dashboard HTTP Status: " . $response2->getStatusCode() . "\n";
assert($response2->getStatusCode() === 200, "/tfa/einvoice/dashboard should return HTTP 200");

echo "\n========================================================";
echo "\nALL 6 QA TESTS PASSED! E-INVOICE SYSTEM FULLY OPERATIONAL\n";
echo "========================================================\n";
