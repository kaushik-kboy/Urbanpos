<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\CustomerPet;
use App\Models\SalesBill;
use App\Models\Branch;
use App\Models\Item;
use Illuminate\Support\Facades\Schema;

echo "=== QA TEST: POS TERMINAL PET TAGGING (STEP 4.1) ===\n\n";

// 1. Column exists
if (!Schema::hasColumn('sales_bills', 'customer_pet_id')) {
    echo "❌ FAILED: customer_pet_id column missing on sales_bills\n";
    exit(1);
}
echo "✅ PASS: customer_pet_id exists on sales_bills.\n";

// 2. Fetch or create a test customer and pet
$customer = Customer::where('status', true)->first();
if (!$customer) {
    echo "❌ FAILED: No active customer found\n";
    exit(1);
}

$pet = CustomerPet::where('customer_id', $customer->id)->first();
if (!$pet) {
    $pet = CustomerPet::create([
        'customer_id' => $customer->id,
        'name' => 'Bruno QA',
        'gender' => 'Male',
        'age' => '2 years',
    ]);
}
echo "✅ PASS: Test customer #{$customer->id} ('{$customer->name}') has pet #{$pet->id} ('{$pet->name}').\n";

// 3. Create or inspect a SalesBill with pet tagged
$branch = Branch::where('status', true)->first();
$bill = new SalesBill();
$bill->bill_number = 'TEST-PET-' . uniqid();
$bill->bill_date = now();
$bill->customer_id = $customer->id;
$bill->customer_pet_id = $pet->id;
$bill->branch_id = $branch->id;
$bill->invoice_type = 'Retail Invoice';
$bill->delivery_type = 'Direct';
$bill->sales_type = 'Local';
$bill->total = 100.00;
$bill->posting_key = 'test_pos_pet_' . uniqid();
$bill->save();

$refetched = SalesBill::with(['pet.breed', 'customer'])->find($bill->id);
if (!$refetched || !$refetched->pet || $refetched->pet->id !== $pet->id) {
    echo "❌ FAILED: Pet relationship did not resolve tagged pet!\n";
    exit(1);
}
echo "✅ PASS: SalesBill #{$refetched->bill_number} correctly tagged with Pet #{$refetched->pet->id} ('{$refetched->pet->name}').\n";

// 4. Test receipt rendering
$html = view('sales.sales-bills.receipt', [
    'salesBill' => $refetched,
    'isPublicGuest' => false,
])->render();

if (!str_contains($html, '🐾 Pet:') || !str_contains($html, $pet->name)) {
    echo "❌ FAILED: Receipt view did not render tagged pet name!\n";
    exit(1);
}
echo "✅ PASS: Receipt renders tagged pet correctly ('{$pet->name}').\n";

// Cleanup test bill
$refetched->delete();
echo "\n🎉 ALL STEP 4.1 POS PET TAGGING TESTS PASSED 100%!\n";
