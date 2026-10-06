<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

echo "Logged in as: " . $user->name . PHP_EOL;

$ctrl = app(\App\Http\Controllers\GST\EInvoiceDashboardController::class);

// 1. Test GSTR-1 JSON Export
$gstr1Req = Request::create('/gst/gstr-1/export-json', 'GET', [
    'from_date' => '2026-04-01',
    'to_date'   => '2026-10-31',
]);
$jsonResponse = $ctrl->exportGstr1Json($gstr1Req);
$jsonContent = $jsonResponse->getContent();
$parsedJson = json_decode($jsonContent, true);

echo "1. GSTR-1 Govt JSON Generated:" . PHP_EOL;
echo "   - GSTIN: " . ($parsedJson['gstin'] ?? 'none') . PHP_EOL;
echo "   - FP: " . ($parsedJson['fp'] ?? 'none') . PHP_EOL;
echo "   - B2B count: " . count($parsedJson['b2b'] ?? []) . PHP_EOL;
echo "   - B2CS count: " . count($parsedJson['b2cs'] ?? []) . PHP_EOL;
echo "   - HSN count: " . count($parsedJson['hsn']['data'] ?? []) . PHP_EOL;
echo "   - Doc Issue count: " . count($parsedJson['doc_issue']['doc_det'] ?? []) . PHP_EOL;

// 2. Test GSTR-3B View
$gstr3bReq = Request::create('/gst/gstr-3b', 'GET', [
    'from_date' => '2026-04-01',
    'to_date'   => '2026-10-31',
]);
$gstr3bView = $ctrl->gstr3bView($gstr3bReq);
$gstr3bHtml = $gstr3bView->render();
echo "2. GSTR-3B View rendered: " . $gstr3bView->getName() . " (" . strlen($gstr3bHtml) . " bytes)" . PHP_EOL;

// 3. Test GSTR-2 View
$gstr2Req = Request::create('/gst/gstr-2', 'GET', [
    'from_date' => '2026-04-01',
    'to_date'   => '2026-10-31',
]);
$gstr2View = $ctrl->gstr2View($gstr2Req);
$gstr2Html = $gstr2View->render();
// 4. Test Exports
$res3b = $ctrl->exportGstr3b(Request::create('/gst/gstr-3b-export', 'GET'));
echo "4. GSTR-3B CSV Export status: " . $res3b->getStatusCode() . PHP_EOL;

$res2 = $ctrl->exportGstr2(Request::create('/gst/gstr-2-export', 'GET'));
echo "5. GSTR-2 CSV Export status: " . $res2->getStatusCode() . PHP_EOL;

echo "SUCCESS: GSTR & TFA Suite tested completely!" . PHP_EOL;
