<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminLTE desktop screens for Stock & Expiry Verification (business requirement,
 * 2026-10-10 client instruction: this module must be reachable from the existing
 * Inventory menu, not only the Flutter app). Covers the Manager review queue,
 * History & Audit, and that a mobile-submitted observation approved through the WEB
 * route posts the exact same ledger entry the API-side approval already proves (no
 * duplicate/bypass logic — StockVerificationController delegates directly to
 * ApiObservationController).
 */
class StockVerificationAdminLteTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Item $item;
    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->branch = Branch::create(['name' => 'Main Branch', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Dog Shampoo 200ml',
            'item_code' => 'DS001',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 180,
            'status' => true,
        ]);

        $this->staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');
    }

    private function seedSystemStock(float $qty): void
    {
        app(StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'OPENING',
            qtyDelta: $qty,
            unitCost: 100,
            referenceType: 'Test',
            referenceId: 0,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
        );
    }

    /** Submits via the real mobile API path, exactly as the Flutter app would. */
    private function submitViaApi(float $physicalQty, string $idempotencyKey): int
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->staff, ['*']);

        $response = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => $physicalQty,
            'idempotency_key' => $idempotencyKey,
        ]);
        $response->assertStatus(201);

        return $response->json('data.id');
    }

    public function test_manager_can_see_the_review_queue_menu_screen(): void
    {
        $this->seedSystemStock(20);
        $this->submitViaApi(17, '10101010-1010-1010-1010-101010101010');

        $response = $this->actingAs($this->manager)->get('/inventory/stock-verification/review-queue');

        $response->assertOk();
        $response->assertSee('Dog Shampoo 200ml');
    }

    public function test_staff_cannot_open_the_review_queue(): void
    {
        $this->actingAs($this->staff)->get('/inventory/stock-verification/review-queue')->assertStatus(403);
    }

    public function test_history_and_audit_screen_lists_all_statuses_for_the_branch(): void
    {
        $this->seedSystemStock(20);
        $id = $this->submitViaApi(17, '20202020-2020-2020-2020-202020202020');

        \Laravel\Sanctum\Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$id}/reject", ['remarks' => 'test reject'])->assertOk();

        $response = $this->actingAs($this->manager)->get('/inventory/stock-verification/history');

        $response->assertOk();
        $response->assertSee('Dog Shampoo 200ml');
        $response->assertSee('Rejected');
    }

    /**
     * The core integration proof: a mobile submission appears in the web review
     * queue, and approving it via the WEB route posts the exact same ledger
     * correction the API-side approval test already proves (20 -> 17 = -3), through
     * the shared StockLedgerService — not a second, duplicated code path.
     */
    public function test_mobile_submission_approved_via_web_screen_posts_the_same_ledger_correction_as_the_api(): void
    {
        $this->seedSystemStock(20);
        $id = $this->submitViaApi(17, '30303030-3030-3030-3030-303030303030');

        $ledgerCountBefore = StockLedger::count();

        $this->actingAs($this->manager)
            ->post("/inventory/stock-verification/{$id}/approve", ['approved_qty' => 17])
            ->assertRedirect(route('inventory.stock-verification.review-queue'));

        $observation = InventoryObservation::find($id);
        $this->assertSame('approved', $observation->status);
        $this->assertEquals(-3, (float) $observation->actual_adjustment_qty);
        $this->assertNotNull($observation->stock_ledger_id);

        $this->assertSame($ledgerCountBefore + 1, StockLedger::count());
        $ledger = StockLedger::find($observation->stock_ledger_id);
        $this->assertSame('CORRECTION', $ledger->movement_type);
        $this->assertEquals(-3, (float) $ledger->qty_in - (float) $ledger->qty_out);

        $stock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(17, (float) $stock->quantity);
    }

    public function test_expiry_check_approved_via_web_screen_does_not_change_stock(): void
    {
        $this->seedSystemStock(20);

        \Laravel\Sanctum\Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'expiry_check',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'exp_date_observed' => now()->addDays(7)->toDateString(),
            'idempotency_key' => '40404040-4040-4040-4040-404040404040',
        ])->assertStatus(201);
        $id = $submission->json('data.id');

        $ledgerCountBefore = StockLedger::count();

        $this->actingAs($this->manager)
            ->post("/inventory/stock-verification/{$id}/approve", ['remarks' => 'confirmed via web'])
            ->assertRedirect(route('inventory.stock-verification.review-queue'));

        $this->assertSame($ledgerCountBefore, StockLedger::count());
        $stock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(20, (float) $stock->quantity);
    }

    public function test_observation_detail_screen_shows_recount_history(): void
    {
        $this->seedSystemStock(20);
        $parentId = $this->submitViaApi(17, '50505050-5050-5050-5050-505050505050');

        \Laravel\Sanctum\Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$parentId}/request-recount", ['remarks' => 'please recount'])->assertOk();

        \Laravel\Sanctum\Sanctum::actingAs($this->staff, ['*']);
        $resubmit = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 18,
            'idempotency_key' => '60606060-6060-6060-6060-606060606060',
            'parent_observation_id' => $parentId,
        ])->assertStatus(201);
        $recountId = $resubmit->json('data.id');

        $response = $this->actingAs($this->manager)->get("/inventory/stock-verification/{$recountId}");

        $response->assertOk();
        $response->assertSee('Recount History');
        $response->assertSee('Parent attempt #'.$parentId);
    }
}
