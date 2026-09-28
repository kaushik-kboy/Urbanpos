<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\DamageStock;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OpeningStock;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDamageOpeningTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::create(['name' => 'DO Branch', 'state' => 'Gujarat']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);
    }

    private function qty(Item $i): float
    {
        return (float) (ItemStock::where('item_id', $i->id)->where('branch_id', $this->branch->id)->value('quantity') ?? 0);
    }

    private function seedStock(Item $i, float $q, float $c): void
    {
        app(StockLedgerService::class)->post($i->id, $this->branch->id, 'OPENING', $q, $c, null, null, '2026-09-01', $this->user->id);
    }

    private function damagePayload(Item $i, float $qty, array $over = []): array
    {
        return array_merge([
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'wastage_type' => 'Damage',
            'items' => [['item_id' => $i->id, 'qty' => $qty, 'cost_price' => 999]],
        ], $over);
    }

    // ---------- Damage ----------

    public function test_damage_deducts_stock_at_average_cost_not_form_cost(): void
    {
        $i = Item::create(['name' => 'Dmg1']);
        $this->seedStock($i, 10, 40);

        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 3))
            ->assertRedirect(route('inventory.damage-stocks.index'));

        $this->assertEquals(7, $this->qty($i));
        $d = DamageStock::latest('id')->first();
        $this->assertEquals('Damage', $d->wastage_type);
        $this->assertEquals(3, (float) $d->total_qty);
        $row = StockLedger::where('reference_type', DamageStock::class)->where('reference_id', $d->id)->first();
        $this->assertEquals('DAMAGE', $row->movement_type);
        $this->assertEquals(40, (float) $row->unit_cost, 'Loss must be valued at moving average, not the typed 999.');
        $this->assertEquals(120, (float) $row->value_out);
        $this->assertEquals('Damage', $row->reason_code);
    }

    public function test_damage_more_than_available_is_rejected_with_no_side_effects(): void
    {
        $i = Item::create(['name' => 'Dmg2']);
        $this->seedStock($i, 2, 10);

        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 5))
            ->assertSessionHasErrors('items');

        $this->assertEquals(2, $this->qty($i));
        $this->assertEquals(0, DamageStock::count());
    }

    public function test_damage_split_lines_of_same_item_are_summed_for_availability(): void
    {
        $i = Item::create(['name' => 'Dmg3']);
        $this->seedStock($i, 5, 10);
        $payload = $this->damagePayload($i, 3);
        $payload['items'][] = ['item_id' => $i->id, 'qty' => 3, 'cost_price' => 10];

        $this->post(route('inventory.damage-stocks.store'), $payload)->assertSessionHasErrors('items');
        $this->assertEquals(5, $this->qty($i));
    }

    public function test_damage_validation_errors(): void
    {
        $i = Item::create(['name' => 'Dmg4']);
        $this->seedStock($i, 5, 10);

        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 1, ['wastage_type' => 'Bogus']))
            ->assertSessionHasErrors('wastage_type');
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 0))
            ->assertSessionHasErrors('items.0.qty');
        $this->post(route('inventory.damage-stocks.store'), ['branch_id' => $this->branch->id, 'entry_date' => now()->toDateString(), 'wastage_type' => 'Theft'])
            ->assertSessionHasErrors('items');
        $this->assertEquals(5, $this->qty($i));
    }

    public function test_damage_posting_key_makes_double_submit_idempotent(): void
    {
        $i = Item::create(['name' => 'Dmg5']);
        $this->seedStock($i, 10, 10);
        $payload = $this->damagePayload($i, 2, ['posting_key' => 'KEY-DMG-1']);

        $this->post(route('inventory.damage-stocks.store'), $payload);
        $this->post(route('inventory.damage-stocks.store'), $payload)->assertRedirect(route('inventory.damage-stocks.index'));

        $this->assertEquals(1, DamageStock::count());
        $this->assertEquals(8, $this->qty($i), 'Second submit with the same key must not deduct again.');
    }

    public function test_damage_update_reverses_old_and_posts_new_quantity(): void
    {
        $i = Item::create(['name' => 'Dmg6']);
        $this->seedStock($i, 10, 20);
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 4));
        $d = DamageStock::latest('id')->first();
        $this->assertEquals(6, $this->qty($i));

        $this->put(route('inventory.damage-stocks.update', $d), $this->damagePayload($i, 1, ['wastage_type' => 'Theft']))
            ->assertRedirect(route('inventory.damage-stocks.index'));

        $this->assertEquals(9, $this->qty($i));
        $d->refresh();
        $this->assertEquals('Theft', $d->wastage_type);
        $this->assertEquals(1, (float) $d->total_qty);
        $this->assertEquals(1, $d->items()->count());
        $this->assertEquals(1, StockLedger::where('reference_type', DamageStock::class)->whereNotNull('reversal_of')->count());
    }

    public function test_damage_destroy_restores_stock_exactly(): void
    {
        $i = Item::create(['name' => 'Dmg7']);
        $this->seedStock($i, 10, 20);
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 10));
        $this->assertEquals(0, $this->qty($i));
        $d = DamageStock::latest('id')->first();

        $this->delete(route('inventory.damage-stocks.destroy', $d))->assertRedirect(route('inventory.damage-stocks.index'));

        $this->assertEquals(10, $this->qty($i));
        $this->assertNull(DamageStock::find($d->id));
    }

    public function test_damage_gst_is_calculated_on_line(): void
    {
        $tax = GstTax::create(['description' => 'GST 12%', 'percentage' => 12, 'status' => true]);
        $i = Item::create(['name' => 'Dmg8', 'gst_tax_id' => $tax->id]);
        $this->seedStock($i, 10, 100);
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 2, ['items' => [['item_id' => $i->id, 'qty' => 2, 'cost_price' => 100]]]));
        $line = DamageStock::latest('id')->first()->items->first();
        $this->assertEquals(12, (float) $line->gst_percent);
        $this->assertEquals(24, (float) $line->gst_tax_amount);
    }

    public function test_damage_requires_create_permission(): void
    {
        $i = Item::create(['name' => 'Dmg9']);
        $this->seedStock($i, 5, 10);
        $this->actingAs(User::factory()->create());

        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 1))->assertForbidden();
        $this->assertEquals(5, $this->qty($i));
        $this->assertEquals(0, DamageStock::count());
    }

    public function test_damage_index_filters_show_and_lookup_endpoints(): void
    {
        $i = Item::create(['name' => 'Dmg Finder', 'item_code' => 'DF1', 'ean_upc_code' => '890111', 'alias' => 'dfalias', 'cost_price' => 12, 'sell_price' => 20, 'mrp' => 25]);
        $this->seedStock($i, 10, 10);
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 1, ['remarks' => 'leaked bag']));
        $this->post(route('inventory.damage-stocks.store'), $this->damagePayload($i, 2, ['wastage_type' => 'Theft', 'message' => 'shelf']));
        $d = DamageStock::where('wastage_type', 'Damage')->first();

        $r = $this->get(route('inventory.damage-stocks.index', ['wastage_type' => 'Theft', 'branch_id' => $this->branch->id, 'date_from' => '2000-01-01', 'date_to' => '2100-01-01', 'q' => 'shelf']));
        $r->assertOk();
        $this->assertEquals(1, $r->viewData('totalEntries'));
        $this->assertEquals(2, (float) $r->viewData('totalQty'));

        $this->get(route('inventory.damage-stocks.index', ['q' => 'leaked']))->assertOk();

        $this->getJson(route('inventory.damage-stocks.show', $d))
            ->assertOk()->assertJsonPath('damage_number', $d->damage_number)->assertJsonPath('items.0.name', 'Dmg Finder');
        $this->get(route('inventory.damage-stocks.show', $d))->assertOk();
        $this->get(route('inventory.damage-stocks.create'))->assertOk();
        $this->get(route('inventory.damage-stocks.edit', $d))->assertOk();

        $this->getJson(route('inventory.damage-stocks.search-items', ['q' => '']))->assertOk()->assertExactJson([]);
        $this->getJson(route('inventory.damage-stocks.search-items', ['q' => 'Finder', 'branch_id' => $this->branch->id]))
            ->assertOk()->assertJsonPath('0.id', $i->id);
        $this->getJson(route('inventory.damage-stocks.item-by-code', ['code' => '']))->assertJsonPath('found', false);
        $this->getJson(route('inventory.damage-stocks.item-by-code', ['code' => '890111', 'branch_id' => $this->branch->id]))
            ->assertJsonPath('found', true)->assertJsonPath('item.id', $i->id);
        $this->getJson(route('inventory.damage-stocks.item-by-code', ['code' => 'dfalias']))->assertJsonPath('found', true);
        $this->getJson(route('inventory.damage-stocks.item-by-code', ['code' => 'nope']))->assertJsonPath('found', false);
    }

    // ---------- Opening stock ----------

    private function openingPayload(Item $i, float $qty, float $cost, array $over = []): array
    {
        return array_merge([
            'branch_id' => $this->branch->id,
            'entry_date' => now()->toDateString(),
            'items' => [['item_id' => $i->id, 'qty' => $qty, 'cost_price' => $cost, 'sell_price' => 150, 'mrp' => 180, 'exp_date' => '2027-03-01']],
        ], $over);
    }

    public function test_opening_stock_posts_qty_cost_expiry_and_updates_item_master(): void
    {
        $i = Item::create(['name' => 'Open1']);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 12, 70))
            ->assertRedirect(route('inventory.opening-stocks.index'));

        $this->assertEquals(12, $this->qty($i));
        $s = ItemStock::where('item_id', $i->id)->first();
        $this->assertEquals(70, (float) $s->cost_price);
        $os = OpeningStock::latest('id')->first();
        $this->assertEquals(12, (float) $os->total_qty);
        $row = StockLedger::where('reference_type', OpeningStock::class)->first();
        $this->assertEquals('OPENING', $row->movement_type);
        $this->assertEquals('2027-03-01', $row->exp_date->toDateString());
        $i->refresh();
        $this->assertEquals(70, (float) $i->cost_price);
        $this->assertEquals(150, (float) $i->sell_price);
        $this->assertEquals(180, (float) $i->mrp);
    }

    public function test_second_opening_blends_average_cost(): void
    {
        $i = Item::create(['name' => 'Open2']);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 10, 100));
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 10, 200));
        $this->assertEquals(20, $this->qty($i));
        $this->assertEquals(150, (float) ItemStock::where('item_id', $i->id)->value('cost_price'));
    }

    public function test_opening_scheme_percent_reduces_net_amount(): void
    {
        $i = Item::create(['name' => 'Open3']);
        $payload = $this->openingPayload($i, 10, 100);
        $payload['items'][0]['scheme_disc_percent'] = 10;
        $this->post(route('inventory.opening-stocks.store'), $payload);
        $line = OpeningStock::latest('id')->first()->items->first();
        $this->assertEquals(100, (float) $line->scheme_amount);
        $this->assertEquals(900, (float) $line->net_amount);
    }

    public function test_opening_validation_and_idempotent_posting_key(): void
    {
        $i = Item::create(['name' => 'Open4']);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 0, 10))->assertSessionHasErrors('items.0.qty');
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 1, -5))->assertSessionHasErrors('items.0.cost_price');
        $this->post(route('inventory.opening-stocks.store'), ['entry_date' => now()->toDateString()])->assertSessionHasErrors('branch_id');
        $this->assertEquals(0, OpeningStock::count());

        $p = $this->openingPayload($i, 5, 10, ['posting_key' => 'OPK1']);
        $this->post(route('inventory.opening-stocks.store'), $p);
        $this->post(route('inventory.opening-stocks.store'), $p)->assertRedirect(route('inventory.opening-stocks.index'));
        $this->assertEquals(1, OpeningStock::count());
        $this->assertEquals(5, $this->qty($i));
    }

    public function test_opening_update_replaces_posting_and_destroy_reverses(): void
    {
        $i = Item::create(['name' => 'Open5']);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 10, 50));
        $os = OpeningStock::latest('id')->first();

        $this->put(route('inventory.opening-stocks.update', $os), $this->openingPayload($i, 4, 50))
            ->assertRedirect(route('inventory.opening-stocks.index'));
        $this->assertEquals(4, $this->qty($i));
        $this->assertEquals(4, (float) $os->fresh()->total_qty);
        $this->assertEquals(1, $os->items()->count());

        $this->delete(route('inventory.opening-stocks.destroy', $os))->assertRedirect(route('inventory.opening-stocks.index'));
        $this->assertEquals(0, $this->qty($i));
        $this->assertNull(OpeningStock::find($os->id));
    }

    public function test_opening_destroy_blocked_when_stock_already_sold(): void
    {
        $i = Item::create(['name' => 'Open6']);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 5, 50));
        $os = OpeningStock::latest('id')->first();
        app(StockLedgerService::class)->post($i->id, $this->branch->id, 'SALE', -3, null, null, null, '2026-09-02', $this->user->id);

        $this->delete(route('inventory.opening-stocks.destroy', $os))->assertSessionHasErrors('stock');

        $this->assertNotNull(OpeningStock::find($os->id));
        $this->assertEquals(2, $this->qty($i));
    }

    public function test_opening_requires_permission_and_pages_and_lookups_work(): void
    {
        $i = Item::create(['name' => 'Open Find', 'item_code' => 'OF9', 'ean_upc_code' => '890222', 'alias' => 'ofalias', 'cost_price' => 5, 'sell_price' => 9, 'mrp' => 10]);
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 1, 5));
        $os = OpeningStock::latest('id')->first();

        $this->get(route('inventory.opening-stocks.index', ['search' => $os->entry_number, 'date_from' => '2000-01-01', 'date_to' => '2100-01-01', 'branch_id' => $this->branch->id]))
            ->assertOk()->assertSee($os->entry_number);
        $this->get(route('inventory.opening-stocks.create'))->assertOk();
        $this->get(route('inventory.opening-stocks.edit', $os))->assertOk();

        $this->getJson(route('inventory.opening-stocks.search-items', ['q' => '']))->assertExactJson([]);
        $this->getJson(route('inventory.opening-stocks.search-items', ['q' => 'Open Find', 'branch_id' => $this->branch->id]))
            ->assertOk()->assertJsonFragment(['id' => $i->id]);
        $this->getJson(route('inventory.opening-stocks.item-by-code', ['code' => '']))->assertJsonPath('found', false);
        $this->getJson(route('inventory.opening-stocks.item-by-code', ['code' => '890222']))->assertJsonPath('found', true);
        $this->getJson(route('inventory.opening-stocks.item-by-code', ['code' => 'zzz']))->assertJsonPath('found', false);

        $this->actingAs(User::factory()->create());
        $this->post(route('inventory.opening-stocks.store'), $this->openingPayload($i, 9, 5))->assertForbidden();
        $this->assertEquals(1, OpeningStock::count());
    }
}
