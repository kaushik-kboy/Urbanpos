<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PO Cancel: like every other document in this app, destroy() IS the cancel action —
 * the row is never hard-deleted. Cancelling requires a reason, is blocked once the PO
 * already has a Purchase Invoice against it or is already cancelled, and writes an
 * audit log row. "Cancelled" can never be set silently through the plain edit form.
 */
class PurchaseOrderCancelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function actingAsManager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        return $manager;
    }

    private function createPo(Supplier $supplier, Branch $branch, Item $item): PurchaseOrder
    {
        $this->post(route('purchase.purchase-orders.store'), [
            'po_date' => '2026-09-16', 'supplier_id' => $supplier->id, 'branch_id' => $branch->id,
            'purchase_type' => 'Local', 'c_form' => 'No Forms', 'status' => 'Open',
            'items' => [['item_id' => $item->id, 'qty' => 5, 'cost_price' => 100]],
        ]);

        return PurchaseOrder::latest('id')->first();
    }

    public function test_manager_can_cancel_an_open_po_with_a_reason_and_it_writes_an_audit_log(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'PO Cancel Branch']);
        $supplier = Supplier::create(['name' => 'PO Cancel Supplier']);
        $item = Item::create(['name' => 'PO Cancel Item']);

        $po = $this->createPo($supplier, $branch, $item);
        $this->assertEquals('Open', $po->status);

        $response = $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'Supplier out of stock']);
        $response->assertRedirect(route('purchase.purchase-orders.index'));

        $po->refresh();
        $this->assertEquals('Cancelled', $po->status);
        $this->assertEquals('Supplier out of stock', $po->cancellation_reason);
        $this->assertEquals($manager->id, $po->cancelled_by_id);
        $this->assertNotNull($po->cancelled_at);
        $this->assertNotNull(PurchaseOrder::find($po->id), 'The PO row must still exist — cancel is not a delete.');

        $log = AuditLog::where('auditable_type', PurchaseOrder::class)->where('auditable_id', $po->id)->first();
        $this->assertNotNull($log, 'Cancelling a PO must write an audit log row.');
        $this->assertEquals('cancel', $log->action);
        $this->assertEquals('Open', $log->old_values['status']);
        $this->assertEquals('Cancelled', $log->new_values['status']);
    }

    public function test_cancelling_a_po_that_already_has_a_purchase_invoice_is_blocked(): void
    {
        $this->actingAsManager();
        $branch = Branch::create(['name' => 'PO Invoiced Branch']);
        $supplier = Supplier::create(['name' => 'PO Invoiced Supplier']);
        $item = Item::create(['name' => 'PO Invoiced Item']);

        $po = $this->createPo($supplier, $branch, $item);

        $this->post(route('purchase.purchase-invoices.store'), [
            'invoice_date' => '2026-09-16', 'supplier_id' => $supplier->id, 'branch_id' => $branch->id,
            'purchase_order_id' => $po->id, 'purchase_type' => 'Local', 'c_form' => 'No Forms',
            'items' => [['item_id' => $item->id, 'qty' => 5, 'cost_price' => 100]],
        ]);

        $response = $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'Trying anyway']);
        $response->assertSessionHasErrors('purchase_order');
        $this->assertEquals('Open', $po->fresh()->status);
    }

    public function test_cancelling_an_already_cancelled_po_is_blocked(): void
    {
        $this->actingAsManager();
        $branch = Branch::create(['name' => 'PO Recancel Branch']);
        $supplier = Supplier::create(['name' => 'PO Recancel Supplier']);
        $item = Item::create(['name' => 'PO Recancel Item']);

        $po = $this->createPo($supplier, $branch, $item);
        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'First cancel']);

        $response = $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'Second attempt']);
        $response->assertSessionHasErrors('purchase_order');
    }

    public function test_status_cancelled_cannot_be_set_via_the_plain_update_form(): void
    {
        $this->actingAsManager();
        $branch = Branch::create(['name' => 'PO Silent Branch']);
        $supplier = Supplier::create(['name' => 'PO Silent Supplier']);
        $item = Item::create(['name' => 'PO Silent Item']);

        $po = $this->createPo($supplier, $branch, $item);

        $response = $this->put(route('purchase.purchase-orders.update', $po), [
            'po_date' => '2026-09-16', 'supplier_id' => $supplier->id, 'branch_id' => $branch->id,
            'purchase_type' => 'Local', 'c_form' => 'No Forms', 'status' => 'Cancelled',
            'items' => [['item_id' => $item->id, 'qty' => 5, 'cost_price' => 100]],
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('Open', $po->fresh()->status);
    }
}
