<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseIndent;
use Illuminate\Http\Request;

echo "=== QA TEST: AUTO-INDENT REPLENISHMENT GENERATOR (STEP 4.2) ===\n\n";

$branch = Branch::where('status', true)->where('name', '!=', 'GLOBAL')->first();
if (!$branch) {
    echo "❌ FAILED: No active branch found\n";
    exit(1);
}

// 1. Test controller endpoint mbqDeficits
$controller = app(\App\Http\Controllers\Purchase\PurchaseIndentController::class);
$request = Request::create('/purchase/purchase-indents/mbq-deficits', 'GET', [
    'branch_id' => $branch->id,
    'mbq_target' => 15.0,
]);

$response = $controller->mbqDeficits($request);
$data = json_decode($response->getContent(), true);

if (!isset($data['branch_id']) || !isset($data['items']) || !isset($data['count'])) {
    echo "❌ FAILED: Unexpected mbqDeficits response format\n";
    print_r($data);
    exit(1);
}

echo "✅ PASS: mbqDeficits endpoint returned {$data['count']} deficit items for branch #{$branch->id} ('{$branch->name}').\n";

if ($data['count'] > 0) {
    $first = $data['items'][0];
    echo "   Sample Deficit SKU: '{$first['name']}' (Code: {$first['item_code']})\n";
    echo "   Current Stock: {$first['current_stock']} | MBQ Target: {$first['mbq_target']} | Deficit Qty: {$first['deficit_qty']}\n";
    
    // Mathematical assertion
    $expectedDeficit = max(1.0, round($data['mbq_target'] - $first['current_stock'], 2));
    if ($first['deficit_qty'] != $expectedDeficit) {
        echo "❌ FAILED: Deficit math mismatch! Got {$first['deficit_qty']}, expected {$expectedDeficit}\n";
        exit(1);
    }
    echo "✅ PASS: Deficit math verified accurate (Deficit = MBQ - Current Stock).\n";
} else {
    echo "ℹ️ Note: No items under 15 units currently in branch.\n";
}

echo "\n🎉 ALL STEP 4.2 AUTO-INDENT REPLENISHMENT TESTS PASSED 100%!\n";
