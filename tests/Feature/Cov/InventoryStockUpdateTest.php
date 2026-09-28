<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\StockUpdate;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $br;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->br = Branch::create(['name' => 'SU Br', 'state' => 'Gujarat']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    private function qty(Item $i): float
    {
        return (float) (ItemStock::where('item_id', $i->id)->where('branch_id', $this->br->id)->value('quantity') ?? 0);
    }

    private function seedStock(Item $i, float $q, float $c = 10): void
    {
        app(StockLedgerService::class)->post($i->id, $this->br->id, 'OPENING', $q, $c, null, null, '2026-09-01', $this->owner->id);
    }

    private function payload(array $lines, array $over = []): array
    {
        return array_merge([
            'branch_id' => $this->br->id,
            'entry_date' => now()->toDateString(),
            'items' => $lines,
        ], $over);
    }

    private function create(array $lines): StockUpdate
    {
        $this->post(route('inventory.stock-updates.store'), $this->payload($lines))->assertSessionHasNoErrors();

        return StockUpdate::latest('id')->first();
    }

    public function test_pending_count_records_delta_but_moves_no_stock(): void
    {
        $i = Item::create(['name' => 'SU1']);
        $this->seedStock($i, 10);
        $su = $this->create([['item_id' => $i->id, 'physical_qty' => 7]]);

        $this->assertEquals('Pending', $su->status);
        $line = $su->items->first();
        $this->assertEquals(10, (float) $line->system_qty_at_entry);
        $this->assertEquals(-3, (float) $line->delta_qty);
        $this->assertEquals(10, $this->qty($i));
        $this->assertEquals(0, StockLedger::where('reference_type', StockUpdate::class)->count());
    }

    public function test_approve_shortage_and_excess_post_exact_deltas_and_skip_exact_lines(): void
    {
        $short = Item::create(['name' => 'SU2s']);
        $excess = Item::create(['name' => 'SU2e']);
        $exact = Item::create(['name' => 'SU2x']);
        foreach ([$short, $excess, $exact] as $x) {
            $this->seedStock($x, 10, 25);
        }
        $su = $this->create([
            ['item_id' => $short->id, 'physical_qty' => 6],
            ['item_id' => $excess->id, 'physical_qty' => 13, 'exp_date' => '2027-02-02'],
            ['item_id' => $exact->id, 'physical_qty' => 10],
        ]);

        $this->post(route('inventory.stock-update-approval.approve', $su))
            ->assertRedirect(route('inventory.stock-update-approval.index'));

        $this->assertEquals('Approved', $su->fresh()->status);
        $this->assertEquals(6, $this->qty($short));
        $this->assertEquals(13, $this->qty($excess));
        $this->assertEquals(10, $this->qty($exact));
        $rows = StockLedger::where('reference_type', StockUpdate::class)->where('reference_id', $su->id)->get();
        $this->assertCount(2, $rows, 'Exact-match line must not post.');
        $this->assertEquals('SHORTAGE', $rows->firstWhere('item_id', $short->id)->movement_type);
        $this->assertEquals(4, (float) $rows->firstWhere('item_id', $short->id)->qty_out);
        $ex = $rows->firstWhere('item_id', $excess->id);
        $this->assertEquals('EXCESS', $ex->movement_type);
        $this->assertEquals(3, (float) $ex->qty_in);
        $this->assertEquals('2027-02-02', $ex->exp_date->toDateString());
        $this->assertEquals('PHYSICAL_COUNT', $ex->reason_code);
    }

    public function test_approving_twice_does_not_double_post(): void
    {
        $i = Item::create(['name' => 'SU3']);
        $this->seedStock($i, 10);
        $su = $this->create([['item_id' => $i->id, 'physical_qty' => 4]]);
        $this->post(route('inventory.stock-update-approval.approve', $su));
        $this->post(route('inventory.stock-update-approval.approve', $su->fresh()))->assertSessionHas('status');

        $this->assertEquals(4, $this->qty($i));
        $this->assertEquals(1, StockLedger::where('reference_type', StockUpdate::class)->count());
    }

    public function test_reject_pending_never_touches_stock_and_reject_approved_reverses(): void
    {
        $i = Item::create(['name' => 'SU4']);
        $this->seedStock($i, 10);
        $pending = $this->create([['item_id' => $i->id, 'physical_qty' => 2]]);
        $this->post(route('inventory.stock-update-approval.reject', $pending))->assertRedirect(route('inventory.stock-update-approval.index'));
        $this->assertEquals('Rejected', $pending->fresh()->status);
        $this->assertEquals(10, $this->qty($i));
        $this->assertEquals(0, StockLedger::where('reference_type', StockUpdate::class)->count());

        $approved = $this->create([['item_id' => $i->id, 'physical_qty' => 15]]);
        $this->post(route('inventory.stock-update-approval.approve', $approved));
        $this->assertEquals(15, $this->qty($i));
        $this->post(route('inventory.stock-update-approval.reject', $approved));
        $this->assertEquals(10, $this->qty($i), 'Rejecting an approved count must reverse its posting.');
        $this->assertEquals('Rejected', $approved->fresh()->status);
    }

    public function test_approval_gated_to_permission_holders(): void
    {
        $i = Item::create(['name' => 'SU5']);
        $this->seedStock($i, 10);
        $su = $this->create([['item_id' => $i->id, 'physical_qty' => 1]]);

        $mgr = User::factory()->create();
        $mgr->assignRole('Manager');
        $this->actingAs($mgr);
        $this->post(route('inventory.stock-update-approval.approve', $su))->assertForbidden();
        $this->post(route('inventory.stock-update-approval.reject', $su))->assertForbidden();

        $this->assertEquals('Pending', $su->fresh()->status);
        $this->assertEquals(10, $this->qty($i));
    }

    public function test_update_allowed_only_while_pending_and_replaces_lines(): void
    {
        $i = Item::create(['name' => 'SU6']);
        $this->seedStock($i, 10);
        $su = $this->create([['item_id' => $i->id, 'physical_qty' => 8]]);

        $this->put(route('inventory.stock-updates.update', $su), $this->payload([['item_id' => $i->id, 'physical_qty' => 12]], ['remarks' => 'recount']))
            ->assertRedirect(route('inventory.stock-updates.index'));
        $su->refresh();
        $this->assertEquals('recount', $su->remarks);
        $this->assertEquals(1, $su->items()->count());
        $this->assertEquals(2, (float) $su->items->first()->delta_qty);

        $this->post(route('inventory.stock-update-approval.approve', $su));
        $this->put(route('inventory.stock-updates.update', $su), $this->payload([['item_id' => $i->id, 'physical_qty' => 1]]))
            ->assertSessionHasErrors('status');
        $this->assertEquals(12, $this->qty($i));

        $rejected = $this->create([['item_id' => $i->id, 'physical_qty' => 1]]);
        $this->post(route('inventory.stock-update-approval.reject', $rejected));
        $this->put(route('inventory.stock-updates.update', $rejected), $this->payload([['item_id' => $i->id, 'physical_qty' => 5]]))
            ->assertSessionHasErrors('status');
    }

    public function test_destroy_pending_leaves_stock_and_destroy_approved_reverses(): void
    {
        $i = Item::create(['name' => 'SU7']);
        $this->seedStock($i, 10);
        $p = $this->create([['item_id' => $i->id, 'physical_qty' => 3]]);
        $this->delete(route('inventory.stock-updates.destroy', $p))->assertRedirect(route('inventory.stock-updates.index'));
        $this->assertNull(StockUpdate::find($p->id));
        $this->assertEquals(10, $this->qty($i));

        $a = $this->create([['item_id' => $i->id, 'physical_qty' => 3]]);
        $this->post(route('inventory.stock-update-approval.approve', $a));
        $this->assertEquals(3, $this->qty($i));
        $this->delete(route('inventory.stock-updates.destroy', $a));
        $this->assertEquals(10, $this->qty($i));
        $this->assertNull(StockUpdate::find($a->id));
    }

    public function test_validation_errors(): void
    {
        $i = Item::create(['name' => 'SU8']);
        $this->post(route('inventory.stock-updates.store'), $this->payload([['item_id' => $i->id, 'physical_qty' => -1]]))
            ->assertSessionHasErrors('items.0.physical_qty');
        $this->post(route('inventory.stock-updates.store'), $this->payload([['item_id' => 999999, 'physical_qty' => 1]]))
            ->assertSessionHasErrors('items.0.item_id');
        $this->post(route('inventory.stock-updates.store'), $this->payload([['item_id' => $i->id, 'physical_qty' => 1]], ['branch_id' => 999999]))
            ->assertSessionHasErrors('branch_id');
        $this->assertEquals(0, StockUpdate::count());
    }

    public function test_index_filters_and_csv_export(): void
    {
        $short = Item::create(['name' => 'Count Short', 'item_code' => 'CS1']);
        $excess = Item::create(['name' => 'Count Excess', 'ean_upc_code' => '890777']);
        $exact = Item::create(['name' => 'Count Exact']);
        foreach ([$short, $excess, $exact] as $x) {
            $this->seedStock($x, 10);
        }
        $su = $this->create([
            ['item_id' => $short->id, 'physical_qty' => 4, 'exp_date' => '2027-01-01'],
            ['item_id' => $excess->id, 'physical_qty' => 12],
            ['item_id' => $exact->id, 'physical_qty' => 10],
        ]);

        $q = fn (array $p) => $this->get(route('inventory.stock-updates.index', $p));
        $this->assertEquals(1, $q(['diff_type' => 'shortage'])->viewData('totalCount'));
        $this->assertEquals(-6, (float) $q(['diff_type' => 'shortage'])->viewData('totalDeltaQty'));
        $this->assertEquals(1, $q(['diff_type' => 'excess'])->viewData('totalCount'));
        $this->assertEquals(1, $q(['diff_type' => 'exact'])->viewData('totalCount'));
        $this->assertEquals(3, $q(['status' => 'Pending', 'branch_id' => $this->br->id, 'date_from' => '2000-01-01', 'date_to' => '2100-01-01', 'per_page' => 25])->viewData('totalCount'));
        $this->assertEquals(0, $q(['status' => 'Approved'])->viewData('totalCount'));
        $this->assertEquals(1, $q(['search' => 'CS1'])->viewData('totalCount'));
        $this->assertEquals(1, $q(['search' => '890777'])->viewData('totalCount'));
        $this->assertEquals(3, $q(['search' => $su->update_number, 'per_page' => 7])->viewData('totalCount'));

        $csv = $q(['export' => 'csv']);
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Count Short', $body);
        $this->assertStringContainsString('01-01-2027', $body);
        $this->assertStringContainsString($su->update_number, $body);
    }

    public function test_pages_render_and_approval_index_filters(): void
    {
        $i = Item::create(['name' => 'SU9']);
        $this->seedStock($i, 10);
        $su = $this->create([['item_id' => $i->id, 'physical_qty' => 9]]);

        $this->get(route('inventory.stock-updates.create'))->assertOk();
        $this->get(route('inventory.stock-updates.edit', $su))->assertOk();
        $r = $this->get(route('inventory.stock-update-approval.index'));
        $r->assertOk();
        $this->assertEquals('Pending', $r->viewData('status'));
        $this->assertEquals(1, $r->viewData('pendingCount'));
        $this->assertEquals(0, $this->get(route('inventory.stock-update-approval.index', ['status' => 'Rejected', 'branch_id' => $this->br->id]))->viewData('stockUpdates')->total());
        $this->assertEquals(1, $this->get(route('inventory.stock-update-approval.index', ['status' => 'All']))->viewData('stockUpdates')->total());
        $this->get(route('inventory.stock-update-approval.show', $su))->assertOk()->assertSee($su->update_number)->assertSee('SU9');

        $this->post(route('inventory.stock-update-approval.approve', $su));
        $this->assertEquals('All', $this->get(route('inventory.stock-update-approval.index'))->viewData('status'));
    }
}
