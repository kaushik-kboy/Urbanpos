<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 2 acceptance tests: repack/kit operations must conserve total inventory value
 * (never create value from nothing, never destroy it) exactly like an internal transfer,
 * and the Tax Engine's CGST/SGST/IGST split must match the spec's Local/Interstate rules.
 */
class Phase2FoundationTest extends TestCase
{
    use DatabaseTransactions;

    private StockLedgerService $stockLedger;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->stockLedger = app(StockLedgerService::class);
        $this->branch = Branch::create(['name' => 'Phase2 Test Branch']);
        $user = User::factory()->create();
        $user->assignRole('Owner');
        $this->actingAs($user);
    }

    private function stockOf(Item $item): ItemStock
    {
        return ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->firstOrFail();
    }

    private function seedOpening(Item $item, float $qty, float $unitCost): void
    {
        $this->stockLedger->post(
            itemId: $item->id,
            branchId: $this->branch->id,
            movementType: 'OPENING',
            qtyDelta: $qty,
            unitCost: $unitCost,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );
    }

    public function test_repack_conserves_total_inventory_value(): void
    {
        $bulkItem = Item::create(['name' => 'Bulk Case']);
        $packItem = Item::create(['name' => 'Brand New Pack']); // never stocked before

        $this->seedOpening($bulkItem, 10, 120); // value 1200

        $response = $this->post(route('inventory.repack.process'), [
            'branch_id' => $this->branch->id,
            'bulk_item_id' => $bulkItem->id,
            'bulk_qty_taken' => 2,
            'packs' => [
                ['item_id' => $packItem->id, 'qty' => 24],
            ],
        ]);
        $response->assertRedirect();

        $bulkStock = $this->stockOf($bulkItem);
        $packStock = $this->stockOf($packItem);

        $this->assertEquals(8, (float) $bulkStock->quantity);
        $this->assertEquals(24, (float) $packStock->quantity);

        // The brand-new pack item must NOT end up valued at 0 — it must have picked up
        // the value released from the bulk item (2 units * 120 = 240, spread over 24 units = 10/unit).
        $this->assertEquals(10, (float) $packStock->cost_price);

        $totalValueAfter = ((float) $bulkStock->quantity * (float) $bulkStock->cost_price)
            + ((float) $packStock->quantity * (float) $packStock->cost_price);
        $this->assertEqualsWithDelta(1200, $totalValueAfter, 0.02, 'Repack must conserve total inventory value.');
    }

    public function test_kit_assembly_and_disassembly_round_trip_conserves_value(): void
    {
        $componentA = Item::create(['name' => 'Component A']);
        $componentB = Item::create(['name' => 'Component B']);
        $kitItem = Item::create(['name' => 'Assembled Kit']);

        $this->seedOpening($componentA, 10, 50); // value 500
        $this->seedOpening($componentB, 10, 30); // value 300

        $totalBefore = 500 + 300;

        $prepResponse = $this->post(route('inventory.kit-preparation.process'), [
            'branch_id' => $this->branch->id,
            'kit_item_id' => $kitItem->id,
            'kit_qty' => 2,
            'components' => [
                ['item_id' => $componentA->id, 'qty_per_kit' => 2],
                ['item_id' => $componentB->id, 'qty_per_kit' => 3],
            ],
        ]);
        $prepResponse->assertRedirect();

        $kitStock = $this->stockOf($kitItem);
        $this->assertEquals(2, (float) $kitStock->quantity);
        // Component A: 4 units consumed @ 50 = 200; Component B: 6 units consumed @ 30 = 180; total 380 / 2 kits = 190/kit.
        $this->assertEquals(190, (float) $kitStock->cost_price);

        $valueAfterAssembly = ((float) $kitStock->quantity * (float) $kitStock->cost_price)
            + ((float) $this->stockOf($componentA)->quantity * (float) $this->stockOf($componentA)->cost_price)
            + ((float) $this->stockOf($componentB)->quantity * (float) $this->stockOf($componentB)->cost_price);
        $this->assertEqualsWithDelta($totalBefore, $valueAfterAssembly, 0.02, 'Kit assembly must conserve total inventory value.');

        $unpackResponse = $this->post(route('inventory.kit-unpack.process'), [
            'branch_id' => $this->branch->id,
            'kit_item_id' => $kitItem->id,
            'unpack_qty' => 1,
            'components' => [
                ['item_id' => $componentA->id, 'qty_per_kit' => 2],
                ['item_id' => $componentB->id, 'qty_per_kit' => 3],
            ],
        ]);
        $unpackResponse->assertRedirect();

        $kitStock->refresh();
        $this->assertEquals(1, (float) $kitStock->quantity);

        $valueAfterUnpack = ((float) $kitStock->quantity * (float) $kitStock->cost_price)
            + ((float) $this->stockOf($componentA)->quantity * (float) $this->stockOf($componentA)->cost_price)
            + ((float) $this->stockOf($componentB)->quantity * (float) $this->stockOf($componentB)->cost_price);
        $this->assertEqualsWithDelta($totalBefore, $valueAfterUnpack, 0.02, 'Kit disassembly must conserve total inventory value.');
    }

    public function test_tax_engine_gst_split_local_vs_interstate(): void
    {
        $taxEngine = app(TaxEngine::class);
        $gstTax = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $item = Item::create(['name' => 'Split Test Item', 'gst_tax_id' => $gstTax->id]);

        // Local: Rs 1000 @ 18% -> GST 180, split 90/90.
        $local = $taxEngine->calculate(qty: 1, price: 1000, item: $item, isInterstate: false);
        $this->assertEquals(90, $local['cgst_amount']);
        $this->assertEquals(90, $local['sgst_amount']);
        $this->assertEquals(0, $local['igst_amount']);
        $this->assertEquals($local['gst_tax_amount'], $local['cgst_amount'] + $local['sgst_amount']);

        // Interstate: same base -> 100% IGST, nothing in CGST/SGST.
        $interstate = $taxEngine->calculate(qty: 1, price: 1000, item: $item, isInterstate: true);
        $this->assertEquals(0, $interstate['cgst_amount']);
        $this->assertEquals(0, $interstate['sgst_amount']);
        $this->assertEquals(180, $interstate['igst_amount']);

        // Odd-cent GST amount (0.15) must split as 0.08/0.07, not silently rounding both
        // sides up/down to something that no longer sums back to the original amount.
        $gstTax15 = GstTax::create(['description' => 'GST 15%', 'percentage' => 15, 'status' => true]);
        $oddItem = Item::create(['name' => 'Odd Cent Item', 'gst_tax_id' => $gstTax15->id]);
        $odd = $taxEngine->calculate(qty: 1, price: 1, item: $oddItem, isInterstate: false);
        $this->assertEquals(0.15, $odd['gst_tax_amount']);
        $this->assertEquals(0.08, $odd['cgst_amount']);
        $this->assertEquals(0.07, $odd['sgst_amount']);
        $this->assertEquals($odd['gst_tax_amount'], round($odd['cgst_amount'] + $odd['sgst_amount'], 2));
    }
}
