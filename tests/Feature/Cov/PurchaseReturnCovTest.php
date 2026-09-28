<?php

namespace Tests\Feature\Cov;

use App\Models\AuditLog;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\StockLedger;
use App\Models\Supplier;
use Tests\TestCase;

class PurchaseReturnCovTest extends TestCase
{
    use PurchaseCovHelper;

    private function invoice(array $over = [], array $lineOver = []): PurchaseInvoice
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->invPayload($over, $lineOver))->assertSessionHasNoErrors();

        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    private function retPayload(?PurchaseInvoice $inv, array $lineOver = [], array $over = []): array
    {
        return array_merge([
            'return_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'purchase_invoice_id' => $inv?->id,
            'items' => [array_merge(['item_id' => $this->item->id, 'qty' => 3, 'cost_price' => 100], $lineOver)],
        ], $over);
    }

    private function makeReturn(?PurchaseInvoice $inv, array $lineOver = [], array $over = []): PurchaseReturn
    {
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, $lineOver, $over))->assertSessionHasNoErrors();

        return PurchaseReturn::latest('id')->firstOrFail();
    }

    public function test_invoice_return_reduces_stock_at_original_cost_and_tracks_remaining(): void
    {
        $inv = $this->invoice();
        $ret = $this->makeReturn($inv, ['cost_price' => 130]);

        $this->assertEquals(7, $this->stockOf($this->item));
        $this->assertStringStartsWith('PRN', $ret->return_number);
        $line = $ret->items()->first();
        $this->assertEquals(100, (float) $line->cost_at_return); // uses ORIGINAL invoice cost, not typed cost
        $this->assertEquals(3 * 130 * 1.18, (float) $ret->total);

        $j = $this->getJson(route('purchase.purchase-returns.invoice-items', $inv))->assertOk()->json();
        $this->assertSame($this->supplier->id, $j['supplier_id']);
        $this->assertSame('Local', $j['purchase_type']);
        $this->assertEquals(10, $j['items'][0]['original_qty']);
        $this->assertEquals(3, $j['items'][0]['already_returned']);
        $this->assertEquals(7, $j['items'][0]['remaining_qty']);
        $this->assertEquals(7, $j['items'][0]['qty']);

        // editing this return: it must not count against itself
        $j = $this->getJson(route('purchase.purchase-returns.invoice-items', [$inv, 'ignore_return_id' => $ret->id]))->json();
        $this->assertEquals(10, $j['items'][0]['remaining_qty']);
    }

    public function test_cannot_return_more_than_remaining_and_message_differs_after_partial_return(): void
    {
        $inv = $this->invoice();
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, ['qty' => 11]))
            ->assertSessionHasErrors(['items' => 'Return quantity cannot be greater than the available purchase quantity.']);

        $this->makeReturn($inv, ['qty' => 8]);
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, ['qty' => 3]))
            ->assertSessionHasErrors(['items' => 'Return quantity cannot exceed the remaining returnable quantity of 2.']);

        $this->assertSame(1, PurchaseReturn::count());
        $this->assertEquals(2, $this->stockOf($this->item));
        $this->makeReturn($inv, ['qty' => 2]); // exactly the remainder is fine
        $this->assertEquals(0, $this->stockOf($this->item));
    }

    public function test_split_lines_of_same_item_are_summed_against_remaining(): void
    {
        $inv = $this->invoice();
        $payload = $this->retPayload($inv);
        $payload['items'] = [
            ['item_id' => $this->item->id, 'qty' => 6, 'cost_price' => 100],
            ['item_id' => $this->item->id, 'qty' => 6, 'cost_price' => 100],
        ];
        $this->post(route('purchase.purchase-returns.store'), $payload)->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_invoice_ownership_status_and_item_membership_are_enforced(): void
    {
        $inv = $this->invoice();
        $other = Supplier::create(['name' => 'Other Sup']);

        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, [], ['supplier_id' => $other->id]))
            ->assertSessionHasErrors('purchase_invoice_id');

        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, ['item_id' => $this->item2->id, 'cost_price' => 50]))
            ->assertSessionHasErrors(['items' => "The item 'Cov Item B' does not belong to the selected Purchase Invoice."]);

        $inv->update(['status' => 'Cancelled']);
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv))
            ->assertSessionHasErrors('purchase_invoice_id');

        $this->assertSame(0, PurchaseReturn::count());
        $this->assertEquals(10, $this->stockOf($this->item));
    }

    public function test_insufficient_branch_stock_blocks_return(): void
    {
        $inv = $this->invoice();
        ItemStock::where('item_id', $this->item->id)->update(['quantity' => 1]);
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, ['qty' => 3]))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_supplier_only_return_allows_supplier_items_and_rejects_foreign_items(): void
    {
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 20]);
        ItemStock::create(['item_id' => $this->item2->id, 'branch_id' => $this->branch->id, 'quantity' => 20]);

        // item2 has no supplier and was never bought from this supplier
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload(null, ['item_id' => $this->item2->id, 'cost_price' => 50]))
            ->assertSessionHasErrors(['items' => "The item 'Cov Item B' does not belong to the selected Supplier."]);
        $this->assertSame(0, PurchaseReturn::count());

        $ret = $this->makeReturn(null, ['qty' => 4]);
        $this->assertNull($ret->purchase_invoice_id);
        $this->assertEquals(16, $this->stockOf($this->item));
        $this->assertSame(1, StockLedger::where('reference_type', PurchaseReturn::class)->where('reference_id', $ret->id)->count());

        // once item2 has been purchased from the supplier it becomes returnable
        $this->item2->update(['supplier_id' => $this->supplier->id]);
        $this->makeReturn(null, ['item_id' => $this->item2->id, 'qty' => 2, 'cost_price' => 50]);
        $this->assertEquals(18, $this->stockOf($this->item2));
    }

    public function test_return_posts_supplier_debit_journal_and_stock_ledger(): void
    {
        $inv = $this->invoice();
        $ret = $this->makeReturn($inv, ['qty' => 5]);
        $this->assertEquals(590, (float) $ret->total);
        $je = JournalEntry::where('reference_type', PurchaseReturn::class)->where('reference_id', $ret->id)->whereNull('reversal_of')->first();
        $this->assertEquals(590, (float) $je->total_debit);
        $this->assertEquals(590, (float) $je->total_credit);
        $sl = StockLedger::where('reference_type', PurchaseReturn::class)->where('reference_id', $ret->id)->first();
        $this->assertSame('PURCHASE_RETURN', $sl->movement_type);
        $this->assertEquals(5, (float) $sl->qty_out);
    }

    public function test_update_reverses_and_reposts_stock_and_journal(): void
    {
        $inv = $this->invoice();
        $ret = $this->makeReturn($inv, ['qty' => 3]);

        // can raise to 10 because the return being edited is excluded from "already returned"
        $this->put(route('purchase.purchase-returns.update', $ret), $this->retPayload($inv, ['qty' => 10]))
            ->assertRedirect(route('purchase.purchase-returns.index'));
        $ret->refresh();
        $this->assertEquals(0, $this->stockOf($this->item));
        $this->assertEquals(1180, (float) $ret->total);
        $this->assertSame(1, $ret->items()->count());
        $this->assertTrue(StockLedger::where('reference_id', $ret->id)->where('reference_type', PurchaseReturn::class)->whereNotNull('reversal_of')->exists());

        // 11 is still over the invoice quantity
        $this->put(route('purchase.purchase-returns.update', $ret), $this->retPayload($inv, ['qty' => 11]))->assertSessionHasErrors('items');
        $this->assertEquals(0, $this->stockOf($this->item));

        $this->get(route('purchase.purchase-returns.edit', $ret))->assertOk();
    }

    public function test_cancelled_return_cannot_be_edited_or_updated(): void
    {
        $inv = $this->invoice();
        $ret = $this->makeReturn($inv);
        $ret->update(['status' => 'Cancelled']);
        $this->get(route('purchase.purchase-returns.edit', $ret))->assertSessionHasErrors('status');
        $this->put(route('purchase.purchase-returns.update', $ret), $this->retPayload($inv, ['qty' => 1]))->assertSessionHasErrors('status');
        $this->assertEquals(7, $this->stockOf($this->item));
    }

    public function test_destroy_restores_stock_reverses_journal_and_frees_returnable_qty(): void
    {
        $inv = $this->invoice();
        $ret = $this->makeReturn($inv, ['qty' => 4]);
        $this->assertEquals(6, $this->stockOf($this->item));

        $this->delete(route('purchase.purchase-returns.destroy', $ret))->assertRedirect(route('purchase.purchase-returns.index'));

        $this->assertEquals(10, $this->stockOf($this->item));
        $this->assertNull(PurchaseReturn::find($ret->id));
        $this->assertTrue(JournalEntry::where('reference_type', PurchaseReturn::class)->where('reference_id', $ret->id)->whereNotNull('reversal_of')->exists());
        $this->assertNotNull(AuditLog::where('auditable_type', PurchaseReturn::class)->where('auditable_id', $ret->id)->where('action', 'cancel')->first());
        // the full quantity is returnable again (deleted return no longer counts)
        $this->makeReturn($inv, ['qty' => 10]);
    }

    public function test_posting_key_makes_return_store_idempotent(): void
    {
        $inv = $this->invoice();
        $this->makeReturn($inv, ['qty' => 2], ['posting_key' => 'rk-1']);
        $this->post(route('purchase.purchase-returns.store'), $this->retPayload($inv, ['qty' => 2], ['posting_key' => 'rk-1']))
            ->assertRedirect(route('purchase.purchase-returns.index'))->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseReturn::count());
        $this->assertEquals(8, $this->stockOf($this->item));
    }

    public function test_validation_errors(): void
    {
        $inv = $this->invoice();
        $route = route('purchase.purchase-returns.store');
        $this->post($route, $this->retPayload($inv, [], ['purchase_type' => 'Import']))->assertSessionHasErrors('purchase_type');
        $this->post($route, $this->retPayload($inv, [], ['supplier_id' => null]))->assertSessionHasErrors('supplier_id');
        $this->post($route, $this->retPayload($inv, ['qty' => 0]))->assertSessionHasErrors('items.0.qty');
        $this->post($route, $this->retPayload($inv, [], ['items' => []]))->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_interstate_return_uses_igst(): void
    {
        $inv = $this->invoice(['purchase_type' => 'Interstate']);
        $ret = $this->makeReturn($inv, ['qty' => 5], ['purchase_type' => 'Interstate']);
        $this->assertEquals(90, (float) $ret->total_igst);
        $this->assertEquals(0, (float) $ret->total_cgst);
    }

    public function test_item_list_scopes_by_supplier_or_invoice_and_reports_remaining(): void
    {
        $this->getJson(route('purchase.purchase-returns.item-list'))->assertJson(['items' => [], 'message' => 'Please select a Supplier first.']);

        $r = $this->getJson(route('purchase.purchase-returns.item-list', ['supplier_id' => $this->supplier->id]))->assertJsonPath('source', 'supplier')->json('items');
        $this->assertSame(['Cov Item A'], collect($r)->pluck('name')->all()); // item2 has no relation to supplier
        $this->assertNull($r[0]['remaining_qty']);

        $inv = $this->invoice();
        $this->makeReturn($inv, ['qty' => 4]);
        $r = $this->getJson(route('purchase.purchase-returns.item-list', ['purchase_invoice_id' => $inv->id, 'branch_id' => $this->branch->id]))->assertJsonPath('source', 'invoice')->json('items');
        $this->assertCount(1, $r);
        $this->assertEquals(10, $r[0]['invoiced_qty']);
        $this->assertEquals(4, $r[0]['already_returned']);
        $this->assertEquals(6, $r[0]['remaining_qty']);
        $this->assertEquals(6, $r[0]['qty']); // branch stock

        $ret = PurchaseReturn::firstOrFail();
        $r = $this->getJson(route('purchase.purchase-returns.item-list', ['purchase_invoice_id' => $inv->id, 'ignore_return_id' => $ret->id]))->json('items');
        $this->assertEquals(10, $r[0]['remaining_qty']);

        $this->assertCount(1, $this->getJson(route('purchase.purchase-returns.item-list', ['supplier_id' => $this->supplier->id, 'search' => 'Item A']))->json('items'));
        $this->assertCount(0, $this->getJson(route('purchase.purchase-returns.item-list', ['supplier_id' => $this->supplier->id, 'search' => 'zzz']))->json('items'));
        $this->assertCount(1, $this->getJson(route('purchase.purchase-returns.item-list', ['supplier_id' => $this->supplier->id, 'code' => 'COVA']))->json('items'));
        $this->assertCount(0, $this->getJson(route('purchase.purchase-returns.item-list', ['supplier_id' => $this->supplier->id, 'code' => 'COVB']))->json('items'));
    }

    public function test_lookup_item_scoping_and_errors(): void
    {
        $this->getJson(route('purchase.purchase-returns.lookup-item', ['query' => 'COVA']))->assertStatus(422)->assertJson(['success' => false]);
        $this->getJson(route('purchase.purchase-returns.lookup-item', ['supplier_id' => $this->supplier->id]))->assertStatus(422)->assertJson(['success' => false]);

        $this->getJson(route('purchase.purchase-returns.lookup-item', ['supplier_id' => $this->supplier->id, 'query' => 'COVA']))
            ->assertOk()->assertJson(['success' => true, 'id' => $this->item->id]);
        // falls back to name search
        $this->getJson(route('purchase.purchase-returns.lookup-item', ['supplier_id' => $this->supplier->id, 'query' => 'Item A']))
            ->assertOk()->assertJson(['success' => true, 'id' => $this->item->id]);
        $this->getJson(route('purchase.purchase-returns.lookup-item', ['supplier_id' => $this->supplier->id, 'query' => 'COVB']))
            ->assertStatus(404)->assertJson(['message' => "Item 'COVB' does not belong to the selected Supplier."]);

        $inv = $this->invoice();
        $this->getJson(route('purchase.purchase-returns.lookup-item', ['purchase_invoice_id' => $inv->id, 'query' => 'COVB']))
            ->assertStatus(404)->assertJson(['message' => "Item 'COVB' was not found in the selected Purchase Invoice."]);
    }

    public function test_supplier_invoices_index_filters_show_and_print(): void
    {
        $inv = $this->invoice();
        $this->getJson(route('purchase.purchase-returns.supplier-invoices', $this->supplier))
            ->assertJsonPath('invoices.0.id', $inv->id)->assertJsonPath('invoices.0.total', '1,180.00');
        $other = Supplier::create(['name' => 'Nobody']);
        $this->getJson(route('purchase.purchase-returns.supplier-invoices', $other))->assertJson(['invoices' => []]);

        $ret = $this->makeReturn($inv, ['qty' => 1], ['supplier_debit_note_no' => 'DN-77']);
        $ids = fn ($q) => $this->get(route('purchase.purchase-returns.index', $q))->assertOk()->viewData('purchaseReturns')->pluck('id')->all();
        $this->assertSame([$ret->id], $ids(['search' => 'DN-77']));
        $this->assertSame([$ret->id], $ids(['search' => $inv->invoice_number]));
        $this->assertSame([$ret->id], $ids(['search' => 'Cov Supplier']));
        $this->assertSame([], $ids(['search' => 'nomatch']));
        $this->assertSame([], $ids(['supplier_id' => $other->id]));
        $this->assertSame([$ret->id], $ids(['branch_id' => $this->branch->id, 'supplier_id' => $this->supplier->id]));
        $this->assertSame([], $ids(['date_from' => now()->addDay()->toDateString()]));
        $this->assertSame([], $ids(['date_to' => now()->subDays(3)->toDateString()]));

        $this->get(route('purchase.purchase-returns.create'))->assertOk();
        $this->get(route('purchase.purchase-returns.show', $ret))->assertOk()->assertSee($ret->return_number);
        $this->get(route('purchase.purchase-returns.print', $ret))->assertOk();
    }
}
