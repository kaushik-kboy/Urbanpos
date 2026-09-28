<?php
/**
 * Production-like bulk data generator for the QA database (dedicated MySQL instance, port 3307).
 *
 *   php scripts/qa/seed_scale.php <target_sales_bills>      e.g. 100000 | 500000 | 1000000 | 2000000
 *
 * Incremental: tops the database up to the requested tier, so the same DB can be measured at every
 * tier. REFUSES to run against anything that is not a *_qa database on port 3307.
 *
 * Data mix (deterministic, repeatable): 5 branches, GST 0/5/12/18/28, unicode + special-character +
 * very long item names, decimal quantities, zero-stock / expired / near-expiry batches, ~3% cancelled
 * bills, partial and full sales returns, purchase invoices, stock-ledger movements.
 */
$target = (int) ($argv[1] ?? 0);
if ($target < 1000) {
    fwrite(STDERR, "usage: php scripts/qa/seed_scale.php <target_sales_bills>\n");
    exit(1);
}

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=urban_pos_qa;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);
$db = $pdo->query('select database()')->fetchColumn();
$port = (int) $pdo->query('select @@port')->fetchColumn();
if (! str_ends_with($db, '_qa') || $port !== 3307) {
    fwrite(STDERR, "REFUSING: $db on port $port is not the dedicated QA instance\n");
    exit(2);
}

$pdo->exec('SET SESSION foreign_key_checks=0, unique_checks=0, sql_log_bin=0, autocommit=1');
$pdo->exec('SET SESSION cte_max_recursion_depth=1000000');
$t0 = microtime(true);
function step(string $label, callable $fn): void
{
    global $t0;
    $s = microtime(true);
    $n = $fn();
    printf("  %-34s %9s rows  %6.1fs (total %.0fs)\n", $label, number_format((int) $n), microtime(true) - $s, microtime(true) - $t0);
}
function q(string $sql): int
{
    global $pdo;

    return (int) $pdo->exec($sql);
}
function scalar(string $sql)
{
    global $pdo;

    return $pdo->query($sql)->fetchColumn();
}
/** Runs an INSERT ... SELECT over a slice of the numbers table in chunks so redo/undo stay bounded. */
function chunked(int $from, int $to, int $chunk, callable $build): int
{
    $total = 0;
    for ($a = $from; $a < $to; $a += $chunk) {
        $b = min($a + $chunk, $to);
        $total += q($build($a, $b));
    }

    return $total;
}

echo "Seeding $db@:$port up to $target sales bills\n";

// ---------------------------------------------------------------------------------------- reference data
step('gst_taxes', function () {
    $n = 0;
    foreach ([0, 5, 12, 18, 28] as $p) {
        $n += q("INSERT INTO gst_taxes (description, percentage, status, created_at, updated_at)
                 SELECT 'GST $p%', $p, 1, NOW(), NOW() FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM gst_taxes WHERE percentage = $p)");
    }

    return $n;
});
step('branches (5)', function () {
    $n = 0;
    foreach (['QA Motera', 'QA Satellite', 'QA Vastrapur', 'QA Surat', 'QA Vadodara'] as $name) {
        $n += q("INSERT INTO branches (name, state, created_at, updated_at)
                 SELECT '$name', 'Gujarat', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM branches WHERE name='$name')");
    }

    return $n;
});

$gst = $pdo->query('select id, percentage from gst_taxes where percentage in (0,5,12,18,28) order by percentage')->fetchAll(PDO::FETCH_KEY_PAIR);
$gstIds = array_keys($gst);            // ids ordered by percentage 0,5,12,18,28
$branchIds = array_map('intval', $pdo->query("select id from branches where name like 'QA %' order by id")->fetchAll(PDO::FETCH_COLUMN));
$nBranch = count($branchIds);
$gstCase = 'ELT(1+MOD(n.n,5),'.implode(',', $gstIds).')';

// numbers helper (0..9,999,999) built once
if (! scalar("select count(*) from information_schema.tables where table_schema='urban_pos_qa' and table_name='qa_nums'")) {
    q('CREATE TABLE qa_nums (n INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    q('INSERT INTO qa_nums SELECT a.d + 10*b.d + 100*c.d + 1000*d.d + 10000*e.d + 100000*f.d + 1000000*g.d
       FROM (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) e,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) f,
            (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) g');
}

// ---------------------------------------------------------------------------------------- master data
$targetItems = max(20000, min(300000, intdiv($target, 8)));
$targetCust = max(20000, intdiv($target, 3));
$targetSupp = max(200, min(5000, intdiv($target, 400)));

step('suppliers', function () use ($targetSupp) {
    $have = (int) scalar('select count(*) from suppliers');

    return $have >= $targetSupp ? 0 : q("INSERT INTO suppliers (name, currency, purchase_type, status, city, state, country, phone, mobile, gst_no, credit_limit, created_at, updated_at)
        SELECT CONCAT('QA Supplier ', LPAD(n.n,6,'0'), ' ', ELT(1+MOD(n.n,4),'Pet Foods Pvt Ltd','Traders','& Sons','Distributors')), 'INR', 'Local', 1,
               ELT(1+MOD(n.n,5),'Ahmedabad','Surat','Vadodara','Mumbai','Pune'), 'Gujarat', 'India',
               CONCAT('079', LPAD(n.n,8,'0')), CONCAT('9', LPAD(MOD(n.n*7919,1000000000),9,'0')), CONCAT('24AAAAA', LPAD(n.n,4,'0'), 'A1Z5'), 100000, NOW(), NOW()
        FROM qa_nums n WHERE n.n >= $have AND n.n < $targetSupp");
});
$supplierMax = (int) scalar('select max(id) from suppliers');
$supplierMin = (int) scalar('select min(id) from suppliers');

step('customers', function () use ($targetCust) {
    $have = (int) scalar("select count(*) from customers where name like 'QA Cust%'");

    return chunked($have, $targetCust, 250000, fn ($a, $b) => "INSERT INTO customers (name, customer_code, sales_type, payment_mode, credit_limit, credit_balance, status, city, state, country, mobile, phone, email, gst_no, created_at, updated_at)
        SELECT CASE WHEN MOD(n.n,97)=0 THEN CONCAT('QA Cust ', n.n, ' O''Brien & Sons \"Pet\" Care')
                    WHEN MOD(n.n,89)=0 THEN CONCAT('QA Cust ', n.n, ' पेट केयर सेंटर')
                    ELSE CONCAT('QA Cust ', LPAD(n.n,7,'0'), ' ', ELT(1+MOD(n.n,8),'Shah','Patel','Mehta','Desai','Joshi','Trivedi','Parmar','Modi')) END,
               CONCAT('C', LPAD(n.n,7,'0')), 'Local', ELT(1+MOD(n.n,3),'Cash Only','Credit Only','Both Cash and Credit'), IF(MOD(n.n,3)>0, 50000, 0), 0, 1,
               ELT(1+MOD(n.n,5),'Ahmedabad','Surat','Vadodara','Mumbai','Pune'), 'Gujarat', 'India',
               CONCAT('9', LPAD(MOD(n.n*104729,1000000000),9,'0')), CONCAT('079', LPAD(n.n,8,'0')), CONCAT('qa', n.n, '@example.com'), IF(MOD(n.n,11)=0, CONCAT('24BBBBB', LPAD(MOD(n.n,10000),4,'0'), 'B1Z6'), NULL), NOW(), NOW()
        FROM qa_nums n WHERE n.n >= $a AND n.n < $b");
});
$custMin = (int) scalar("select min(id) from customers where name like 'QA Cust%'");
$custCount = (int) scalar("select count(*) from customers where name like 'QA Cust%'");

step('items', function () use ($targetItems, $gstCase, $supplierMin, $supplierMax) {
    $have = (int) scalar("select count(*) from items where item_code like 'QAI%'");
    $span = max(1, $supplierMax - $supplierMin + 1);

    return chunked($have, $targetItems, 100000, fn ($a, $b) => "INSERT INTO items (item_code, ean_upc_code, name, alias, supplier_id, product_type, cost_price, landing_cost, sell_price, mrp, status, tax_inclusive, batch_expiry_details, allow_negative_stock, gst_tax_id, hsn_code, created_at, updated_at)
        SELECT CONCAT('QAI', LPAD(n.n,7,'0')), CONCAT('890', LPAD(n.n,10,'0')),
               CASE WHEN MOD(n.n,997)=0 THEN CONCAT('QA नमूना पशु आहार ', n.n, ' – 1किग्रा')
                    WHEN MOD(n.n,499)=0 THEN CONCAT('QA ', n.n, ' Dog\\'s \"Premium\" Chew & Treat 100% Natural / Grain-Free (5-10kg)')
                    WHEN MOD(n.n,9973)=0 THEN CONCAT('QA ', n.n, ' ', REPEAT('Extra Long Product Name ', 9))
                    ELSE CONCAT(ELT(1+MOD(n.n,12),'Royal Canin','Pedigree','Whiskas','Drools','Purina','Me-O','Himalaya','Kong','Trixie','Sheba','Farmina','Orijen'), ' ',
                                ELT(1+MOD(n.n DIV 12,10),'Adult Dry Food','Puppy Starter','Cat Treats','Chew Toy','Shampoo','Collar','Leash','Bowl','Litter','Vitamin'), ' ',
                                n.n, ' ', ELT(1+MOD(n.n,4),'500g','1kg','5kg','10kg')) END,
               CONCAT('alias', n.n), $supplierMin + MOD(n.n,$span), 'Standard',
               ROUND(50 + MOD(n.n*31,4950)/10, 2), ROUND(50 + MOD(n.n*31,4950)/10, 2), ROUND(80 + MOD(n.n*31,4950)/8, 2), ROUND(100 + MOD(n.n*31,4950)/7, 2),
               IF(MOD(n.n,50)=0,0,1), IF(MOD(n.n,3)=0,1,0), IF(MOD(n.n,6)=0,'Mandatory','Not Required'), IF(MOD(n.n,40)=0,1,0),
               $gstCase, LPAD(MOD(n.n*7,100000000),8,'0'), NOW(), NOW()
        FROM qa_nums n WHERE n.n >= $a AND n.n < $b");
});
$itemMin = (int) scalar("select min(id) from items where item_code like 'QAI%'");
$itemCount = (int) scalar("select count(*) from items where item_code like 'QAI%'");

step('item_stocks (item x branch)', function () use ($branchIds, $itemMin, $itemCount) {
    $n = 0;
    foreach ($branchIds as $bi => $bid) {
        $have = (int) scalar("select count(*) from item_stocks s join items i on i.id=s.item_id where s.branch_id=$bid and i.item_code like 'QAI%'");
        $n += chunked($have, $itemCount, 200000, fn ($a, $b) => "INSERT INTO item_stocks (item_id, branch_id, quantity, cost_price, landing_cost, sell_price, mrp, created_at, updated_at)
            SELECT i.id, $bid, CASE WHEN MOD(i.id+$bi,7)=0 THEN 0 ELSE MOD(i.id*13+$bi,500)+(MOD(i.id,4)*0.25) END, i.cost_price, i.landing_cost, i.sell_price, i.mrp, NOW(), NOW()
            FROM items i WHERE i.id >= ".($itemMin + $a).' AND i.id < '.($itemMin + $b)." AND i.item_code LIKE 'QAI%'");
    }

    return $n;
});

// ---------------------------------------------------------------------------------------- transactions
$haveBills = (int) scalar("select count(*) from sales_bills where bill_number like 'QB-%'");
$nextBillNo = $haveBills;
$billBase = (int) scalar('select coalesce(max(id),0) from sales_bills');
$branchCase = 'ELT(1+MOD(n.n,'.$nBranch.'),'.implode(',', $branchIds).')';
$custSpan = max(1, $custCount);


// sales bills: id = $billBase + 1 + (n - $haveBills). Lines per bill = 1 + MOD(n,5) (avg 3).
step('sales_bills + lines + ledger', function () use ($haveBills, $target, $branchCase, $custMin, $custSpan, $billBase, $itemMin, $itemCount) {
    $rows = 0;
    $qty = '(1 + MOD(n.n + l.n,5) + IF(MOD(n.n,13)=0,0.5,0))';   // decimal quantities on ~8% of bills
    for ($a = $haveBills; $a < $target; $a += 100000) {
        $b = min($a + 100000, $target);
        $firstId = $billBase + 1 + ($a - $haveBills);
        $lastId = $billBase + ($b - $haveBills);

        q("INSERT INTO sales_bills (id, bill_number, bill_date, customer_id, branch_id, invoice_type, delivery_type, sales_type, payment_type, status, created_at, updated_at)
           SELECT $billBase + 1 + (n.n - $haveBills), CONCAT('QB-', LPAD(n.n,8,'0')),
                  NOW() - INTERVAL MOD(n.n*7919,1095) DAY - INTERVAL MOD(n.n*31,86400) SECOND,
                  $custMin + MOD(n.n*104729,$custSpan), $branchCase, 'Retail Invoice', 'Delivered', 'Local', ELT(1+MOD(n.n,3),'Cash','Card','UPI'),
                  IF(MOD(n.n,33)=0,'Cancelled','Posted'), NOW(), NOW()
           FROM qa_nums n WHERE n.n >= $a AND n.n < $b");

        // line k of bill n exists when k < 1 + MOD(n,5); ~1 in 6 items is batch/expiry tracked (expired, near-expiry and fresh mixed)
        q("INSERT INTO sales_bill_items (sales_bill_id, item_id, exp_date, qty, sell_price, cost_at_sale, mrp, gst_percent, gst_tax_amount, cgst_amount, sgst_amount, net_amount, created_at, updated_at)
           SELECT $billBase + 1 + (n.n - $haveBills), it.id,
                  IF(MOD(it.id,6)=0, CURDATE() + INTERVAL (CAST(MOD(n.n*13,720) AS SIGNED)-120) DAY, NULL),
                  $qty, it.sell_price, it.cost_price, it.mrp, gt.percentage,
                  ROUND($qty*it.sell_price*gt.percentage/(100+gt.percentage),2), ROUND($qty*it.sell_price*gt.percentage/(100+gt.percentage)/2,2), ROUND($qty*it.sell_price*gt.percentage/(100+gt.percentage)/2,2),
                  ROUND($qty*it.sell_price,2), NOW(), NOW()
           FROM qa_nums n
           JOIN qa_nums l ON l.n < 1 + MOD(n.n,5)
           JOIN items it ON it.id = $itemMin + MOD(n.n*17 + l.n*7919, $itemCount)
           JOIN gst_taxes gt ON gt.id = it.gst_tax_id
           WHERE n.n >= $a AND n.n < $b");

        q("UPDATE sales_bills sb JOIN (SELECT sales_bill_id, SUM(qty) q, SUM(net_amount) t, SUM(gst_tax_amount) g, SUM(cgst_amount) c
                                      FROM sales_bill_items WHERE sales_bill_id BETWEEN $firstId AND $lastId GROUP BY sales_bill_id) s ON s.sales_bill_id = sb.id
           SET sb.total_qty = s.q, sb.total = s.t, sb.total_gst = s.g, sb.total_cgst = s.c, sb.total_sgst = s.c
           WHERE sb.id BETWEEN $firstId AND $lastId");

        // stock movements for posted sales (cancelled bills net to zero, so no rows)
        q("INSERT INTO stock_ledger (item_id, branch_id, exp_date, movement_type, reference_type, reference_id, qty_in, qty_out, unit_cost, value_in, value_out, running_balance_qty, running_balance_value, document_date, posted_at, created_at, updated_at)
           SELECT i.item_id, b.branch_id, i.exp_date, 'SALE', 'App\\\\Models\\\\SalesBill', b.id, 0, i.qty, i.cost_at_sale, 0, ROUND(i.qty*i.cost_at_sale,2), 0, 0, DATE(b.bill_date), b.bill_date, b.bill_date, b.bill_date
           FROM sales_bill_items i JOIN sales_bills b ON b.id = i.sales_bill_id
           WHERE i.sales_bill_id BETWEEN $firstId AND $lastId AND b.status = 'Posted'");

        $rows += $b - $a;
        printf("    ... %s / %s bills\n", number_format($rows), number_format($target - $haveBills));
    }

    return $rows;
});

// purchases: 1 per 10 bills, 4 lines each, straight into stock_ledger as PURCHASE
step('purchase_invoices + lines + ledger', function () use ($target, $supplierMin, $supplierMax, $branchIds, $nBranch, $itemMin, $itemCount) {
    $want = intdiv($target, 10);
    $have = (int) scalar("select count(*) from purchase_invoices where invoice_number like 'QP-%'");
    if ($have >= $want) {
        return 0;
    }
    $base = (int) scalar('select coalesce(max(id),0) from purchase_invoices');
    $span = max(1, $supplierMax - $supplierMin + 1);
    $branchCase = 'ELT(1+MOD(n.n,'.$nBranch.'),'.implode(',', $branchIds).')';
    $pq = '(20 + MOD(n.n + l.n,80))';

    for ($a = $have; $a < $want; $a += 100000) {
        $b = min($a + 100000, $want);
        $first = $base + 1 + ($a - $have);
        $last = $base + ($b - $have);
        q("INSERT INTO purchase_invoices (id, invoice_number, invoice_date, supplier_id, branch_id, purchase_type, status, created_at, updated_at)
           SELECT $base + 1 + (n.n - $have), CONCAT('QP-', LPAD(n.n,8,'0')), CURDATE() - INTERVAL MOD(n.n*613,1095) DAY,
                  $supplierMin + MOD(n.n*31,$span), $branchCase, 'Local', 'Posted', NOW(), NOW()
           FROM qa_nums n WHERE n.n >= $a AND n.n < $b");
        q("INSERT INTO purchase_invoice_items (purchase_invoice_id, item_id, qty, cost_price, sell_price, mrp, gst_percent, gst_tax_amount, net_amount, created_at, updated_at)
           SELECT $base + 1 + (n.n - $have), it.id, $pq, it.cost_price, it.sell_price, it.mrp, gt.percentage,
                  ROUND($pq*it.cost_price*gt.percentage/100,2), ROUND($pq*it.cost_price*(1+gt.percentage/100),2), NOW(), NOW()
           FROM qa_nums n JOIN qa_nums l ON l.n < 4
           JOIN items it ON it.id = $itemMin + MOD(n.n*29 + l.n*104729, $itemCount)
           JOIN gst_taxes gt ON gt.id = it.gst_tax_id
           WHERE n.n >= $a AND n.n < $b");
        q("UPDATE purchase_invoices p JOIN (SELECT purchase_invoice_id, SUM(qty) q, SUM(net_amount) t, SUM(gst_tax_amount) g FROM purchase_invoice_items
                                            WHERE purchase_invoice_id BETWEEN $first AND $last GROUP BY purchase_invoice_id) s ON s.purchase_invoice_id = p.id
           SET p.total_qty = s.q, p.total = s.t, p.total_gst = s.g WHERE p.id BETWEEN $first AND $last");
        q("INSERT INTO stock_ledger (item_id, branch_id, movement_type, reference_type, reference_id, qty_in, qty_out, unit_cost, value_in, value_out, running_balance_qty, running_balance_value, document_date, posted_at, created_at, updated_at)
           SELECT i.item_id, p.branch_id, 'PURCHASE_RECEIPT', 'App\\\\Models\\\\PurchaseInvoice', p.id, i.qty, 0, i.cost_price, ROUND(i.qty*i.cost_price,2), 0, 0, 0, p.invoice_date, p.created_at, p.created_at, p.created_at
           FROM purchase_invoice_items i JOIN purchase_invoices p ON p.id = i.purchase_invoice_id WHERE i.purchase_invoice_id BETWEEN $first AND $last");
    }

    return $want - $have;
});

// sales returns: 1 per 20 posted bills; every 2nd one is a FULL return, the rest partial (half qty on a subset of lines)
step('sales_returns + items', function () {
    $have = (int) scalar("select count(*) from sales_returns where return_number like 'QR-%'");
    $want = (int) scalar("select count(*) from sales_bills where bill_number like 'QB-%' and status='Posted' and MOD(id,20)=0");
    if ($have >= $want) {
        return 0;
    }
    $retBase = (int) scalar('select coalesce(max(id),0) from sales_returns');
    q('SET @r := 0');
    $n = q("INSERT INTO sales_returns (id, return_number, return_date, customer_id, branch_id, sales_bill_id, sales_type, return_mode, total, status, created_at, updated_at)
            SELECT $retBase + (@r := @r + 1), CONCAT('QR-', LPAD(b.id,8,'0')), DATE(b.bill_date) + INTERVAL (1 + MOD(b.id,20)) DAY, b.customer_id, b.branch_id, b.id, 'Local', 'Cash', 0, 'Posted', b.bill_date, b.bill_date
            FROM sales_bills b
            WHERE b.bill_number LIKE 'QB-%' AND b.status='Posted' AND MOD(b.id,20)=0
              AND NOT EXISTS (SELECT 1 FROM sales_returns r WHERE r.return_number = CONCAT('QR-', LPAD(b.id,8,'0')))");
    q("INSERT INTO sales_return_items (sales_return_id, item_id, qty, sell_price, mrp, gst_percent, net_amount, created_at, updated_at)
       SELECT r.id, i.item_id, IF(MOD(i.sales_bill_id,40)=0, i.qty, GREATEST(1, FLOOR(i.qty/2))), i.sell_price, i.mrp, i.gst_percent,
              ROUND(IF(MOD(i.sales_bill_id,40)=0, i.qty, GREATEST(1, FLOOR(i.qty/2))) * i.sell_price, 2), r.created_at, r.created_at
       FROM sales_returns r JOIN sales_bill_items i ON i.sales_bill_id = r.sales_bill_id
       WHERE r.return_number LIKE 'QR-%' AND r.id > $retBase AND (MOD(i.sales_bill_id,40)=0 OR MOD(i.id,3)=0 OR i.id = (SELECT MIN(x.id) FROM sales_bill_items x WHERE x.sales_bill_id = i.sales_bill_id))");
    q("UPDATE sales_returns r JOIN (SELECT sales_return_id, SUM(net_amount) t FROM sales_return_items GROUP BY sales_return_id) s ON s.sales_return_id = r.id
       SET r.total = s.t WHERE r.return_number LIKE 'QR-%' AND r.id > $retBase");

    return $n;
});

// ---------------------------------------------------------------------------------------- finish
$pdo->exec('SET SESSION foreign_key_checks=1, unique_checks=1');
$tables = ['items', 'customers', 'suppliers', 'item_stocks', 'sales_bills', 'sales_bill_items', 'stock_ledger', 'purchase_invoices', 'purchase_invoice_items', 'sales_returns', 'sales_return_items'];
step('ANALYZE TABLE (fresh statistics)', function () use ($pdo, $tables) {
    foreach ($tables as $t) {
        $pdo->query("ANALYZE TABLE $t")->fetchAll();
    }

    return 0;
});

echo "\nRow counts:\n";
$sum = 0;
foreach ($tables as $t) {
    $c = (int) scalar("select count(*) from $t");
    $sum += $c;
    printf("  %-24s %s\n", $t, number_format($c));
}
printf("Total records across these tables: %s  (%.0fs)\n", number_format($sum), microtime(true) - $t0);
