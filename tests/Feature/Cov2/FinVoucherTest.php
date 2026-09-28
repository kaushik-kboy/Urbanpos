<?php

namespace Tests\Feature\Cov2;

use App\Models\AuditLog;
use App\Models\FinancialYear;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use Tests\TestCase;

class FinVoucherTest extends TestCase
{
    use FinHelper;

    private function led(string $name, string $group = 'Indirect Expense'): Ledger
    {
        return Ledger::create(['name' => $name, 'ledger_group' => $group, 'opening_balance' => 0, 'opening_balance_type' => 'Debit', 'status' => true]);
    }

    private function vPayload(string $type, array $lines, array $over = []): array
    {
        return array_merge([
            'voucher_type' => $type, 'voucher_date' => now()->toDateString(),
            'branch_id' => $this->branch->id, 'narration' => 'test '.$type, 'lines' => $lines,
        ], $over);
    }

    public function test_each_voucher_type_gets_its_own_prefix_and_sequence(): void
    {
        $a = $this->led('Cash X', 'Cash in Hand');
        $b = $this->led('Rent X');
        $lines = [['ledger_id' => $b->id, 'debit' => 100], ['ledger_id' => $a->id, 'credit' => 100]];

        foreach (['Payment' => 'PMT-', 'Receipt' => 'RCT-', 'Contra' => 'CTR-', 'Journal' => 'JV-'] as $type => $pfx) {
            $this->post(route('finance.vouchers.store'), $this->vPayload($type, $lines))
                ->assertRedirect(route('finance.vouchers.index'));
            $je = JournalEntry::where('voucher_type', $type)->firstOrFail();
            $this->assertMatchesRegularExpression('/^'.$pfx.'\d{6}$/', $je->voucher_number);
            $this->assertEquals(100, (float) $je->total_debit);
            $this->assertEquals(100, (float) $je->total_credit);
            $this->assertSame(2, $je->lines()->count());
        }

        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', $lines));
        $nums = JournalEntry::where('voucher_type', 'Payment')->orderBy('id')->pluck('voucher_number')->all();
        $this->assertCount(2, $nums);
        $this->assertSame((int) substr($nums[0], 4) + 1, (int) substr($nums[1], 4));
    }

    public function test_unbalanced_voucher_is_rejected_and_nothing_is_saved(): void
    {
        $a = $this->led('Cash Y', 'Cash in Hand');
        $b = $this->led('Rent Y');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => $b->id, 'debit' => 100], ['ledger_id' => $a->id, 'credit' => 90],
        ]))->assertSessionHasErrors('lines');
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_zero_amount_voucher_is_rejected(): void
    {
        $a = $this->led('Cash Z', 'Cash in Hand');
        $b = $this->led('Rent Z');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Journal', [
            ['ledger_id' => $b->id, 'debit' => 0], ['ledger_id' => $a->id, 'credit' => 0],
        ]))->assertSessionHasErrors('lines');
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_voucher_validation_rules(): void
    {
        $a = $this->led('Cash V', 'Cash in Hand');
        $b = $this->led('Rent V');
        $ok = [['ledger_id' => $b->id, 'debit' => 5], ['ledger_id' => $a->id, 'credit' => 5]];

        $this->post(route('finance.vouchers.store'), $this->vPayload('Sales', $ok))->assertSessionHasErrors('voucher_type');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [$ok[0]]))->assertSessionHasErrors('lines');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => 999999, 'debit' => 5], $ok[1],
        ]))->assertSessionHasErrors('lines.0.ledger_id');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => $b->id, 'debit' => -5], $ok[1],
        ]))->assertSessionHasErrors('lines.0.debit');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', $ok, ['branch_id' => 999999]))->assertSessionHasErrors('branch_id');
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_update_replaces_lines_and_recomputes_totals_keeping_number(): void
    {
        $a = $this->led('Cash U', 'Cash in Hand');
        $b = $this->led('Rent U');
        $c = $this->led('Power U');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => $b->id, 'debit' => 100], ['ledger_id' => $a->id, 'credit' => 100],
        ]));
        $je = JournalEntry::firstOrFail();
        $number = $je->voucher_number;

        $this->get(route('finance.vouchers.edit', $je))->assertOk();

        $this->put(route('finance.vouchers.update', $je), $this->vPayload('Payment', [
            ['ledger_id' => $c->id, 'debit' => 250], ['ledger_id' => $a->id, 'credit' => 250],
        ], ['narration' => 'revised']))->assertRedirect(route('finance.vouchers.index'));

        $je->refresh();
        $this->assertSame($number, $je->voucher_number);
        $this->assertSame('revised', $je->narration);
        $this->assertEquals(250, (float) $je->total_debit);
        $this->assertSame(2, $je->lines()->count());
        $this->assertEquals(250, (float) $je->lines()->where('ledger_id', $c->id)->value('debit'));
        $this->assertSame(0, $je->lines()->where('ledger_id', $b->id)->count());

        $this->put(route('finance.vouchers.update', $je), $this->vPayload('Payment', [
            ['ledger_id' => $c->id, 'debit' => 10], ['ledger_id' => $a->id, 'credit' => 5],
        ]))->assertSessionHasErrors('lines');
        $this->assertEquals(250, (float) $je->fresh()->total_debit);
        $this->assertSame(2, $je->lines()->count());
    }

    public function test_destroy_deletes_voucher_and_writes_audit_entry(): void
    {
        $a = $this->led('Cash D', 'Cash in Hand');
        $b = $this->led('Rent D');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Journal', [
            ['ledger_id' => $b->id, 'debit' => 40], ['ledger_id' => $a->id, 'credit' => 40],
        ]));
        $je = JournalEntry::firstOrFail();
        $id = $je->id;

        $this->delete(route('finance.vouchers.destroy', $je))->assertRedirect(route('finance.vouchers.index'));
        $this->assertNull(JournalEntry::find($id));
        $this->assertSame(0, \App\Models\JournalEntryLine::where('journal_entry_id', $id)->count());
        $this->assertSame(1, AuditLog::where('action', 'delete')->where('auditable_id', $id)->count());
    }

    public function test_index_filters_and_only_lists_manual_types(): void
    {
        $a = $this->led('Cash I', 'Cash in Hand');
        $b = $this->led('Rent I');
        $l = [['ledger_id' => $b->id, 'debit' => 10], ['ledger_id' => $a->id, 'credit' => 10]];
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', $l, ['narration' => 'alpha-rent', 'voucher_date' => '2026-01-05']));
        $this->post(route('finance.vouchers.store'), $this->vPayload('Receipt', $l, ['narration' => 'beta-sale', 'voucher_date' => '2026-02-05']));
        JournalEntry::create(['voucher_number' => 'SB-AUTO-1', 'voucher_type' => 'Sales', 'voucher_date' => '2026-01-05', 'branch_id' => $this->branch->id, 'narration' => 'auto', 'total_debit' => 1, 'total_credit' => 1]);

        $all = $this->get(route('finance.vouchers.index'))->assertOk();
        $all->assertDontSee('SB-AUTO-1');
        $this->assertCount(2, $all->viewData('vouchers'));

        $this->assertSame(['alpha-rent'], $this->get(route('finance.vouchers.index', ['search' => 'alpha']))->viewData('vouchers')->pluck('narration')->all());
        $this->assertSame(['beta-sale'], $this->get(route('finance.vouchers.index', ['voucher_type' => 'Receipt']))->viewData('vouchers')->pluck('narration')->all());
        $this->assertSame(['beta-sale'], $this->get(route('finance.vouchers.index', ['date_from' => '2026-02-01']))->viewData('vouchers')->pluck('narration')->all());
        $this->assertSame(['alpha-rent'], $this->get(route('finance.vouchers.index', ['date_to' => '2026-01-31']))->viewData('vouchers')->pluck('narration')->all());
        $this->assertCount(2, $this->get(route('finance.vouchers.index', ['branch_id' => $this->branch->id]))->viewData('vouchers'));
        $this->assertCount(0, $this->get(route('finance.vouchers.index', ['branch_id' => 999999]))->viewData('vouchers'));
        $this->get(route('finance.vouchers.create'))->assertOk();
    }

    public function test_voucher_writes_require_permissions(): void
    {
        $a = $this->led('Cash P', 'Cash in Hand');
        $b = $this->led('Rent P');
        $l = [['ledger_id' => $b->id, 'debit' => 10], ['ledger_id' => $a->id, 'credit' => 10]];
        $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', $l))->assertForbidden();
        $this->assertSame(0, JournalEntry::count());
    }

    // ---- Ledgers ---------------------------------------------------------

    public function test_ledger_crud_and_validation(): void
    {
        $this->post(route('finance.ledgers.store'), ['name' => 'Bad', 'ledger_group' => 'Nonsense', 'opening_balance' => -1, 'opening_balance_type' => 'X', 'status' => 'maybe'])
            ->assertSessionHasErrors(['ledger_group', 'opening_balance', 'opening_balance_type', 'status']);
        $this->assertSame(0, Ledger::where('name', 'Bad')->count());

        $this->get(route('finance.ledgers.create'))->assertOk();
        $this->post(route('finance.ledgers.store'), ['name' => 'Petty Cash', 'ledger_group' => 'Cash in Hand', 'opening_balance' => 500, 'opening_balance_type' => 'Debit', 'status' => 1])
            ->assertRedirect(route('finance.ledgers.index'));
        $l = Ledger::where('name', 'Petty Cash')->firstOrFail();
        $this->assertEquals(500, (float) $l->opening_balance);
        $this->assertTrue($l->status);

        $this->get(route('finance.ledgers.edit', $l))->assertOk();
        $this->put(route('finance.ledgers.update', $l), ['name' => 'Petty Cash 2', 'ledger_group' => 'Bank Account', 'opening_balance' => 50, 'opening_balance_type' => 'Credit', 'status' => 0])
            ->assertRedirect(route('finance.ledgers.index'));
        $l->refresh();
        $this->assertSame('Petty Cash 2', $l->name);
        $this->assertFalse($l->status);
        $this->assertEquals(-50.0, $l->balance());

        $this->delete(route('finance.ledgers.destroy', $l))->assertRedirect(route('finance.ledgers.index'));
        $this->assertNull(Ledger::find($l->id));
    }

    public function test_ledger_index_filters_and_balance(): void
    {
        $cash = $this->led('Cash Q', 'Cash in Hand');
        $rent = $this->led('Rent Q');
        $rent->update(['status' => false]);
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => $rent->id, 'debit' => 70], ['ledger_id' => $cash->id, 'credit' => 70],
        ]));

        $rows = $this->get(route('finance.ledgers.index', ['search' => 'Cash Q']))->assertOk()->viewData('ledgers');
        $this->assertCount(1, $rows);
        $this->assertEquals(-70.0, $rows->first()->current_balance);

        $this->assertSame(['Rent Q'], $this->get(route('finance.ledgers.index', ['ledger_group' => 'Indirect Expense']))->viewData('ledgers')->pluck('name')->all());
        $this->assertSame(['Rent Q'], $this->get(route('finance.ledgers.index', ['status' => '0']))->viewData('ledgers')->pluck('name')->all());
    }

    public function test_ledger_delete_blocked_when_linked_or_posted(): void
    {
        $linked = Ledger::create(['name' => 'Cust Ledger', 'ledger_group' => 'Sundry Debtors', 'opening_balance' => 0, 'opening_balance_type' => 'Debit', 'status' => true, 'customer_id' => $this->cust->id]);
        $this->delete(route('finance.ledgers.destroy', $linked))->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Customer/Supplier'));
        $this->assertNotNull(Ledger::find($linked->id));

        $cash = $this->led('Cash R', 'Cash in Hand');
        $rent = $this->led('Rent R');
        $this->post(route('finance.vouchers.store'), $this->vPayload('Payment', [
            ['ledger_id' => $rent->id, 'debit' => 9], ['ledger_id' => $cash->id, 'credit' => 9],
        ]));
        $this->delete(route('finance.ledgers.destroy', $rent))->assertSessionHas('status', fn ($s) => str_contains($s, 'posting history'));
        $this->assertNotNull(Ledger::find($rent->id));
    }

    // ---- Financial years ---------------------------------------------------

    public function test_financial_year_create_validation_lock_reopen_and_locked_edit_rule(): void
    {
        $this->post(route('master.financial-years.store'), ['name' => 'FY Bad', 'start_date' => '2026-04-01', 'end_date' => '2026-04-01'])
            ->assertSessionHasErrors('end_date');
        $this->assertSame(0, FinancialYear::count());

        $this->get(route('master.financial-years.create'))->assertOk();
        $this->post(route('master.financial-years.store'), ['name' => 'FY 26-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31'])
            ->assertRedirect(route('master.financial-years.index'));
        $fy = FinancialYear::firstOrFail();
        $this->assertFalse($fy->is_locked);

        $this->get(route('master.financial-years.index'))->assertOk()->assertSee('FY 26-27');
        $this->get(route('master.financial-years.show', $fy))->assertRedirect(route('master.financial-years.edit', $fy));
        $this->get(route('master.financial-years.edit', $fy))->assertOk();

        $this->post(route('master.financial-years.lock', $fy))->assertRedirect(route('master.financial-years.index'));
        $fy->refresh();
        $this->assertTrue($fy->is_locked);
        $this->assertSame($this->owner->id, $fy->locked_by_id);
        $this->assertNotNull($fy->locked_at);
        $this->assertSame(1, AuditLog::where('action', 'lock')->where('auditable_id', $fy->id)->count());

        $this->put(route('master.financial-years.update', $fy), ['name' => 'Renamed', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31'])
            ->assertSessionHasErrors('financial_year');
        $this->assertSame('FY 26-27', $fy->fresh()->name);

        $this->post(route('master.financial-years.reopen', $fy))->assertRedirect(route('master.financial-years.index'));
        $fy->refresh();
        $this->assertFalse($fy->is_locked);
        $this->assertNull($fy->locked_by_id);
        $this->assertNull($fy->locked_at);
        $this->assertSame(1, AuditLog::where('action', 'reopen')->where('auditable_id', $fy->id)->count());

        $this->put(route('master.financial-years.update', $fy), ['name' => 'Renamed', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31'])
            ->assertRedirect(route('master.financial-years.index'));
        $this->assertSame('Renamed', $fy->fresh()->name);
    }

    public function test_financial_year_actions_require_permission(): void
    {
        $fy = FinancialYear::create(['name' => 'FYP', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
        $this->post(route('master.financial-years.lock', $fy))->assertForbidden();
        $this->post(route('master.financial-years.reopen', $fy))->assertForbidden();
        $this->post(route('master.financial-years.store'), ['name' => 'N', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31'])->assertForbidden();
        $this->assertFalse($fy->fresh()->is_locked);
        $this->assertSame(1, FinancialYear::count());
    }

    // ---- Sales aux ---------------------------------------------------------

    public function test_sales_aux_module_titles_known_and_fallback(): void
    {
        $this->get(route('sales.aux', 'quotations'))->assertOk()->assertSee('Sales Quotation');
        $r = $this->get(route('sales.aux', 'order-approval'))->assertOk();
        $this->assertSame('Sales Order Approval', $r->viewData('title'));
        $this->assertNull($r->viewData('newButton'));
        $this->assertSame('Delivery Note Return', $this->get(route('sales.aux', 'delivery-note-returns'))->viewData('title'));
        $this->assertSame('Sales Order', $this->get(route('sales.aux', 'orders'))->viewData('title'));
        $this->assertSame('Delivery Note', $this->get(route('sales.aux', 'delivery-notes'))->viewData('title'));
        $this->assertSame('Sales > More', $this->get(route('sales.aux', 'transfer-out-approval'))->viewData('section'));

        $f = $this->get(route('sales.aux', 'gift-vouchers'))->assertOk();
        $this->assertSame('Gift Vouchers', $f->viewData('title'));
        $this->assertSame('New Entry', $f->viewData('newButton'));
        $this->assertCount(5, $f->viewData('columns'));
    }

    public function test_sales_aux_requires_login(): void
    {
        auth()->logout();
        $this->get(route('sales.aux', 'orders'))->assertRedirect(route('login'));
    }
}
