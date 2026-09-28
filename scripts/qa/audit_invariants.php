<?php
/**
 * Priority 7 post-test integrity audit. Runs entirely in SQL (never N+1 loops) against a *_qa database, so it's
 * safe to run after every load test or concurrency scenario regardless of data volume.
 *
 *   php scripts/qa/audit_invariants.php [--json]
 *
 * Checks (each is a business invariant that must hold no matter how much concurrent traffic hit the app):
 *   1. No negative stock (except items explicitly flagged allow_negative_stock)
 *   2. No duplicate bill numbers (sales_bills, purchase_invoices, sales_returns, purchase_returns — each has
 *      its own unique numbering column; a duplicate here means the atomic nextNumber()/nextPrefixed() broke)
 *   3. No duplicate posting_key (DB-unique already, this is a defense-in-depth re-check, not a discovery mechanism)
 *   4. No excess returns (sales + purchase): SUM(returned qty) per (document, item) must not exceed sold/bought qty
 *   5. No orphan bill lines (sales_bill_items / purchase_invoice_items whose parent id doesn't exist)
 *   6. No bill without payment (a Posted, non-Draft/Cancelled sales bill with neither a payments row nor a
 *      payment_type — i.e. genuinely no record of how it was paid)
 *   7. Bill total = line totals: sales_bills.total == round(SUM(items.net_amount) + round_off + total_extra_cess
 *      + gst_calamity_cess, 2) — the exact formula SalesBillController::computeTotals() uses
 *   8. Stock ledger = expected stock: for every (item, branch) with any stock_ledger postings, the latest
 *      posting's running_balance_qty must equal item_stocks.quantity
 */
foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'urban_pos_qa', 'APP_ENV' => 'testing',
    'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'] as $k => $v) {
    putenv("$k=$v"); $_ENV[$k] = $_SERVER[$k] = $v;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dbName = config('database.connections.mysql.database');
if (! str_ends_with((string) $dbName, '_qa')) {
    fwrite(STDERR, "REFUSING: database '$dbName' is not a *_qa database\n");
    exit(2);
}

use Illuminate\Support\Facades\DB;

$asJson = in_array('--json', $argv, true);
$results = [];
$fail = function (string $name, array $rows, string $hint = '') use (&$results) {
    $results[] = ['name' => $name, 'ok' => count($rows) === 0, 'violations' => count($rows), 'sample' => array_slice($rows, 0, 5), 'hint' => $hint];
};

// 1. Negative stock (excluding items that explicitly allow it)
$fail('no_negative_stock',
    DB::table('item_stocks as s')->join('items as i', 'i.id', '=', 's.item_id')
        ->where('s.quantity', '<', 0)->where('i.allow_negative_stock', false)
        ->select('s.item_id', 's.branch_id', 's.quantity')->limit(20)->get()->all()
);

// 2. Duplicate document numbers, one check per numbered table
foreach ([['sales_bills', 'bill_number'], ['purchase_invoices', 'invoice_number'], ['sales_returns', 'return_number'], ['purchase_returns', 'return_number']] as [$table, $col]) {
    $fail("no_duplicate_numbers:$table.$col",
        DB::table($table)->select($col, DB::raw('COUNT(*) as n'))->whereNotNull($col)
            ->groupBy($col)->having('n', '>', 1)->limit(20)->get()->all()
    );
}

// 3. Duplicate posting_key (defense-in-depth; the column is DB-unique already)
foreach (['sales_bills', 'purchase_invoices', 'sales_returns', 'purchase_returns'] as $table) {
    $fail("no_duplicate_posting_key:$table",
        DB::table($table)->select('posting_key', DB::raw('COUNT(*) as n'))->whereNotNull('posting_key')
            ->groupBy('posting_key')->having('n', '>', 1)->limit(20)->get()->all()
    );
}

// 4. Excess returns: per (sales_bill, item), returned qty must not exceed the qty actually sold on that bill.
$fail('no_excess_sales_returns',
    DB::table('sales_return_items as ri')
        ->join('sales_returns as r', 'r.id', '=', 'ri.sales_return_id')
        ->whereNotNull('r.sales_bill_id')
        ->select('r.sales_bill_id', 'ri.item_id', DB::raw('SUM(ri.qty) as returned'))
        ->groupBy('r.sales_bill_id', 'ri.item_id')
        ->havingRaw('SUM(ri.qty) > (SELECT COALESCE(SUM(bi.qty), 0) FROM sales_bill_items bi WHERE bi.sales_bill_id = r.sales_bill_id AND bi.item_id = ri.item_id)')
        ->limit(20)->get()->all()
);
$fail('no_excess_purchase_returns',
    DB::table('purchase_return_items as ri')
        ->join('purchase_returns as r', 'r.id', '=', 'ri.purchase_return_id')
        ->whereNotNull('r.purchase_invoice_id')
        ->select('r.purchase_invoice_id', 'ri.item_id', DB::raw('SUM(ri.qty) as returned'))
        ->groupBy('r.purchase_invoice_id', 'ri.item_id')
        ->havingRaw('SUM(ri.qty) > (SELECT COALESCE(SUM(pi.qty), 0) FROM purchase_invoice_items pi WHERE pi.purchase_invoice_id = r.purchase_invoice_id AND pi.item_id = ri.item_id)')
        ->limit(20)->get()->all()
);

// 5. Orphan lines
$fail('no_orphan_sales_bill_items',
    DB::table('sales_bill_items as i')->leftJoin('sales_bills as b', 'b.id', '=', 'i.sales_bill_id')
        ->whereNull('b.id')->select('i.id', 'i.sales_bill_id')->limit(20)->get()->all()
);
$fail('no_orphan_purchase_invoice_items',
    DB::table('purchase_invoice_items as i')->leftJoin('purchase_invoices as b', 'b.id', '=', 'i.purchase_invoice_id')
        ->whereNull('b.id')->select('i.id', 'i.purchase_invoice_id')->limit(20)->get()->all()
);

// 6. Bill without payment: Posted (not Draft/Cancelled) bill with NO payments row AND no payment_type at all.
$fail('no_bill_without_payment',
    DB::table('sales_bills as b')
        ->whereNotIn('b.status', ['Cancelled', 'Draft'])
        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('sales_bill_payments as p')->whereColumn('p.sales_bill_id', 'b.id'))
        ->where(fn ($q) => $q->whereNull('b.payment_type')->orWhere('b.payment_type', ''))
        ->select('b.id', 'b.bill_number', 'b.status', 'b.payment_type')->limit(20)->get()->all()
);

// 7. Bill total must equal the exact formula computeTotals() uses (2 dp tolerance for float rounding).
$fail('bill_total_equals_line_totals',
    DB::table('sales_bills as b')
        ->select('b.id', 'b.bill_number', 'b.total', 'b.round_off', 'b.total_extra_cess', 'b.gst_calamity_cess',
            DB::raw('(SELECT COALESCE(SUM(net_amount), 0) FROM sales_bill_items WHERE sales_bill_id = b.id) as lines_sum'))
        ->havingRaw('ABS(b.total - ROUND(lines_sum + b.round_off + b.total_extra_cess + b.gst_calamity_cess, 2)) > 0.01')
        ->limit(20)->get()->all()
);

// 8. Stock ledger's latest running balance must match item_stocks.quantity for every posted (item, branch).
$fail('stock_ledger_matches_item_stocks',
    DB::table('item_stocks as s')
        ->joinSub(
            DB::table('stock_ledger as l1')
                ->select('l1.item_id', 'l1.branch_id', 'l1.running_balance_qty')
                ->whereRaw('l1.id = (SELECT MAX(l2.id) FROM stock_ledger l2 WHERE l2.item_id = l1.item_id AND l2.branch_id = l1.branch_id)'),
            'latest', fn ($j) => $j->on('latest.item_id', '=', 's.item_id')->on('latest.branch_id', '=', 's.branch_id')
        )
        ->whereRaw('ABS(s.quantity - latest.running_balance_qty) > 0.001')
        ->select('s.item_id', 's.branch_id', 's.quantity', 'latest.running_balance_qty')
        ->limit(20)->get()->all()
);

$failed = array_filter($results, fn ($r) => ! $r['ok']);
if ($asJson) {
    echo json_encode(['ok' => count($failed) === 0, 'checks' => $results], JSON_PRETTY_PRINT), "\n";
} else {
    foreach ($results as $r) {
        printf("%-4s %-42s %s\n", $r['ok'] ? 'PASS' : 'FAIL', $r['name'], $r['ok'] ? '' : "{$r['violations']} violation(s)");
        if (! $r['ok']) { echo '       sample: '.json_encode($r['sample'])."\n"; }
    }
    echo "\n".(count($failed) ? count($failed).' of '.count($results).' checks FAILED' : 'ALL '.count($results).' checks passed')."\n";
}
exit(count($failed) ? 1 : 0);
