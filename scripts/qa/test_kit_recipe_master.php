<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Item;
use App\Models\KitRecipe;
use App\Models\KitRecipeItem;
use Illuminate\Support\Facades\Schema;

echo "=== QA TEST: KIT RECIPE BUILDER MASTER (STEP 4.3) ===\n\n";

// 1. Schema check
if (!Schema::hasTable('kit_recipes') || !Schema::hasTable('kit_recipe_items')) {
    echo "❌ FAILED: kit_recipes or kit_recipe_items tables missing\n";
    exit(1);
}
echo "✅ PASS: kit_recipes and kit_recipe_items tables exist.\n";

// 2. Fetch test items
$items = Item::where('status', true)->limit(3)->get();
if ($items->count() < 3) {
    echo "❌ FAILED: Need at least 3 active items to test kit recipe\n";
    exit(1);
}

$parentKit = $items[0];
$childComp1 = $items[1];
$childComp2 = $items[2];

// Cleanup any old test recipe for this item
KitRecipe::where('kit_item_id', $parentKit->id)->delete();

// 3. Create a recipe
$recipe = KitRecipe::create([
    'kit_item_id' => $parentKit->id,
    'name' => 'QA Test Combo Recipe',
    'notes' => 'Tested via automated test suite',
    'is_active' => true,
]);

$recipe->items()->create([
    'component_item_id' => $childComp1->id,
    'qty_per_kit' => 2.0000,
    'remarks' => 'Item 1 component',
]);

$recipe->items()->create([
    'component_item_id' => $childComp2->id,
    'qty_per_kit' => 1.5000,
    'remarks' => 'Item 2 component',
]);

echo "✅ PASS: Created KitRecipe #{$recipe->id} for parent '{$parentKit->name}' with 2 components.\n";

// 4. Test Controller byKitItem endpoint
$controller = app(\App\Http\Controllers\Inventory\KitRecipeController::class);
$res = $controller->byKitItem($parentKit->id);
$data = json_decode($res->getContent(), true);

if (!isset($data['found']) || $data['found'] !== true || count($data['components']) !== 2) {
    echo "❌ FAILED: byKitItem did not return expected recipe components!\n";
    print_r($data);
    exit(1);
}

echo "✅ PASS: byKitItem endpoint returned 2 recipe ingredients accurately.\n";
echo "   Component 1: {$data['components'][0]['name']} (Qty: {$data['components'][0]['qty_per_kit']})\n";
echo "   Component 2: {$data['components'][1]['name']} (Qty: {$data['components'][1]['qty_per_kit']})\n";

// 5. Test Item relation
$itemWithRecipe = Item::with('kitRecipe.items')->find($parentKit->id);
if (!$itemWithRecipe->kitRecipe || $itemWithRecipe->kitRecipe->items->count() !== 2) {
    echo "❌ FAILED: Item::kitRecipe relationship failed\n";
    exit(1);
}
echo "✅ PASS: Item::kitRecipe relationship resolved correctly.\n";

// Cleanup test recipe
$recipe->delete();
echo "✅ PASS: Recipe cleanup completed.\n";

echo "\n🎉 ALL STEP 4.3 KIT RECIPE MASTER TESTS PASSED 100%!\n";
