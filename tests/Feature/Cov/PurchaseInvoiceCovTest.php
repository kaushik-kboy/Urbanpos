<?php

namespace Tests\Feature\Cov;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceiptNote;
use App\Models\StockLedger;
use App\Models\Supplier;
use Tests\TestCase;

class PurchaseInvoiceCovTest extends TestCase
{
    use PurchaseCovHelper;

    private function create(array $over = [], array $lineOver = []): PurchaseInvoice
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload($over, $lineOver))->assertSessionHasNoErrors();

        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    private function makePo(Supplier $s, string $no, string $status): PurchaseOrder
    {
        return PurchaseOrder::create(['po_number' => $no, 'po_date' => now(), 'supplier_id' => $s->id, 'branch_id' => $this->branch->id, 'status' => $status, 'total' => 1, 'purchase_type' => 'Local', 'c_form' => 'No Forms']);
    }

    public function test_local_invoice_splits_gst_posts_stock_journal_and_grn(): void
    {
        $inv = $this->create(['freight' => 20]);

        $this->assertStringStartsWith('PINV', $inv->invoice_number);
        $this->assertStringStartsWith('GRN', $inv->grn_number);
        $this->assertEquals(180, (float) $inv->total_gst);
        $this->assertEquals(90, (float) $inv->total_cgst);
        $this->assertEquals(90, (float) $inv->total_sgst);
        $this->assertEquals(0, (float) $inv->total_igst);
        $this->assertEquals(1200, (float) $inv->total);
        $this->assertEquals(10, $this->stockOf($this->item));
        $this->assertDatabaseHas('stock_ledger', ['reference_type' => PurchaseInvoice::class, 'reference_id' => $inv->id, 'movement_type' => 'PURCHASE_RECEIPT']);

        $je = JournalEntry::where('reference_type', PurchaseInvoice::class)->where('reference_id', $inv->id)->first();
        $this->assertNotNull($je);
        $this->assertEquals((float) $je->total_debit, (float) $je->total_credit);
    }

    public function test_interstate_invoice_uses_igst_only(): void
    {
        $inv = $this->create(['purchase_type' => 'Interstate']);
        $this->assertEquals(180, (float) $inv->total_igst);
        $this->assertEquals(0, (float) $inv->total_cgst);
        $this->assertEquals(0, (float) $inv->total_sgst);
    }

    public function test_header_discount_reduces_taxable_value_and_free_qty_adds_stock(): void
    {
        $inv = $this->create(['other_disc_amt' => 100], ['free_qty' => 2]);
        $this->assertEquals(162, (float) $inv->total_gst);
        $this->assertEquals(1062, (float) $inv->total);
        $this->assertEquals(12, (float) $inv->total_qty);
        $this->assertEquals(12, $this->stockOf($this->item));
    }

    public function test_item_percent_and_amount_discount_are_synchronised(): void
    {
        $inv = $this->create([], ['disc_percent' => 10]);
        $this->assertEquals(100, (float) $inv->items()->first()->disc_amount);
        $this->assertEquals(162, (float) $inv->total_gst);

        $inv2 = $this->create(['supplier_inv_no' => 'X2'], ['disc_amount' => 200]);
        $this->assertEquals(20, (float) $inv2->items()->first()->disc_percent);
    }

    public function test_supplier_invoice_amount_must_match_total(): void
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['supplier_inv_amount' => 999]))
            ->assertSessionHasErrors('supplier_inv_amount');
        $this->assertSame(0, PurchaseInvoice::count());

        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['supplier_inv_amount' => 1180]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseInvoice::count());
    }

    public function test_duplicate_supplier_invoice_number_rejected_and_checker_reports_it(): void
    {
        $inv = $this->create(['supplier_inv_no' => ' abc-1 ']);
        $this->assertSame('ABC-1', $inv->supplier_inv_no);

        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['supplier_inv_no' => 'ABC-1']))
            ->assertSessionHasErrors('supplier_inv_no');
        $this->assertSame(1, PurchaseInvoice::count());

        $this->getJson(route('purchase.purchase-invoices.check-supplier-inv', ['supplier_id' => $this->supplier->id, 'supplier_inv_no' => 'abc-1']))
            ->assertJson(['is_duplicate' => true, 'existing_invoice_id' => $inv->id]);
        $this->getJson(route('purchase.purchase-invoices.check-supplier-inv', ['supplier_id' => $this->supplier->id, 'supplier_inv_no' => 'abc-1', 'ignore_id' => $inv->id]))
            ->assertJson(['is_duplicate' => false]);
        $this->getJson(route('purchase.purchase-invoices.check-supplier-inv', ['supplier_id' => $this->supplier->id]))
            ->assertJson(['is_duplicate' => false]);
    }

    public function test_same_amount_invoice_without_posting_key_is_a_legitimate_second_invoice(): void
    {
        // Was a 30 s content heuristic that rejected this; idempotency is now posting_key based.
        $this->create(['supplier_inv_amount' => 1180]);
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['supplier_inv_amount' => 1180]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, PurchaseInvoice::count());
    }

    public function test_posting_key_makes_store_idempotent(): void
    {
        $this->create(['posting_key' => 'pk-1']);
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['posting_key' => 'pk-1']))
            ->assertRedirect(route('purchase.purchase-invoices.index'));
        $this->assertSame(1, PurchaseInvoice::count());
        $this->assertEquals(10, $this->stockOf($this->item));
    }

    public function test_taken_prefilled_grn_number_is_replaced_with_fresh_one(): void
    {
        $a = $this->create();
        $b = $this->create(['supplier_inv_no' => 'S2', 'grn_number' => $a->grn_number]);
        $this->assertNotSame($a->grn_number, $b->grn_number);
        $this->assertStringStartsWith('GRN', $b->grn_number);
    }

    public function test_validation_rules(): void
    {
        $route = route('purchase.purchase-invoices.store');
        $this->post($route, $this->invPayload(['c_form' => null]))->assertSessionHasErrors('c_form');
        $this->post($route, $this->invPayload(['purchase_type' => 'Import']))->assertSessionHasErrors('purchase_type');
        $this->post($route, $this->invPayload(['invoice_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('invoice_date');
        $this->post($route, $this->invPayload(['items' => []]))->assertSessionHasErrors('items');
        $this->post($route, $this->invPayload([], ['qty' => 0]))->assertSessionHasErrors('items.0.qty');
        $this->post($route, $this->invPayload([], ['sell_price' => 100]))->assertSessionHasErrors('items.0.sell_price');
        $this->post($route, $this->invPayload([], ['sell_price' => 200]))->assertSessionHasErrors('items.0.sell_price');
        $this->assertSame(0, PurchaseInvoice::count());
        $this->assertSame(0, StockLedger::count());
    }

    public function test_mandatory_expiry_item_requires_exp_date_and_stores_it(): void
    {
        $this->item->update(['batch_expiry_details' => 'Mandatory']);
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload())
            ->assertSessionHasErrors('items.0.exp_date');

        $inv = $this->create([], ['exp_date' => '2027-12-31']);
        $this->assertSame('2027-12-31', $inv->items()->first()->exp_date->format('Y-m-d'));
        $this->assertSame('2027-12-31', StockLedger::where('reference_id', $inv->id)->first()->exp_date->format('Y-m-d'));
    }

    public function test_update_reverses_old_stock_and_reposts_new_quantities_and_journal(): void
    {
        $inv = $this->create();
        $this->assertEquals(10, $this->stockOf($this->item));

        $this->put(route('purchase.purchase-invoices.update', $inv), $this->invPayload([], ['qty' => 4]))
            ->assertRedirect(route('purchase.purchase-invoices.index'));

        $inv->refresh();
        $this->assertEquals(4, $this->stockOf($this->item));
        $this->assertEquals(472, (float) $inv->total);
        $this->assertSame(1, $inv->items()->count());
        $this->assertTrue(StockLedger::where('reference_id', $inv->id)->whereNotNull('reversal_of')->exists());
        $live = JournalEntry::where('reference_type', PurchaseInvoice::class)->where('reference_id', $inv->id)->whereNull('reversal_of')->get();
        $this->assertTrue($live->contains(fn ($j) => (float) $j->total_credit === 472.0));
    }

    public function test_update_can_switch_item_and_moves_stock_between_items(): void
    {
        $inv = $this->create();
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->invPayload([], ['item_id' => $this->item2->id, 'qty' => 6, 'cost_price' => 50, 'sell_price' => 80, 'mrp' => 90]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->stockOf($this->item));
        $this->assertEquals(6, $this->stockOf($this->item2));
    }

    public function test_update_keeps_originally_saved_gst_rate(): void
    {
        $inv = $this->create();
        $inv->items()->update(['gst_percent' => 12]);
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->invPayload())->assertSessionHasNoErrors();
        $this->assertEquals(12, (float) $inv->items()->first()->gst_percent);
        $this->assertEquals(120, (float) $inv->fresh()->total_gst);
    }

    public function test_cancelled_invoice_cannot_be_updated(): void
    {
        $inv = $this->create();
        $inv->update(['status' => 'Cancelled']);
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->invPayload([], ['qty' => 1]))
            ->assertSessionHasErrors('status');
        $this->assertEquals(10, (float) $inv->fresh()->total_qty);
    }

    public function test_destroy_reverses_stock_and_journal_and_writes_audit(): void
    {
        $inv = $this->create();
        $this->delete(route('purchase.purchase-invoices.destroy', $inv))
            ->assertRedirect(route('purchase.purchase-invoices.index'));

        $this->assertEquals(0, $this->stockOf($this->item));
        $this->assertNull(PurchaseInvoice::find($inv->id));
        $this->assertTrue(JournalEntry::where('reference_type', PurchaseInvoice::class)->where('reference_id', $inv->id)->whereNotNull('reversal_of')->exists());
        $this->assertNotNull(AuditLog::where('auditable_type', PurchaseInvoice::class)->where('auditable_id', $inv->id)->where('action', 'cancel')->first());
    }

    public function test_invoice_from_receipt_note_does_not_double_post_stock_and_marks_invoiced(): void
    {
        $this->post(route('purchase.purchase-receipt-notes.store'), [
            'receipt_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'items' => [['item_id' => $this->item->id, 'received_qty' => 5, 'accepted_qty' => 5, 'unit_cost' => 100]],
        ])->assertSessionHasNoErrors();
        $grn = PurchaseReceiptNote::firstOrFail();
        $this->assertEquals(5, $this->stockOf($this->item));

        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload(['purchase_receipt_note_id' => $grn->id], ['qty' => 5, 'cost_price' => 110]))
            ->assertSessionHasNoErrors();
        $inv = PurchaseInvoice::firstOrFail();

        $this->assertEquals(5, $this->stockOf($this->item));
        $this->assertSame('Invoiced', $grn->fresh()->status);
        $this->assertSame($inv->id, (int) $grn->fresh()->purchase_invoice_id);
        $this->assertEquals(110, (float) $this->item->fresh()->cost_price);
    }

    public function test_form_lists_only_selected_suppliers_open_pos(): void
    {
        $other = Supplier::create(['name' => 'Other Sup']);
        $this->makePo($this->supplier, 'PO-OPEN', 'Open');
        $this->makePo($this->supplier, 'PO-CLOSED', 'Closed');
        $this->makePo($other, 'PO-OTHER', 'Open');

        $opts = $this->get(route('purchase.purchase-invoices.create', ['supplier_id' => $this->supplier->id]))
            ->assertOk()->viewData('purchaseOrders');
        $this->assertSame(['PO-OPEN'], $opts->values()->all());

        $this->assertCount(0, $this->get(route('purchase.purchase-invoices.create'))->assertOk()->viewData('purchaseOrders'));

        $this->getJson(route('purchase.purchase-orders.open-by-supplier', ['supplier_id' => $this->supplier->id]))
            ->assertJsonCount(1, 'purchase_orders')->assertJsonPath('purchase_orders.0.po_number', 'PO-OPEN');
        $this->getJson(route('purchase.purchase-orders.open-by-supplier'))->assertJson(['purchase_orders' => []]);
    }

    public function test_edit_form_keeps_its_own_closed_po_selectable(): void
    {
        $po = $this->makePo($this->supplier, 'PO-CL', 'Closed');
        $inv = $this->create(['purchase_order_id' => $po->id]);
        $this->assertSame($po->id, (int) $inv->purchase_order_id);
        $opts = $this->get(route('purchase.purchase-invoices.edit', $inv))->assertOk()->viewData('purchaseOrders');
        $this->assertSame(['PO-CL'], $opts->values()->all());
    }

    public function test_create_prefilled_from_order_and_receipt_note(): void
    {
        $po = $this->makePo($this->supplier, 'PO-1', 'Open');
        $po->items()->create(['item_id' => $this->item->id, 'qty' => 7, 'cost_price' => 100, 'net_amount' => 700]);
        $po->items()->create(['item_id' => $this->item2->id, 'qty' => 0, 'cost_price' => 50, 'net_amount' => 0]);
        $v = $this->get(route('purchase.purchase-invoices.create', ['from_order' => $po->id]))->assertOk();
        $this->assertCount(1, $v->viewData('convertedItems'));
        $this->assertEquals(7, $v->viewData('convertedItems')[0]['qty']);
        $this->assertSame($this->supplier->id, $v->viewData('sourceOrder')->supplier_id);

        $this->post(route('purchase.purchase-receipt-notes.store'), [
            'receipt_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'items' => [['item_id' => $this->item->id, 'received_qty' => 3, 'accepted_qty' => 3, 'unit_cost' => 100]],
        ]);
        $grn = PurchaseReceiptNote::firstOrFail();
        $v = $this->get(route('purchase.purchase-invoices.create', ['from_receipt_note' => $grn->id]))->assertOk();
        $this->assertEquals(3, $v->viewData('convertedItems')[0]['qty']);
    }

    public function test_index_filters_show_print_and_supplier_invoices(): void
    {
        $inv = $this->create(['supplier_inv_no' => 'FLT-1']);
        $other = Supplier::create(['name' => 'Zed Sup']);
        $inv2 = $this->create(['supplier_id' => $other->id, 'supplier_inv_no' => 'FLT-2', 'purchase_type' => 'Interstate']);

        $ids = fn ($q) => $this->get(route('purchase.purchase-invoices.index', $q))->assertOk()->viewData('purchaseInvoices')->pluck('id')->all();
        $this->assertSame([$inv->id], $ids(['search' => 'FLT-1']));
        $this->assertSame([$inv2->id], $ids(['search' => 'Zed']));
        $this->assertSame([$inv2->id], $ids(['purchase_type' => 'Interstate']));
        $this->assertSame([$inv->id], $ids(['supplier_id' => $this->supplier->id]));
        $this->assertSame([], $ids(['date_from' => now()->addDay()->toDateString()]));
        $this->assertSame([], $ids(['date_to' => now()->subDays(2)->toDateString()]));
        $this->assertCount(2, $ids(['branch_id' => $this->branch->id]));

        $this->get(route('purchase.purchase-invoices.show', $inv))->assertOk()->assertSee($inv->invoice_number);
        $this->get(route('purchase.purchase-invoices.print', $inv))->assertOk();
        $this->get(route('purchase.purchase-invoices.edit', $inv))->assertOk();

        $this->getJson(route('purchase.purchase-invoices.supplier-invoices', $this->supplier))
            ->assertJsonPath('count', 1)->assertJsonPath('invoices.0.supplier_inv_no', 'FLT-1');
        $this->getJson(route('purchase.purchase-invoices.supplier-invoices', [$this->supplier, 'search' => 'nomatch']))->assertJsonPath('count', 0);
        $this->getJson(route('purchase.purchase-invoices.supplier-invoices', [$this->supplier, 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]))->assertJsonPath('count', 1);
    }

    public function test_item_list_requires_filter_and_ranks_and_filters_by_expiry(): void
    {
        $this->getJson(route('purchase.purchase-invoices.item-list'))->assertJson(['items' => []])->assertJsonStructure(['items', 'hint']);

        $r = $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Cov Item']))->assertOk();
        $this->assertSame(['Cov Item A', 'Cov Item B'], collect($r->json('items'))->pluck('name')->all());

        $r = $this->getJson(route('purchase.purchase-invoices.item-list', ['code' => 'COVB']))->json('items');
        $this->assertCount(1, $r);
        $this->assertSame('COVB', $r[0]['code']);
        $this->assertEquals(18, $r[0]['gst_percent']);

        $r = $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => '8900000000011']))->json('items');
        $this->assertSame($this->item->id, $r[0]['id']);

        $this->item->update(['batch_expiry_details' => 'Mandatory']);
        $this->create([], ['exp_date' => '2027-06-30']);
        $r = $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Cov Item A', 'branch_id' => $this->branch->id]))->json('items');
        $this->assertEquals(10, $r[0]['qty']);
        $this->assertSame('2027-06-30', $r[0]['exp_date']);
        $this->assertSame('Mandatory', $r[0]['batch_expiry_details']);

        $this->assertCount(1, $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Cov', 'expiry' => '2027-06']))->json('items'));
        $this->assertCount(0, $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Cov', 'expiry' => '2031']))->json('items'));

        $this->item2->update(['status' => false]);
        $names = collect($this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Cov Item']))->json('items'))->pluck('name')->all();
        $this->assertSame(['Cov Item A'], $names);
    }

    public function test_lookup_item_branches(): void
    {
        $this->assertEmpty($this->getJson(route('purchase.purchase-invoices.lookup-item', ['query' => 'nothing-here']))->json());
        $this->assertEmpty($this->getJson(route('purchase.purchase-invoices.lookup-item'))->json());

        $j = $this->getJson(route('purchase.purchase-invoices.lookup-item', ['item_id' => $this->item->id]))->assertOk()->json();
        $this->assertSame('Cov Item A', $j['name']);
        $this->assertEquals(18, $j['gst_percent']);
        $this->assertEquals(0, $j['stock']);
        $this->assertNull($j['exp_date']);

        $this->assertSame($this->item2->id, $this->getJson(route('purchase.purchase-invoices.lookup-item', ['query' => 'COVB']))->json('id'));
        $this->assertSame($this->item->id, $this->getJson(route('purchase.purchase-invoices.lookup-item', ['query' => '8900000000011']))->json('id'));
        $this->assertSame($this->item->id, $this->getJson(route('purchase.purchase-invoices.lookup-item', ['query' => (string) $this->item->id]))->json('id'));

        $this->item->update(['batch_expiry_details' => 'Days']);
        $this->create([], ['exp_date' => '2028-01-15']);
        $j = $this->getJson(route('purchase.purchase-invoices.lookup-item', ['item_id' => $this->item->id, 'branch_id' => $this->branch->id]))->json();
        $this->assertEquals(10, $j['stock']);
        $this->assertSame('2028-01-15', $j['exp_date']);

        $this->item->update(['status' => false]);
        $this->assertEmpty($this->getJson(route('purchase.purchase-invoices.lookup-item', ['item_id' => $this->item->id]))->json());
    }

    public function test_lookup_item_falls_back_to_other_branch_stock_and_item_details(): void
    {
        $other = Branch::create(['name' => 'Other Br']);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $other->id, 'quantity' => 7]);
        $j = $this->getJson(route('purchase.purchase-invoices.lookup-item', ['item_id' => $this->item->id, 'branch_id' => $this->branch->id]))->json();
        $this->assertEquals(7, $j['stock']);

        $this->getJson(route('purchase.purchase-invoices.item-details', $this->item))
            ->assertOk()->assertJson(['name' => 'Cov Item A', 'cost_price' => 100, 'gst_percent' => 18, 'batch_expiry_details' => 'Not Required']);
    }
}
