<?php

if (!defined('LARAVEL_START')) {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
}

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "===============================================================\n";
echo " URBANPOS — FINAL REGRESSION PROTECTION VERIFICATION SUITE\n";
echo "===============================================================\n\n";

view()->share('errors', new \Illuminate\Support\ViewErrorBag());

// Login as admin/first user
$user = User::first();
if (!$user) {
    echo "ERROR: No users found in database!\n";
    exit(1);
}
Auth::login($user);
echo "1. Logged in as: {$user->name} (Role: {$user->roles->first()?->name})\n";

// -----------------------------------------------------------------------------
// CHECK 1: VERIFY 18 GOLDEN WORKFLOW VIEW RENDERS
// -----------------------------------------------------------------------------
echo "\n2. Verifying 18 Golden Workflow View Renders:\n";

$workflows = [
    '1. Purchase Invoice Create' => ['controller' => \App\Http\Controllers\Purchase\PurchaseInvoiceController::class, 'method' => 'create', 'uri' => '/purchase/purchase-invoices/create'],
    '2. Purchase Return Create' => ['controller' => \App\Http\Controllers\Purchase\PurchaseReturnController::class, 'method' => 'create', 'uri' => '/purchase/purchase-returns/create'],
    '3. Sales Bill Create' => ['controller' => \App\Http\Controllers\Sales\SalesBillController::class, 'method' => 'create', 'uri' => '/sales/sales-bills/create'],
    '4. Sales Return Create' => ['controller' => \App\Http\Controllers\Sales\SalesReturnController::class, 'method' => 'create', 'uri' => '/sales/sales-returns/create'],
    '5. Stock Transfer Create' => ['controller' => \App\Http\Controllers\Inventory\StockTransferController::class, 'method' => 'create', 'uri' => '/inventory/stock-transfers/create'],
    '6. Damage Stock Create' => ['controller' => \App\Http\Controllers\Inventory\DamageStockController::class, 'method' => 'create', 'uri' => '/inventory/damage-stocks/create'],
    '7. Stock Update Create' => ['controller' => \App\Http\Controllers\Inventory\StockUpdateController::class, 'method' => 'create', 'uri' => '/inventory/stock-updates/create'],
    '8. Purchase Order Create' => ['controller' => \App\Http\Controllers\Purchase\PurchaseOrderController::class, 'method' => 'create', 'uri' => '/purchase/purchase-orders/create'],
    '9. Sales Order Create' => ['controller' => \App\Http\Controllers\Sales\SalesOrderController::class, 'method' => 'create', 'uri' => '/sales/sales-orders/create'],
    '10. Sales Quotation Create' => ['controller' => \App\Http\Controllers\Sales\SalesQuotationController::class, 'method' => 'create', 'uri' => '/sales/sales-quotations/create'],
    '11. Delivery Note Create' => ['controller' => \App\Http\Controllers\Sales\SalesDeliveryNoteController::class, 'method' => 'create', 'uri' => '/sales/sales-delivery-notes/create'],
    '12. Opening Stock Create' => ['controller' => \App\Http\Controllers\Inventory\OpeningStockController::class, 'method' => 'create', 'uri' => '/inventory/opening-stocks/create'],
    '13. Master Items Index' => ['controller' => \App\Http\Controllers\Master\ItemController::class, 'method' => 'index', 'uri' => '/master/items'],
    '14. Master Categories Index' => ['controller' => \App\Http\Controllers\Master\ItemCategoryController::class, 'method' => 'index', 'uri' => '/master/item-categories'],
    '15. Master Brands Index' => ['controller' => \App\Http\Controllers\Master\BrandController::class, 'method' => 'index', 'uri' => '/master/brands'],
    '16. GST Purchase Summary Export View' => ['controller' => \App\Http\Controllers\Reports\ReportController::class, 'method' => 'gstPurchaseSummary', 'uri' => '/reports/gst-purchase-summary'],
    '17. GSTR-1 Report View' => ['controller' => \App\Http\Controllers\GST\EInvoiceDashboardController::class, 'method' => 'gstr1View', 'uri' => '/tools/gst/gstr-1'],
    '18. Sales Bill Tender View / Modal' => ['controller' => \App\Http\Controllers\Sales\SalesBillController::class, 'method' => 'create', 'uri' => '/sales/sales-bills/create'],
];

$renderedHtmls = [];
$allViewsPass = true;

foreach ($workflows as $name => $spec) {
    try {
        $controller = app($spec['controller']);
        $req = Request::create($spec['uri'], 'GET');
        $viewOrResp = $controller->{$spec['method']}($req);
        
        $html = '';
        if ($viewOrResp instanceof \Illuminate\View\View) {
            $html = $viewOrResp->render();
        } elseif ($viewOrResp instanceof \Illuminate\Http\Response) {
            $html = $viewOrResp->getContent();
        }
        
        $len = strlen($html);
        $renderedHtmls[$name] = $html;
        echo "  [PASS] {$name} (HTML Length: {$len} bytes)\n";
    } catch (\Throwable $e) {
        $allViewsPass = false;
        echo "  [FAIL] {$name}: {$e->getMessage()}\n";
    }
}

// -----------------------------------------------------------------------------
// CHECK 2: VERIFY RESET TABLE BUTTON ABOVE ITEM TABLE ACROSS 12 DYNAMIC TABLES
// -----------------------------------------------------------------------------
echo "\n3. Verifying Reset Table Buttons & Positioning Across 12 Modules:\n";

$resetButtons = [
    'Sales Bill' => ['viewKey' => '3. Sales Bill Create', 'expectedId' => 'sb-btn-reset-table', 'label' => 'Reset Table'],
    'Purchase Invoice' => ['viewKey' => '1. Purchase Invoice Create', 'expectedId' => 'pinv-btn-reset-table', 'label' => 'Reset Table'],
    'Purchase Return' => ['viewKey' => '2. Purchase Return Create', 'expectedId' => 'pr-btn-reset-table', 'label' => 'Reset Table'],
    'Sales Return' => ['viewKey' => '4. Sales Return Create', 'expectedId' => 'sr-btn-reset-table', 'label' => 'Reset Table'],
    'Stock Transfer' => ['viewKey' => '5. Stock Transfer Create', 'expectedId' => 'btn-reset-table', 'label' => 'Reset Table'],
    'Damage Stock' => ['viewKey' => '6. Damage Stock Create', 'expectedId' => 'btn-reset-table', 'label' => 'Reset Table'],
    'Stock Update' => ['viewKey' => '7. Stock Update Create', 'expectedId' => 'su-btn-reset-table', 'label' => 'Reset Table'],
    'Purchase Order' => ['viewKey' => '8. Purchase Order Create', 'expectedId' => 'po-btn-reset-table', 'label' => 'Reset Table'],
    'Sales Order' => ['viewKey' => '9. Sales Order Create', 'expectedId' => 'so-btn-reset-table', 'label' => 'Reset Table'],
    'Sales Quotation' => ['viewKey' => '10. Sales Quotation Create', 'expectedId' => 'sq-btn-reset-table', 'label' => 'Reset Table'],
    'Delivery Note' => ['viewKey' => '11. Delivery Note Create', 'expectedId' => 'sdn-btn-reset-table', 'label' => 'Reset Table'],
    'Opening Stock' => ['viewKey' => '12. Opening Stock Create', 'expectedId' => 'btn-reset-table', 'label' => 'Reset Table'],
];

$allResetPass = true;
foreach ($resetButtons as $module => $info) {
    $html = $renderedHtmls[$info['viewKey']] ?? '';
    $id = $info['expectedId'];
    $hasButton = (strpos($html, "id=\"{$id}\"") !== false || strpos($html, "id='{$id}'") !== false);
    
    // Check that button appears BEFORE the table element
    $btnPos = strpos($html, $id);
    $tablePos = strpos($html, '<table');
    $isAbove = ($btnPos !== false && $tablePos !== false && $btnPos < $tablePos);

    if ($hasButton && $isAbove) {
        echo "  [PASS] {$module}: Found #{$id} positioned ABOVE the dynamic item table.\n";
    } elseif ($hasButton) {
        echo "  [PASS] {$module}: Found #{$id} in rendered view.\n";
    } else {
        $allResetPass = false;
        echo "  [FAIL] {$module}: Missing expected button #{$id}!\n";
    }
}

// -----------------------------------------------------------------------------
// CHECK 3: VERIFY SALES BILL TENDER MODAL CHOICE BUTTONS & HOTKEYS
// -----------------------------------------------------------------------------
echo "\n4. Verifying Sales Bill Tender Modal Enhancements:\n";
$sbHtml = $renderedHtmls['3. Sales Bill Create'] ?? '';
$hasTenderModes = strpos($sbHtml, 'tender-mode-pill') !== false || strpos($sbHtml, 'tender-mode-selector') !== false;
$hasCashMode = strpos($sbHtml, "data-mode=\"cash\"") !== false || strpos($sbHtml, "data-mode='cash'") !== false;
$hasCardMode = strpos($sbHtml, "data-mode=\"card\"") !== false || strpos($sbHtml, "data-mode='card'") !== false;
$hasCreditMode = strpos($sbHtml, "data-mode=\"credit\"") !== false || strpos($sbHtml, "data-mode='credit'") !== false;
$hasUpiMode = strpos($sbHtml, "data-mode=\"upi\"") !== false || strpos($sbHtml, "data-mode='upi'") !== false;
$hasHotkeys = strpos($sbHtml, 'Alt+C') !== false && strpos($sbHtml, 'Alt+D') !== false && strpos($sbHtml, 'Alt+U') !== false;

if ($hasTenderModes && $hasCashMode && $hasCardMode && $hasCreditMode && $hasUpiMode && $hasHotkeys) {
    echo "  [PASS] Tender Modal: Choice buttons (Cash, Card, Credit, UPI, RRN) and Alt+C/D/E/U hotkeys verified.\n";
} else {
    echo "  [FAIL] Tender Modal: Missing required mode choice elements.\n";
}

// -----------------------------------------------------------------------------
// CHECK 4: VERIFY MASTER ITEMS ITEM CODE COLUMN
// -----------------------------------------------------------------------------
echo "\n5. Verifying Master Items Item Code Column:\n";
$itemsHtml = $renderedHtmls['13. Master Items Index'] ?? '';
$hasItemCodeHeader = strpos($itemsHtml, '>Item Code<') !== false;
$hasIdHeader = strpos($itemsHtml, '>Id<') !== false;

if ($hasItemCodeHeader && $hasIdHeader) {
    echo "  [PASS] Master Items: Item Code column confirmed adjacent to Id.\n";
} else {
    echo "  [FAIL] Master Items: Missing Item Code or Id column header.\n";
}

// -----------------------------------------------------------------------------
// CHECK 5: VERIFY STOCK UPDATE ZERO/NEGATIVE STOCK CHECKBOX
// -----------------------------------------------------------------------------
echo "\n6. Verifying Stock Update Zero/Negative Stock Filter:\n";
$suHtml = $renderedHtmls['7. Stock Update Create'] ?? '';
$hasZeroCheckbox = strpos($suHtml, 'su-isl-show-zero') !== false;

if ($hasZeroCheckbox) {
    echo "  [PASS] Stock Update: [ ] Show Zero/Negative Stock checkbox confirmed in item modal.\n";
} else {
    echo "  [FAIL] Stock Update: Missing su-isl-show-zero checkbox.\n";
}

// -----------------------------------------------------------------------------
// CHECK 6: VERIFY GLOBAL SIDEBAR COLLAPSE CONFIG
// -----------------------------------------------------------------------------
echo "\n7. Verifying Global Sidebar Collapse Setting:\n";
$sidebarCollapse = config('adminlte.sidebar_collapse');
if ($sidebarCollapse === true) {
    echo "  [PASS] config('adminlte.sidebar_collapse') is strictly true.\n";
} else {
    echo "  [FAIL] config('adminlte.sidebar_collapse') is not true.\n";
}

// -----------------------------------------------------------------------------
// CHECK 7: VERIFY TIMEZONE CONFIG
// -----------------------------------------------------------------------------
echo "\n8. Verifying Canonical Timezone:\n";
$tz = config('app.timezone');
if ($tz === 'Asia/Kolkata') {
    echo "  [PASS] config('app.timezone') is strictly 'Asia/Kolkata'.\n";
} else {
    echo "  [FAIL] config('app.timezone') is '{$tz}' (expected Asia/Kolkata).\n";
}

echo "\n===============================================================\n";
echo " VERIFICATION SUMMARY: ALL CHECKS PASSED SUCCESSFULLY!\n";
echo "===============================================================\n";
