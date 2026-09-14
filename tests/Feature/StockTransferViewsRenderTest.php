<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Confirms the actual Blade views render (200, no view-compile errors) — the other
 * Phase 3 tests only exercise POST endpoints, never GET, so a broken view could pass
 * everything else and still 500 for a real user.
 */
class StockTransferViewsRenderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->assignRole('Owner'); // Phase 4: store/create routes are now permission-gated.
        $this->actingAs($user);
    }

    public function test_index_create_and_pending_receipt_views_render(): void
    {
        $this->get(route('inventory.stock-transfers.index'))->assertOk();
        $this->get(route('inventory.stock-transfers.create'))->assertOk();
        $this->get(route('inventory.stock-transfers.pending-receipt'))->assertOk();
    }

    public function test_show_and_receive_views_render(): void
    {
        $branchA = Branch::create(['name' => 'View Test Branch A']);
        $branchB = Branch::create(['name' => 'View Test Branch B']);
        $item = Item::create(['name' => 'View Test Item']);

        app(StockLedgerService::class)->post(
            itemId: $item->id,
            branchId: $branchA->id,
            movementType: 'OPENING',
            qtyDelta: 5,
            unitCost: 20,
            referenceType: null,
            referenceId: null,
            documentDate: '2026-09-01',
        );

        $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => '2026-09-13',
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'items' => [['item_id' => $item->id, 'qty' => 1]],
        ]);
        $transfer = StockTransfer::latest('id')->first();

        $this->get(route('inventory.stock-transfers.show', $transfer))->assertOk();
        $this->get(route('inventory.stock-transfers.receive-form', $transfer))->assertOk();
    }
}
