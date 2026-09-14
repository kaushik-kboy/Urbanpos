<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\TenderType;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 7 acceptance tests: Till/EOD is additive — a Sales Bill posted the old way (no
 * "payments" key at all) must behave byte-for-byte as it did before this phase. Only a
 * request that opts in by sending payments[] gets till linkage and tender-split ledger
 * postings.
 */
class Phase7TillEodTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function actingAsOwner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        return $owner;
    }

    public function test_cashier_can_open_and_close_a_till_with_correct_variance(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);

        $branch = Branch::create(['name' => 'Till Test Branch']);
        $register = Register::create(['branch_id' => $branch->id, 'name' => 'Counter 1']);

        $openResponse = $this->post(route('till.sessions.open'), [
            'register_id' => $register->id,
            'opening_cash' => 200,
        ]);
        $session = TillSession::latest('id')->first();
        $openResponse->assertRedirect(route('till.sessions.show', $session));
        $this->assertTrue($session->isOpen());

        $this->post(route('till.sessions.cash-movements', $session), ['type' => 'In', 'amount' => 50]);
        $this->post(route('till.sessions.cash-movements', $session), ['type' => 'Out', 'amount' => 20]);

        // Expected cash = 200 opening + 0 cash sales + 50 in - 20 out = 230.
        $closeResponse = $this->post(route('till.sessions.close', $session), ['actual_cash' => 230]);
        $closeResponse->assertRedirect(route('till.sessions.show', $session));

        $session->refresh();
        $this->assertEquals(230.0, (float) $session->expected_cash);
        $this->assertEquals(230.0, (float) $session->actual_cash);
        $this->assertEquals(0.0, (float) $session->variance);
        $this->assertFalse($session->isOpen());
    }

    public function test_opening_a_second_session_on_the_same_register_is_blocked(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Double Open Branch']);
        $register = Register::create(['branch_id' => $branch->id, 'name' => 'Counter 2']);

        $this->post(route('till.sessions.open'), ['register_id' => $register->id, 'opening_cash' => 100]);
        $response = $this->post(route('till.sessions.open'), ['register_id' => $register->id, 'opening_cash' => 100]);

        $response->assertSessionHasErrors('register_id');
        $this->assertEquals(1, TillSession::where('register_id', $register->id)->where('status', 'Open')->count());
    }

    public function test_closing_an_already_closed_till_is_blocked(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Reclose Branch']);
        $register = Register::create(['branch_id' => $branch->id, 'name' => 'Counter 3']);

        $this->post(route('till.sessions.open'), ['register_id' => $register->id, 'opening_cash' => 100]);
        $session = TillSession::latest('id')->first();
        $this->post(route('till.sessions.close', $session), ['actual_cash' => 100]);

        $response = $this->post(route('till.sessions.close', $session), ['actual_cash' => 100]);
        $response->assertSessionHasErrors('till_session');
    }

    public function test_old_style_sale_with_no_payments_key_posts_identically_to_before(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Old Style Branch']);
        $customer = Customer::create(['name' => 'Old Style Customer']);
        $item = Item::create(['name' => 'Old Style Item', 'allow_negative_stock' => true]);

        $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16', 'customer_id' => $customer->id, 'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice', 'delivery_type' => 'Counter', 'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
        ]);

        $bill = SalesBill::latest('id')->first();
        $this->assertNull($bill->till_session_id);
        $this->assertCount(0, $bill->payments);

        $entry = JournalEntry::where('reference_type', SalesBill::class)->where('reference_id', $bill->id)->first();
        $this->assertCount(2, $entry->lines);
        $customerLine = $entry->lines->firstWhere('ledger_id', $customer->ledger->id);
        $this->assertEquals(100.0, (float) $customerLine->debit, 'Old-style sale must still debit the full total to the customer ledger.');
    }

    public function test_split_payment_sale_posts_correct_debit_lines_per_tender(): void
    {
        $owner = $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Split Payment Branch']);
        $register = Register::create(['branch_id' => $branch->id, 'name' => 'Counter 4']);
        $customer = Customer::create(['name' => 'Split Payment Customer', 'credit_limit' => 1000]);
        $item = Item::create(['name' => 'Split Payment Item', 'allow_negative_stock' => true]);
        $cashTender = TenderType::create(['name' => 'Cash', 'type' => 'Cash']);
        $creditTender = TenderType::create(['name' => 'Credit', 'type' => 'Credit']);

        $this->post(route('till.sessions.open'), ['register_id' => $register->id, 'opening_cash' => 0]);
        $session = TillSession::latest('id')->first();

        $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16', 'customer_id' => $customer->id, 'branch_id' => $branch->id,
            'till_session_id' => $session->id,
            'invoice_type' => 'Tax Invoice', 'delivery_type' => 'Counter', 'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
            'payments' => [
                ['tender_type_id' => $cashTender->id, 'amount' => 60],
                ['tender_type_id' => $creditTender->id, 'amount' => 40],
            ],
        ]);

        $bill = SalesBill::latest('id')->first();
        $this->assertEquals($session->id, $bill->till_session_id);
        $this->assertCount(2, $bill->payments);

        $entry = JournalEntry::where('reference_type', SalesBill::class)->where('reference_id', $bill->id)->first();
        $cashLedger = \App\Models\Ledger::where('name', 'Cash in Hand')->where('ledger_group', 'Cash in Hand')->first();
        $cashLine = $entry->lines->firstWhere('ledger_id', $cashLedger->id);
        $customerLine = $entry->lines->firstWhere('ledger_id', $customer->ledger->id);

        $this->assertEquals(60.0, (float) $cashLine->debit, 'Cash portion must debit the Cash in Hand ledger.');
        $this->assertEquals(40.0, (float) $customerLine->debit, 'Credit portion must still debit the customer ledger.');

        // Expected till cash = 0 opening + 60 cash sale = 60.
        $this->post(route('till.sessions.close', $session), ['actual_cash' => 60]);
        $session->refresh();
        $this->assertEquals(0.0, (float) $session->variance);
    }

    public function test_payments_must_sum_to_the_bill_total(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Mismatch Branch']);
        $customer = Customer::create(['name' => 'Mismatch Customer']);
        $item = Item::create(['name' => 'Mismatch Item', 'allow_negative_stock' => true]);
        $cashTender = TenderType::create(['name' => 'Cash', 'type' => 'Cash']);

        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16', 'customer_id' => $customer->id, 'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice', 'delivery_type' => 'Counter', 'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
            'payments' => [['tender_type_id' => $cashTender->id, 'amount' => 50]],
        ]);

        $response->assertSessionHasErrors('payments');
        $this->assertEquals(0, SalesBill::count(), 'A mismatched payment split must block the sale from being created at all.');
    }

    public function test_eod_report_aggregates_match_a_hand_built_scenario(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'EOD Branch']);
        $customer = Customer::create(['name' => 'EOD Customer', 'credit_limit' => 1000]);
        $item = Item::create(['name' => 'EOD Item', 'allow_negative_stock' => true]);
        $cashTender = TenderType::create(['name' => 'Cash', 'type' => 'Cash']);

        $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16', 'customer_id' => $customer->id, 'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice', 'delivery_type' => 'Counter', 'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
            'payments' => [['tender_type_id' => $cashTender->id, 'amount' => 100]],
        ]);

        $response = $this->get(route('reports.eod', ['from' => '2026-09-16', 'to' => '2026-09-16', 'branch_id' => $branch->id]));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['sales_count'] === 1
                && (float) $summary['payment_totals']['Cash'] === 100.0
                && (float) $summary['unattributed_total'] === 0.0;
        });
    }
}
