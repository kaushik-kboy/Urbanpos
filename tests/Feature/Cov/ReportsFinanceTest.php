<?php

namespace Tests\Feature\Cov;

use App\Models\BillSettlement;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\SalesBillPayment;
use App\Models\TenderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b1;
    private Branch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $this->actingAs($u);
        $this->b1 = Branch::create(['name' => 'Main', 'state' => 'Gujarat']);
        $this->b2 = Branch::create(['name' => 'Second', 'state' => 'Gujarat']);
    }

    private function je(string $no, string $type, string $date, Branch $b, array $lines, ?string $narr = null): JournalEntry
    {
        $je = JournalEntry::create([
            'voucher_number' => $no, 'voucher_type' => $type, 'voucher_date' => $date,
            'branch_id' => $b->id, 'narration' => $narr,
            'total_debit' => collect($lines)->sum(1), 'total_credit' => collect($lines)->sum(2),
        ]);
        foreach ($lines as [$ledger, $dr, $cr]) {
            $je->lines()->create(['ledger_id' => $ledger->id, 'debit' => $dr, 'credit' => $cr]);
        }
        return $je;
    }

    private function ledger(string $name, string $group, float $open = 0, string $type = 'Debit'): Ledger
    {
        return Ledger::create(['name' => $name, 'ledger_group' => $group, 'opening_balance' => $open, 'opening_balance_type' => $type]);
    }

    private function bill(string $no, string $date, Branch $b, float $total, string $status = 'Posted', ?Customer $c = null, string $pay = 'Credit'): SalesBill
    {
        $c ??= Customer::create(['name' => 'Cust ' . $no, 'status' => true]);
        return SalesBill::create([
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $b->id,
            'sales_type' => 'Local', 'total' => $total, 'status' => $status, 'payment_type' => $pay,
        ]);
    }

    public function test_index_renders(): void
    {
        $this->get(route('finance.reports.index'))->assertOk();
    }

    public function test_general_ledger_opening_balance_and_period_lines(): void
    {
        $cash = $this->ledger('Cash Box', 'Cash in Hand', 1000);
        $sales = $this->ledger('Sales A/c', 'Sales Account', 0, 'Credit');
        $this->je('V-1', 'Sales', '2026-08-10', $this->b1, [[$cash, 500, 0], [$sales, 0, 500]]);   // before period
        $this->je('V-2', 'Receipt', '2026-09-05', $this->b1, [[$cash, 200, 0], [$sales, 0, 200]]);   // in period
        $this->je('V-3', 'Payment', '2026-09-20', $this->b1, [[$cash, 0, 50], [$sales, 50, 0]]);      // in period
        $this->je('V-4', 'Payment', '2026-10-20', $this->b1, [[$cash, 0, 999], [$sales, 999, 0]]);    // after period

        $r = $this->get(route('finance.reports.general-ledger', ['ledger_id' => $cash->id, 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $this->assertSame(1500.0, (float) $r->viewData('openingBalance'));   // 1000 opening + 500 prior movement
        $lines = $r->viewData('lines');
        $this->assertCount(2, $lines);
        $this->assertEquals([200.0, 0.0], [(float) $lines[0]->debit, (float) $lines[0]->credit]);
        $this->assertEquals(50.0, (float) $lines[1]->credit);

        $liab = $this->ledger('Loan', 'Current Liabilities', 300, 'Credit');
        $r2 = $this->get(route('finance.reports.general-ledger', ['ledger_id' => $liab->id]))->assertOk();
        $this->assertSame(-300.0, (float) $r2->viewData('openingBalance'));

        $r3 = $this->get(route('finance.reports.general-ledger'))->assertOk();
        $this->assertCount(0, $r3->viewData('lines'));
        $this->assertNull($r3->viewData('ledger'));
    }

    public function test_day_book_filters_and_totals(): void
    {
        $cash = $this->ledger('Cash', 'Cash in Hand');
        $rent = $this->ledger('Shop Rent', 'Indirect Expense');
        $this->je('DB-1', 'Payment', '2026-09-10', $this->b1, [[$rent, 400, 0], [$cash, 0, 400]], 'September rent');
        $this->je('DB-2', 'Receipt', '2026-09-10', $this->b2, [[$cash, 250, 0], [$rent, 0, 250]], 'refund');
        $this->je('DB-3', 'Payment', '2026-09-11', $this->b1, [[$rent, 90, 0], [$cash, 0, 90]], 'other day');

        $base = ['from' => '2026-09-10', 'to' => '2026-09-10'];
        $r = $this->get(route('finance.reports.day-book', $base))->assertOk();
        $this->assertCount(2, $r->viewData('entries'));
        $this->assertEquals(650.0, (float) $r->viewData('totalDebit'));
        $this->assertEquals(650.0, (float) $r->viewData('totalCredit'));

        $r = $this->get(route('finance.reports.day-book', $base + ['branch_id' => $this->b2->id]));
        $this->assertSame(['DB-2'], $r->viewData('entries')->pluck('voucher_number')->all());

        $r = $this->get(route('finance.reports.day-book', $base + ['voucher_type' => 'Payment']));
        $this->assertSame(['DB-1'], $r->viewData('entries')->pluck('voucher_number')->all());

        $r = $this->get(route('finance.reports.day-book', $base + ['search' => 'September']));
        $this->assertSame(['DB-1'], $r->viewData('entries')->pluck('voucher_number')->all());
        $r = $this->get(route('finance.reports.day-book', $base + ['search' => 'DB-2']));
        $this->assertSame(['DB-2'], $r->viewData('entries')->pluck('voucher_number')->all());
        $r = $this->get(route('finance.reports.day-book', ['from' => '2026-09-10', 'to' => '2026-09-11', 'search' => 'Shop Rent']));
        $this->assertCount(3, $r->viewData('entries')); // ledger-name search matches via lines
    }

    public function test_trial_balance_balances_and_hides_zero_ledgers(): void
    {
        $cash = $this->ledger('Cash', 'Cash in Hand', 100);
        $this->ledger('Capital', 'Capital Account', 100, 'Credit');
        $sales = $this->ledger('Sales', 'Sales Account', 0, 'Credit');
        $this->ledger('Unused', 'Fixed Assets');
        $this->je('TB-1', 'Sales', '2026-09-10', $this->b1, [[$cash, 700, 0], [$sales, 0, 700]]);
        $this->je('TB-2', 'Sales', '2026-12-01', $this->b1, [[$cash, 5000, 0], [$sales, 0, 5000]]); // after as_of

        $rows = $this->get(route('finance.reports.trial-balance', ['as_of' => '2026-09-30']))->assertOk()->viewData('ledgers');
        $this->assertCount(3, $rows);
        $this->assertNull($rows->firstWhere('name', 'Unused'));
        $this->assertEquals(800.0, $rows->firstWhere('name', 'Cash')->debit);
        $this->assertEquals(100.0, $rows->firstWhere('name', 'Capital')->credit);
        $this->assertEquals(700.0, $rows->firstWhere('name', 'Sales')->credit);
        $this->assertEquals($rows->sum('debit'), $rows->sum('credit'));

        $rows = $this->get(route('finance.reports.trial-balance', ['as_of' => '2026-12-31']))->viewData('ledgers');
        $this->assertEquals(5800.0, $rows->firstWhere('name', 'Cash')->debit);
    }

    public function test_profit_loss_figures_branch_filter_and_ledgers(): void
    {
        $this->bill('P-1', '2026-09-05', $this->b1, 1000);
        $this->bill('P-2', '2026-09-06', $this->b2, 400);
        $this->bill('P-OLD', '2026-01-06', $this->b1, 7777);
        $c = Customer::create(['name' => 'RC', 'status' => true]);
        SalesReturn::create(['return_number' => 'R-1', 'return_date' => '2026-09-07', 'customer_id' => $c->id, 'branch_id' => $this->b1->id, 'total' => 100]);
        $sup = Supplier::create(['name' => 'Sup', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'PI-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->b1->id, 'total' => 600, 'status' => 'Posted']);
        PurchaseInvoice::create(['invoice_number' => 'PI-2', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->b2->id, 'total' => 50, 'status' => 'Posted']);

        $exp = $this->ledger('Rent', 'Indirect Expense');
        $inc = $this->ledger('Commission', 'Indirect Income', 0, 'Credit');
        $bank = $this->ledger('Bank', 'Bank Account');
        $this->je('PL-1', 'Payment', '2026-09-09', $this->b1, [[$exp, 120, 0], [$bank, 0, 120]]);
        $this->je('PL-2', 'Receipt', '2026-09-09', $this->b1, [[$bank, 30, 0], [$inc, 0, 30]]);
        $this->je('PL-3', 'Payment', '2026-02-09', $this->b1, [[$exp, 999, 0], [$bank, 0, 999]]); // out of range

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $r = $this->get(route('finance.reports.profit-loss', $q))->assertOk();
        $this->assertEquals(1400.0, $r->viewData('grossSales'));
        $this->assertEquals(100.0, $r->viewData('salesReturn'));
        $this->assertEquals(1300.0, $r->viewData('netSales'));
        $this->assertEquals(650.0, $r->viewData('grossPurchase'));
        $this->assertEquals(650.0, $r->viewData('grossProfit'));
        $this->assertEquals(120.0, $r->viewData('totalExpenses'));
        $this->assertEquals(30.0, $r->viewData('totalIndirectIncomes'));
        $this->assertEquals(560.0, $r->viewData('netProfit')); // 650 + 30 - 120

        $r = $this->get(route('finance.reports.profit-loss', $q + ['branch_id' => $this->b2->id]));
        $this->assertEquals(400.0, $r->viewData('grossSales'));
        $this->assertEquals(0.0, $r->viewData('salesReturn'));
        $this->assertEquals(50.0, $r->viewData('grossPurchase'));
    }

    public function test_profit_loss_excludes_cancelled_and_draft_bills(): void
    {
        $this->bill('PC-1', '2026-09-05', $this->b1, 1000, 'Posted');
        $this->bill('PC-2', '2026-09-05', $this->b1, 300, 'Cancelled');
        $this->bill('PC-3', '2026-09-05', $this->b1, 200, 'Draft');
        $sup = Supplier::create(['name' => 'Sup', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'PCI-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->b1->id, 'total' => 500, 'status' => 'Posted']);
        PurchaseInvoice::create(['invoice_number' => 'PCI-2', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->b1->id, 'total' => 80, 'status' => 'Cancelled']);

        $r = $this->get(route('finance.reports.profit-loss', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $this->assertEquals(1000.0, $r->viewData('grossSales'));
        $this->assertEquals(500.0, $r->viewData('grossPurchase'));
    }

    public function test_cash_bank_book_opening_closing_and_filters(): void
    {
        $cash = $this->ledger('Till Drawer', 'Cash in Hand', 1000);
        $bank = $this->ledger('HDFC', 'Bank Account');
        $sales = $this->ledger('Sales', 'Sales Account', 0, 'Credit');
        $this->je('CB-0', 'Receipt', '2026-08-15', $this->b1, [[$cash, 100, 0], [$sales, 0, 100]]);
        $this->je('CB-1', 'Receipt', '2026-09-05', $this->b1, [[$cash, 300, 0], [$sales, 0, 300]]);
        $this->je('CB-2', 'Receipt', '2026-09-06', $this->b2, [[$bank, 700, 0], [$sales, 0, 700]]);
        $this->je('CB-3', 'Payment', '2026-09-07', $this->b1, [[$cash, 0, 50], [$sales, 50, 0]]);

        $q = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $r = $this->get(route('finance.reports.cash-bank-book', $q))->assertOk();
        $this->assertEquals(1100.0, $r->viewData('openingBalance'));
        $this->assertEquals(1000.0, $r->viewData('totalDebit'));
        $this->assertEquals(50.0, $r->viewData('totalCredit'));
        $this->assertEquals(2050.0, $r->viewData('closingBalance'));
        $this->assertCount(3, $r->viewData('lines'));

        $r = $this->get(route('finance.reports.cash-bank-book', $q + ['account_type' => 'Cash']));
        $this->assertEquals(300.0, $r->viewData('totalDebit'));
        $this->assertEquals(1350.0, $r->viewData('closingBalance'));

        $r = $this->get(route('finance.reports.cash-bank-book', $q + ['account_type' => 'Bank']));
        $this->assertEquals(0.0, $r->viewData('openingBalance'));
        $this->assertEquals(700.0, $r->viewData('closingBalance'));

        $r = $this->get(route('finance.reports.cash-bank-book', $q + ['ledger_id' => $bank->id]));
        $this->assertCount(1, $r->viewData('lines'));

        $r = $this->get(route('finance.reports.cash-bank-book', $q + ['branch_id' => $this->b2->id]));
        $this->assertEquals(1000.0, $r->viewData('openingBalance')); // opening only; prior movement is branch 1
        $this->assertEquals(700.0, $r->viewData('totalDebit'));
    }

    public function test_outstanding_aging_customer_buckets_and_settlements(): void
    {
        $c = Customer::create(['name' => 'Alpha', 'status' => true, 'phone' => '9000000001']);
        $this->bill('A-10', '2026-09-20', $this->b1, 100, 'Posted', $c);   // 10 days
        $this->bill('A-45', '2026-08-16', $this->b1, 200, 'Posted', $c);   // 45 days
        $this->bill('A-75', '2026-07-17', $this->b1, 300, 'Posted', $c);   // 75 days
        $old = $this->bill('A-120', '2026-06-02', $this->b1, 400, 'Posted', $c); // 120 days
        $this->bill('A-PAID', '2026-09-01', $this->b1, 999, 'Posted', $c, 'Cash'); // paid immediately
        $other = Customer::create(['name' => 'Beta', 'status' => true]);
        $this->bill('B-1', '2026-09-25', $this->b2, 50, 'Posted', $other);

        $bank = $this->ledger('Bank', 'Bank Account');
        $mkSt = fn ($no, $date, $status) => BillSettlement::create([
            'settlement_number' => $no, 'settlement_type' => 'Customer', 'customer_id' => $c->id, 'branch_id' => $this->b1->id,
            'settlement_date' => $date, 'total_amount' => 100, 'bank_ledger_id' => $bank->id, 'status' => $status,
        ]);
        $st = $mkSt('ST-1', '2026-09-15', 'Active');
        $old->settlementItems()->create(['bill_settlement_id' => $st->id, 'bill_amount' => 400, 'settled_amount' => 130, 'discount_amount' => 20]);
        $stc = $mkSt('ST-2', '2026-09-16', 'Cancelled'); // cancelled settlement must be ignored
        $old->settlementItems()->create(['bill_settlement_id' => $stc->id, 'bill_amount' => 400, 'settled_amount' => 100, 'discount_amount' => 0]);

        $r = $this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-30']))->assertOk();
        $rows = collect($r->viewData('rows'));
        $this->assertCount(2, $rows);
        $alpha = $rows->firstWhere('party_name', 'Alpha');
        $this->assertEquals(4, $alpha['bill_count']);
        $this->assertEquals(100.0, $alpha['bucket_0_30']);
        $this->assertEquals(200.0, $alpha['bucket_31_60']);
        $this->assertEquals(300.0, $alpha['bucket_61_90']);
        $this->assertEquals(250.0, $alpha['bucket_90_plus']); // 400 - 130 - 20
        $this->assertEquals(850.0, $alpha['total_due']);
        $this->assertSame('Alpha', $rows->first()['party_name']); // sorted desc by due
        $this->assertEquals(900.0, $r->viewData('totals')['total']);

        // Settlement dated after the as-of date is not yet applied: the 06-02 bill is still 400 outstanding
        $r = $this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-10']));
        $alpha = collect($r->viewData('rows'))->firstWhere('party_name', 'Alpha');
        $this->assertEquals(400.0, $alpha['bucket_90_plus']);

        $r = $this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-30', 'branch_id' => $this->b2->id]));
        $this->assertSame(['Beta'], collect($r->viewData('rows'))->pluck('party_name')->all());
    }

    public function test_outstanding_aging_ignores_cancelled_and_draft_customer_bills(): void
    {
        $c = Customer::create(['name' => 'Gamma', 'status' => true]);
        $this->bill('G-OK', '2026-09-20', $this->b1, 100, 'Posted', $c);
        $this->bill('G-CAN', '2026-09-20', $this->b1, 500, 'Cancelled', $c);
        $this->bill('G-DRAFT', '2026-09-20', $this->b1, 700, 'Draft', $c);

        $rows = collect($this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-30']))->viewData('rows'));
        $this->assertEquals(100.0, $rows->firstWhere('party_name', 'Gamma')['total_due']);
    }

    public function test_outstanding_aging_supplier_side(): void
    {
        $s = Supplier::create(['name' => 'VendorX', 'status' => true]);
        $mk = fn ($no, $d, $t, $st = 'Posted') => PurchaseInvoice::create(['invoice_number' => $no, 'invoice_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $this->b1->id, 'total' => $t, 'status' => $st]);
        $mk('S-1', '2026-09-25', 100);
        $p2 = $mk('S-2', '2026-06-01', 500);
        $mk('S-CAN', '2026-09-25', 9999, 'Cancelled');
        $bank = $this->ledger('Bank', 'Bank Account');
        $st = BillSettlement::create([
            'settlement_number' => 'ST-S', 'settlement_type' => 'Supplier', 'supplier_id' => $s->id, 'branch_id' => $this->b1->id,
            'settlement_date' => '2026-08-01', 'total_amount' => 200, 'bank_ledger_id' => $bank->id, 'status' => 'Active',
        ]);
        $p2->settlementItems()->create(['bill_settlement_id' => $st->id, 'bill_amount' => 500, 'settled_amount' => 200, 'discount_amount' => 0]);

        $r = $this->get(route('finance.reports.outstanding-aging', ['party_type' => 'Supplier', 'as_of_date' => '2026-09-30']))->assertOk();
        $row = collect($r->viewData('rows'))->first();
        $this->assertSame('VendorX', $row['party_name']);
        $this->assertEquals(100.0, $row['bucket_0_30']);
        $this->assertEquals(300.0, $row['bucket_90_plus']);
        $this->assertEquals(400.0, $row['total_due']);
        $this->assertEquals(2, $row['bill_count']);
        $this->assertSame('Supplier', $r->viewData('partyType'));

        $r = $this->get(route('finance.reports.outstanding-aging', ['party_type' => 'Supplier', 'branch_id' => $this->b2->id]));
        $this->assertCount(0, $r->viewData('rows'));
    }

    public function test_customer_loyalty_report_balances_and_filters(): void
    {
        $a = Customer::create(['name' => 'Loyal Lata', 'status' => true, 'phone' => '9111111111', 'branch_id' => $this->b1->id]);
        $z = Customer::create(['name' => 'Zed', 'status' => true, 'phone' => '9222222222', 'branch_id' => $this->b2->id]);
        Customer::create(['name' => 'Inactive', 'status' => false]);
        $pt = fn ($c, $type, $pts) => CustomerLoyaltyPoint::create(['customer_id' => $c->id, 'type' => $type, 'points' => $pts, 'branch_id' => $c->branch_id]);
        $pt($a, 'Earned', 100);
        $pt($a, 'Adjustment_Add', 20);
        $pt($a, 'Redeemed', 30);
        $pt($a, 'Reversal', -10);
        $pt($z, 'Earned', 10);
        $pt($z, 'Redeemed', 40);

        $r = $this->get(route('reports.customer-loyalty'))->assertOk();
        $cs = $r->viewData('customers');
        $this->assertCount(2, $cs);
        $lata = $cs->firstWhere('name', 'Loyal Lata');
        $this->assertEquals([120.0, 30.0, 80.0], [$lata['earned'], $lata['redeemed'], $lata['balance']]);
        $this->assertEquals(0.0, $cs->firstWhere('name', 'Zed')['balance']); // never negative
        $this->assertSame('Loyal Lata', $cs->first()['name']);
        $this->assertEquals(80.0, $r->viewData('totals')['total_balance_points']);

        $r = $this->get(route('reports.customer-loyalty', ['branch_id' => $this->b2->id]));
        $this->assertSame(['Zed'], $r->viewData('customers')->pluck('name')->all());
        $r = $this->get(route('reports.customer-loyalty', ['search' => '9111']));
        $this->assertSame(['Loyal Lata'], $r->viewData('customers')->pluck('name')->all());
    }

    public function test_outstanding_aging_credits_pos_payments_but_not_credit_tender(): void
    {
        $c = Customer::create(['name' => 'Delta', 'status' => true]);
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $credit = TenderType::create(['name' => 'On Account', 'type' => 'Credit', 'status' => true]);

        $split = $this->bill('PP-1', '2026-08-01', $this->b1, 500, 'Posted', $c);          // exactly 60 days old
        SalesBillPayment::create(['sales_bill_id' => $split->id, 'tender_type_id' => $cash->id, 'amount' => 200]);
        SalesBillPayment::create(['sales_bill_id' => $split->id, 'tender_type_id' => $credit->id, 'amount' => 300]);
        $this->bill('PP-2', '2026-07-02', $this->b1, 100, 'Posted', $c, 'Cash');            // settled at the counter
        $this->bill('PP-3', '2026-07-03', $this->b1, 40, 'Posted', $c, 'credit');           // 89 days, on credit
        $this->bill('PP-4', '2026-09-30', $this->b1, 25, 'Posted', $c, 'None');             // today, nothing paid

        $rows = collect($this->get(route('finance.reports.outstanding-aging', ['as_of_date' => '2026-09-30']))->assertOk()->viewData('rows'));
        $row = $rows->firstWhere('party_name', 'Delta');
        $this->assertSame(3, $row['bill_count']);
        $this->assertEquals([25.0, 300.0, 40.0, 0.0, 365.0], [$row['bucket_0_30'], $row['bucket_31_60'], $row['bucket_61_90'], $row['bucket_90_plus'], $row['total_due']]);
    }

    public function test_outstanding_aging_supplier_buckets_31_to_90_days(): void
    {
        $s = Supplier::create(['name' => 'Slowpay Ltd', 'status' => true]);
        foreach ([['SB-A', '2026-08-16', 100], ['SB-B', '2026-07-17', 200]] as [$no, $d, $t]) {
            PurchaseInvoice::create(['invoice_number' => $no, 'invoice_date' => $d, 'supplier_id' => $s->id, 'branch_id' => $this->b1->id, 'total' => $t, 'status' => 'Posted']);
        }
        $row = collect($this->get(route('finance.reports.outstanding-aging', ['party_type' => 'Supplier', 'as_of_date' => '2026-09-30']))->viewData('rows'))->first();
        $this->assertEquals([0.0, 100.0, 200.0, 0.0, 300.0], [$row['bucket_0_30'], $row['bucket_31_60'], $row['bucket_61_90'], $row['bucket_90_plus'], $row['total_due']]);
    }
}
