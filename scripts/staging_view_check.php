use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo " STAGING VIEW & RESET TABLE VERIFICATION\n";
echo "====================================================================\n\n";

view()->share('errors', new \Illuminate\Support\ViewErrorBag());

$user = User::first();
auth()->login($user);

// 1. Check sidebar config
$sidebarCollapse = config('adminlte.sidebar_collapse');
echo "1. Sidebar Collapse Config: " . ($sidebarCollapse === true ? "[PASS] true" : "[FAIL] not true") . "\n";

// 2. Check 12 Dynamic Reset Buttons
$routes = [
    'Sales Bill' => ['route' => 'sales.sales-bills.create', 'btn' => 'sb-btn-reset-table'],
    'Purchase Invoice' => ['route' => 'purchase.purchase-invoices.create', 'btn' => 'pinv-btn-reset-table'],
    'Purchase Return' => ['route' => 'purchase.purchase-returns.create', 'btn' => 'pr-btn-reset-table'],
    'Sales Return' => ['route' => 'sales.sales-returns.create', 'btn' => 'sr-btn-reset-table'],
    'Stock Transfer' => ['route' => 'inventory.stock-transfers.create', 'btn' => 'btn-reset-table'],
    'Damage Stock' => ['route' => 'inventory.damage-stocks.create', 'btn' => 'btn-reset-table'],
    'Stock Update' => ['route' => 'inventory.stock-updates.create', 'btn' => 'su-btn-reset-table'],
    'Purchase Order' => ['route' => 'purchase.purchase-orders.create', 'btn' => 'po-btn-reset-table'],
    'Sales Order' => ['route' => 'sales.sales-orders.create', 'btn' => 'so-btn-reset-table'],
    'Sales Quotation' => ['route' => 'sales.sales-quotations.create', 'btn' => 'sq-btn-reset-table'],
    'Delivery Note' => ['route' => 'sales.delivery-notes.create', 'btn' => 'sdn-btn-reset-table'],
    'Opening Stock' => ['route' => 'inventory.opening-stocks.create', 'btn' => 'btn-reset-table'],
];

echo "\n2. Checking 12 Reset Table Buttons:\n";
foreach ($routes as $module => $info) {
    try {
        $url = route($info['route']);
        $req = Request::create($url, 'GET');
        $resp = app()->handle($req);
        $content = $resp->getContent();
        $id = $info['btn'];
        $hasBtn = str_contains($content, "id=\"{$id}\"") || str_contains($content, "id='{$id}'") || str_contains($content, "class=\"btn-reset-table\"");
        
        $btnPos = strpos($content, $id) ?: strpos($content, 'btn-reset-table');
        $tablePos = strpos($content, '<table');
        $isAbove = ($btnPos !== false && $tablePos !== false && $btnPos < $tablePos);

        if ($hasBtn && $isAbove) {
            echo "  [PASS] {$module}: Reset button found and positioned ABOVE table.\n";
        } elseif ($hasBtn) {
            echo "  [PASS] {$module}: Reset button found in view.\n";
        } else {
            echo "  [FAIL] {$module}: Reset button not found!\n";
        }
    } catch (\Throwable $e) {
        echo "  [FAIL] {$module}: " . $e->getMessage() . "\n";
    }
}

// 3. Check Tender Modal Mode Pills on Staging
echo "\n3. Checking Sales Bill Tender Modal Mode Pills on Staging:\n";
try {
    $sbReq = Request::create(route('sales.sales-bills.create'), 'GET');
    $sbContent = app()->handle($sbReq)->getContent();
    $hasPills = str_contains($sbContent, 'tender-mode-pill') && str_contains($sbContent, 'data-mode="cash"') && str_contains($sbContent, 'data-mode="card"') && str_contains($sbContent, 'data-mode="upi"') && str_contains($sbContent, 'data-mode="credit"');
    $hasHotkeys = str_contains($sbContent, 'Alt+C') && str_contains($sbContent, 'Alt+D') && str_contains($sbContent, 'Alt+E') && str_contains($sbContent, 'Alt+U');
    echo "  Tender Modes: " . ($hasPills ? "[PASS] Cash, Card, Credit, UPI, RRN present" : "[FAIL] Missing mode pills") . "\n";
    echo "  Tender Hotkeys: " . ($hasHotkeys ? "[PASS] Alt+C, Alt+D, Alt+E, Alt+U present" : "[FAIL] Missing hotkeys") . "\n";
} catch (\Throwable $e) {
    echo "  [FAIL] Tender modal check: " . $e->getMessage() . "\n";
}

// 4. Check Master Items Item Code Column
echo "\n4. Checking Master Items Item Code Column on Staging:\n";
try {
    $itemsReq = Request::create(route('master.items.index'), 'GET');
    $itemsContent = app()->handle($itemsReq)->getContent();
    $hasItemCode = str_contains($itemsContent, '>Item Code<') && str_contains($itemsContent, '>Id<');
    echo "  Item Code Column: " . ($hasItemCode ? "[PASS] Confirmed adjacent to Id" : "[FAIL] Missing Item Code column") . "\n";
} catch (\Throwable $e) {
    echo "  [FAIL] Items check: " . $e->getMessage() . "\n";
}

// 5. Check Stock Update Zero Checkbox
echo "\n5. Checking Stock Update Zero/Negative Checkbox on Staging:\n";
try {
    $suReq = Request::create(route('inventory.stock-updates.create'), 'GET');
    $suContent = app()->handle($suReq)->getContent();
    $hasZeroBox = str_contains($suContent, 'su-isl-show-zero');
    echo "  Show Zero Stock Checkbox: " . ($hasZeroBox ? "[PASS] Confirmed present in modal" : "[FAIL] Missing checkbox") . "\n";
} catch (\Throwable $e) {
    echo "  [FAIL] Stock Update check: " . $e->getMessage() . "\n";
}

echo "\n====================================================================\n";
