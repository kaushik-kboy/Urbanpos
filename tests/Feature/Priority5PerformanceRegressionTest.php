<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\PaginatesDeep;
use App\Models\BillSettlement;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPet;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PetType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesBillPayment;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/**
 * Priority 5 (performance at 20 lakh rows). These pin the BEHAVIOUR that had to survive the rewrites:
 * paginated reports must still cover the whole filtered set (KPIs, totals, CSV export), the SQL-side aging/margin
 * maths must equal the original per-row PHP maths, and the customer picker must keep returning the same customers.
 */
class Priority5PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b1;
    private Branch $b2;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->b1 = Branch::create(['name' => 'P5 Main', 'state' => 'Gujarat', 'status' => true]);
        $this->b2 = Branch::create(['name' => 'P5 Second', 'state' => 'Gujarat', 'status' => true]);
    }

    public function get($uri, array $headers = [])
    {
        $this->withSession(['active_branch_id' => 'all']);

        return parent::get($uri, $headers);
    }

    private function item(string $name, array $extra = []): Item
    {
        static $n = 0;

        return Item::create($extra + ['name' => $name, 'item_code' => 'P5I'.(++$n), 'sell_price' => 100, 'cost_price' => 60, 'mrp' => 120, 'status' => true]);
    }

    private function bill(string $no, string $date, Branch $b, float $total, string $status = 'Posted', ?Customer $c = null, array $extra = []): SalesBill
    {
        $c ??= Customer::create(['name' => 'Cust '.$no, 'status' => true]);

        return SalesBill::create($extra + [
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $b->id,
            'sales_type' => 'Local', 'total' => $total, 'status' => $status, 'total_qty' => 1,
        ]);
    }

    /** Data rows of a streamed CSV response (header stripped, BOM removed). */
    private function csv($response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', substr($body, 3))), 'strlen')));

        return $rows;
    }

    // ------------------------------------------------------------------ Reorder report

    public function test_reorder_report_paginates_but_kpis_and_csv_cover_every_row(): void
    {
        foreach (range(1, 130) as $i) {
            // 40 out of stock (qty <= 0), 90 low
            ItemStock::create(['item_id' => $this->item("Reorder $i")->id, 'branch_id' => $this->b1->id, 'quantity' => $i <= 40 ? 0 : 3, 'sell_price' => 50]);
        }
        ItemStock::create(['item_id' => $this->item('Plenty')->id, 'branch_id' => $this->b1->id, 'quantity' => 500]);

        $page1 = $this->get(route('reports.reorder-report'))->assertOk();
        $this->assertCount(100, $page1->viewData('rows'), 'only one page of rows is hydrated');
        $this->assertSame(130, $page1->viewData('rows')->total());
        $this->assertSame(['out_of_stock' => 40, 'low_stock' => 90, 'total_skus' => 130], $page1->viewData('kpi'), 'KPIs cover the whole filtered set, not the page');
        $this->assertCount(30, $this->get(route('reports.reorder-report', ['page' => 2]))->viewData('rows'));

        $rows = $this->csv($this->get(route('reports.reorder-report', ['export' => 'csv'])));
        $this->assertCount(131, $rows, 'header + all 130 rows, not just the visible page');

        $out = $this->csv($this->get(route('reports.reorder-report', ['export' => 'csv', 'stock_status' => 'out'])));
        $this->assertCount(41, $out);
    }

    // ------------------------------------------------------------------ Billwise sales

    public function test_billwise_sales_paginates_bills_and_exports_every_line(): void
    {
        $item = $this->item('Kibble');
        foreach (range(1, 130) as $i) {
            $bill = $this->bill(sprintf('BW-%03d', $i), '2026-09-05', $this->b1, 100);
            SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 2, 'sell_price' => 50, 'mrp' => 60, 'net_amount' => 100]);
        }
        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $r = $this->get(route('reports.billwise-sales', $q))->assertOk();
        $this->assertCount(100, $r->viewData('bills'));
        $this->assertSame(130, $r->viewData('bills')->total());
        $this->assertSame('BW-001', $r->viewData('bills')->first()->bill_number);

        $rows = $this->csv($this->get(route('reports.billwise-sales', $q + ['export' => 'csv'])));
        $this->assertCount(131, $rows);
        $this->assertSame(['05-09-2026', 'BW-001'], array_slice($rows[1], 0, 2));
        $this->assertSame('BW-130', $rows[130][1]);
    }

    // ------------------------------------------------------------------ Sales margin (itemwise)

    public function test_sales_margin_itemwise_totals_cover_all_lines_not_just_the_page(): void
    {
        $item = $this->item('Margin Item');
        $bill = $this->bill('MI-1', '2026-09-05', $this->b1, 0);
        foreach (range(1, 120) as $i) {
            // sell 200, cost 60 x qty 2 => margin 80 each; the last 10 lines have NULL cost_at_sale (treated as 0)
            SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 2, 'sell_price' => 100, 'net_amount' => 200, 'cost_at_sale' => $i <= 110 ? 60 : null]);
        }
        $cancelled = $this->bill('MI-CAN', '2026-09-05', $this->b1, 0, 'Cancelled');
        SalesBillItem::create(['sales_bill_id' => $cancelled->id, 'item_id' => $item->id, 'qty' => 1, 'sell_price' => 999, 'net_amount' => 999, 'cost_at_sale' => 1]);
        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $r = $this->get(route('reports.sales-margin-itemwise', $q))->assertOk();
        $this->assertCount(100, $r->viewData('lines'));
        $this->assertSame(120, $r->viewData('lines')->total());
        $t = $r->viewData('totals');
        $this->assertEquals([24000.0, 110 * 120.0], [$t['sell_total'], $t['cog_total']]);
        $this->assertEquals(24000.0 - 13200.0, $t['gross_margin']);
        $this->assertEqualsWithDelta((10800 / 24000) * 100, $t['margin_pct'], 0.001);

        $this->assertCount(121, $this->csv($this->get(route('reports.sales-margin-itemwise', $q + ['export' => 'csv']))));
    }

    // ------------------------------------------------------------------ Sales margin (category) — SQL GROUP BY

    public function test_category_margin_groups_by_name_excludes_cancelled_and_uses_uncategorised(): void
    {
        $cat = ItemCategory::create(['name' => 'CATEGORY', 'status' => true]);
        $food = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Food', 'status' => true]);
        $toys = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Toys', 'status' => true]);
        $iFood = $this->item('F1', ['category_value_id' => $food->id]);
        $iFood2 = $this->item('F2', ['category_value_id' => $food->id]);
        $iToy = $this->item('T1', ['category_value_id' => $toys->id]);
        $iNone = $this->item('N1');

        $b = $this->bill('CM-1', '2026-09-05', $this->b1, 0);
        $line = fn ($bill, $item, $qty, $net, $cost) => SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => $net / $qty, 'net_amount' => $net, 'cost_at_sale' => $cost]);
        $line($b, $iFood, 2, 200, 60);      // margin 80
        $line($b, $iFood2, 1, 100, 40);     // margin 60  -> Food total sell 300 cog 160 margin 140
        $line($b, $iToy, 1, 500, 100);      // margin 400
        $line($b, $iNone, 3, 90, null);     // NULL cost => cog 0, margin 90
        $can = $this->bill('CM-CAN', '2026-09-05', $this->b1, 0, 'Cancelled');
        $line($can, $iToy, 1, 7777, 1);
        $out = $this->bill('CM-OUT', '2026-01-05', $this->b1, 0);
        $line($out, $iToy, 1, 8888, 1);

        $r = $this->get(route('reports.sales-margin-category', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $g = $r->viewData('grouped');
        $this->assertSame(['Toys', 'Food', 'Uncategorised'], $g->pluck('category_name')->all(), 'sorted by gross margin desc');
        $food = $g->firstWhere('category_name', 'Food');
        $this->assertEquals([3.0, 300.0, 160.0, 140.0], [(float) $food->qty, $food->sell_total, $food->cog_total, $food->gross_margin]);
        $this->assertEquals(400.0, $g->firstWhere('category_name', 'Toys')->gross_margin);
        $this->assertEquals([90.0, 0.0], [$g->firstWhere('category_name', 'Uncategorised')->sell_total, $g->firstWhere('category_name', 'Uncategorised')->cog_total]);
        $this->assertEquals(890.0, $r->viewData('totals')['sell_total']);
    }

    // ------------------------------------------------------------------ Purchase detail

    public function test_purchase_detail_paginates_invoices_and_exports_every_line(): void
    {
        $supplier = Supplier::create(['name' => 'PD Supplier', 'status' => true]);
        $item = $this->item('PD Item');
        foreach (range(1, 120) as $i) {
            $inv = PurchaseInvoice::create(['invoice_number' => sprintf('PD-%03d', $i), 'invoice_date' => '2026-09-05', 'supplier_id' => $supplier->id, 'branch_id' => $this->b1->id, 'total' => 100, 'status' => 'Posted']);
            PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'qty' => 1, 'cost_price' => 100, 'sell_price' => 120, 'mrp' => 130, 'gst_percent' => 18, 'net_amount' => 100]);
        }
        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $r = $this->get(route('reports.purchase-detail', $q))->assertOk();
        $this->assertCount(100, $r->viewData('invoices'));
        $this->assertSame(120, $r->viewData('invoices')->total());

        $rows = $this->csv($this->get(route('reports.purchase-detail', $q + ['export' => 'csv'])));
        $this->assertCount(121, $rows);
        $this->assertSame('PD-120', $rows[120][1]);

        // sargable date range keeps the inclusive boundaries of the old whereDate()
        PurchaseInvoice::create(['invoice_number' => 'PD-EDGE', 'invoice_date' => '2026-09-30', 'supplier_id' => $supplier->id, 'branch_id' => $this->b1->id, 'total' => 1, 'status' => 'Posted']);
        $this->assertSame(121, $this->get(route('reports.purchase-detail', $q))->viewData('invoices')->total());
        $this->assertSame(1, $this->get(route('reports.purchase-detail', ['from' => '2026-09-30', 'to' => '2026-09-30']))->viewData('invoices')->total());
    }

    // ------------------------------------------------------------------ Day book

    public function test_day_book_totals_cover_the_whole_period_and_entries_paginate(): void
    {
        $cash = Ledger::create(['name' => 'Cash', 'ledger_group' => 'Cash in Hand', 'opening_balance' => 0, 'opening_balance_type' => 'Debit']);
        $sales = Ledger::create(['name' => 'Sales', 'ledger_group' => 'Sales Account', 'opening_balance' => 0, 'opening_balance_type' => 'Credit']);
        foreach (range(1, 130) as $i) {
            $je = JournalEntry::create(['voucher_number' => sprintf('DB-%03d', $i), 'voucher_type' => 'Sales', 'voucher_date' => '2026-09-05', 'branch_id' => $this->b1->id, 'total_debit' => 10, 'total_credit' => 10]);
            $je->lines()->create(['ledger_id' => $cash->id, 'debit' => 10, 'credit' => 0]);
            $je->lines()->create(['ledger_id' => $sales->id, 'debit' => 0, 'credit' => 10]);
        }
        $other = JournalEntry::create(['voucher_number' => 'DB-OUT', 'voucher_type' => 'Sales', 'voucher_date' => '2026-08-05', 'branch_id' => $this->b1->id, 'total_debit' => 999, 'total_credit' => 999]);
        $other->lines()->create(['ledger_id' => $cash->id, 'debit' => 999, 'credit' => 0]);
        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $r = $this->get(route('finance.reports.day-book', $q))->assertOk();
        $this->assertCount(100, $r->viewData('entries'));
        $this->assertSame(130, $r->viewData('entries')->total());
        $this->assertEquals([1300.0, 1300.0], [$r->viewData('totalDebit'), $r->viewData('totalCredit')], 'totals span all pages');
        $this->assertCount(30, $this->get(route('finance.reports.day-book', $q + ['page' => 2]))->viewData('entries'));

        $filtered = $this->get(route('finance.reports.day-book', $q + ['search' => 'Sales']))->viewData('entries');
        $this->assertSame(130, $filtered->total(), 'search through lines.ledger still works');
        $this->assertSame(0, $this->get(route('finance.reports.day-book', $q + ['branch_id' => $this->b2->id]))->viewData('entries')->total());
    }

    // ------------------------------------------------------------------ Outstanding aging vs the original algorithm

    /** Verbatim port of the pre-Priority-5 per-model loop: the oracle the SQL version must equal. */
    private function oracleAging(string $partyType, string $asOfDate): array
    {
        $asOf = \Carbon\Carbon::parse($asOfDate);
        $rows = [];
        if ($partyType === 'Customer') {
            $docs = SalesBill::with(['customer', 'payments.tenderType', 'settlementItems.settlement'])->whereNotIn('status', ['Cancelled', 'Draft'])->whereDate('bill_date', '<=', $asOfDate)->get()->groupBy('customer_id');
        } else {
            $docs = PurchaseInvoice::with(['supplier', 'settlementItems.settlement'])->where('status', '!=', 'Cancelled')->whereDate('invoice_date', '<=', $asOfDate)->get()->groupBy('supplier_id');
        }
        foreach ($docs as $group) {
            $party = $partyType === 'Customer' ? $group->first()->customer : $group->first()->supplier;
            if (! $party) {
                continue;
            }
            $total = 0.0;
            $b = [0.0, 0.0, 0.0, 0.0];
            $count = 0;
            foreach ($group as $d) {
                $paid = 0.0;
                if ($partyType === 'Customer') {
                    if ($d->payments->isNotEmpty()) {
                        $paid = (float) $d->payments->filter(fn ($p) => ($p->tenderType?->type ?? '') !== 'Credit')->sum('amount');
                    } elseif (! empty($d->payment_type) && strtolower($d->payment_type) !== 'credit' && strtolower($d->payment_type) !== 'none') {
                        $paid = (float) $d->total;
                    }
                }
                $settled = (float) $d->settlementItems
                    ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active' && $si->settlement?->settlement_date?->lte($asOf))
                    ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);
                $due = round((float) $d->total - $paid - $settled, 2);
                if ($due > 0.01) {
                    $total += $due;
                    $count++;
                    $date = $partyType === 'Customer' ? $d->bill_date : $d->invoice_date;
                    $days = $date ? max(0, (int) $date->diffInDays($asOf, false)) : 0;
                    $b[$days <= 30 ? 0 : ($days <= 60 ? 1 : ($days <= 90 ? 2 : 3))] += $due;
                }
            }
            if ($total > 0.01) {
                $rows[] = ['party_id' => $party->id, 'bill_count' => $count, 'total_due' => round($total, 2), 'b0' => round($b[0], 2), 'b1' => round($b[1], 2), 'b2' => round($b[2], 2), 'b3' => round($b[3], 2)];
            }
        }
        usort($rows, fn ($x, $y) => $x['party_id'] <=> $y['party_id']);

        return $rows;
    }

    private function agingFromReport(array $params): array
    {
        $rows = collect($this->get(route('finance.reports.outstanding-aging', $params))->assertOk()->viewData('rows'))
            ->map(fn ($r) => ['party_id' => $r['party_id'], 'bill_count' => $r['bill_count'], 'total_due' => round($r['total_due'], 2), 'b0' => round($r['bucket_0_30'], 2), 'b1' => round($r['bucket_31_60'], 2), 'b2' => round($r['bucket_61_90'], 2), 'b3' => round($r['bucket_90_plus'], 2)])
            ->all();
        usort($rows, fn ($x, $y) => $x['party_id'] <=> $y['party_id']);

        return $rows;
    }

    public function test_outstanding_aging_equals_the_original_algorithm_on_mixed_customer_data(): void
    {
        mt_srand(20260927);
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $credit = TenderType::create(['name' => 'On Credit', 'type' => 'Credit', 'status' => true]);
        $bank = Ledger::create(['name' => 'Bank', 'ledger_group' => 'Bank Account', 'opening_balance' => 0, 'opening_balance_type' => 'Debit']);
        $customers = collect(range(1, 8))->map(fn ($i) => Customer::create(['name' => "Aging Cust $i", 'status' => true]));
        $paymentTypes = ['', 'Credit', 'credit', 'None', 'none', 'Cash', 'UPI', '0', 'Card'];
        $statuses = ['Posted', 'Posted', 'Posted', 'Cancelled', 'Draft'];

        foreach (range(1, 60) as $i) {
            $bill = $this->bill("AG-$i", now()->subDays(mt_rand(0, 200))->setTime(mt_rand(0, 23), mt_rand(0, 59))->format('Y-m-d H:i:s'), mt_rand(0, 1) ? $this->b1 : $this->b2,
                mt_rand(100, 99999) / 100, $statuses[array_rand($statuses)], $customers->random(), ['payment_type' => $paymentTypes[array_rand($paymentTypes)]]);
            if (mt_rand(0, 2) === 0) {                              // split tender: some Cash, some Credit
                SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $cash->id, 'amount' => round($bill->total * mt_rand(0, 60) / 100, 2)]);
                SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $credit->id, 'amount' => round($bill->total * mt_rand(0, 40) / 100, 2)]);
            }
            foreach (range(1, mt_rand(0, 2)) as $k) {               // 0-2 settlements, active/cancelled, dated around "today"
                $st = BillSettlement::create([
                    'settlement_number' => "AGST-$i-$k", 'settlement_type' => 'Customer', 'customer_id' => $bill->customer_id, 'branch_id' => $bill->branch_id,
                    'settlement_date' => now()->subDays(mt_rand(-3, 60))->format('Y-m-d'), 'total_amount' => 10, 'bank_ledger_id' => $bank->id,
                    'status' => mt_rand(0, 3) === 0 ? 'Cancelled' : 'Active',
                ]);
                $bill->settlementItems()->create(['bill_settlement_id' => $st->id, 'bill_amount' => $bill->total, 'settled_amount' => round($bill->total * mt_rand(0, 50) / 100, 2), 'discount_amount' => mt_rand(0, 5)]);
            }
        }
        // bill created on the as-of day itself, later than 00:00: 0 days old, must sit in the 0-30 bucket
        $this->bill('AG-EDGE', now()->format('Y-m-d').' 15:30:00', $this->b1, 77.77, 'Posted', $customers->first(), ['payment_type' => 'Credit']);

        foreach ([now()->toDateString(), now()->subDays(45)->toDateString(), now()->subDays(400)->toDateString()] as $asOf) {
            $this->assertSame($this->oracleAging('Customer', $asOf), $this->agingFromReport(['as_of_date' => $asOf]), "customer aging as of $asOf");
        }
        $branch1 = $this->agingFromReport(['as_of_date' => now()->toDateString(), 'branch_id' => $this->b1->id]);
        $this->assertNotSame([], $branch1);
        $this->assertNotEquals($this->agingFromReport(['as_of_date' => now()->toDateString()]), $branch1, 'the branch filter narrows the result');
    }

    public function test_outstanding_aging_equals_the_original_algorithm_on_supplier_data(): void
    {
        mt_srand(4242);
        $bank = Ledger::create(['name' => 'Bank', 'ledger_group' => 'Bank Account', 'opening_balance' => 0, 'opening_balance_type' => 'Debit']);
        $suppliers = collect(range(1, 5))->map(fn ($i) => Supplier::create(['name' => "Aging Sup $i", 'status' => true]));
        foreach (range(1, 40) as $i) {
            $inv = PurchaseInvoice::create([
                'invoice_number' => "AGP-$i", 'invoice_date' => now()->subDays(mt_rand(0, 300))->format('Y-m-d'), 'supplier_id' => $suppliers->random()->id,
                'branch_id' => mt_rand(0, 1) ? $this->b1->id : $this->b2->id, 'total' => mt_rand(100, 99999) / 100, 'status' => mt_rand(0, 6) === 0 ? 'Cancelled' : 'Posted',
            ]);
            if (mt_rand(0, 1)) {
                $st = BillSettlement::create([
                    'settlement_number' => "AGPST-$i", 'settlement_type' => 'Supplier', 'supplier_id' => $inv->supplier_id, 'branch_id' => $inv->branch_id,
                    'settlement_date' => now()->subDays(mt_rand(-2, 100))->format('Y-m-d'), 'total_amount' => 10, 'bank_ledger_id' => $bank->id, 'status' => mt_rand(0, 3) === 0 ? 'Cancelled' : 'Active',
                ]);
                $inv->settlementItems()->create(['bill_settlement_id' => $st->id, 'bill_amount' => $inv->total, 'settled_amount' => round($inv->total * mt_rand(0, 100) / 100, 2), 'discount_amount' => mt_rand(0, 3)]);
            }
        }
        foreach ([now()->toDateString(), now()->subDays(90)->toDateString()] as $asOf) {
            $this->assertSame($this->oracleAging('Supplier', $asOf), $this->agingFromReport(['party_type' => 'Supplier', 'as_of_date' => $asOf]), "supplier aging as of $asOf");
        }
    }

    public function test_outstanding_aging_order_is_deterministic_when_totals_tie(): void
    {
        foreach (['Zed', 'Amy', 'Mid'] as $name) {
            $c = Customer::create(['name' => $name, 'status' => true]);
            $this->bill("TIE-$name", '2026-09-20', $this->b1, 100, 'Posted', $c, ['payment_type' => 'Credit']);
        }
        $names = collect($this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-30']))->viewData('rows'))->pluck('party_name')->all();
        $this->assertSame(['Amy', 'Mid', 'Zed'], $names);
    }

    // ------------------------------------------------------------------ Customer picker (mobile / name / code / pet fragments)

    /** The pre-Priority-5 query, kept here as the oracle. */
    private function legacyPicker(string $q): array
    {
        return Customer::where('status', true)->where(function ($sub) use ($q) {
            $sub->where('name', 'like', "%{$q}%")->orWhere('mobile', 'like', "%{$q}%")->orWhere('customer_code', 'like', "%{$q}%")
                ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('customer_pets')->select('customer_id')->where('name', 'like', "%{$q}%"));
        })->orderBy('name')->limit(30)->pluck('id')->all();
    }

    public function test_customer_picker_returns_the_same_customers_as_the_legacy_query(): void
    {
        $dog = PetType::create(['name' => 'Dog', 'status' => true]);
        foreach (range(1, 45) as $i) {
            Customer::create(['name' => sprintf('Sharma Family %02d', $i), 'mobile' => '90000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'customer_code' => sprintf('C%07d', $i), 'status' => true]);
        }
        Customer::create(['name' => 'Inactive Sharma', 'mobile' => '9111122222', 'status' => false]);
        $owner = Customer::create(['name' => 'Pet Owner', 'mobile' => '9222233333', 'status' => true]);
        CustomerPet::create(['customer_id' => $owner->id, 'pet_type_id' => $dog->id, 'name' => 'Bruno']);
        Customer::create(['name' => 'Zed Last', 'mobile' => '9333344444', 'customer_code' => 'C9999999', 'status' => true]);

        foreach (['Sharma', 'sharma family 07', '9000000012', '00012', '3344', 'C000004', 'C9999', 'Brun', 'Inactive', 'nomatchzzz', '9'] as $term) {
            $got = collect($this->getJson(route('sales.sales-bills.customer-search', ['q' => $term]))->assertOk()->json('results'))->pluck('id')->all();
            $this->assertSame($this->legacyPicker($term), $got, "customer picker term '$term'");
        }
        $this->assertNotContains(Customer::where('name', 'Inactive Sharma')->value('id'), Customer::pickerMatchIds('Sharma'));
    }

    // ------------------------------------------------------------------ Deep pagination (deferred join)

    public function test_deferred_join_pagination_returns_exactly_what_plain_paginate_returns(): void
    {
        foreach (range(1, 47) as $i) {
            $this->bill(sprintf('DP-%03d', $i), '2026-09-05', $i % 2 ? $this->b1 : $this->b2, $i);
        }
        $deep = new class {
            use PaginatesDeep;

            protected function deepOffset(): int
            {
                return 0; // force the deferred-join path on every page
            }

            public function run($query, int $perPage)
            {
                return $this->paginateDeep($query, $perPage);
            }
        };
        $query = fn () => SalesBill::with('customer')->where('branch_id', $this->b1->id)->orderByDesc('id');

        foreach ([1, 2, 3] as $page) {
            Paginator::currentPageResolver(fn () => $page);
            $plain = $query()->paginate(10);
            $viaDeep = $deep->run($query(), 10);
            $this->assertSame($plain->pluck('id')->all(), $viaDeep->pluck('id')->all(), "page $page rows and order");
            $this->assertSame($plain->total(), $viaDeep->total());
            $this->assertSame($plain->lastPage(), $viaDeep->lastPage());
            $this->assertSame($plain->currentPage(), $viaDeep->currentPage());
            $this->assertTrue($viaDeep->first()?->relationLoaded('customer') ?? true, 'eager loads are kept');
        }
        Paginator::currentPageResolver(fn () => 99);
        $this->assertCount(0, $deep->run($query(), 10), 'a page past the end is empty, not an error');
        Paginator::currentPageResolver(fn () => 1);
    }
}
