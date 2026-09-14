<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Services\Accounting\DocumentNumberingService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Encodes the foundation spec's Golden Test Dataset (Urban_Pets_POS_Foundation_Signoff_Report.pdf
 * §23): opening -> purchase -> sell -> return -> damage -> purchase return, asserting quantity,
 * moving-weighted-average cost, and ledger running balance at every step. This is the acceptance
 * gate for the Phase 1 foundation engines — it must pass before any Phase 2 retrofitting.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) because this app's tests run against the real
 * dev database (urban_pos) with no dedicated testing DB configured — every assertion here happens
 * inside a transaction that is rolled back at the end, so no real data is ever touched or wiped.
 *
 * The Transfer step from the spec's dataset is skipped: Stock Transfer is an explicitly
 * out-of-scope Phase 3 module (see plan), so there is nothing to exercise yet.
 */
class GoldenFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private StockLedgerService $stockLedger;
    private TaxEngine $taxEngine;
    private Branch $branch;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockLedger = app(StockLedgerService::class);
        $this->taxEngine = app(TaxEngine::class);

        $this->branch = Branch::create(['name' => 'Golden Test Branch']);
        $gstTax = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $this->item = Item::create([
            'name' => 'Golden Test Product A',
            'gst_tax_id' => $gstTax->id,
            'tax_inclusive' => false,
        ]);
    }

    private function currentStock(): ItemStock
    {
        return ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->firstOrFail();
    }

    public function test_golden_dataset_reconciles_at_every_step(): void
    {
        // Step 2 — Opening: 10 units @ Rs 100
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'OPENING',
            qtyDelta: 10,
            unitCost: 100,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );
        $stock = $this->currentStock();
        $this->assertEquals(10, (float) $stock->quantity);
        $this->assertEquals(100, (float) $stock->cost_price);

        // Step 3 — Purchase: 20 units @ landed Rs 130 -> weighted avg (10*100 + 20*130)/30 = 120
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 20,
            unitCost: 130,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-03',
        );
        $stock = $this->currentStock();
        $this->assertEquals(30, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);

        // Step 4 — Sell 3 units: COGS must be the current average (120), average unchanged
        $saleLedger = $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALE',
            qtyDelta: -3,
            unitCost: null,
            referenceType: 'GoldenTestSale',
            referenceId: 1,
            documentDate: '2026-09-04',
        );
        $stock = $this->currentStock();
        $this->assertEquals(27, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);
        $this->assertEquals(120, (float) $saleLedger->unit_cost, 'COGS released on sale must equal the average cost at time of sale.');

        // Step 5 — Return 1 resalable unit at the ORIGINAL sale's cost (120) -> qty 28, avg still 120
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALE_RETURN',
            qtyDelta: 1,
            unitCost: (float) $saleLedger->unit_cost,
            referenceType: 'GoldenTestSale',
            referenceId: 1,
            documentDate: '2026-09-06',
        );
        $stock = $this->currentStock();
        $this->assertEquals(28, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);

        // Step 6 — Damage 2 units at carrying cost -> qty 26, avg unchanged
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'DAMAGE',
            qtyDelta: -2,
            unitCost: null,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-07',
        );
        $stock = $this->currentStock();
        $this->assertEquals(26, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);

        // Step 7 — Purchase return 1 valid unit at current average -> qty 25
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'PURCHASE_RETURN',
            qtyDelta: -1,
            unitCost: null,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-08',
        );
        $stock = $this->currentStock();
        $this->assertEquals(25, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);

        // Step 9 — MRP/selling price change must NOT touch quantity or stock value
        $this->item->update(['mrp' => 1100, 'sell_price' => 999]);
        $stock = $this->currentStock();
        $this->assertEquals(25, (float) $stock->quantity);
        $this->assertEquals(120, (float) $stock->cost_price);

        // Step 12 — Reports must reconcile: ledger net movement == item_stocks running balance
        $netQty = StockLedger::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)
            ->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as net')->value('net');
        $this->assertEquals(25, round((float) $netQty, 3));

        $latestLedgerRow = StockLedger::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)
            ->latest('id')->first();
        $this->assertEquals((float) $stock->quantity, (float) $latestLedgerRow->running_balance_qty);
        $this->assertEquals((float) $stock->quantity * (float) $stock->cost_price, (float) $latestLedgerRow->running_balance_value);
    }

    public function test_reversal_restores_stock_without_deleting_history(): void
    {
        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'OPENING',
            qtyDelta: 10,
            unitCost: 100,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );

        $this->stockLedger->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALE',
            qtyDelta: -4,
            unitCost: null,
            referenceType: 'GoldenTestReversal',
            referenceId: 42,
            documentDate: '2026-09-05',
        );
        $this->assertEquals(6, (float) $this->currentStock()->quantity);

        $this->stockLedger->reverseByReference('GoldenTestReversal', 42);
        $this->assertEquals(10, (float) $this->currentStock()->quantity, 'Reversing the sale must restore the pre-sale quantity.');

        $rowCount = StockLedger::where('reference_type', 'GoldenTestReversal')->where('reference_id', 42)->count();
        $this->assertEquals(2, $rowCount, 'Reversal must ADD an offsetting row, never delete the original.');

        // Calling reverse a second time must be a no-op (idempotent), not double-restore stock.
        $this->stockLedger->reverseByReference('GoldenTestReversal', 42);
        $this->assertEquals(10, (float) $this->currentStock()->quantity);
    }

    public function test_tax_engine_matches_spec_worked_examples(): void
    {
        // §10.4: tax-exclusive Rs 1,000 @ 18% -> taxable 1000, GST 180
        $exclusiveItem = $this->item;
        $result = $this->taxEngine->calculate(qty: 1, price: 1000, item: $exclusiveItem);
        $this->assertEquals(1000, $result['taxable_value']);
        $this->assertEquals(180, $result['gst_tax_amount']);
        $this->assertEquals(1180, $result['net_amount']);

        // §10.4: tax-inclusive Rs 1,180 @ 18% -> taxable value extracted to 1000, GST 180
        $inclusiveItem = Item::create([
            'name' => 'Golden Test Product B (inclusive)',
            'gst_tax_id' => $this->item->gst_tax_id,
            'tax_inclusive' => true,
        ]);
        $result = $this->taxEngine->calculate(qty: 1, price: 1180, item: $inclusiveItem);
        $this->assertEquals(1000, $result['taxable_value']);
        $this->assertEquals(180, $result['gst_tax_amount']);
        $this->assertEquals(1180, $result['net_amount']);

        // Sales discount example: MRP 1200, selling 1000, discount 100 -> taxable 900, GST on 900 not 1200
        $result = $this->taxEngine->calculate(qty: 1, price: 1000, item: $exclusiveItem, discAmount: 100);
        $this->assertEquals(900, $result['taxable_value']);
        $this->assertEquals(round(900 * 0.18, 2), $result['gst_tax_amount']);
    }

    public function test_document_numbering_service_is_sequential_per_series(): void
    {
        $numbering = app(DocumentNumberingService::class);

        $first = $numbering->next('golden-test-series');
        $second = $numbering->next('golden-test-series');
        $otherSeries = $numbering->next('golden-test-series-other');

        $this->assertEquals($first + 1, $second, 'Same series must increment sequentially.');
        $this->assertEquals(1, $otherSeries, 'A different series must have its own independent counter.');
    }
}
