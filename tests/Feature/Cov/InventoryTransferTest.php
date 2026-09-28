<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private Branch $a;
    private Branch $b;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Branch::create(['name' => 'TR A', 'state' => 'Gujarat']);
        $this->b = Branch::create(['name' => 'TR B', 'state' => 'Gujarat']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    private function qty(Item $i, Branch $br): float
    {
        return (float) (ItemStock::where('item_id', $i->id)->where('branch_id', $br->id)->value('quantity') ?? 0);
    }

    private function seedStock(Item $i, Branch $br, float $q, float $c): void
    {
        app(StockLedgerService::class)->post($i->id, $br->id, 'OPENING', $q, $c, null, null, '2026-09-01', $this->owner->id);
    }

    private function dispatchPayload(Item $i, float $qty, array $over = []): array
    {
        return array_merge([
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->a->id,
            'to_branch_id' => $this->b->id,
            'items' => [['item_id' => $i->id, 'qty' => $qty]],
        ], $over);
    }

    private function dispatchTransfer(Item $i, float $qty, array $over = []): StockTransfer
    {
        $this->post(route('inventory.stock-transfers.store'), $this->dispatchPayload($i, $qty, $over))->assertSessionHasNoErrors();

        return StockTransfer::latest('id')->first();
    }

    public function test_dispatch_more_than_available_is_rejected_and_nothing_is_created(): void
    {
        $i = Item::create(['name' => 'T1']);
        $this->seedStock($i, $this->a, 3, 10);

        $this->post(route('inventory.stock-transfers.store'), $this->dispatchPayload($i, 4))->assertSessionHasErrors('items');

        $this->assertEquals(3, $this->qty($i, $this->a));
        $this->assertEquals(0, StockTransfer::count());
        $this->assertEquals(0, StockLedger::where('movement_type', 'TRANSFER_OUT')->count());
    }

    public function test_dispatch_same_branch_future_date_and_expired_lines_rejected(): void
    {
        $i = Item::create(['name' => 'T2']);
        $this->seedStock($i, $this->a, 10, 10);

        $this->post(route('inventory.stock-transfers.store'), $this->dispatchPayload($i, 1, ['to_branch_id' => $this->a->id]))
            ->assertSessionHasErrors('from_branch_id');
        $this->post(route('inventory.stock-transfers.store'), $this->dispatchPayload($i, 1, ['transfer_date' => now()->addDays(3)->toDateString()]))
            ->assertSessionHasErrors('transfer_date');
        $p = $this->dispatchPayload($i, 1);
        $p['items'][0]['exp_date'] = now()->subDays(5)->toDateString();
        $this->post(route('inventory.stock-transfers.store'), $p)->assertSessionHasErrors('items');

        $this->assertEquals(10, $this->qty($i, $this->a));
        $this->assertEquals(0, StockTransfer::count());
    }

    public function test_dispatch_split_lines_are_summed_against_availability_and_zero_qty_lines_dropped(): void
    {
        $i = Item::create(['name' => 'T3']);
        $this->seedStock($i, $this->a, 5, 10);
        $p = $this->dispatchPayload($i, 3);
        $p['items'][] = ['item_id' => $i->id, 'qty' => 3];
        $this->post(route('inventory.stock-transfers.store'), $p)->assertSessionHasErrors('items');
        $this->assertEquals(0, StockTransfer::count());

        $p['items'] = [['item_id' => $i->id, 'qty' => 2], ['item_id' => $i->id, 'qty' => 0], ['item_id' => '', 'qty' => 4]];
        $this->post(route('inventory.stock-transfers.store'), $p)->assertSessionHasNoErrors();
        $t = StockTransfer::first();
        $this->assertEquals(1, $t->items()->count());
        $this->assertEquals(2, (float) $t->total_qty);
        $this->assertEquals(3, $this->qty($i, $this->a));
    }

    public function test_dispatch_posting_key_is_idempotent(): void
    {
        $i = Item::create(['name' => 'T4']);
        $this->seedStock($i, $this->a, 10, 10);
        $p = $this->dispatchPayload($i, 2, ['posting_key' => 'TK1']);
        $this->post(route('inventory.stock-transfers.store'), $p);
        $this->post(route('inventory.stock-transfers.store'), $p)->assertRedirect(route('inventory.stock-transfers.index'));
        $this->assertEquals(1, StockTransfer::count());
        $this->assertEquals(8, $this->qty($i, $this->a));
    }

    public function test_transfer_total_value_uses_source_average_cost(): void
    {
        $i = Item::create(['name' => 'T5']);
        $this->seedStock($i, $this->a, 5, 100);
        $this->seedStock($i, $this->a, 5, 200); // avg 150
        $t = $this->dispatchTransfer($i, 4);
        $this->assertEquals(600, (float) $t->total_value);
        $this->assertEquals(150, (float) $t->items->first()->unit_cost);
    }

    public function test_over_receipt_is_clamped_to_dispatched_qty(): void
    {
        $i = Item::create(['name' => 'T6']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 4);
        $line = $t->items->first();

        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $line->id, 'received_qty' => 99]]]);

        $this->assertEquals(4, $this->qty($i, $this->b), 'Cannot receive more than was dispatched.');
        $this->assertEquals(4, (float) $line->fresh()->received_qty);
    }

    public function test_zero_receipt_marks_received_without_posting_transfer_in(): void
    {
        $i = Item::create(['name' => 'T7']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 4);
        $line = $t->items->first();

        $this->post(route('inventory.stock-transfers.receive', $t), [
            'items' => [['id' => $line->id, 'received_qty' => 0]],
            'remarks' => 'all lost',
        ])->assertRedirect(route('inventory.stock-transfers.pending-receipt'));

        $t->refresh();
        $this->assertEquals('Received', $t->status);
        $this->assertNotNull($t->received_at);
        $this->assertStringContainsString('Inward: all lost', $t->remarks);
        $this->assertEquals(0, StockLedger::where('movement_type', 'TRANSFER_IN')->count());
        $this->assertEquals(0, $this->qty($i, $this->b));
        $this->assertEquals(6, $this->qty($i, $this->a));
    }

    public function test_receive_appends_remarks_to_existing_remarks(): void
    {
        $i = Item::create(['name' => 'T8']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 2, ['remarks' => 'urgent']);
        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $t->items->first()->id, 'received_qty' => 2]], 'remarks' => 'ok']);
        $this->assertEquals('urgent | Inward: ok', $t->fresh()->remarks);
    }

    public function test_receive_twice_or_after_cancel_is_rejected_and_stock_unchanged(): void
    {
        $i = Item::create(['name' => 'T9']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 4);
        $lineId = $t->items->first()->id;
        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $lineId, 'received_qty' => 4]]]);

        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $lineId, 'received_qty' => 4]]])
            ->assertSessionHasErrors('status');
        $this->post(route('inventory.stock-transfers.cancel', $t))->assertSessionHasErrors('status');
        $this->assertEquals(4, $this->qty($i, $this->b));
        $this->assertEquals('Received', $t->fresh()->status);

        $t2 = $this->dispatchTransfer($i, 2);
        $this->post(route('inventory.stock-transfers.cancel', $t2));
        $this->post(route('inventory.stock-transfers.receive', $t2), ['items' => [['id' => $t2->items->first()->id, 'received_qty' => 2]]])
            ->assertSessionHasErrors('status');
        $this->assertEquals(4, $this->qty($i, $this->b), 'A cancelled transfer must never add stock at destination.');
    }

    public function test_receive_validation(): void
    {
        $i = Item::create(['name' => 'T10']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 4);
        $this->post(route('inventory.stock-transfers.receive', $t), [])->assertSessionHasErrors('items');
        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $t->items->first()->id, 'received_qty' => -1]]])
            ->assertSessionHasErrors('items.0.received_qty');
        $this->assertEquals('Dispatched', $t->fresh()->status);
    }

    public function test_cancel_writes_reversal_rows_and_restores_source_exactly(): void
    {
        $i = Item::create(['name' => 'T11']);
        $this->seedStock($i, $this->a, 10, 30);
        $t = $this->dispatchTransfer($i, 7);
        $this->assertEquals(3, $this->qty($i, $this->a));

        $this->post(route('inventory.stock-transfers.cancel', $t))->assertRedirect(route('inventory.stock-transfers.index'));

        $this->assertEquals(10, $this->qty($i, $this->a));
        $this->assertEquals('Cancelled', $t->fresh()->status);
        $rev = StockLedger::where('reference_type', StockTransfer::class)->whereNotNull('reversal_of')->get();
        $this->assertCount(1, $rev);
        $this->assertEquals(7, (float) $rev->first()->qty_in);
    }

    public function test_interstate_transfer_splits_igst_on_dispatch_cost(): void
    {
        $tax = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $i = Item::create(['name' => 'T12', 'gst_tax_id' => $tax->id]);
        $this->seedStock($i, $this->a, 10, 50);
        $c = Branch::create(['name' => 'TR C', 'state' => 'Kerala']);

        $t = $this->dispatchTransfer($i, 4, ['to_branch_id' => $c->id]);
        $line = $t->items->first();
        $this->assertEquals(200, (float) $line->taxable_value);
        $this->assertEquals(36, (float) $line->igst_amount);
        $this->assertEquals(0, (float) $line->cgst_amount);
        $this->assertEquals(0, (float) $line->sgst_amount);
    }

    public function test_receive_form_only_for_dispatched_and_pending_receipt_filters(): void
    {
        $i = Item::create(['name' => 'T13']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 2);
        $done = $this->dispatchTransfer($i, 1);
        $this->post(route('inventory.stock-transfers.receive', $done), ['items' => [['id' => $done->items->first()->id, 'received_qty' => 1]]]);

        $this->get(route('inventory.stock-transfers.receive-form', $t))->assertOk();
        $this->get(route('inventory.stock-transfers.receive-form', $done))->assertRedirect(route('inventory.stock-transfers.pending-receipt'));

        $r = $this->get(route('inventory.stock-transfers.pending-receipt', ['branch_id' => $this->b->id]));
        $r->assertOk();
        $this->assertEquals(1, $r->viewData('pendingCount'));
        $this->assertEquals(1, $r->viewData('receivedCount'));
        $this->assertEquals(2, $r->viewData('allCount'));
        $this->assertEquals(1, $this->get(route('inventory.stock-transfers.pending-receipt', ['status' => 'Received']))->viewData('stockTransfers')->total());
        $this->assertEquals(2, $this->get(route('inventory.stock-transfers.pending-receipt', ['status' => 'All']))->viewData('stockTransfers')->total());
    }

    public function test_index_filters_show_print_create(): void
    {
        $i = Item::create(['name' => 'T14']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 2);

        $r = $this->get(route('inventory.stock-transfers.index', [
            'search' => $t->transfer_number, 'date_from' => '2000-01-01', 'date_to' => '2100-01-01',
            'from_branch_id' => $this->a->id, 'to_branch_id' => $this->b->id, 'status' => 'Dispatched',
        ]));
        $r->assertOk();
        $this->assertEquals(1, $r->viewData('stockTransfers')->total());
        $this->assertEquals(0, $this->get(route('inventory.stock-transfers.index', ['status' => 'Cancelled']))->viewData('stockTransfers')->total());
        $this->get(route('inventory.stock-transfers.show', $t))->assertOk();
        $this->get(route('inventory.stock-transfers.print', $t))->assertOk();
        $this->get(route('inventory.stock-transfers.create'))->assertOk();
    }

    public function test_lookup_endpoints_respect_stock_and_expiry(): void
    {
        $i = Item::create(['name' => 'Lookup Widget', 'item_code' => 'LW1', 'ean_upc_code' => '890333', 'alias' => 'lwa']);
        $none = Item::create(['name' => 'Lookup Empty', 'ean_upc_code' => '890444']);
        $this->seedStock($i, $this->a, 6, 10);

        $this->getJson(route('inventory.stock-transfers.search-items', ['q' => '', 'branch_id' => $this->a->id]))->assertExactJson([]);
        $found = $this->getJson(route('inventory.stock-transfers.search-items', ['q' => 'Lookup', 'branch_id' => $this->a->id]))->assertOk()->json();
        $this->assertCount(1, $found, 'Zero-stock items must not be offered for transfer.');
        $this->assertEquals(6, $found[0]['available_qty']);

        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => '', 'branch_id' => $this->a->id]))->assertJsonPath('found', false);
        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => 'zzz', 'branch_id' => $this->a->id]))->assertJsonPath('found', false);
        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => '890444', 'branch_id' => $this->a->id]))
            ->assertJsonPath('found', false)->assertJsonPath('error', "Product 'Lookup Empty' has 0 available stock in this branch. Cannot transfer.");
        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => 'lwa', 'branch_id' => $this->a->id]))
            ->assertJsonPath('found', true)->assertJsonPath('item.available_qty', 6);

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('purchase_invoice_items')->insert([
            'purchase_invoice_id' => 987654, 'item_id' => $i->id, 'exp_date' => now()->subDay()->toDateString(),
            'qty' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        $this->getJson(route('inventory.stock-transfers.item-by-code', ['code' => '890333', 'branch_id' => $this->a->id]))
            ->assertJsonPath('found', false)
            ->assertJsonPath('error', "Product 'Lookup Widget' has expired on " . now()->subDay()->toDateString() . ". Transfer of expired items is not permitted.");
    }

    public function test_item_list_requires_a_filter_and_filters_by_stock_and_expiry(): void
    {
        $i = Item::create(['name' => 'Listed Thing', 'item_code' => 'LT1', 'ean_upc_code' => '890555']);
        $empty = Item::create(['name' => 'Listed Empty']);
        $this->seedStock($i, $this->a, 4, 10);

        $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->a->id]))->assertJsonPath('items', []);
        $byName = $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->a->id, 'search' => 'Listed']))->assertOk()->json('items');
        $this->assertCount(1, $byName);
        $this->assertEquals(4, $byName[0]['qty']);
        $byCode = $this->getJson(route('inventory.stock-transfers.item-list', ['from_branch_id' => $this->a->id, 'code' => 'LT1']))->json('items');
        $this->assertEquals($i->id, $byCode[0]['id']);
        $this->assertCount(0, $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->a->id, 'expiry' => '2031']))->json('items'));
        $this->assertCount(0, $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->b->id, 'search' => 'Listed']))->json('items'));
    }

    public function test_scoped_branch_user_permissions_for_receive_and_cancel(): void
    {
        $i = Item::create(['name' => 'T15']);
        $this->seedStock($i, $this->a, 10, 20);
        $t = $this->dispatchTransfer($i, 3);
        $lineId = $t->items->first()->id;
        $c = Branch::create(['name' => 'TR C2', 'state' => 'Gujarat']);

        $outsider = User::factory()->create(['branch_id' => $c->id]);
        $outsider->assignRole('Manager');
        $this->actingAs($outsider);
        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $lineId, 'received_qty' => 3]]])->assertForbidden();
        $this->post(route('inventory.stock-transfers.cancel', $t))->assertForbidden();
        $this->assertEquals('Dispatched', $t->fresh()->status);

        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);
        $this->post(route('inventory.stock-transfers.store'), $this->dispatchPayload($i, 1))->assertForbidden();

        $receiver = User::factory()->create(['branch_id' => $this->b->id]);
        $receiver->assignRole('Manager');
        $this->actingAs($receiver);
        $this->post(route('inventory.stock-transfers.receive', $t), ['items' => [['id' => $lineId, 'received_qty' => 3]]])->assertRedirect();
        $this->assertEquals(3, $this->qty($i, $this->b));
    }
}
