<?php

namespace Tests\Feature\Cov2;

use App\Models\AuditLog;
use App\Models\BillSettlement;
use App\Models\FinancialYear;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\User;
use Tests\TestCase;

class FinSettlementTest extends TestCase
{
    use FinHelper;

    private function bankLedger(): Ledger
    {
        return Ledger::firstOrCreate(['name' => 'HDFC Bank', 'ledger_group' => 'Bank Account'], ['opening_balance' => 0, 'opening_balance_type' => 'Debit', 'status' => true]);
    }

    private function creditBill(float $total, ?string $date = null): SalesBill
    {
        return SalesBill::create([
            'bill_number' => 'FB-'.uniqid(), 'bill_date' => $date ?? now()->subDays(10)->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'payment_type' => 'Credit', 'total' => $total, 'status' => 'Posted',
        ]);
    }

    private function purchaseInv(float $total): PurchaseInvoice
    {
        return PurchaseInvoice::create([
            'invoice_number' => 'FP-'.uniqid(), 'invoice_date' => now()->subDays(5)->toDateString(), 'supplier_id' => $this->supp->id,
            'branch_id' => $this->branch->id, 'purchase_type' => 'Local', 'total' => $total, 'status' => 'Posted',
        ]);
    }

    private function setPayload(string $type, int $billId, array $over = [], array $alloc = []): array
    {
        return array_merge([
            'settlement_type' => $type,
            $type === 'Customer' ? 'customer_id' : 'supplier_id' => $type === 'Customer' ? $this->cust->id : $this->supp->id,
            'branch_id' => $this->branch->id, 'settlement_date' => now()->toDateString(),
            'total_amount' => 300, 'discount_amount' => 0, 'payment_mode' => 'Cash',
            'bank_ledger_id' => $this->bankLedger()->id,
            'allocations' => [array_merge(['bill_id' => $billId, 'bill_amount' => 1000, 'settled_amount' => 300, 'discount_amount' => 0], $alloc)],
        ], $over);
    }

    public function test_unpaid_bills_customer_side_computes_balances(): void
    {
        $credit = $this->creditBill(1000);
        SalesBill::create(['bill_number' => 'FB-PAID', 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 400, 'status' => 'Posted']);
        $none = SalesBill::create(['bill_number' => 'FB-NONE', 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'payment_type' => 'none', 'total' => 50, 'status' => 'Posted']);

        $this->assertSame([], $this->getJson(route('finance.settlements.unpaid-bills'))->json('bills'));

        $bills = collect($this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id]))->assertOk()->json('bills'));
        $this->assertEqualsCanonicalizing([$credit->id, $none->id], $bills->pluck('id')->all());
        $row = $bills->firstWhere('id', $credit->id);
        $this->assertEquals(1000, $row['balance_due']);
        $this->assertSame(10, $row['days_overdue']);

        $this->assertSame([], $this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id, 'branch_id' => 999999]))->json('bills'));

        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $credit->id, ['discount_amount' => 20], ['discount_amount' => 20]));
        $s = BillSettlement::firstOrFail();
        $row = collect($this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id]))->json('bills'))->firstWhere('id', $credit->id);
        $this->assertEquals(680, $row['balance_due']);
        $this->assertEquals(320, $row['paid_amount']);

        $this->delete(route('finance.settlements.destroy', $s), ['reason' => 'oops']);
        $row = collect($this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id]))->json('bills'))->firstWhere('id', $credit->id);
        $this->assertEquals(1000, $row['balance_due']);
    }

    public function test_customer_settlement_posts_receipt_journal_and_cancel_reverses_it(): void
    {
        $bill = $this->creditBill(1000);
        $payload = $this->setPayload('Customer', $bill->id, ['total_amount' => 300, 'discount_amount' => 50], ['settled_amount' => 300, 'discount_amount' => 50]);

        $this->get(route('finance.settlements.create'))->assertOk();
        $r = $this->post(route("finance.settlements.store"), $payload);
        $s = BillSettlement::firstOrFail();
        $r->assertRedirect(route('finance.settlements.show', $s));
        $this->assertMatchesRegularExpression('/^SET-CST\d{6}$/', $s->settlement_number);
        $this->assertSame('Active', $s->status);
        $this->assertSame(1, $s->items()->count());

        $je = JournalEntry::findOrFail($s->journal_entry_id);
        $this->assertSame('Receipt', $je->voucher_type);
        $this->assertEquals(350, (float) $je->total_credit);
        $this->assertEquals(350, (float) $je->total_debit);
        $custLedger = Ledger::where('name', 'Fin Customer')->where('ledger_group', 'Sundry Debtors')->firstOrFail();
        $this->assertEquals(350, (float) $je->lines()->where('ledger_id', $custLedger->id)->value('credit'));
        $this->assertEquals(300, (float) $je->lines()->where('ledger_id', $payload['bank_ledger_id'])->value('debit'));
        $this->assertEquals(50, (float) $je->lines()->whereHas('ledger', fn ($q) => $q->where('name', 'Discount Allowed'))->value('debit'));

        $this->get(route('finance.settlements.show', $s))->assertOk()->assertSee($s->settlement_number);

        $this->delete(route('finance.settlements.destroy', $s), [])->assertSessionHasErrors('reason');
        $this->assertSame('Active', $s->fresh()->status);

        $this->delete(route('finance.settlements.destroy', $s), ['reason' => 'wrong party'])->assertRedirect(route('finance.settlements.index'));
        $s->refresh();
        $this->assertSame('Cancelled', $s->status);
        $this->assertSame('wrong party', $s->cancellation_reason);
        $this->assertSame($this->owner->id, $s->cancelled_by_id);
        $this->assertEquals(0.0, $custLedger->balance(), 'reversal nets the customer ledger to zero');
        $this->assertSame(1, AuditLog::where('action', 'cancel')->where('auditable_id', $s->id)->count());

        $this->delete(route('finance.settlements.destroy', $s), ['reason' => 'again'])->assertSessionHasErrors('status');
    }

    public function test_supplier_settlement_posts_payment_journal(): void
    {
        $inv = $this->purchaseInv(1000);
        $this->post(route('finance.settlements.store'), $this->setPayload('Supplier', $inv->id, ['discount_amount' => 25], ['discount_amount' => 25]));
        $s = BillSettlement::firstOrFail();
        $this->assertMatchesRegularExpression('/^SET-SUP\d{6}$/', $s->settlement_number);
        $je = JournalEntry::findOrFail($s->journal_entry_id);
        $this->assertSame('Payment', $je->voucher_type);
        $supLedger = Ledger::where('name', 'Fin Supplier')->where('ledger_group', 'Sundry Creditors')->firstOrFail();
        $this->assertEquals(325, (float) $je->lines()->where('ledger_id', $supLedger->id)->value('debit'));
        $this->assertEquals(25, (float) $je->lines()->whereHas('ledger', fn ($q) => $q->where('name', 'Discount Received'))->value('credit'));

        $rows = collect($this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Supplier', 'party_id' => $this->supp->id]))->json('bills'));
        $this->assertEquals(675, $rows->firstWhere('id', $inv->id)['balance_due']);

        $this->get(route('finance.settlements.create', ['type' => 'Supplier']))->assertOk()->assertViewHas('settlementType', 'Supplier');

        $this->post(route('finance.settlements.store'), $this->setPayload('Supplier', $inv->id, ['total_amount' => 675], ['settled_amount' => 675, 'bill_amount' => 1000]));
        $this->assertSame([], $this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Supplier', 'party_id' => $this->supp->id, 'branch_id' => $this->branch->id]))->json('bills'));
        $inv2 = $this->purchaseInv(500);
        $inv2->update(['status' => 'Cancelled']);
        $this->assertSame([], $this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Supplier', 'party_id' => $this->supp->id]))->json('bills'));
    }

    public function test_settlement_validation_rules(): void
    {
        $bill = $this->creditBill(1000);
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $bill->id, ['total_amount' => 500]))
            ->assertSessionHasErrors('total_amount');
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $bill->id, [], ['settled_amount' => 0]))
            ->assertSessionHasErrors('allocations');
        $p = $this->setPayload('Customer', $bill->id);
        unset($p['customer_id']);
        $this->post(route('finance.settlements.store'), $p)->assertSessionHasErrors('customer_id');
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $bill->id, ['payment_mode' => 'Barter']))->assertSessionHasErrors('payment_mode');
        $this->assertSame(0, BillSettlement::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_settlement_blocked_in_locked_financial_year_and_permission(): void
    {
        $bill = $this->creditBill(1000);
        FinancialYear::create(['name' => 'FY lock', 'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString(), 'is_locked' => true]);
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $bill->id))->assertSessionHasErrors('financial_year');
        $this->assertSame(0, BillSettlement::count());

        $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $bill->id))->assertForbidden();
    }

    public function test_settlement_index_filters(): void
    {
        $b1 = $this->creditBill(1000);
        $this->post(route('finance.settlements.store'), $this->setPayload('Customer', $b1->id, ['reference_no' => 'CHQ-777']));
        $inv = $this->purchaseInv(1000);
        $this->post(route('finance.settlements.store'), $this->setPayload('Supplier', $inv->id, ['settlement_date' => '2020-01-15']));
        $this->assertSame(2, BillSettlement::count());

        $count = fn (array $q) => $this->get(route('finance.settlements.index', $q))->assertOk()->viewData('settlements')->count();
        $this->assertSame(2, $count([]));
        $this->assertSame(1, $count(['search' => 'CHQ-777']));
        $this->assertSame(1, $count(['search' => 'Fin Supplier']));
        $this->assertSame(1, $count(['settlement_type' => 'Customer']));
        $this->assertSame(1, $count(['date_to' => '2021-01-01']));
        $this->assertSame(1, $count(['date_from' => '2025-01-01']));
        $this->assertSame(2, $count(['branch_id' => $this->branch->id]));
        $this->assertSame(0, $count(['status' => 'Cancelled']));
        $this->assertSame(2, $count(['status' => 'Active']));
    }

    public function test_unpaid_bills_ignore_credit_tender_lines_of_split_pos_payments(): void
    {
        $cash = \App\Models\TenderType::create(['name' => 'Cash X', 'type' => 'Cash', 'status' => true]);
        $credit = \App\Models\TenderType::create(['name' => 'On Account X', 'type' => 'Credit', 'status' => true]);
        $bill = $this->creditBill(1000);
        \App\Models\SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $cash->id, 'amount' => 300]);
        \App\Models\SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $credit->id, 'amount' => 700]);

        $row = collect($this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id]))->json('bills'))->firstWhere('id', $bill->id);
        $this->assertEquals(700, $row['balance_due'], 'only the credit part is outstanding');
        $this->assertEquals(300, $row['paid_amount']);

        // once the cash part covers the whole bill nothing is outstanding
        \App\Models\SalesBillPayment::where('tender_type_id', $credit->id)->update(['tender_type_id' => $cash->id]);
        $this->assertSame([], $this->getJson(route('finance.settlements.unpaid-bills', ['type' => 'Customer', 'party_id' => $this->cust->id]))->json('bills'));
    }

    public function test_create_form_offers_preselected_customer_outside_first_thirty(): void
    {
        for ($i = 0; $i < 32; $i++) {
            \App\Models\Customer::create(['name' => sprintf('AAA %02d', $i), 'mobile' => '93000000'.sprintf('%02d', $i), 'state' => 'Gujarat', 'status' => true]);
        }
        $late = \App\Models\Customer::create(['name' => 'ZZZ Late Payer', 'mobile' => '9666000000', 'state' => 'Gujarat', 'status' => true]);
        $this->assertArrayNotHasKey($late->id, $this->get(route('finance.settlements.create'))->viewData('customers')->all());
        $this->assertSame('ZZZ Late Payer', $this->get(route('finance.settlements.create', ['customer_id' => $late->id]))->viewData('customers')[$late->id]);
    }
}
