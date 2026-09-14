<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3 acceptance tests for the Stock Transfer module — dispatch/receive through the
 * real HTTP routes (not just the service layer), confirming: source-only effect on
 * dispatch, dispatch-time cost conservation on receipt (never the destination's own
 * average), short-receipt handling, cancellation reversal, and the same-state/
 * different-state GST split.
 */
class StockTransferFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private StockLedgerService $stockLedger;
    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockLedger = app(StockLedgerService::class);
        $this->branchA = Branch::create(['name' => 'Transfer Test Branch A', 'state' => 'Gujarat']);
        $this->branchB = Branch::create(['name' => 'Transfer Test Branch B', 'state' => 'Gujarat']);
        $user = User::factory()->create();
        $user->assignRole('Owner'); // Phase 4: dispatch/receive/cancel are now permission-gated.
        $this->actingAs($user);
    }

    private function stockOf(Item $item, Branch $branch): ?ItemStock
    {
        return ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
    }

    private function seedOpening(Item $item, Branch $branch, float $qty, float $unitCost): void
    {
        $this->stockLedger->post(
            itemId: $item->id,
            branchId: $branch->id,
            movementType: 'OPENING',
            qtyDelta: $qty,
            unitCost: $unitCost,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );
    }

    public function test_dispatch_reduces_source_only_and_receipt_conserves_dispatch_cost(): void
    {
        $item = Item::create(['name' => 'Transfer Item']);
        $this->seedOpening($item, $this->branchA, 10, 120); // value 1200

        $dispatchResponse = $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $this->branchB->id,
            'items' => [
                ['item_id' => $item->id, 'qty' => 4],
            ],
        ]);
        $dispatchResponse->assertRedirect(route('inventory.stock-transfers.index'));

        $transfer = StockTransfer::latest('id')->first();
        $this->assertEquals('Dispatched', $transfer->status);
        $this->assertEquals(6, (float) $this->stockOf($item, $this->branchA)->quantity);
        $this->assertNull($this->stockOf($item, $this->branchB), 'Destination must not receive stock until the transfer is received.');

        $line = $transfer->items->first();
        $this->assertEquals(120, (float) $line->unit_cost, 'Dispatch must snapshot the source average cost.');

        // Destination has NEVER stocked this item — full receipt must value it at the
        // dispatch snapshot (120), not 0.
        $receiveResponse = $this->post(route('inventory.stock-transfers.receive', $transfer), [
            'items' => [
                ['id' => $line->id, 'received_qty' => 4],
            ],
        ]);
        $receiveResponse->assertRedirect(route('inventory.stock-transfers.pending-receipt'));

        $transfer->refresh();
        $this->assertEquals('Received', $transfer->status);

        $destStock = $this->stockOf($item, $this->branchB);
        $this->assertEquals(4, (float) $destStock->quantity);
        $this->assertEquals(120, (float) $destStock->cost_price, 'Destination must receive stock at the dispatch cost, not its own (zero) average.');
    }

    public function test_short_receipt_only_posts_the_received_quantity(): void
    {
        $item = Item::create(['name' => 'Short Receipt Item']);
        $this->seedOpening($item, $this->branchA, 10, 50);

        $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $this->branchB->id,
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ]);
        $transfer = StockTransfer::latest('id')->first();
        $line = $transfer->items->first();

        // Only 3 of the 5 dispatched units actually arrive.
        $this->post(route('inventory.stock-transfers.receive', $transfer), [
            'items' => [['id' => $line->id, 'received_qty' => 3]],
        ]);

        $line->refresh();
        $this->assertEquals(5, (float) $line->qty, 'Dispatched qty must remain visible.');
        $this->assertEquals(3, (float) $line->received_qty, 'Received qty must be recorded separately.');
        $this->assertEquals(3, (float) $this->stockOf($item, $this->branchB)->quantity, 'Destination must only gain the actually-received quantity.');
        $this->assertEquals(5, (float) $this->stockOf($item, $this->branchA)->quantity, 'Source already lost all 5 at dispatch, regardless of what arrives.');
    }

    public function test_cancel_before_receipt_fully_restores_source_stock(): void
    {
        $item = Item::create(['name' => 'Cancelled Transfer Item']);
        $this->seedOpening($item, $this->branchA, 10, 80);

        $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $this->branchB->id,
            'items' => [['item_id' => $item->id, 'qty' => 6]],
        ]);
        $transfer = StockTransfer::latest('id')->first();
        $this->assertEquals(4, (float) $this->stockOf($item, $this->branchA)->quantity);

        $cancelResponse = $this->post(route('inventory.stock-transfers.cancel', $transfer));
        $cancelResponse->assertRedirect(route('inventory.stock-transfers.index'));

        $transfer->refresh();
        $this->assertEquals('Cancelled', $transfer->status);
        $this->assertEquals(10, (float) $this->stockOf($item, $this->branchA)->quantity, 'Cancelling must fully restore the source branch.');
        $this->assertNull($this->stockOf($item, $this->branchB));
    }

    public function test_same_state_transfer_has_zero_tax_and_different_state_splits_correctly(): void
    {
        $gstTax = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $itemLocal = Item::create(['name' => 'Same State Item', 'gst_tax_id' => $gstTax->id]);
        $itemInterstate = Item::create(['name' => 'Cross State Item', 'gst_tax_id' => $gstTax->id]);

        $this->seedOpening($itemLocal, $this->branchA, 10, 100);
        $this->seedOpening($itemInterstate, $this->branchA, 10, 100);

        // Same state (branchA and branchB are both 'Gujarat') -> no tax.
        $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $this->branchB->id,
            'items' => [['item_id' => $itemLocal->id, 'qty' => 2]],
        ]);
        $localTransfer = StockTransfer::latest('id')->first();
        $localLine = $localTransfer->items->first();
        $this->assertEquals(0, (float) $localLine->gst_tax_amount);
        $this->assertEquals(0, (float) $localLine->igst_amount);

        // Different state -> distinct-person supply, IGST split, using carrying cost as base.
        $branchC = Branch::create(['name' => 'Transfer Test Branch C', 'state' => 'Maharashtra']);
        $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $branchC->id,
            'items' => [['item_id' => $itemInterstate->id, 'qty' => 2]],
        ]);
        $interstateTransfer = StockTransfer::latest('id')->first();
        $interstateLine = $interstateTransfer->items->first();

        // 2 units @ cost 100 = taxable 200 @ 18% = 36, all IGST.
        $this->assertEquals(200, (float) $interstateLine->taxable_value);
        $this->assertEquals(36, (float) $interstateLine->gst_tax_amount);
        $this->assertEquals(36, (float) $interstateLine->igst_amount);
        $this->assertEquals(0, (float) $interstateLine->cgst_amount);
        $this->assertEquals(0, (float) $interstateLine->sgst_amount);
    }
}
