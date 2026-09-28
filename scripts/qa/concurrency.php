<?php
/**
 * Real multi-process concurrency harness for the POS (Windows-safe: no pcntl).
 *
 *   php scripts/qa/concurrency.php run <scenario|all> [workers=8]
 *
 * Every worker is a SEPARATE php process with its own MySQL connection, released at the same
 * wall-clock instant (start barrier), and sends a real HTTP-kernel request through the full
 * middleware stack. Afterwards DB invariants are checked. Runs ONLY against a *_qa database.
 *
 * Scenarios:
 *   oversell          stock=10, N terminals each sell 7 (distinct posting keys)   -> exactly 1 wins
 *   duplicate_sale    N submissions of the SAME posting_key                       -> exactly 1 bill
 *   return_race       bill qty 5, N terminals each return 5                       -> total returned <= 5
 *   duplicate_return  N submissions of the SAME sales-return posting_key          -> exactly 1 return
 *   preturn_race      purchase qty 10, N terminals each return 10                 -> total returned <= 10
 *   purchase_race     N terminals post purchases of 5                             -> stock = 5*N (no lost update)
 */

const QA_DB_SUFFIX = '_qa';

function qa_env(): void
{
    foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'urban_pos_qa', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'APP_ENV' => 'testing',
        'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'] as $k => $v) {
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

function qa_boot()
{
    qa_env();
    require_once __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db = config('database.connections.'.config('database.default').'.database');
    if (! str_ends_with((string) $db, QA_DB_SUFFIX)) {
        fwrite(STDERR, "REFUSING: database '$db' is not a *_qa database\n");
        exit(2);
    }

    return $app;
}

// ------------------------------------------------------------------ WORKER
if (($argv[1] ?? '') === 'worker') {
    [, , $file, $startAt, $idx] = $argv;
    $job = json_decode(file_get_contents($file), true)['workers'][(int) $idx];
    $app = qa_boot();
    $user = App\Models\User::find($job['user_id']);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    // Warm the app (route cache, config, autoload) BEFORE the barrier so timing is about the DB, not boot cost.
    $app['auth']->guard('web')->setUser($user);
    while (microtime(true) < (float) $startAt) {
        usleep(200);
    }
    $t0 = microtime(true);
    try {
        $req = Illuminate\Http\Request::create($job['uri'], 'POST', $job['payload'], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $resp = $kernel->handle($req);
        $status = $resp->getStatusCode();
        $body = substr((string) $resp->getContent(), 0, 300);
    } catch (Throwable $e) {
        $status = 500;
        $body = get_class($e).': '.substr($e->getMessage(), 0, 250);
    }
    echo json_encode(['idx' => (int) $idx, 'status' => $status, 'ms' => round((microtime(true) - $t0) * 1000), 'body' => $body]);
    exit(0);
}

// ------------------------------------------------------------------ ORCHESTRATOR
use App\Models\{Branch, Customer, GstTax, Item, ItemStock, PurchaseInvoice, PurchaseInvoiceItem, SalesBill, SalesBillItem, Supplier, TenderType, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$scenarioArg = $argv[2] ?? 'all';
$N = (int) ($argv[3] ?? 8);
$app = qa_boot();
$run = substr(md5((string) microtime(true)), 0, 6);

function fixtures(string $run): array
{
    $branch = Branch::firstOrCreate(['name' => 'QA Branch'], ['state' => 'Gujarat']);
    $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Owner');
    $customer = Customer::create(['name' => "QA Cust $run", 'mobile' => '9'.random_int(100000000, 999999999), 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 0]);
    $supplier = Supplier::create(['name' => "QA Supp $run", 'phone' => '9'.random_int(100000000, 999999999), 'state' => 'Gujarat', 'credit_limit' => 0]);

    return compact('branch', 'gst', 'user', 'customer', 'supplier');
}

function makeItem(string $code, $gst, float $stock, int $branchId, bool $negOk = false): Item
{
    $item = Item::create(['name' => "QA $code", 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60, 'landing_cost' => 60,
        'gst_tax_id' => $gst->id, 'tax_inclusive' => false, 'status' => true, 'allow_negative_stock' => $negOk, 'batch_expiry_details' => 'Not Required']);
    ItemStock::create(['item_id' => $item->id, 'branch_id' => $branchId, 'quantity' => $stock, 'cost_price' => 60]);

    return $item;
}

function saleLine($item, $qty): array
{
    return ['item_id' => $item->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 120, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18];
}

function launch(array $workers, int $N): array
{
    $file = sys_get_temp_dir().'/qa_conc_'.uniqid().'.json';
    file_put_contents($file, json_encode(['workers' => $workers]));
    $startAt = microtime(true) + 6.0; // generous: workers boot the framework, then spin until this instant
    $procs = [];
    foreach ($workers as $i => $_) {
        $cmd = [PHP_BINARY, __FILE__, 'worker', $file, (string) $startAt, (string) $i];
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[$i] = [$p, $pipes];
    }
    $results = [];
    foreach ($procs as $i => [$p, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($p);
        $results[$i] = json_decode($out, true) ?? ['idx' => $i, 'status' => 'CRASH', 'body' => trim($out.' '.$err)];
    }
    @unlink($file);

    return $results;
}

function summarize(array $results): string
{
    $by = [];
    foreach ($results as $r) {
        $by[$r['status']] = ($by[$r['status']] ?? 0) + 1;
    }
    ksort($by);
    $lat = array_column($results, 'ms');
    sort($lat);
    if (getenv('QA_DEBUG')) {
        echo '  sample bodies: '.json_encode(array_slice(array_unique(array_column($results, 'body')), 0, 3))."\n";
    }

    return json_encode($by).' latency_ms(min/med/max)='.($lat[0] ?? '-').'/'.($lat[intdiv(count($lat), 2)] ?? '-').'/'.(end($lat) ?: '-');
}

$report = [];
function check(string $name, bool $ok, string $detail): void
{
    global $report;
    $report[] = [$name, $ok];
    echo ($ok ? '  PASS ' : '  FAIL ').$name.' — '.$detail."\n";
}

$scenarios = [
    'oversell' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("OV-$run", $f['gst'], 10, $f['branch']->id);
        $tender = TenderType::first();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-bills', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $f['customer']->id,
                'branch_id' => $f['branch']->id, 'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
                'items' => [saleLine($item, 7)], 'payments' => [['tender_type_id' => $tender->id, 'amount' => 700]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        $sold = (float) SalesBillItem::where('item_id', $item->id)->sum('qty');
        $bills = SalesBillItem::where('item_id', $item->id)->count();
        check('oversell: exactly one 7-unit sale accepted', $bills === 1 && $sold == 7.0, "bills=$bills sold=$sold");
        check('oversell: stock never negative and == 10-sold', $stock >= 0 && $stock == 10 - $sold, "stock=$stock");
        check('oversell: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'Exception')))));
    },

    'duplicate_sale' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("DS-$run", $f['gst'], 100, $f['branch']->id);
        $tender = TenderType::first();
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-bills', 'payload' => [
                'posting_key' => $key, 'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $f['customer']->id,
                'branch_id' => $f['branch']->id, 'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
                'items' => [saleLine($item, 2)], 'payments' => [['tender_type_id' => $tender->id, 'amount' => 200]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $bills = SalesBill::where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_sale: exactly one bill for one posting_key', $bills === 1, "bills=$bills");
        check('duplicate_sale: stock deducted once (100-2)', $stock == 98.0, "stock=$stock");
        check('duplicate_sale: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    'return_race' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("RR-$run", $f['gst'], 0, $f['branch']->id, true);
        $bill = SalesBill::create(['bill_number' => "QA-RR-$run", 'bill_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
            'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 500, 'status' => 'Posted']); // total == the single line's net_amount (500): these fixtures test return quantity races, not GST/total reconciliation
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 5, 'sell_price' => 100, 'net_amount' => 500]);
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-returns', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
                'sales_bill_id' => $bill->id, 'return_mode' => 'Cash', 'sales_type' => 'Local', 'items' => [saleLine($item, 5)]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $returned = (float) DB::table('sales_return_items as i')->join('sales_returns as r', 'r.id', '=', 'i.sales_return_id')->where('r.sales_bill_id', $bill->id)->sum('i.qty');
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('return_race: total returned <= sold qty (5)', $returned <= 5.0, "returned=$returned (sold 5)");
        check('return_race: stock increased only by accepted returns', $stock == $returned, "stock=$stock returned=$returned");
    },

    'duplicate_return' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("DR-$run", $f['gst'], 0, $f['branch']->id, true);
        $bill = SalesBill::create(['bill_number' => "QA-DR-$run", 'bill_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
            'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 5000, 'status' => 'Posted']); // total == the single line's net_amount (5000, qty 50 x 100)
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 50, 'sell_price' => 100, 'net_amount' => 5000]);
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-returns', 'payload' => [
                'posting_key' => $key, 'return_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
                'sales_bill_id' => $bill->id, 'return_mode' => 'Cash', 'sales_type' => 'Local', 'items' => [saleLine($item, 1)]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $n = DB::table('sales_returns')->where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_return: exactly one return for one posting_key', $n === 1, "returns=$n");
        check('duplicate_return: stock +1 once', $stock == 1.0, "stock=$stock");
        check('duplicate_return: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    'nobill_return_race' => function () use ($N, $run) {
        // No original bill: the customer-wide pool (bought 5) must cap total returned at 5 even with N terminals at once.
        $f = fixtures($run);
        $item = makeItem("NB-$run", $f['gst'], 0, $f['branch']->id, true);
        $bill = SalesBill::create(['bill_number' => "QA-NB-$run", 'bill_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
            'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 500, 'status' => 'Posted']); // total == the single line's net_amount (500): these fixtures test return quantity races, not GST/total reconciliation
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 5, 'sell_price' => 100, 'net_amount' => 500]);
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-returns', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
                'return_mode' => 'Cash', 'sales_type' => 'Local', 'items' => [saleLine($item, 5)]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."
";
        $returned = (float) DB::table('sales_return_items as i')->join('sales_returns as r', 'r.id', '=', 'i.sales_return_id')->where('r.customer_id', $f['customer']->id)->sum('i.qty');
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('nobill_return_race: total returned <= customer pool (5)', $returned <= 5.0, "returned=$returned (bought 5)");
        check('nobill_return_race: stock rose only by accepted returns', $stock == $returned, "stock=$stock returned=$returned");
        check('nobill_return_race: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), '');
    },

    // Priority 8: PCOV coverage flagged PurchaseReturnController::store()'s two race-recovery paths (with an
    // invoice lock, and the no-invoice unique-constraint catch) as untested. duplicate_sale/duplicate_return
    // already prove this PATTERN works for sales bills/sales returns; these two scenarios prove it for
    // purchase returns specifically, with genuine multi-process concurrency (not a same-process simulation).
    'duplicate_purchase_return' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("DPR-$run", $f['gst'], 100, $f['branch']->id);
        $inv = PurchaseInvoice::create(['invoice_number' => "QA-DPR-$run", 'invoice_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id,
            'branch_id' => $f['branch']->id, 'purchase_type' => 'Local', 'status' => 'Posted', 'total' => 1180, 'total_gst' => 180, 'total_qty' => 10]);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'qty' => 10, 'cost_price' => 100, 'gst_percent' => 18, 'disc_percent' => 0, 'disc_amount' => 0, 'total' => 1180]);
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/purchase/purchase-returns', 'payload' => [
                'posting_key' => $key, 'return_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id, 'branch_id' => $f['branch']->id,
                'purchase_invoice_id' => $inv->id, 'purchase_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 2, 'cost_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $n = DB::table('purchase_returns')->where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_purchase_return: exactly one return for one posting_key', $n === 1, "returns=$n");
        check('duplicate_purchase_return: stock reduced once (100-2)', $stock == 98.0, "stock=$stock");
        check('duplicate_purchase_return: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    'duplicate_purchase_return_no_invoice' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = Item::create(['name' => "QA DPRNI-$run", 'item_code' => "DPRNI-$run", 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60, 'landing_cost' => 60,
            'gst_tax_id' => $f['gst']->id, 'tax_inclusive' => false, 'status' => true, 'supplier_id' => $f['supplier']->id, 'batch_expiry_details' => 'Not Required']);
        ItemStock::create(['item_id' => $item->id, 'branch_id' => $f['branch']->id, 'quantity' => 100, 'cost_price' => 60]);
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/purchase/purchase-returns', 'payload' => [
                'posting_key' => $key, 'return_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id, 'branch_id' => $f['branch']->id,
                'purchase_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 2, 'cost_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $n = DB::table('purchase_returns')->where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_purchase_return_no_invoice: exactly one return for one posting_key (unique-index recovery)', $n === 1, "returns=$n");
        check('duplicate_purchase_return_no_invoice: stock reduced once (100-2)', $stock == 98.0, "stock=$stock");
        check('duplicate_purchase_return_no_invoice: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    // Priority 8: SalesReturnController's no-bill unique-constraint catch (nobill_return_race above uses a
    // DISTINCT posting_key per worker to test the quantity ceiling; this uses the SAME posting_key to test
    // idempotency specifically, which nobill_return_race does not exercise).
    'duplicate_return_no_bill' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("DRNB-$run", $f['gst'], 0, $f['branch']->id, true);
        $bill = SalesBill::create(['bill_number' => "QA-DRNB-$run", 'bill_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
            'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 500, 'status' => 'Posted']);
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 5, 'sell_price' => 100, 'net_amount' => 500]);
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/sales/sales-returns', 'payload' => [
                'posting_key' => $key, 'return_date' => now()->toDateString(), 'customer_id' => $f['customer']->id, 'branch_id' => $f['branch']->id,
                'return_mode' => 'Cash', 'sales_type' => 'Local', 'items' => [saleLine($item, 1)]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $n = DB::table('sales_returns')->where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_return_no_bill: exactly one return for one posting_key (unique-index recovery)', $n === 1, "returns=$n");
        check('duplicate_return_no_bill: stock rose once (0+1)', $stock == 1.0, "stock=$stock");
        check('duplicate_return_no_bill: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    'preturn_race' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("PR-$run", $f['gst'], 100, $f['branch']->id);
        $inv = PurchaseInvoice::create(['invoice_number' => "QA-PI-$run", 'invoice_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id,
            'branch_id' => $f['branch']->id, 'purchase_type' => 'Local', 'status' => 'Posted', 'total' => 1180, 'total_gst' => 180, 'total_qty' => 10]);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'qty' => 10, 'cost_price' => 100, 'gst_percent' => 18, 'disc_percent' => 0, 'disc_amount' => 0, 'total' => 1180]);
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/purchase/purchase-returns', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id, 'branch_id' => $f['branch']->id,
                'purchase_invoice_id' => $inv->id, 'purchase_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 10, 'cost_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $returned = (float) DB::table('purchase_return_items as i')->join('purchase_returns as r', 'r.id', '=', 'i.purchase_return_id')->where('r.purchase_invoice_id', $inv->id)->sum('i.qty');
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('preturn_race: total returned <= purchased qty (10)', $returned <= 10.0, "returned=$returned (purchased 10)");
        check('preturn_race: stock reduced only by accepted returns', $stock == 100 - $returned, "stock=$stock returned=$returned");
    },

    'purchase_race' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("PU-$run", $f['gst'], 0, $f['branch']->id);
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/purchase/purchase-invoices', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'invoice_number' => "QA-PU-$run-$i", 'c_form' => 'No Forms', 'invoice_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id,
                'branch_id' => $f['branch']->id, 'purchase_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 5, 'cost_price' => 60, 'mrp' => 120, 'sell_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $ok = count(array_filter($res, fn ($r) => in_array($r['status'], [200, 201, 302], true)));
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('purchase_race: no lost updates (stock == 5 x accepted)', $stock == 5.0 * $ok, "stock=$stock accepted=$ok");
        check('purchase_race: all purchases accepted', $ok === $N, "accepted=$ok/$N ".json_encode(array_slice(array_column($res, 'body'), 0, 1)));
    },

    // Priority 7 item 5 "same invoice submission repeated": the Priority 4 posting_key fix on
    // PurchaseInvoiceController, exercised as a genuine multi-process race (not the sequential
    // request-then-retry that DuplicateSubmitGuardRegressionTest already covers).
    'duplicate_purchase_invoice' => function () use ($N, $run) {
        $f = fixtures($run);
        $item = makeItem("DP-$run", $f['gst'], 0, $f['branch']->id);
        $key = (string) Str::uuid();
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $f['user']->id, 'uri' => '/purchase/purchase-invoices', 'payload' => [
                'posting_key' => $key, 'invoice_number' => "QA-DP-$run", 'c_form' => 'No Forms', 'invoice_date' => now()->toDateString(), 'supplier_id' => $f['supplier']->id,
                'branch_id' => $f['branch']->id, 'purchase_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 5, 'cost_price' => 60, 'mrp' => 120, 'sell_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $invoices = PurchaseInvoice::where('posting_key', $key)->count();
        $stock = (float) ItemStock::where('item_id', $item->id)->where('branch_id', $f['branch']->id)->value('quantity');
        check('duplicate_purchase_invoice: exactly one invoice for one posting_key', $invoices === 1, "invoices=$invoices");
        check('duplicate_purchase_invoice: stock posted once (0+5)', $stock == 5.0, "stock=$stock");
        check('duplicate_purchase_invoice: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },

    // Priority 7 item 6: N branches each post a bill in the SAME instant. Checks the numbering/stock
    // scoping that's per-branch by design doesn't race or leak across branches under real concurrency.
    'multi_branch_bills' => function () use ($N, $run) {
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $tender = TenderType::first();
        $branches = []; $items = []; $users = []; $customers = [];
        for ($i = 0; $i < $N; $i++) {
            $branches[$i] = Branch::create(['name' => "QA MB Branch $run-$i", 'state' => 'Gujarat']);
            $items[$i] = makeItem("MB-$run-$i", $gst, 20, $branches[$i]->id);
            $users[$i] = User::factory()->create(['branch_id' => $branches[$i]->id]);
            $users[$i]->assignRole('Owner');
            $customers[$i] = Customer::create(['name' => "QA MB Cust $run-$i", 'mobile' => '9'.random_int(100000000, 999999999), 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 0]);
        }
        $workers = [];
        for ($i = 0; $i < $N; $i++) {
            $workers[] = ['user_id' => $users[$i]->id, 'uri' => '/sales/sales-bills', 'payload' => [
                'posting_key' => (string) Str::uuid(), 'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $customers[$i]->id,
                'branch_id' => $branches[$i]->id, 'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
                'items' => [saleLine($items[$i], 3)], 'payments' => [['tender_type_id' => $tender->id, 'amount' => 300]]]];
        }
        $res = launch($workers, $N);
        echo '  '.summarize($res)."\n";
        $branchIds = array_map(fn ($b) => $b->id, $branches);
        $bills = SalesBill::whereIn('branch_id', $branchIds)->get(['id', 'bill_number', 'branch_id']);
        $ok = count(array_filter($res, fn ($r) => in_array($r['status'], [200, 201, 302], true)));
        $uniqueNumbers = $bills->pluck('bill_number')->unique()->count();
        $stockOk = true;
        foreach ($branchIds as $idx => $bid) {
            $stock = (float) ItemStock::where('item_id', $items[$idx]->id)->where('branch_id', $bid)->value('quantity');
            $stockOk = $stockOk && $stock == 17.0; // 20 - 3, only that branch's own sale touches it
        }
        check('multi_branch_bills: every branch got exactly one bill', $bills->count() === $N && $ok === $N, "bills=".$bills->count()." accepted=$ok/$N");
        check('multi_branch_bills: bill numbers unique across branches', $uniqueNumbers === $bills->count(), "unique=$uniqueNumbers of {$bills->count()}");
        check('multi_branch_bills: each branch stock deducted only by its own sale (20-3)', $stockOk, 'per-branch stock mismatch — see items above');
        check('multi_branch_bills: no 5xx errors', ! array_filter($res, fn ($r) => (int) $r['status'] >= 500 || $r['status'] === 'CRASH'), json_encode(array_values(array_filter(array_column($res, 'body'), fn ($b) => str_contains((string) $b, 'xception')))));
    },
];

$selected = $scenarioArg === 'all' ? array_keys($scenarios) : [$scenarioArg];
foreach ($selected as $name) {
    echo "\n== $name (workers=$N)\n";
    $scenarios[$name]();
}
$fails = count(array_filter($report, fn ($r) => ! $r[1]));
echo "\nRESULT: ".(count($report) - $fails).' passed, '.$fails." failed\n";
exit($fails ? 1 : 0);
