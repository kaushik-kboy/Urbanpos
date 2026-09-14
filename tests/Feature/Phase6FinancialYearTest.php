<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 6 acceptance tests: Financial Year lock (spec §15.4). The guard fails OPEN when
 * zero FinancialYear rows exist anywhere (feature not yet adopted — every pre-Phase-6
 * test/production document predates this screen), but once at least one is defined,
 * a document date must fall inside an open (non-locked) year.
 */
class Phase6FinancialYearTest extends TestCase
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

    private function postSalesBill(Customer $customer, Branch $branch, Item $item, string $billDate)
    {
        return $this->post(route('sales.sales-bills.store'), [
            'bill_date' => $billDate,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 10]],
        ]);
    }

    public function test_posting_is_unrestricted_when_no_financial_year_is_defined_anywhere(): void
    {
        $this->actingAsOwner();
        $branch = Branch::create(['name' => 'No FY Branch']);
        $customer = Customer::create(['name' => 'No FY Customer']);
        $item = Item::create(['name' => 'No FY Item', 'allow_negative_stock' => true]);

        $this->assertEquals(0, FinancialYear::count());
        $response = $this->postSalesBill($customer, $branch, $item, '2020-01-01');
        $response->assertSessionDoesntHaveErrors('financial_year');
    }

    public function test_document_date_outside_any_defined_financial_year_is_blocked(): void
    {
        $this->actingAsOwner();
        FinancialYear::create(['name' => 'FY 2025-26', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31']);

        $branch = Branch::create(['name' => 'Outside FY Branch']);
        $customer = Customer::create(['name' => 'Outside FY Customer']);
        $item = Item::create(['name' => 'Outside FY Item', 'allow_negative_stock' => true]);

        // 2026-09-16 falls outside the only defined FY (which ends 2026-03-31).
        $response = $this->postSalesBill($customer, $branch, $item, '2026-09-16');
        $response->assertSessionHasErrors('financial_year');
    }

    public function test_backdated_posting_within_an_open_financial_year_is_unaffected(): void
    {
        $this->actingAsOwner();
        FinancialYear::create(['name' => 'FY 2025-26', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31']);

        $branch = Branch::create(['name' => 'Backdate Branch']);
        $customer = Customer::create(['name' => 'Backdate Customer']);
        $item = Item::create(['name' => 'Backdate Item', 'allow_negative_stock' => true]);

        // A date well in the past relative to "today" but still inside the open FY.
        $response = $this->postSalesBill($customer, $branch, $item, '2025-05-10');
        $response->assertSessionDoesntHaveErrors('financial_year');
    }

    public function test_posting_into_a_locked_financial_year_is_blocked_then_succeeds_after_reopen_with_audit_log(): void
    {
        $owner = $this->actingAsOwner();
        $fy = FinancialYear::create([
            'name' => 'FY Locked Test', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_locked' => true, 'locked_by_id' => $owner->id, 'locked_at' => now(),
        ]);

        $branch = Branch::create(['name' => 'Locked FY Branch']);
        $customer = Customer::create(['name' => 'Locked FY Customer']);
        $item = Item::create(['name' => 'Locked FY Item', 'allow_negative_stock' => true]);

        $blocked = $this->postSalesBill($customer, $branch, $item, '2026-06-15');
        $blocked->assertSessionHasErrors('financial_year');

        $reopenResponse = $this->post(route('master.financial-years.reopen', $fy));
        $reopenResponse->assertRedirect(route('master.financial-years.index'));

        $log = AuditLog::where('auditable_type', FinancialYear::class)->where('auditable_id', $fy->id)->first();
        $this->assertNotNull($log, 'Reopening a Financial Year must write an audit log row.');
        $this->assertEquals('reopen', $log->action);
        $this->assertTrue($log->old_values['is_locked']);
        $this->assertFalse($log->new_values['is_locked']);

        $allowed = $this->postSalesBill($customer, $branch, $item, '2026-06-15');
        $allowed->assertSessionDoesntHaveErrors('financial_year');
    }

    public function test_manager_is_blocked_from_creating_locking_or_reopening_financial_years(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        $this->post(route('master.financial-years.store'), [
            'name' => 'Should Not Be Created', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ])->assertForbidden();

        $fy = FinancialYear::create(['name' => 'Manager Lock Test', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->post(route('master.financial-years.lock', $fy))->assertForbidden();

        $fy->update(['is_locked' => true]);
        $this->post(route('master.financial-years.reopen', $fy))->assertForbidden();
    }

    public function test_owner_financial_year_screens_render(): void
    {
        $this->actingAsOwner();
        $fy = FinancialYear::create(['name' => 'Render Test FY', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->get(route('master.financial-years.index'))->assertOk();
        $this->get(route('master.financial-years.create'))->assertOk();
        $this->get(route('master.financial-years.edit', $fy))->assertOk();
    }
}
