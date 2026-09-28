<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMorePricingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $br;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->br = Branch::create(['name' => 'MP Br', 'state' => 'Gujarat']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    private function qty(Item $i): float
    {
        return (float) (ItemStock::where('item_id', $i->id)->where('branch_id', $this->br->id)->value('quantity') ?? 0);
    }

    private function avg(Item $i): float
    {
        return (float) ItemStock::where('item_id', $i->id)->where('branch_id', $this->br->id)->value('cost_price');
    }

    private function seedStock(Item $i, float $q, float $c): void
    {
        app(StockLedgerService::class)->post($i->id, $this->br->id, 'OPENING', $q, $c, null, null, '2026-09-01', $this->owner->id);
    }

    // ---------- Repack ----------

    public function test_repack_conserves_value_across_packs(): void
    {
        $bulk = Item::create(['name' => 'Bulk 20kg']);
        $p1 = Item::create(['name' => 'Pack 1kg']);
        $p2 = Item::create(['name' => 'Pack 5kg']);
        $this->seedStock($bulk, 10, 100); // value 1000

        $this->post(route('inventory.repack.process'), [
            'branch_id' => $this->br->id,
            'bulk_item_id' => $bulk->id,
            'bulk_qty_taken' => 4, // value released 400
            'packs' => [['item_id' => $p1->id, 'qty' => 10], ['item_id' => $p2->id, 'qty' => 30]],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(6, $this->qty($bulk));
        $this->assertEquals(10, $this->qty($p1));
        $this->assertEquals(30, $this->qty($p2));
        $this->assertEquals(10, $this->avg($p1), 'Per-unit pack cost = 400 / 40 units.');
        $this->assertEquals(10, $this->avg($p2));
        $this->assertEquals(400.0, round($this->qty($p1) * $this->avg($p1) + $this->qty($p2) * $this->avg($p2), 2));
        $this->assertEquals(3, StockLedger::where('movement_type', 'REPACK')->count());
    }

    public function test_repack_more_than_bulk_stock_is_blocked_atomically(): void
    {
        $bulk = Item::create(['name' => 'Bulk B']);
        $pack = Item::create(['name' => 'Pack B']);
        $this->seedStock($bulk, 3, 10);

        $this->post(route('inventory.repack.process'), [
            'branch_id' => $this->br->id, 'bulk_item_id' => $bulk->id, 'bulk_qty_taken' => 5,
            'packs' => [['item_id' => $pack->id, 'qty' => 5]],
        ])->assertSessionHasErrors('stock');

        $this->assertEquals(3, $this->qty($bulk));
        $this->assertEquals(0, $this->qty($pack));
        $this->assertEquals(1, StockLedger::count());
    }

    public function test_repack_validation_and_permission(): void
    {
        $bulk = Item::create(['name' => 'Bulk V']);
        $this->post(route('inventory.repack.process'), ['branch_id' => $this->br->id, 'bulk_item_id' => $bulk->id, 'bulk_qty_taken' => 0, 'packs' => []])
            ->assertSessionHasErrors(['bulk_qty_taken', 'packs']);

        $this->actingAs(User::factory()->create()); // no role
        $this->post(route('inventory.repack.process'), ['branch_id' => $this->br->id, 'bulk_item_id' => $bulk->id, 'bulk_qty_taken' => 1, 'packs' => [['item_id' => $bulk->id, 'qty' => 1]]])
            ->assertForbidden();
    }

    // ---------- Kit ----------

    public function test_kit_preparation_consumes_components_and_values_kit_from_them(): void
    {
        $c1 = Item::create(['name' => 'Comp 1']);
        $c2 = Item::create(['name' => 'Comp 2']);
        $kit = Item::create(['name' => 'Gift Kit']);
        $this->seedStock($c1, 20, 10);
        $this->seedStock($c2, 20, 30);

        $this->post(route('inventory.kit-preparation.process'), [
            'branch_id' => $this->br->id, 'kit_item_id' => $kit->id, 'kit_qty' => 3,
            'components' => [['item_id' => $c1->id, 'qty_per_kit' => 2], ['item_id' => $c2->id, 'qty_per_kit' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(14, $this->qty($c1));
        $this->assertEquals(17, $this->qty($c2));
        $this->assertEquals(3, $this->qty($kit));
        $this->assertEquals(50, $this->avg($kit), '2*10 + 1*30 per kit.');
    }

    public function test_kit_preparation_blocked_when_any_component_short_and_rolls_back(): void
    {
        $c1 = Item::create(['name' => 'Comp A']);
        $c2 = Item::create(['name' => 'Comp B']);
        $kit = Item::create(['name' => 'Kit X']);
        $this->seedStock($c1, 20, 10);
        $this->seedStock($c2, 2, 10);

        $this->post(route('inventory.kit-preparation.process'), [
            'branch_id' => $this->br->id, 'kit_item_id' => $kit->id, 'kit_qty' => 5,
            'components' => [['item_id' => $c1->id, 'qty_per_kit' => 1], ['item_id' => $c2->id, 'qty_per_kit' => 1]],
        ])->assertSessionHasErrors('stock');

        $this->assertEquals(20, $this->qty($c1), 'First component deduction must roll back.');
        $this->assertEquals(2, $this->qty($c2));
        $this->assertEquals(0, $this->qty($kit));
    }

    public function test_kit_unpack_returns_components_conserving_value_and_blocks_over_unpack(): void
    {
        $c1 = Item::create(['name' => 'UComp 1']);
        $c2 = Item::create(['name' => 'UComp 2']);
        $kit = Item::create(['name' => 'UKit']);
        $this->seedStock($kit, 4, 90); // value 360

        $payload = [
            'branch_id' => $this->br->id, 'kit_item_id' => $kit->id, 'unpack_qty' => 2,
            'components' => [['item_id' => $c1->id, 'qty_per_kit' => 2], ['item_id' => $c2->id, 'qty_per_kit' => 1]],
        ];
        $this->post(route('inventory.kit-unpack.process'), $payload)->assertSessionHasNoErrors();

        $this->assertEquals(2, $this->qty($kit));
        $this->assertEquals(4, $this->qty($c1));
        $this->assertEquals(2, $this->qty($c2));
        $this->assertEquals(30, $this->avg($c1), '180 released / 6 units.');
        $this->assertEquals(180.0, round($this->qty($c1) * $this->avg($c1) + $this->qty($c2) * $this->avg($c2), 2));

        $payload['unpack_qty'] = 3;
        $this->post(route('inventory.kit-unpack.process'), $payload)->assertSessionHasErrors('stock');
        $this->assertEquals(2, $this->qty($kit));
        $this->assertEquals(4, $this->qty($c1));
    }

    public function test_kit_validation_and_serial_number_endpoint_and_more_pages(): void
    {
        $i = Item::create(['name' => 'Serial Thing']);
        $this->post(route('inventory.kit-preparation.process'), ['branch_id' => $this->br->id])->assertSessionHasErrors(['kit_item_id', 'kit_qty', 'components']);
        $this->post(route('inventory.kit-unpack.process'), ['branch_id' => $this->br->id])->assertSessionHasErrors(['kit_item_id', 'unpack_qty', 'components']);

        $this->post(route('inventory.change-serial-no.process'), ['item_id' => $i->id, 'serials' => []])->assertSessionHasErrors('serials');
        $this->post(route('inventory.change-serial-no.process'), ['item_id' => $i->id, 'serials' => [['old_serial' => 'A']]])->assertSessionHasErrors('serials.0.new_serial');
        $this->post(route('inventory.change-serial-no.process'), ['item_id' => $i->id, 'serials' => [['old_serial' => 'A', 'new_serial' => 'B']]])
            ->assertSessionHas('status');

        foreach (['repack', 'kit-preparation', 'kit-unpack', 'price-drop', 'shelf-talker', 'change-serial-no'] as $page) {
            $this->get(route("inventory.{$page}.index", ['branch_id' => $this->br->id]))->assertOk();
        }
        $this->get(route('inventory.repack.index'))->assertOk();
    }

    // ---------- Change selling ----------

    public function test_change_selling_updates_branch_prices_and_master_only_when_missing(): void
    {
        $bare = Item::create(['name' => 'CS bare']);
        $priced = Item::create(['name' => 'CS priced', 'sell_price' => 100, 'mrp' => 120]);

        $this->post(route('inventory.change-selling.update'), [
            'branch_id' => $this->br->id,
            'prices' => [
                $bare->id => ['sell_price' => 55, 'mrp' => 60],
                $priced->id => ['sell_price' => 130, 'mrp' => 150],
            ],
        ])->assertSessionHas('status');

        $sBare = ItemStock::where('item_id', $bare->id)->where('branch_id', $this->br->id)->first();
        $this->assertEquals(55, (float) $sBare->sell_price);
        $this->assertEquals(0, (float) $sBare->quantity);
        $this->assertEquals(130, (float) ItemStock::where('item_id', $priced->id)->value('sell_price'));
        $this->assertEquals(55, (float) $bare->fresh()->sell_price, 'Master filled when it had no price.');
        $this->assertEquals(100, (float) $priced->fresh()->sell_price, 'Existing master price must not be overwritten.');
        $this->assertEquals(0, StockLedger::count(), 'Price change must not move stock.');
    }

    public function test_change_selling_validation_permission_and_index_filters(): void
    {
        $brand = Brand::create(['name' => 'BrandCS']);
        $a = Item::create(['name' => 'Alpha CS', 'brand_id' => $brand->id, 'ean_upc_code' => '890999']);
        Item::create(['name' => 'Beta CS']);

        $this->post(route('inventory.change-selling.update'), ['branch_id' => $this->br->id, 'prices' => [$a->id => ['sell_price' => -1, 'mrp' => 5]]])
            ->assertSessionHasErrors('prices.' . $a->id . '.sell_price');
        $this->post(route('inventory.change-selling.update'), ['branch_id' => $this->br->id])->assertSessionHasErrors('prices');

        $r = $this->get(route('inventory.change-selling.index', ['branch_id' => $this->br->id, 'brand_id' => $brand->id, 'search' => 'Alpha']));
        $r->assertOk();
        $this->assertEquals(1, $r->viewData('items')->total());
        $this->assertEquals(1, $this->get(route('inventory.change-selling.index', ['branch_id' => $this->br->id, 'search' => '890999']))->viewData('items')->total());
        $this->get(route('inventory.change-selling.index'))->assertOk();

        $mgr = User::factory()->create();
        $mgr->assignRole('Manager');
        $this->actingAs($mgr);
        $this->post(route('inventory.change-selling.update'), ['branch_id' => $this->br->id, 'prices' => [$a->id => ['sell_price' => 9, 'mrp' => 9]]])->assertForbidden();
        $this->assertNull(ItemStock::where('item_id', $a->id)->first());
    }

    // ---------- Price fixing ----------

    private function applyPayload(array $over = []): array
    {
        return array_merge([
            'branch_id' => $this->br->id,
            'base_field' => 'cost_price',
            'target_field' => 'sell_price',
            'calc_type' => 'percentage',
            'operation' => 'markup',
            'value' => 10,
            'apply_scope' => 'all',
        ], $over);
    }

    public function test_price_fixing_markup_percentage_with_rounding_modes(): void
    {
        $i = Item::create(['name' => 'PF1', 'cost_price' => 103]);

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['round_off' => 'none']))->assertSessionHas('status');
        $this->assertEquals(113.3, (float) ItemStock::where('item_id', $i->id)->value('sell_price'));

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['round_off' => 'near_1']));
        $this->assertEquals(113, (float) ItemStock::where('item_id', $i->id)->value('sell_price'));

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['round_off' => 'near_10']));
        $this->assertEquals(110, (float) ItemStock::where('item_id', $i->id)->value('sell_price'));

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['round_off' => 'round_up']));
        $this->assertEquals(114, (float) ItemStock::where('item_id', $i->id)->value('sell_price'));
        $this->assertEquals(0, StockLedger::count());
    }

    public function test_price_fixing_markdown_amount_never_goes_negative_and_targets_mrp(): void
    {
        $i = Item::create(['name' => 'PF2', 'cost_price' => 40, 'landing_cost' => 0]);

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload([
            'calc_type' => 'amount', 'operation' => 'markdown', 'value' => 500, 'target_field' => 'mrp', 'base_field' => 'landing_cost',
        ]));
        $stock = ItemStock::where('item_id', $i->id)->first();
        $this->assertEquals(0, (float) $stock->mrp, 'Markdown beyond base is floored at 0.');

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['calc_type' => 'amount', 'value' => 5, 'target_field' => 'mrp']));
        $this->assertEquals(45, (float) $stock->fresh()->mrp);
    }

    public function test_price_fixing_skips_items_without_a_cost_base_and_respects_scope(): void
    {
        $noCost = Item::create(['name' => 'PF nocost']);
        $sel = Item::create(['name' => 'PF selected', 'cost_price' => 100]);
        $other = Item::create(['name' => 'PF other', 'cost_price' => 100]);

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['apply_scope' => 'selected', 'selected_ids' => [$sel->id, $noCost->id]]))
            ->assertSessionHas('status', 'Price Fixing rule applied successfully to 1 items in the selected branch.');

        $this->assertEquals(110, (float) ItemStock::where('item_id', $sel->id)->value('sell_price'));
        $this->assertNull(ItemStock::where('item_id', $other->id)->first());
        $this->assertNull(ItemStock::where('item_id', $noCost->id)->first());
    }

    public function test_price_fixing_filtered_scope_by_brand_category_and_search(): void
    {
        $brand = Brand::create(['name' => 'PFBrand']);
        $in = Item::create(['name' => 'Filtered In', 'cost_price' => 200, 'brand_id' => $brand->id]);
        $out = Item::create(['name' => 'Filtered Out', 'cost_price' => 200]);

        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['apply_scope' => 'filtered', 'brand_id' => $brand->id, 'search' => 'Filtered']));
        $this->assertEquals(220, (float) ItemStock::where('item_id', $in->id)->value('sell_price'));
        $this->assertNull(ItemStock::where('item_id', $out->id)->first());
    }

    public function test_price_fixing_validation_permission_and_pages(): void
    {
        $i = Item::create(['name' => 'PF3', 'cost_price' => 10]);
        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['base_field' => 'bogus']))->assertSessionHasErrors('base_field');
        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload(['operation' => 'x', 'value' => -1]))->assertSessionHasErrors(['operation', 'value']);

        foreach (['index', 'markup-markdown', 'price-level', 'price-level-items'] as $p) {
            $this->get(route('inventory.price-fixing.' . $p, ['branch_id' => $this->br->id, 'search' => 'PF', 'brand_id' => '']))->assertOk();
        }
        $this->get(route('inventory.price-fixing.index'))->assertOk();

        $mgr = User::factory()->create();
        $mgr->assignRole('Manager');
        $this->actingAs($mgr);
        $this->post(route('inventory.price-fixing.apply'), $this->applyPayload())->assertForbidden();
        $this->assertNull(ItemStock::where('item_id', $i->id)->first());
    }

    // ---------- Barcode printing ----------

    public function test_barcode_printing_expands_labels_per_qty_and_validates(): void
    {
        $r = $this->post(route('inventory.barcode-printing.print'), [
            'items' => [
                ['name' => 'Label A', 'barcode' => '111', 'mrp' => 10, 'sell_price' => 9, 'qty' => 3],
                ['name' => 'Label B', 'barcode' => '222', 'qty' => 2],
            ],
            'label_size' => 'compact',
        ]);
        $r->assertOk();
        $labels = $r->viewData('labels');
        $this->assertCount(5, $labels);
        $this->assertEquals(0.0, $labels[3]['mrp']);
        $this->assertEquals('compact', $r->viewData('labelSize'));

        $this->post(route('inventory.barcode-printing.print'), ['items' => [['name' => 'X', 'barcode' => '1', 'qty' => 0]]])->assertSessionHasErrors('items.0.qty');
        $this->post(route('inventory.barcode-printing.print'), [])->assertSessionHasErrors('items');
    }

    public function test_barcode_printing_index_search_and_invoice_prefill(): void
    {
        $withEan = Item::create(['name' => 'Bar Ean', 'ean_upc_code' => '890123', 'mrp' => 50, 'sell_price' => 45]);
        $noEan = Item::create(['name' => 'Bar NoEan', 'alias' => 'baralias']);

        $this->getJson(route('inventory.barcode-printing.search-items', ['q' => '']))->assertExactJson([]);
        $res = $this->getJson(route('inventory.barcode-printing.search-items', ['q' => 'baralias']))->assertOk()->json();
        $this->assertSame(str_pad((string) $noEan->id, 8, '0', STR_PAD_LEFT), $res[0]['barcode']);
        $this->assertSame('890123', $this->getJson(route('inventory.barcode-printing.search-items', ['q' => '890123']))->json('0.barcode'));

        $this->get(route('inventory.barcode-printing.index'))->assertOk();
        $r = $this->get(route('inventory.barcode-printing.index', ['branch_id' => $this->br->id, 'purchase_invoice_id' => 99999]));
        $r->assertOk();
        $this->assertCount(0, $r->viewData('selectedItems'));
    }

    public function test_barcode_page_search_and_print_queue(): void
    {
        $brand = Brand::create(['name' => 'BCBrand']);
        $i = Item::create(['name' => 'BC Item', 'item_code' => 'BC1', 'ean_upc_code' => '890888', 'brand_id' => $brand->id, 'status' => true, 'sell_price' => 20, 'mrp' => 25]);
        Item::create(['name' => 'BC Inactive', 'item_code' => 'BC2', 'status' => false]);
        $this->seedStock($i, 7, 10);

        $this->get(route('inventory.barcode.index'))->assertOk();
        $r = $this->get(route('inventory.barcode.index', ['search' => 'BC', 'branch_id' => $this->br->id]));
        $r->assertOk();
        $items = $r->viewData('items');
        $this->assertCount(1, $items, 'Inactive items are excluded.');
        $this->assertEquals(7, (float) $items->first()->stock_qty);
        $this->assertEquals('890888', $items->first()->barcode);
        $this->assertCount(1, $this->get(route('inventory.barcode.index', ['brand_id' => $brand->id]))->viewData('items'));

        $this->getJson(route('inventory.barcode.search'))->assertExactJson([]);
        $this->getJson(route('inventory.barcode.search', ['q' => 'BC1']))->assertJsonCount(1)->assertJsonPath('0.brand', 'BCBrand');
        $this->getJson(route('inventory.barcode.search', ['brand_id' => $brand->id]))->assertJsonCount(1);

        $this->get(route('inventory.barcode.print'))->assertRedirect(route('inventory.barcode.index'));
        $p = $this->get(route('inventory.barcode.print', ['items' => [['id' => $i->id, 'qty' => 3]]]));
        $p->assertOk();
        $this->assertCount(3, $p->viewData('labels'));
        $p2 = $this->get(route('inventory.barcode.print', ['items' => [['id' => $i->id, 'qty' => -4]]]));
        $this->assertCount(1, $p2->viewData('labels'), 'Qty is floored at 1.');
    }
}
