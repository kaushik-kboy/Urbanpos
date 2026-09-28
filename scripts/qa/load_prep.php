<?php
/**
 * Prepares the QA database for the load test (idempotent):
 *   - 300 cashier accounts  qa-load-1..300@example.com  (password: secret123), spread over the 5 QA branches
 *   - 1 back-office Owner   qa-load-owner@example.com
 *   - 300 "hot" items with a huge stock in every branch, so sales never fail because stock ran out
 * Prints a JSON manifest (users, hot items, sample customers/bills) consumed by load_test.mjs.
 */
foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'urban_pos_qa', 'APP_ENV' => 'local', 'LOG_CHANNEL' => 'null'] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $_SERVER[$k] = $v;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! str_ends_with((string) config('database.connections.mysql.database'), '_qa') || (int) config('database.connections.mysql.port') !== 3307) {
    fwrite(STDERR, "REFUSING: not the dedicated QA instance\n");
    exit(2);
}

use App\Models\{Branch, GstTax, Item, ItemStock, User};
use Illuminate\Support\Facades\{DB, Hash};

$users = (int) ($argv[1] ?? 300);
$branchIds = Branch::where('name', 'like', 'QA %')->orderBy('id')->pluck('id')->all();
$hash = Hash::make('secret123');   // one hash reused: the login-time bcrypt cost is still paid on every login
$now = now();

$existing = User::where('email', 'like', 'qa-load-%')->pluck('id', 'email')->all();
$rows = [];
for ($i = 1; $i <= $users; $i++) {
    $email = "qa-load-$i@example.com";
    if (isset($existing[$email])) {
        continue;
    }
    $rows[] = ['name' => "QA Cashier $i", 'email' => $email, 'password' => $hash, 'branch_id' => $branchIds[($i - 1) % count($branchIds)], 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now];
}
foreach (array_chunk($rows, 100) as $chunk) {
    User::insert($chunk);
}
$cashiers = User::where('email', 'like', 'qa-load-%')->where('email', '!=', 'qa-load-owner@example.com')->get();
$roleId = Spatie\Permission\Models\Role::where('name', 'Cashier')->value('id');
foreach ($cashiers as $u) {
    DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $u->id]);
}
$owner = User::firstOrCreate(['email' => 'qa-load-owner@example.com'], ['name' => 'QA Load Owner', 'password' => $hash, 'branch_id' => null, 'email_verified_at' => $now]);
$owner->assignRole('Owner');

// hot items
$gst = GstTax::where('percentage', 18)->first();
$hotItems = [];
for ($i = 1; $i <= 300; $i++) {
    $item = Item::firstOrCreate(['item_code' => 'LOADHOT'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)], [
        'ean_upc_code' => '8801'.str_pad((string) $i, 9, '0', STR_PAD_LEFT), 'name' => "Load Hot Item $i", 'product_type' => 'Standard', 'cost_price' => 60, 'landing_cost' => 60,
        'sell_price' => 100, 'mrp' => 120, 'status' => true, 'tax_inclusive' => false, 'batch_expiry_details' => 'Not Required', 'allow_negative_stock' => false, 'gst_tax_id' => $gst->id,
    ]);
    foreach ($branchIds as $b) {
        ItemStock::updateOrCreate(['item_id' => $item->id, 'branch_id' => $b], ['quantity' => 5000000, 'cost_price' => 60]);
    }
    $hotItems[] = ['id' => $item->id, 'code' => $item->item_code, 'ean' => $item->ean_upc_code];
}

$custMin = (int) DB::table('customers')->where('name', 'like', 'QA Cust%')->min('id');
$custCount = (int) DB::table('customers')->where('name', 'like', 'QA Cust%')->count();
$supplierId = (int) DB::table('suppliers')->min('id');
$tender = (int) DB::table('tender_types')->where('name', 'Cash')->value('id');

$custIds = DB::table('customers')->where('name', 'like', 'QA Cust%')->inRandomOrder()->limit(3000)->pluck('id')->all();

echo json_encode([ 'customer_ids' => $custIds,
    'password' => 'secret123', 'users' => $cashiers->map(fn ($u) => ['email' => $u->email, 'branch_id' => $u->branch_id])->values(), 'owner' => 'qa-load-owner@example.com',
    'hot_items' => $hotItems, 'customer_min' => $custMin, 'customer_count' => $custCount, 'supplier_id' => $supplierId, 'cash_tender_id' => $tender, 'branch_ids' => $branchIds,
], JSON_UNESCAPED_SLASHES);
