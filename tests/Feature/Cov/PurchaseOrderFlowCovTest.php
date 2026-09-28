<?php

namespace Tests\Feature\Cov;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PurchaseIndent;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptNote;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class PurchaseOrderFlowCovTest extends TestCase
{
    use PurchaseCovHelper;

    private function poPayload(array $over = [], array $lineOver = []): array
    {
        return array_merge([
            'po_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'purchase_type' => 'Local', 'c_form' => 'No Forms', 'status' => 'Open',
            'items' => [array_merge(['item_id' => $this->item->id, 'qty' => 10, 'cost_price' => 100, 'gst_percent' => 18], $lineOver)],
        ], $over);
    }

    private function po(array $over = [], array $lineOver = []): PurchaseOrder
    {
        $this->post(route('purchase.purchase-orders.store'), $this->poPayload($over, $lineOver))->assertSessionHasNoErrors();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    private function grnPayload(?PurchaseOrder $po, float $recv, float $acc, array $over = []): array
    {
        $poItem = $po?->items()->first();

        return array_merge([
            'receipt_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'purchase_order_id' => $po?->id,
            'items' => [[
                'item_id' => $this->item->id, 'purchase_order_item_id' => $poItem?->id, 'ordered_qty' => 10,
                'received_qty' => $recv, 'accepted_qty' => $acc, 'rejected_qty' => $recv - $acc, 'unit_cost' => 100,
            ]],
        ], $over);
    }

    // ---------------------------------------------------------------- PO

    public function test_po_totals_include_freight_discounts_and_round_off(): void
    {
        $po = $this->po(['freight' => 50, 'other_disc_amt' => 30, 'scheme_item_disc_amt' => 20, 'round_off' => 0.5], ['free_qty' => 2, 'disc_percent' => 10]);
        // base 1000, 10% disc = 100 -> 900 + 18% = 1062 ; +50 +0.5 -30 -20
        $this->assertEquals(1062.5, (float) $po->total);
        $this->assertEquals(162, (float) $po->total_gst);
        $this->assertEquals(12, (float) $po->total_qty);
        $this->assertEquals(100, (float) $po->disc_amount);
        $this->assertSame(0, StockLedger::count()); // a PO never moves stock
    }

    public function test_po_validation_blocks_cancelled_status_and_bad_input(): void
    {
        $r = route('purchase.purchase-orders.store');
        $this->post($r, $this->poPayload(['status' => 'Cancelled']))->assertSessionHasErrors('status');
        $this->post($r, $this->poPayload(['c_form' => 'x']))->assertSessionHasErrors('c_form');
        $this->post($r, $this->poPayload([], ['qty' => 0]))->assertSessionHasErrors('items.0.qty');
        $this->post($r, $this->poPayload(['items' => []]))->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_po_update_replaces_lines_and_cancelled_po_cannot_be_edited(): void
    {
        $po = $this->po();
        $this->put(route('purchase.purchase-orders.update', $po), $this->poPayload([], ['qty' => 5, 'cost_price' => 200]))
            ->assertRedirect(route('purchase.purchase-orders.index'));
        $po->refresh();
        $this->assertSame(1, $po->items()->count());
        $this->assertEquals(1180, (float) $po->total);

        $this->get(route('purchase.purchase-orders.edit', $po))->assertOk();
        $this->get(route('purchase.purchase-orders.show', $po))->assertOk()->assertSee($po->po_number);
        $this->get(route('purchase.purchase-orders.print', $po))->assertOk();

        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'x']);
        $this->put(route('purchase.purchase-orders.update', $po), $this->poPayload([], ['qty' => 1]))->assertSessionHasErrors('purchase_order');
        $this->assertEquals(1180, (float) $po->fresh()->total);
    }

    public function test_po_cancel_requires_reason_and_rejects_double_cancel(): void
    {
        $po = $this->po();
        $this->delete(route('purchase.purchase-orders.destroy', $po))->assertSessionHasErrors('reason');
        $this->assertSame('Open', $po->fresh()->status);
        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'r'])->assertSessionHasNoErrors();
        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'r'])->assertSessionHasErrors('purchase_order');
    }

    public function test_po_index_filters(): void
    {
        $a = $this->po();
        $other = Supplier::create(['name' => 'Zeta Traders']);
        $b = $this->po(['supplier_id' => $other->id, 'status' => 'Closed']);

        $ids = fn ($q) => $this->get(route('purchase.purchase-orders.index', $q))->assertOk()->viewData('purchaseOrders')->pluck('id')->sort()->values()->all();
        $this->assertSame([$b->id], $ids(['search' => 'Zeta']));
        $this->assertSame([$a->id], $ids(['search' => $a->po_number]));
        $this->assertSame([$b->id], $ids(['status' => 'Closed']));
        $this->assertSame([$a->id], $ids(['supplier_id' => $this->supplier->id]));
        $this->assertSame([], $ids(['date_from' => now()->addDay()->toDateString()]));
        $this->assertSame([], $ids(['date_to' => now()->subDay()->toDateString()]));
        $this->assertCount(2, $ids(['branch_id' => $this->branch->id]));
        $this->get(route('purchase.purchase-orders.create'))->assertOk();
    }

    public function test_po_from_indent_prefill_and_conversion_rules(): void
    {
        $indent = $this->makeIndent('Approved');
        $v = $this->get(route('purchase.purchase-orders.create', ['from_indent' => $indent->id]))->assertOk();
        $this->assertSame(1, $v->viewData('initialItems')->count());
        $this->assertEquals(6, $v->viewData('initialItems')[0]['qty']); // approved qty wins

        $pending = $this->makeIndent('Pending');
        $this->get(route('purchase.purchase-orders.create', ['from_indent' => $pending->id]))
            ->assertRedirect(route('purchase.purchase-indents.show', $pending));

        $po = $this->po(['purchase_indent_id' => $indent->id]);
        $this->assertSame('Converted', $indent->fresh()->status);
        $this->assertSame($po->id, (int) $indent->fresh()->purchase_order_id);

        // cancelling the PO puts the indent back to Approved
        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'changed mind']);
        $this->assertSame('Approved', $indent->fresh()->status);
        $this->assertNull($indent->fresh()->purchase_order_id);
    }

    public function test_po_with_invoice_cannot_be_cancelled(): void
    {
        $po = $this->po();
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['purchase_order_id' => $po->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseInvoice::where('purchase_order_id', $po->id)->count());
        $this->delete(route('purchase.purchase-orders.destroy', $po), ['reason' => 'x'])->assertSessionHasErrors('purchase_order');
        $this->assertSame('Open', $po->fresh()->status);
    }

    // ---------------------------------------------------------------- GRN

    public function test_partial_then_full_grn_keeps_po_open_then_closes_it_and_cancel_reopens(): void
    {
        $po = $this->po();

        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload($po, 4, 4))->assertSessionHasNoErrors();
        $grn1 = PurchaseReceiptNote::latest('id')->first();
        $this->assertSame('Open', $po->fresh()->status);
        $this->assertEquals(4, (float) $po->items()->first()->received_qty);
        $this->assertEquals(4, $this->stockOf($this->item));

        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload($po, 6, 6))->assertSessionHasNoErrors();
        $grn2 = PurchaseReceiptNote::latest('id')->first();
        $this->assertSame('Closed', $po->fresh()->status);
        $this->assertEquals(10, $this->stockOf($this->item));
        $this->assertNotSame($grn1->receipt_number, $grn2->receipt_number);
        $this->assertStringStartsWith('GRN', $grn2->receipt_number);

        // a closed PO is no longer offered to the invoice / GRN forms
        $opts = $this->get(route('purchase.purchase-invoices.create', ['supplier_id' => $this->supplier->id]))->viewData('purchaseOrders');
        $this->assertCount(0, $opts);
        $this->getJson(route('purchase.purchase-orders.open-by-supplier', ['supplier_id' => $this->supplier->id]))->assertJsonCount(0, 'purchase_orders');

        // cancelling the second GRN reverses stock and reopens the PO
        $this->delete(route('purchase.purchase-receipt-notes.destroy', $grn2), ['reason' => 'wrong goods'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelled', $grn2->fresh()->status);
        $this->assertSame('wrong goods', $grn2->fresh()->cancellation_reason);
        $this->assertSame('Open', $po->fresh()->status);
        $this->assertEquals(4, (float) $po->items()->first()->received_qty);
        $this->assertEquals(4, $this->stockOf($this->item));
        $this->assertNotNull(AuditLog::where('auditable_type', PurchaseReceiptNote::class)->where('auditable_id', $grn2->id)->where('action', 'cancel')->first());

        $this->assertCount(1, $this->getJson(route('purchase.purchase-orders.open-by-supplier', ['supplier_id' => $this->supplier->id]))->json('purchase_orders'));
    }

    public function test_grn_rejected_qty_is_not_stocked_and_totals_are_recorded(): void
    {
        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload(null, 10, 7, ['purchase_order_id' => null]))->assertSessionHasNoErrors();
        $grn = PurchaseReceiptNote::firstOrFail();
        $this->assertEquals(7, $this->stockOf($this->item));
        $this->assertEquals(10, (float) $grn->total_received_qty);
        $this->assertEquals(7, (float) $grn->total_accepted_qty);
        $this->assertEquals(3, (float) $grn->total_rejected_qty);
        $this->assertEquals(700, (float) $grn->total_amount);
        $this->assertSame('Received', $grn->status);

        $payload = $this->grnPayload(null, 2, 0, ['purchase_order_id' => null]);
        $this->post(route('purchase.purchase-receipt-notes.store'), $payload);
        $this->assertEquals(7, $this->stockOf($this->item)); // accepted 0 -> no stock
    }

    public function test_grn_posting_key_idempotent_and_validation(): void
    {
        $p = $this->grnPayload(null, 3, 3, ['purchase_order_id' => null, 'posting_key' => 'g1']);
        $this->post(route('purchase.purchase-receipt-notes.store'), $p)->assertSessionHasNoErrors();
        $this->post(route('purchase.purchase-receipt-notes.store'), $p)->assertRedirect(route('purchase.purchase-receipt-notes.index'));
        $this->assertSame(1, PurchaseReceiptNote::count());
        $this->assertEquals(3, $this->stockOf($this->item));

        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload(null, 3, 3, ['supplier_id' => null]))->assertSessionHasErrors('supplier_id');
        $bad = $this->grnPayload(null, 3, 3);
        $bad['items'][0]['accepted_qty'] = -1;
        $this->post(route('purchase.purchase-receipt-notes.store'), $bad)->assertSessionHasErrors('items.0.accepted_qty');
        $this->assertSame(1, PurchaseReceiptNote::count());
    }

    public function test_grn_cancel_rules(): void
    {
        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload(null, 3, 3, ['purchase_order_id' => null]));
        $grn = PurchaseReceiptNote::firstOrFail();

        $this->delete(route('purchase.purchase-receipt-notes.destroy', $grn))->assertSessionHasErrors('reason');
        $this->assertSame('Received', $grn->fresh()->status);

        $this->delete(route('purchase.purchase-receipt-notes.destroy', $grn), ['reason' => 'dup'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->stockOf($this->item));
        $this->delete(route('purchase.purchase-receipt-notes.destroy', $grn), ['reason' => 'dup'])->assertSessionHasErrors('receipt_note');
        $this->assertEquals(0, $this->stockOf($this->item));
    }

    public function test_grn_create_from_po_prefills_pending_qty_and_rejects_cancelled_po(): void
    {
        $po = $this->po();
        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload($po, 4, 4));

        $v = $this->get(route('purchase.purchase-receipt-notes.create', ['from_po' => $po->id]))->assertOk();
        $this->assertEquals(6, $v->viewData('convertedItems')[0]['pending_qty']);
        $this->assertSame(['PO-'], [substr($v->viewData('purchaseOrders')->first(), 0, 3)]);

        $this->get(route('purchase.purchase-receipt-notes.create', ['supplier_id' => $this->supplier->id]))->assertOk();

        $po2 = $this->po();
        $this->delete(route('purchase.purchase-orders.destroy', $po2), ['reason' => 'x']);
        $this->get(route('purchase.purchase-receipt-notes.create', ['from_po' => $po2->id]))
            ->assertRedirect(route('purchase.purchase-orders.index'))->assertSessionHasErrors('purchase_order');
    }

    public function test_grn_index_filters_show_and_print(): void
    {
        $po = $this->po();
        $this->post(route('purchase.purchase-receipt-notes.store'), $this->grnPayload($po, 2, 2, ['supplier_challan_no' => 'CH-9']));
        $grn = PurchaseReceiptNote::firstOrFail();
        $other = Supplier::create(['name' => 'Unrelated']);

        $ids = fn ($q) => $this->get(route('purchase.purchase-receipt-notes.index', $q))->assertOk()->viewData('receiptNotes')->pluck('id')->all();
        $this->assertSame([$grn->id], $ids(['search' => 'CH-9']));
        $this->assertSame([$grn->id], $ids(['search' => $po->po_number]));
        $this->assertSame([$grn->id], $ids(['search' => 'Cov Supplier']));
        $this->assertSame([], $ids(['search' => 'zzz']));
        $this->assertSame([], $ids(['supplier_id' => $other->id]));
        $this->assertSame([$grn->id], $ids(['status' => 'Received', 'branch_id' => $this->branch->id]));
        $this->assertSame([], $ids(['status' => 'Cancelled']));
        $this->assertSame([], $ids(['date_from' => now()->addDay()->toDateString()]));
        $this->assertSame([], $ids(['date_to' => now()->subDay()->toDateString()]));

        $this->get(route('purchase.purchase-receipt-notes.show', $grn))->assertOk()->assertSee($grn->receipt_number);
        $this->get(route('purchase.purchase-receipt-notes.print', $grn))->assertOk();
    }

    // ---------------------------------------------------------------- Indent

    private function makeIndent(string $status): PurchaseIndent
    {
        $indent = PurchaseIndent::create([
            'indent_number' => 'IND-T'.uniqid(), 'indent_date' => now()->toDateString(), 'branch_id' => $this->branch->id,
            'department' => 'General', 'priority' => 'Low', 'status' => $status, 'total_requested_qty' => 8,
            'total_approved_qty' => $status === 'Approved' ? 6 : 0, 'total_estimated_amount' => 800,
            'posting_key' => 'k'.uniqid(), 'requested_by_id' => $this->owner->id,
        ]);
        $indent->items()->create([
            'item_id' => $this->item->id, 'current_stock' => 0, 'requested_qty' => 8,
            'approved_qty' => $status === 'Approved' ? 6 : null, 'estimated_cost' => 100,
        ]);

        return $indent;
    }

    private function indentPayload(array $over = [], array $lineOver = []): array
    {
        return array_merge([
            'indent_date' => now()->toDateString(), 'branch_id' => $this->branch->id, 'department' => 'Warehouse', 'priority' => 'High',
            'items' => [array_merge(['item_id' => $this->item->id, 'requested_qty' => 5], $lineOver)],
        ], $over);
    }

    public function test_indent_store_uses_item_cost_fallback_snapshots_stock_and_validates(): void
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload())->assertSessionHasNoErrors(); // stock 10

        $this->post(route('purchase.purchase-indents.store'), $this->indentPayload())->assertSessionHasNoErrors();
        $ind = PurchaseIndent::latest('id')->firstOrFail();
        $this->assertSame('Pending', $ind->status);
        $this->assertStringStartsWith('IND', $ind->indent_number);
        $this->assertEquals(500, (float) $ind->total_estimated_amount); // 5 x item cost 100
        $line = $ind->items()->first();
        $this->assertEquals(10, (float) $line->current_stock);
        $this->assertEquals(100, (float) $line->estimated_cost);

        $this->post(route('purchase.purchase-indents.store'), $this->indentPayload([], ['estimated_cost' => 120]));
        $this->assertEquals(600, (float) PurchaseIndent::latest('id')->first()->total_estimated_amount);

        $r = route('purchase.purchase-indents.store');
        $this->post($r, $this->indentPayload(['priority' => 'Nope']))->assertSessionHasErrors('priority');
        $this->post($r, $this->indentPayload(['required_by_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('required_by_date');
        $this->post($r, $this->indentPayload([], ['requested_qty' => 0]))->assertSessionHasErrors('items.0.requested_qty');
        $this->post($r, $this->indentPayload(['department' => '']))->assertSessionHasErrors('department');
        $this->assertSame(2, PurchaseIndent::count());
    }

    public function test_approve_sets_quantities_totals_reviewer_and_appends_note(): void
    {
        $ind = $this->makeIndent('Pending');
        $item = $ind->items()->first();

        $this->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $item->id, 'approved_qty' => 3]], 'remarks' => 'ok'])
            ->assertRedirect(route('purchase.purchase-indents.show', $ind));
        $ind->refresh();
        $this->assertSame('Approved', $ind->status);
        $this->assertEquals(3, (float) $ind->total_approved_qty);
        $this->assertEquals(300, (float) $ind->total_estimated_amount);
        $this->assertSame($this->owner->id, (int) $ind->reviewed_by_id);
        $this->assertNotNull($ind->reviewed_at);
        $this->assertSame('ok', $ind->remarks);
        $this->assertEquals(3, (float) $item->fresh()->approved_qty);
        $this->assertNotNull(AuditLog::where('auditable_type', PurchaseIndent::class)->where('auditable_id', $ind->id)->where('action', 'approve')->first());
    }

    public function test_approve_appends_to_existing_remarks_and_rejects_foreign_line_and_bad_state(): void
    {
        $ind = $this->makeIndent('Pending');
        $ind->update(['remarks' => 'Need urgently']);
        $foreign = $this->makeIndent('Pending')->items()->first();
        $mine = $ind->items()->first();

        // a line belonging to another indent must 404 and roll everything back
        $this->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $mine->id, 'approved_qty' => 2], ['id' => $foreign->id, 'approved_qty' => 2]]])
            ->assertNotFound();
        $this->assertSame('Pending', $ind->fresh()->status);
        $this->assertNull($mine->fresh()->approved_qty);

        $this->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $mine->id, 'approved_qty' => 2]], 'remarks' => 'fine'])->assertSessionHasNoErrors();
        $this->assertSame('Need urgently | Approval Note: fine', $ind->fresh()->remarks);

        $this->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $mine->id, 'approved_qty' => 9]]])->assertSessionHasErrors('indent');
        $this->assertEquals(2, (float) $mine->fresh()->approved_qty);

        $p2 = $this->makeIndent('Pending');
        $this->post(route('purchase.purchase-indents.approve', $p2), ['items' => [['id' => $p2->items()->first()->id, 'approved_qty' => -1]]])->assertSessionHasErrors('items.0.approved_qty');
        $this->post(route('purchase.purchase-indents.approve', $p2), [])->assertSessionHasErrors('items');
        $this->assertSame('Pending', $p2->fresh()->status);
    }

    public function test_reject_requires_reason_and_only_pending_can_be_rejected(): void
    {
        $ind = $this->makeIndent('Pending');
        $this->post(route('purchase.purchase-indents.reject', $ind), [])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('Pending', $ind->fresh()->status);

        $this->post(route('purchase.purchase-indents.reject', $ind), ['rejection_reason' => 'Over budget'])->assertRedirect(route('purchase.purchase-indents.show', $ind));
        $ind->refresh();
        $this->assertSame('Rejected', $ind->status);
        $this->assertSame('Over budget', $ind->rejection_reason);
        $this->assertSame($this->owner->id, (int) $ind->reviewed_by_id);
        $this->assertNotNull(AuditLog::where('auditable_type', PurchaseIndent::class)->where('auditable_id', $ind->id)->where('action', 'reject')->first());

        $this->post(route('purchase.purchase-indents.reject', $ind), ['rejection_reason' => 'again'])->assertSessionHasErrors('indent');
        $this->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $ind->items()->first()->id, 'approved_qty' => 1]]])->assertSessionHasErrors('indent');
        $this->assertSame('Rejected', $ind->fresh()->status);

        $approved = $this->makeIndent('Approved');
        $this->post(route('purchase.purchase-indents.reject', $approved), ['rejection_reason' => 'late'])->assertSessionHasErrors('indent');
        $this->assertSame('Approved', $approved->fresh()->status);
    }

    public function test_indent_cancel_rules_and_index_filters(): void
    {
        $converted = $this->makeIndent('Converted');
        $this->delete(route('purchase.purchase-indents.destroy', $converted), ['cancellation_reason' => 'x'])->assertSessionHasErrors('indent');
        $this->assertSame('Converted', $converted->fresh()->status);

        $pending = $this->makeIndent('Pending');
        $this->delete(route('purchase.purchase-indents.destroy', $pending))->assertSessionHasErrors('cancellation_reason');
        $this->delete(route('purchase.purchase-indents.destroy', $pending), ['cancellation_reason' => 'dup'])->assertRedirect(route('purchase.purchase-indents.index'));
        $this->assertSame('Cancelled', $pending->fresh()->status);
        $this->assertSame($this->owner->id, (int) $pending->fresh()->cancelled_by_id);
        $this->delete(route('purchase.purchase-indents.destroy', $pending), ['cancellation_reason' => 'dup'])->assertSessionHasErrors('indent');

        $ids = fn ($q) => $this->get(route('purchase.purchase-indents.index', $q))->assertOk()->viewData('indents')->pluck('id')->all();
        $this->assertSame([$pending->id], $ids(['status' => 'Cancelled']));
        $this->assertSame([$converted->id], $ids(['status' => 'Converted', 'priority' => 'Low', 'branch_id' => $this->branch->id]));
        $this->assertSame([], $ids(['priority' => 'Urgent']));
        $this->assertSame([$pending->id], $ids(['search' => $pending->indent_number]));
        $this->assertCount(2, $ids(['search' => 'General']));
        $this->assertSame([], $ids(['date_from' => now()->addDay()->toDateString()]));
        $this->assertSame([], $ids(['date_to' => now()->subDay()->toDateString()]));
        $this->get(route('purchase.purchase-indents.create'))->assertOk();
    }

    public function test_indent_item_stock_endpoint_validates(): void
    {
        $this->getJson(route('purchase.purchase-indents.item-stock', ['item_id' => 999999, 'branch_id' => $this->branch->id]))->assertStatus(422);
        $this->getJson(route('purchase.purchase-indents.item-stock', ['item_id' => $this->item->id]))->assertStatus(422);
    }

    public function test_indent_permissions_cashier_cannot_approve(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $cashier->assignRole('Cashier');
        $ind = $this->makeIndent('Pending');
        $this->actingAs($cashier)
            ->post(route('purchase.purchase-indents.approve', $ind), ['items' => [['id' => $ind->items()->first()->id, 'approved_qty' => 1]]])
            ->assertForbidden();
        $this->assertSame('Pending', $ind->fresh()->status);
    }

    public function test_aux_module_pages_render(): void
    {
        $this->get(route('purchase.aux', 'receipt-notes'))->assertOk();
        $this->get(route('purchase.aux', 'something-else'))->assertOk();
    }
}
