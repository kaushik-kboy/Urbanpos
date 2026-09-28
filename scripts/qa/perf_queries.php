<?php
/**
 * Endpoint + query performance at a given data volume, through the REAL HTTP kernel (routes, middleware,
 * controllers, Eloquent, Blade) against the dedicated QA MySQL instance.
 *
 *   php scripts/qa/perf_queries.php [label] [iterations=25]
 *
 * For every endpoint: warm-up, then N measured runs with rotating realistic parameters (random item
 * codes / barcodes / customers / bills). Reports p50/p95/p99 wall time, DB query count (N+1 hint),
 * DB time, peak memory, and EXPLAINs any query slower than 100 ms. Results also go to
 * scripts/qa/results/perf_<label>.json so tiers can be compared.
 */
foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'urban_pos_qa', 'APP_ENV' => 'testing',
    'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'] as $k => $v) {
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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$label = $argv[1] ?? 'run';
$iters = (int) ($argv[2] ?? 25);
mt_srand(12345);

$user = App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'Owner'))->where('email', 'qa-perf@example.com')->first();
if (! $user) {
    $user = App\Models\User::factory()->create(['email' => 'qa-perf@example.com', 'branch_id' => null]);
    $user->assignRole('Owner');
}
$branchId = (int) DB::table('branches')->where('name', 'like', 'QA %')->min('id');
$counts = [
    'items' => (int) DB::table('items')->where('item_code', 'like', 'QAI%')->count(),
    'customers' => (int) DB::table('customers')->where('name', 'like', 'QA Cust%')->count(),
    'bills' => (int) DB::table('sales_bills')->where('bill_number', 'like', 'QB-%')->count(),
    'purchases' => (int) DB::table('purchase_invoices')->where('invoice_number', 'like', 'QP-%')->count(),
    'returned_bills' => (int) DB::table('sales_returns')->where('return_number', 'like', 'QR-%')->count(),
];
$totalRows = 0;
foreach (['items', 'customers', 'suppliers', 'item_stocks', 'sales_bills', 'sales_bill_items', 'stock_ledger', 'purchase_invoices', 'purchase_invoice_items', 'sales_returns', 'sales_return_items'] as $t) {
    $totalRows += (int) DB::table($t)->count();
}
$firstBill = (int) DB::table('sales_bills')->where('bill_number', 'like', 'QB-%')->min('id');
$firstCust = (int) DB::table('customers')->where('name', 'like', 'QA Cust%')->min('id');
$firstPI = (int) DB::table('purchase_invoices')->where('invoice_number', 'like', 'QP-%')->min('id');
$returnedBillIds = DB::table('sales_returns')->where('return_number', 'like', 'QR-%')->orderBy('id')->limit(500)->pluck('sales_bill_id')->all();

$rnd = fn (int $max) => mt_rand(1, max(1, $max));
$words = ['Royal', 'Pedigree Puppy', 'Chew Toy', 'Sheba', 'Orijen Litter', 'Whiskas Adult', 'Kong', 'Vitamin', 'Drools 5kg', 'Shampoo'];
$surnames = ['Shah', 'Patel', 'Mehta', 'Desai', 'Joshi', 'Trivedi', 'Parmar', 'Modi'];
$to = now()->toDateString();
$from30 = now()->subDays(30)->toDateString();
$from365 = now()->subDays(365)->toDateString();

/** name => [uri, params-generator] */
$endpoints = [
    // ---- the keystroke-level POS lookups (must stay fast at any scale) ----
    'POS barcode lookup (exact EAN)' => ['/sales/sales-bills/lookup-item', fn () => ['query' => '890'.str_pad((string) $rnd($counts['items']), 10, '0', STR_PAD_LEFT), 'exact_match_only' => 1, 'branch_id' => $branchId]],
    'POS item-code lookup (exact)' => ['/sales/sales-bills/lookup-item', fn () => ['query' => 'QAI'.str_pad((string) $rnd($counts['items']), 7, '0', STR_PAD_LEFT), 'exact_match_only' => 1, 'branch_id' => $branchId]],
    'POS invalid barcode (miss)' => ['/sales/sales-bills/lookup-item', fn () => ['query' => '8909999'.mt_rand(100000, 999999), 'exact_match_only' => 1, 'branch_id' => $branchId]],
    'POS item search popup (name)' => ['/sales/sales-bills/item-list', fn () => ['search' => $words[mt_rand(0, 9)], 'branch_id' => $branchId]],
    'POS item search popup (code prefix)' => ['/sales/sales-bills/item-list', fn () => ['search' => 'QAI00'.mt_rand(10, 99), 'branch_id' => $branchId]],
    'POS item popup, empty (open popup)' => ['/sales/sales-bills/item-list', fn () => ['branch_id' => $branchId]],
    'Customer search (name substring)' => ['/sales/sales-bills/customer-search', fn () => ['q' => $surnames[mt_rand(0, 7)]]],
    'Customer search (mobile fragment)' => ['/sales/sales-bills/customer-search', fn () => ['q' => (string) mt_rand(10000, 99999)]],
    'Customer search (exact code)' => ['/sales/sales-bills/customer-search', fn () => ['q' => 'C'.str_pad((string) $rnd($counts['customers']), 7, '0', STR_PAD_LEFT)]],
    // ---- return workflows ----
    'Sales return: customer bills' => ['GENERATED', fn () => ['__uri' => '/sales/sales-returns/customer-bills/'.($firstCust + $rnd($counts['customers']) - 1)]],
    'Sales return: bill items (+remaining)' => ['GENERATED', fn () => ['__uri' => '/sales/sales-returns/bill-items/'.($returnedBillIds[array_rand($returnedBillIds)] ?? $firstBill)]],
    'Purchase return: invoice items' => ['GENERATED', fn () => ['__uri' => '/purchase/purchase-returns/invoice-items/'.($firstPI + $rnd($counts['purchases']) - 1)]],
    // ---- history / search ----
    'Invoice list (page 1, no filter)' => ['/sales/sales-bills', fn () => ['branch_id' => $branchId]],
    'Invoice list (deep page 500)' => ['/sales/sales-bills', fn () => ['branch_id' => $branchId, 'page' => 500]],
    'Invoice search (bill number)' => ['/sales/sales-bills', fn () => ['search' => 'QB-'.str_pad((string) $rnd($counts['bills']), 8, '0', STR_PAD_LEFT), 'branch_id' => $branchId]],
    'Invoice search (customer name)' => ['/sales/sales-bills', fn () => ['search' => $surnames[mt_rand(0, 7)], 'search_column' => 'customer_name', 'branch_id' => $branchId]],
    'Invoice list (30-day date filter)' => ['/sales/sales-bills', fn () => ['date_from' => $from30, 'date_to' => $to, 'branch_id' => $branchId]],
    'Purchase history (page 1)' => ['/purchase/purchase-invoices', fn () => ['branch_id' => $branchId]],
    'Master: items list (search)' => ['/master/items', fn () => ['search' => $words[mt_rand(0, 9)]]],
    'Master: customers list (search)' => ['/master/customers', fn () => ['search' => $surnames[mt_rand(0, 7)]]],
    // ---- reports ----
    'Report: current stock' => ['/reports/current-stock', fn () => ['branch_id' => $branchId]],
    'Report: GST sales summary (30d)' => ['/reports/gst-sales-summary', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: GST sales summary (1y)' => ['/reports/gst-sales-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: GST purchase summary (1y)' => ['/reports/gst-purchase-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: sales summary (30d)' => ['/reports/sales-summary', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: billwise sales (7d)' => ['/reports/billwise-sales', fn () => ['from' => now()->subDays(7)->toDateString(), 'to' => $to, 'branch_id' => $branchId]],
    'Report: sales margin itemwise (30d)' => ['/reports/sales-margin-itemwise', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: sales return summary (1y)' => ['/reports/sales-return-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: reorder' => ['/reports/reorder-report', fn () => ['branch_id' => $branchId]],
    // ---- Priority 5 additions: remaining potentially-unbounded reports ----
    'Report: sales summary (1y)' => ['/reports/sales-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: purchase detail (30d)' => ['/reports/purchase-detail', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: purchase detail (1y)' => ['/reports/purchase-detail', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: tender summary (1y)' => ['/reports/tender-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: sales margin category (30d)' => ['/reports/sales-margin-category', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: sales margin category (1y)' => ['/reports/sales-margin-category', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Report: EOD (30d)' => ['/reports/eod', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Report: quotation/order summary (1y)' => ['/reports/quotation-order-summary', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Finance: day book (30d)' => ['/finance/reports/day-book', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Finance: day book (all 3y, all journals)' => ['/finance/reports/day-book', fn () => ['from' => '2023-01-01', 'to' => $to, 'branch_id' => $branchId]],
    'Finance: cash/bank book (all 3y)' => ['/finance/reports/cash-bank-book', fn () => ['from' => '2023-01-01', 'to' => $to, 'branch_id' => $branchId]],
    'Finance: trial balance' => ['/finance/reports/trial-balance', fn () => ['as_of' => $to]],
    'Finance: profit & loss (1y)' => ['/finance/reports/profit-loss', fn () => ['from' => $from365, 'to' => $to, 'branch_id' => $branchId]],
    'Finance: cash/bank book (30d)' => ['/finance/reports/cash-bank-book', fn () => ['from' => $from30, 'to' => $to, 'branch_id' => $branchId]],
    'Finance: outstanding aging (customers)' => ['/finance/reports/outstanding-aging', fn () => ['party_type' => 'Customer', 'as_of_date' => $to, 'branch_id' => $branchId]],
];

// A runaway report must show up as a failure in the table, not hang the run.
DB::statement('SET SESSION MAX_EXECUTION_TIME=30000');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app['auth']->guard('web')->setUser($user);

$slowSql = [];
$rows = [];
$state = ['queries' => 0, 'dbms' => 0.0, 'name' => '', 'measure' => false];
DB::listen(function ($q) use (&$state, &$slowSql) {
    if (getenv('DEBUGSQL')) { fwrite(STDERR, sprintf("[%.0fms] %s
", $q->time, substr($q->sql, 0, 160))); }
    $state['queries']++;
    $state['dbms'] += $q->time;
    if ($q->time > 100 && $state['measure']) {
        $slowSql[$state['name']][md5($q->sql)] = ['ms' => $q->time, 'sql' => $q->sql, 'bindings' => $q->bindings];
    }
});
printf("\nPerformance @ %s   [%s total rows; %s bills, %s items, %s customers]   iterations=%d\n", $label, number_format($totalRows), number_format($counts['bills']), number_format($counts['items']), number_format($counts['customers']), $iters);
printf("%-40s %8s %8s %8s %6s %8s %7s  %s\n", 'endpoint', 'p50 ms', 'p95 ms', 'p99 ms', 'SQLs', 'db ms', 'MB', 'status');
echo str_repeat('-', 112)."\n";

foreach ($endpoints as $name => [$uriTpl, $gen]) {
    if (getenv('ONLY') && ! str_contains($name, getenv('ONLY'))) { continue; }
    $times = $dbTimes = $qcounts = $mem = [];
    $statuses = [];
    $runs = $iters + 3; // 3 warm-ups discarded
    for ($i = 0; $i < $runs; $i++) {
        $params = $gen();
        $uri = $uriTpl === 'GENERATED' ? $params['__uri'] : $uriTpl;
        unset($params['__uri']);
        $state = ['queries' => 0, 'dbms' => 0.0, 'name' => $name, 'measure' => $i >= 3];
        gc_collect_cycles();
        $t = microtime(true);
        try {
            $req = Request::create($uri, 'GET', $params, [], [], ['HTTP_ACCEPT' => 'application/json, text/html']);
            $resp = $kernel->handle($req);
            $status = $resp->getStatusCode();
            $resp->getContent();
        } catch (Throwable $e) {
            $status = str_contains($e->getMessage(), 'maximum statement execution time') ? 'TIMEOUT>30s' : 'EXC:'.substr(get_class($e), -20);
        }
        $ms = (microtime(true) - $t) * 1000;
        if ($i >= 3) {
            $times[] = $ms;
            $dbTimes[] = $state['dbms'];
            $qcounts[] = $state['queries'];
            $mem[] = memory_get_peak_usage(true) / 1048576;
            $statuses[(string) $status] = ($statuses[(string) $status] ?? 0) + 1;
        }
    }
    sort($times);
    $pct = fn (array $a, float $p) => $a[(int) min(count($a) - 1, max(0, ceil($p / 100 * count($a)) - 1))];
    $row = ['endpoint' => $name, 'p50' => round($pct($times, 50), 1), 'p95' => round($pct($times, 95), 1), 'p99' => round($pct($times, 99), 1),
        'sqls' => (int) round(array_sum($qcounts) / count($qcounts)), 'db_ms' => round(array_sum($dbTimes) / count($dbTimes), 1), 'mb' => round(max($mem), 0), 'status' => $statuses];
    $rows[] = $row;
    printf("%-40s %8.1f %8.1f %8.1f %6d %8.1f %7.0f  %s\n", $name, $row['p50'], $row['p95'], $row['p99'], $row['sqls'], $row['db_ms'], $row['mb'], json_encode($statuses));
}

if ($slowSql) {
    echo "\nSlow queries (>100 ms) with EXPLAIN:\n";
    foreach ($slowSql as $endpoint => $qs) {
        foreach ($qs as $q) {
            printf("\n[%s] %.0f ms\n  %s\n", $endpoint, $q['ms'], preg_replace('/\s+/', ' ', substr($q['sql'], 0, 400)));
            try {
                $plan = DB::select('EXPLAIN '.$q['sql'], $q['bindings']);
                foreach ($plan as $p) {
                    printf("    table=%-22s type=%-6s key=%-30s rows=%-10s extra=%s\n", $p->table ?? '-', $p->type ?? '-', $p->key ?? 'NULL', $p->rows ?? '-', $p->Extra ?? '');
                }
            } catch (Throwable $e) {
                echo '    (explain failed: '.substr($e->getMessage(), 0, 100).")\n";
            }
        }
    }
}

@mkdir(__DIR__.'/results', 0777, true);
file_put_contents(__DIR__."/results/perf_$label.json", json_encode(['label' => $label, 'total_rows' => $totalRows, 'counts' => $counts, 'rows' => $rows], JSON_PRETTY_PRINT));
echo "\nSaved scripts/qa/results/perf_$label.json\n";
