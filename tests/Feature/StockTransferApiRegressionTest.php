<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the Stock Transfer item-search API endpoints.
 *
 * These tests act as a canary: if a SQL column referenced in itemList()
 * or getItemByCode() stops existing (e.g. purchase_rate → landing_cost),
 * the test will fail BEFORE the bug reaches manual QA.
 *
 * Covered endpoints:
 *   GET  inventory.stock-transfers.item-list     (itemList)
 *   GET  inventory.stock-transfers.item-by-code  (getItemByCode)
 */
class StockTransferApiRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Item $item;
    private StockLedgerService $stockLedger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockLedger = app(StockLedgerService::class);
        $this->branch = Branch::create(['name' => 'API Test Branch', 'state' => 'Gujarat']);

        $this->item = Item::create([
            'name'       => 'Regression Test Item',
            'item_code'  => 'RTI-001',
            'cost_price' => 150.00,
            'status'     => true,
        ]);

        // Give the item positive stock in our branch
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'OPENING',
            qtyDelta: 20,
            unitCost: 150.00,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );

        $user = User::factory()->create();
        $user->assignRole('Owner');
        $this->actingAs($user);
    }

    // =========================================================================
    // itemList endpoint
    // =========================================================================

    /** @test */
    public function item_list_returns_200_without_sql_errors(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
            'search'    => 'Regression',
        ]));

        $response->assertOk()
                 ->assertJsonStructure(['items']);
    }

    /** @test */
    public function item_list_returns_matching_item_with_unit_cost(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
            'search'    => 'Regression',
        ]));

        $response->assertOk();
        $items = $response->json('items');

        $this->assertNotEmpty($items, 'itemList must return the seeded item when searched by name.');

        $found = collect($items)->firstWhere('id', $this->item->id);
        $this->assertNotNull($found, 'itemList must include the seeded item.');

        $this->assertArrayHasKey('unit_cost', $found, 'Every itemList row must carry unit_cost.');
        $this->assertGreaterThan(0, $found['unit_cost'], 'unit_cost must be > 0 when cost_price is set.');
        $this->assertArrayHasKey('available_qty', $found, 'Every itemList row must carry available_qty.');
        $this->assertGreaterThan(0, $found['available_qty'], 'available_qty must reflect seeded stock.');
    }

    /** @test */
    public function item_list_search_by_code_works(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
            'code'      => 'RTI-001',
        ]));

        $response->assertOk();
        $found = collect($response->json('items'))->firstWhere('id', $this->item->id);
        $this->assertNotNull($found, 'itemList must find item when filtering by item_code.');
    }

    /** @test */
    public function item_list_empty_filter_returns_hint_not_crash(): void
    {
        // With no search params the endpoint must return an empty list + hint,
        // NOT a 500 SQL error.
        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
                 ->assertJsonPath('items', []);
    }

    /** @test */
    public function item_list_excludes_zero_stock_items(): void
    {
        $noStockItem = Item::create([
            'name'      => 'Zero Stock Item',
            'item_code' => 'ZSI-001',
            'status'    => true,
        ]);

        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
            'search'    => 'Zero Stock',
        ]));

        $response->assertOk();
        $ids = collect($response->json('items'))->pluck('id')->toArray();
        $this->assertNotContains($noStockItem->id, $ids, 'itemList must NOT return items with 0 stock.');
    }

    // =========================================================================
    // getItemByCode endpoint
    // =========================================================================

    /** @test */
    public function get_item_by_code_returns_found_true_with_unit_cost(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-by-code', [
            'item_id'   => $this->item->id,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
                 ->assertJsonPath('found', true);

        $item = $response->json('item');
        $this->assertNotNull($item);
        $this->assertArrayHasKey('unit_cost', $item, 'getItemByCode must return unit_cost.');
        $this->assertArrayHasKey('available_qty', $item);
        $this->assertGreaterThan(0, $item['available_qty']);
    }

    /** @test */
    public function get_item_by_code_lookup_via_item_code_string(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-by-code', [
            'code'      => 'RTI-001',
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
                 ->assertJsonPath('found', true)
                 ->assertJsonPath('item.id', $this->item->id);
    }

    /** @test */
    public function get_item_by_code_returns_not_found_for_unknown_code(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-by-code', [
            'code'      => 'DOES-NOT-EXIST',
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
                 ->assertJsonPath('found', false);
    }

    /** @test */
    public function get_item_by_code_returns_not_found_when_stock_is_zero(): void
    {
        // Drain stock to 0
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'CORRECTION',
            qtyDelta: -20,
            unitCost: 150.00,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-02',
        );

        $response = $this->getJson(route('inventory.stock-transfers.item-by-code', [
            'item_id'   => $this->item->id,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
                 ->assertJsonPath('found', false);
    }

    /** @test */
    public function get_item_by_code_no_params_returns_not_found(): void
    {
        $response = $this->getJson(route('inventory.stock-transfers.item-by-code'));

        $response->assertOk()
                 ->assertJsonPath('found', false);
    }

    // =========================================================================
    // SQL column canary — guards against non-existent column regressions
    // =========================================================================

    /** @test */
    public function item_list_does_not_reference_nonexistent_purchase_rate_column(): void
    {
        // This is the exact scenario that caused the SQLSTATE[42S22] crash.
        // Any DB exception (column not found) would produce a 500, not 200.
        $response = $this->getJson(route('inventory.stock-transfers.item-list', [
            'branch_id' => $this->branch->id,
            'search'    => 'Regression',
        ]));

        $response->assertStatus(200);
    }
}
