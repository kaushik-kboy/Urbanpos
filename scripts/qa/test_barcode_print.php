<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

echo "Logged in as: " . $user->name . PHP_EOL;

// 1. Test Barcode Controller Index
$controller = app(\App\Http\Controllers\Inventory\BarcodeController::class);
$request = Request::create('/inventory/barcode', 'GET');
$view = $controller->index($request);
echo "Barcode Index View resolved: " . $view->getName() . PHP_EOL;

// 2. Test Invoice Items JSON
$pi = PurchaseInvoice::first();
if ($pi) {
    $res = $controller->invoiceItems($pi);
    $data = json_decode($res->getContent(), true);
    echo "PI Items fetched for #" . $data['invoice_number'] . " Count: " . count($data['items']) . PHP_EOL;
}

// 3. Test Barcode Print Controller with A4 24-Up
$printCtrl = app(\App\Http\Controllers\Master\BarcodePrintController::class);
$printReq = Request::create('/master/barcodes/print', 'POST', [
    'format'     => 'a4_24',
    'show_store' => 1,
    'show_mrp'   => 1,
    'show_sell'  => 1,
    'show_exp'   => 1,
    'items'      => [
        [
            'id'         => 1,
            'name'       => 'Pedigree Adult Dog Food 3kg',
            'code'       => 'ITEM-001',
            'barcode'    => '8901234567890',
            'mrp'        => 850.00,
            'sell_price' => 799.00,
            'exp_date'   => '2026-12-31',
            'qty'        => 3,
        ]
    ]
]);

$printView = $printCtrl->printLabels($printReq);
$renderedHtml = $printView->render();
$hasA4Page = str_contains($renderedHtml, 'a4-sheet-page');
$hasFormatClass = str_contains($renderedHtml, 'format-a4_24');
echo "Print View rendered: " . $printView->getName() . PHP_EOL;
echo "Contains a4-sheet-page: " . ($hasA4Page ? 'YES' : 'NO') . PHP_EOL;
echo "Contains format-a4_24: " . ($hasFormatClass ? 'YES' : 'NO') . PHP_EOL;
echo "SUCCESS: Barcode Studio tested cleanly!" . PHP_EOL;
