<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\CreditLimitGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 5 acceptance tests: the three small, independent fixes flagged after the
 * permission-gating pass — ItemPriceChangeController's loop is now transactional,
 * LedgerController::destroy() no longer 500s on a ledger with journal history, and
 * Customer/Supplier credit limits are enforced against the LIVE ledger balance (not the
 * dead credit_balance column).
 */
class Phase5FixesTest extends TestCase
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

    public function test_customer_at_credit_limit_is_blocked_from_a_new_sale_that_would_exceed_it(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Credit Test Branch']);
        $customer = Customer::create(['name' => 'Credit Test Customer', 'credit_limit' => 100]);
        $item = Item::create(['name' => 'Credit Test Item', 'allow_negative_stock' => true]);

        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 500]],
        ]);

        $response->assertSessionHasErrors('credit_limit');
        $this->assertEquals(0, $customer->fresh()->ledger->balance(), 'Blocked sale must not have posted anything.');
    }

    public function test_customer_with_zero_credit_limit_is_never_blocked(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'No Limit Branch']);
        $customer = Customer::create(['name' => 'No Limit Customer', 'credit_limit' => 0]);
        $item = Item::create(['name' => 'No Limit Item', 'allow_negative_stock' => true]);

        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 999999]],
        ]);

        $response->assertSessionDoesntHaveErrors('credit_limit');
    }

    public function test_customer_under_their_limit_can_still_buy(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Under Limit Branch']);
        $customer = Customer::create(['name' => 'Under Limit Customer', 'credit_limit' => 1000]);
        $item = Item::create(['name' => 'Under Limit Item', 'allow_negative_stock' => true]);

        $response = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertGreaterThan(0, $customer->fresh()->ledger->balance());
    }

    public function test_credit_limit_guard_uses_live_ledger_balance_not_the_stale_column(): void
    {
        $customer = Customer::create(['name' => 'Stale Column Customer', 'credit_limit' => 500, 'credit_balance' => 999999]);

        // credit_balance says 999999 (way over limit) but nothing has ever actually been
        // posted to this customer's ledger — the guard must trust the ledger, not the field.
        app(CreditLimitGuard::class)->assertWithinLimit($customer, 100);
        $this->assertTrue(true, 'Guard did not throw despite the stale credit_balance column being way over limit.');
    }

    public function test_supplier_over_credit_limit_is_blocked_from_a_new_purchase(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Supplier Credit Branch']);
        $supplier = Supplier::create(['name' => 'Supplier Credit Test', 'credit_limit' => 50]);
        $item = Item::create(['name' => 'Supplier Credit Item']);

        $response = $this->post(route('purchase.purchase-invoices.store'), [
            'invoice_date' => '2026-09-16',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'cost_price' => 500]],
            'supplier_inv_amount' => 500,
        ]);

        $response->assertSessionHasErrors('credit_limit');
    }

    public function test_item_price_change_loop_rolls_back_fully_on_mid_loop_failure(): void
    {
        $owner = $this->actingAsOwner();
        $branchA = Branch::create(['name' => 'Price Rollback Branch A']);
        $item = Item::create(['name' => 'Price Rollback Item', 'sell_price' => 10, 'mrp' => 15]);

        // A non-existent branch id in the second slot forces ItemStock::updateOrCreate()
        // to fail on its foreign key after branch A has already been written in the loop —
        // the transaction must undo branch A's write too, not leave it half-applied.
        try {
            $this->post(route('master.item-price-change.update'), [
                'item_id' => $item->id,
                'prices' => [
                    $branchA->id => ['sell_price' => 999, 'mrp' => 1099],
                    999999 => ['sell_price' => 111, 'mrp' => 222],
                ],
            ]);
        } catch (\Throwable $e) {
            // Expected: the FK violation propagates past the transaction in a test context.
        }

        $this->assertNull(
            \App\Models\ItemStock::where('item_id', $item->id)->where('branch_id', $branchA->id)->first(),
            'Branch A must not have been committed once the transaction rolled back.'
        );
    }

    public function test_deleting_a_ledger_with_journal_history_redirects_cleanly_instead_of_500ing(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'Ledger History Branch']);
        $ledger = Ledger::create([
            'name' => 'Ledger With History', 'ledger_group' => 'Cash in Hand',
            'opening_balance' => 0, 'opening_balance_type' => 'Debit', 'status' => true,
        ]);
        $otherLedger = Ledger::create([
            'name' => 'Other Side', 'ledger_group' => 'Sales Account',
            'opening_balance' => 0, 'opening_balance_type' => 'Credit', 'status' => true,
        ]);

        \App\Models\JournalEntry::create([
            'voucher_number' => 'JE-TEST-'.$ledger->id, 'voucher_type' => 'Receipt',
            'voucher_date' => '2026-09-16', 'branch_id' => $branch->id, 'narration' => 'Test entry',
        ])->lines()->createMany([
            ['ledger_id' => $ledger->id, 'debit' => 100, 'credit' => 0],
            ['ledger_id' => $otherLedger->id, 'debit' => 0, 'credit' => 100],
        ]);

        $response = $this->from(route('finance.ledgers.index'))->delete(route('finance.ledgers.destroy', $ledger));

        $response->assertRedirect(route('finance.ledgers.index'));
        $this->assertNotNull($ledger->fresh(), 'Ledger with journal history must not be deleted.');
    }
}
