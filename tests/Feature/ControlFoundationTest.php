<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 4 acceptance tests: role/permission enforcement actually blocks unauthorized
 * requests (not just hides a button), branch scoping restricts a single-branch user to
 * their own branch while a null-branch (Owner) user is unrestricted, and cancelling a
 * document / editing GST rates writes a real audit_logs row.
 */
class ControlFoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles/permissions are seeded once into the real dev DB (RolesAndPermissionsSeeder
        // already ran outside these tests); re-seeding here is idempotent (firstOrCreate)
        // and makes this test file self-sufficient if run against a fresh database.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_cashier_role_is_blocked_from_purchase_invoices_but_allowed_sales_bills(): void
    {
        $branch = Branch::create(['name' => 'Control Test Branch']);
        $supplier = Supplier::create(['name' => 'Control Test Supplier']);
        $customer = Customer::create(['name' => 'Control Test Customer']);
        $item = Item::create(['name' => 'Control Test Item']);

        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);

        $purchaseResponse = $this->post(route('purchase.purchase-invoices.store'), [
            'invoice_date' => '2026-09-16',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'cost_price' => 10]],
        ]);
        $purchaseResponse->assertForbidden();

        $salesResponse = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 10]],
        ]);
        // Cashier lacks stock, but the point here is permission (not stock availability) —
        // a 422/redirect-with-errors from validation proves the permission gate let it
        // through; a 403 would mean the gate wrongly blocked it.
        $this->assertNotEquals(403, $salesResponse->getStatusCode());
    }

    public function test_branch_scoped_user_is_blocked_from_another_branchs_sales_bill(): void
    {
        $branchA = Branch::create(['name' => 'Scope Test Branch A']);
        $branchB = Branch::create(['name' => 'Scope Test Branch B']);
        $customer = Customer::create(['name' => 'Scope Test Customer']);
        $item = Item::create(['name' => 'Scope Test Item']);

        app(StockLedgerService::class)->post(
            itemId: $item->id, branchId: $branchB->id, movementType: 'OPENING',
            qtyDelta: 10, unitCost: 50, referenceType: null, referenceId: null,
            documentDate: '2026-09-01',
        );

        $scopedUser = User::factory()->create(['branch_id' => $branchA->id]);
        $scopedUser->assignRole('Manager');
        $this->actingAs($scopedUser);

        $blocked = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branchB->id, // NOT their own branch
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 10]],
        ]);
        $blocked->assertForbidden();

        $allowed = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id, // their own branch, no stock needed for this check
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 10]],
        ]);
        $this->assertNotEquals(403, $allowed->getStatusCode());
    }

    public function test_null_branch_owner_is_unrestricted_across_branches(): void
    {
        $branchA = Branch::create(['name' => 'Owner Test Branch A']);
        $branchB = Branch::create(['name' => 'Owner Test Branch B']);
        $customer = Customer::create(['name' => 'Owner Test Customer']);
        $item = Item::create(['name' => 'Owner Test Item']);

        $owner = User::factory()->create(['branch_id' => null]);
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        foreach ([$branchA, $branchB] as $branch) {
            $response = $this->post(route('sales.sales-bills.store'), [
                'bill_date' => '2026-09-16',
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'invoice_type' => 'Tax Invoice',
                'delivery_type' => 'Counter',
                'sales_type' => 'Local',
                'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 10]],
            ]);
            $this->assertNotEquals(403, $response->getStatusCode(), "Owner must not be branch-restricted for branch {$branch->id}.");
        }
    }

    public function test_cancelling_sales_bill_writes_audit_log_with_old_values(): void
    {
        $branch = Branch::create(['name' => 'Audit Test Branch']);
        $customer = Customer::create(['name' => 'Audit Test Customer']);
        $item = Item::create(['name' => 'Audit Test Item']);

        app(StockLedgerService::class)->post(
            itemId: $item->id, branchId: $branch->id, movementType: 'OPENING',
            qtyDelta: 10, unitCost: 50, referenceType: null, referenceId: null,
            documentDate: '2026-09-01',
        );

        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-16',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'sell_price' => 100]],
        ]);
        $salesBill = SalesBill::latest('id')->first();

        $this->delete(route('sales.sales-bills.destroy', $salesBill));

        $log = AuditLog::where('auditable_type', SalesBill::class)->where('auditable_id', $salesBill->id)->first();
        $this->assertNotNull($log, 'Cancelling a Sales Bill must write an audit log row.');
        $this->assertEquals('cancel', $log->action);
        $this->assertEquals($owner->id, $log->user_id);
        $this->assertEquals($salesBill->bill_number, $log->old_values['bill_number']);
    }

    public function test_gst_rate_edit_writes_audit_log_with_old_and_new_values(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);

        $gstTax = GstTax::create(['description' => 'Audit GST Test', 'percentage' => 12, 'status' => true]);

        $this->put(route('master.gst-taxes.update', $gstTax), [
            'description' => 'Audit GST Test',
            'percentage' => 18,
            'status' => true,
        ]);

        $log = AuditLog::where('auditable_type', GstTax::class)->where('auditable_id', $gstTax->id)->first();
        $this->assertNotNull($log, 'Editing a GST rate must write an audit log row.');
        $this->assertEquals(12, $log->old_values['percentage']);
        $this->assertEquals(18, $log->new_values['percentage']);
    }

    public function test_roles_are_seeded_with_expected_permission_boundaries(): void
    {
        $owner = Role::where('name', 'Owner')->first();
        $manager = Role::where('name', 'Manager')->first();
        $cashier = Role::where('name', 'Cashier')->first();

        $this->assertTrue($owner->hasPermissionTo('stock-update-approval.approve'));
        $this->assertFalse($manager->hasPermissionTo('stock-update-approval.approve'), 'Approval authority is Owner-only.');
        $this->assertFalse($manager->hasPermissionTo('gst-taxes.edit'), 'GST master edits are Owner-only.');
        $this->assertTrue($manager->hasPermissionTo('purchase-invoices.create'));
        $this->assertTrue($cashier->hasPermissionTo('sales-bills.create'));
        $this->assertFalse($cashier->hasPermissionTo('purchase-invoices.create'));
    }
}
