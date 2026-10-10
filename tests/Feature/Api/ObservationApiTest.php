<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers URBANPETS_MASTER_IMPLEMENTATION_PROMPT.md §6/§9 and
 * URBANPETS_DATABASE_AND_WORKFLOW.md §4/§5: blind submission (no inventory side effects,
 * no system qty leak), idempotent resubmission, manager review queue with variance,
 * the worked concurrency example, duplicate-approval protection, rollback on a failed
 * ledger post, and recount/reject not touching inventory.
 */
class ObservationApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Item $item;
    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Cat Food 500g',
            'item_code' => 'CF001',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 90,
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
            unitCost: 50,
            referenceType: null,
            referenceId: null,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
        );
    }

    public function test_submission_never_exposes_system_qty_and_never_touches_inventory(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);

        $itemStockBefore = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $qtyBefore = (float) $itemStockBefore->quantity;
        $ledgerCountBefore = StockLedger::count();

        $response = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '11111111-1111-1111-1111-111111111111',
        ]);

        $response->assertStatus(201);

        // Blind counting: system_qty_snapshot (or any system-quantity field) must be
        // entirely absent from the JSON given to the submitter.
        $response->assertJsonMissingPath('data.system_qty_snapshot');
        $response->assertDontSee('system_qty_snapshot', false);
        $this->assertArrayNotHasKey('system_qty_snapshot', $response->json('data'));

        // Zero inventory side effects.
        $itemStockAfter = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertSame($qtyBefore, (float) $itemStockAfter->quantity);
        $this->assertSame($ledgerCountBefore, StockLedger::count());

        // But the snapshot WAS captured server-side, for the manager's eventual review.
        $observation = InventoryObservation::first();
        $this->assertNotNull($observation);
        $this->assertEquals(20.0, (float) $observation->system_qty_snapshot);
        $this->assertSame('pending', $observation->status);
    }

    public function test_staff_history_also_never_exposes_system_qty(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);

        $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '22222222-2222-2222-2222-222222222222',
        ])->assertStatus(201);

        $mine = $this->getJson('/api/v1/observations/mine');
        $mine->assertOk();
        $mine->assertDontSee('system_qty_snapshot', false);

        foreach ($mine->json('data') as $row) {
            $this->assertArrayNotHasKey('system_qty_snapshot', $row);
        }
    }

    public function test_duplicate_submission_with_same_idempotency_key_returns_existing_row_not_a_new_one(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $payload = [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 5,
            'idempotency_key' => '33333333-3333-3333-3333-333333333333',
        ];

        $first = $this->postJson('/api/v1/observations', $payload);
        $first->assertStatus(201);
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/v1/observations', $payload);
        $second->assertStatus(200);
        $this->assertSame($firstId, $second->json('data.id'));

        $this->assertSame(1, InventoryObservation::count());
    }

    public function test_manager_pending_queue_includes_system_qty_and_variance(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);

        $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '44444444-4444-4444-4444-444444444444',
        ])->assertStatus(201);

        Sanctum::actingAs($this->manager, ['*']);

        $pending = $this->getJson('/api/v1/observations/pending');
        $pending->assertOk();

        $row = collect($pending->json('data'))->firstWhere('item_id', $this->item->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('system_qty_snapshot', $row);
        $this->assertEquals(20.0, (float) $row['system_qty_snapshot']);
        $this->assertEquals(-3.0, (float) $row['variance']); // 17 - 20
    }

    /**
     * The worked example from URBANPETS_DATABASE_AND_WORKFLOW.md §5:
     * system qty 20 -> staff counts 17 (snapshot=20) -> a sale drops current stock to 18
     * -> manager approves approved_qty=17 -> posted delta must be 17-18=-1, NOT 17-20=-3.
     */
    public function test_worked_example_approval_uses_current_stock_not_stale_snapshot(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '55555555-5555-5555-5555-555555555555',
        ])->assertStatus(201);

        $observationId = $submission->json('data.id');

        $observation = InventoryObservation::find($observationId);
        $this->assertEquals(20.0, (float) $observation->system_qty_snapshot);

        // A sale occurs before approval, dropping current stock from 20 to 18.
        app(StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'SALE',
            qtyDelta: -2,
            unitCost: null,
            referenceType: 'TestSale',
            referenceId: 1,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
        );

        $this->assertEquals(18.0, (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity);

        Sanctum::actingAs($this->manager, ['*']);
        $approve = $this->postJson("/api/v1/observations/{$observationId}/approve", [
            'approved_qty' => 17,
        ]);

        $approve->assertOk();
        $this->assertEquals(-1.0, (float) $approve->json('data.actual_adjustment_qty'));

        $finalStock = ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(17.0, (float) $finalStock->quantity); // 18 - 1 = 17, not 18 - 3 = 15

        $observation->refresh();
        $this->assertSame('approved', $observation->status);
        $this->assertNotNull($observation->stock_ledger_id);

        $ledgerRow = StockLedger::find($observation->stock_ledger_id);
        $this->assertSame('InventoryObservation', $ledgerRow->reference_type);
        $this->assertEquals($observation->id, $ledgerRow->reference_id);
        $this->assertEquals(1.0, (float) $ledgerRow->qty_out);
    }

    public function test_duplicate_approval_on_already_approved_row_returns_409_and_makes_no_further_ledger_change(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '66666666-6666-6666-6666-666666666666',
        ])->assertStatus(201);
        $observationId = $submission->json('data.id');

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$observationId}/approve", ['approved_qty' => 17])->assertOk();

        $ledgerCountAfterFirstApproval = StockLedger::count();

        $second = $this->postJson("/api/v1/observations/{$observationId}/approve", ['approved_qty' => 17]);
        $second->assertStatus(409);

        $this->assertSame($ledgerCountAfterFirstApproval, StockLedger::count());
    }

    public function test_approval_rolls_back_entirely_when_ledger_post_would_go_negative(): void
    {
        // No stock seeded at all -> current stock is 0. allow_negative_stock defaults false.
        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 0,
            'idempotency_key' => '77777777-7777-7777-7777-777777777777',
        ])->assertStatus(201);
        $observationId = $submission->json('data.id');

        $ledgerCountBefore = StockLedger::count();

        Sanctum::actingAs($this->manager, ['*']);
        // approved_qty negative forces delta = -10 - 0 = -10, which StockLedgerService
        // rejects as it would take stock below zero without allow_negative_stock.
        $response = $this->postJson("/api/v1/observations/{$observationId}/approve", [
            'approved_qty' => -10,
        ]);

        $response->assertStatus(422);

        $this->assertSame($ledgerCountBefore, StockLedger::count());

        $observation = InventoryObservation::find($observationId);
        $this->assertSame('pending', $observation->status);
        $this->assertNull($observation->stock_ledger_id);
        $this->assertNull($observation->approved_qty);
    }

    public function test_reject_does_not_change_inventory_and_is_terminal(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '88888888-8888-8888-8888-888888888888',
        ])->assertStatus(201);
        $observationId = $submission->json('data.id');

        $qtyBefore = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity;
        $ledgerCountBefore = StockLedger::count();

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$observationId}/reject", ['remarks' => 'Count looks wrong'])->assertOk();

        $qtyAfter = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity;
        $this->assertSame($qtyBefore, $qtyAfter);
        $this->assertSame($ledgerCountBefore, StockLedger::count());

        // Terminal: a second reject/approve/recount call returns 409, no further change.
        $second = $this->postJson("/api/v1/observations/{$observationId}/reject", ['remarks' => 'again']);
        $second->assertStatus(409);

        $thirdApprove = $this->postJson("/api/v1/observations/{$observationId}/approve", ['approved_qty' => 17]);
        $thirdApprove->assertStatus(409);

        $this->assertSame($ledgerCountBefore, StockLedger::count());
    }

    public function test_recount_preserves_parent_row_and_resubmission_links_to_it(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '99999999-9999-9999-9999-999999999999',
        ])->assertStatus(201);
        $parentId = $submission->json('data.id');

        $qtyBefore = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity;
        $ledgerCountBefore = StockLedger::count();

        Sanctum::actingAs($this->manager, ['*']);
        $recount = $this->postJson("/api/v1/observations/{$parentId}/request-recount", ['remarks' => 'Please recount']);
        $recount->assertOk();
        $this->assertSame('recount_requested', $recount->json('data.status'));

        // No inventory change from the recount request itself.
        $this->assertSame($qtyBefore, (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity);
        $this->assertSame($ledgerCountBefore, StockLedger::count());

        // Parent row is frozen/untouched apart from status/review fields — not overwritten.
        $parent = InventoryObservation::find($parentId);
        $this->assertSame('recount_requested', $parent->status);
        $this->assertEquals(17, (float) $parent->physical_qty);

        // Staff resubmits a NEW row referencing the parent.
        Sanctum::actingAs($this->staff, ['*']);
        $resubmit = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 19,
            'idempotency_key' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'parent_observation_id' => $parentId,
        ]);
        $resubmit->assertStatus(201);
        $this->assertSame($parentId, $resubmit->json('data.parent_observation_id'));
        $this->assertSame('pending', $resubmit->json('data.status'));

        // The old parent row is untouched by the new submission.
        $parent->refresh();
        $this->assertSame('recount_requested', $parent->status);
        $this->assertEquals(17, (float) $parent->physical_qty);

        $this->assertSame(2, InventoryObservation::count());
    }

    public function test_recount_resubmission_rejects_parent_not_in_recount_requested_state(): void
    {
        $this->seedSystemStock(20);
        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ])->assertStatus(201);
        $parentId = $submission->json('data.id'); // still 'pending', not recount_requested

        $resubmit = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 19,
            'idempotency_key' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'parent_observation_id' => $parentId,
        ]);

        $resubmit->assertStatus(422);
    }

    public function test_blind_count_submission_cross_branch_rejected(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch', 'status' => true]);

        Sanctum::actingAs($this->staff, ['*']);
        $response = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $otherBranch->id,
            'physical_qty' => 5,
            'idempotency_key' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        ]);

        $response->assertStatus(403);
    }

    public function test_expiry_check_requires_exp_date_when_no_batch_no(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->postJson('/api/v1/observations', [
            'type' => 'expiry_check',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'idempotency_key' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Business rule (2026-10-10): approving an expiry_check observation must NEVER
     * change stock quantity — that stays exclusively a stock_count-approval concern.
     */
    public function test_expiry_check_approval_never_changes_stock_or_posts_ledger(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'expiry_check',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-EXP-1',
            'exp_date_observed' => now()->addDays(10)->toDateString(),
            'idempotency_key' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
        ])->assertStatus(201);
        $observationId = $submission->json('data.id');

        $qtyBefore = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity;
        $ledgerCountBefore = StockLedger::count();

        Sanctum::actingAs($this->manager, ['*']);
        // Even if a caller tries to smuggle approved_qty in, it must be ignored —
        // the expiry_check branch never reads it.
        $approve = $this->postJson("/api/v1/observations/{$observationId}/approve", [
            'approved_qty' => 999,
            'remarks' => 'Expiry date confirmed',
        ]);
        $approve->assertOk();
        $this->assertSame('approved', $approve->json('data.status'));
        $this->assertNull($approve->json('data.approved_qty'));
        $this->assertNull($approve->json('data.actual_adjustment_qty'));

        $this->assertSame($qtyBefore, (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->first()->quantity);
        $this->assertSame($ledgerCountBefore, StockLedger::count());

        $observation = InventoryObservation::find($observationId);
        $this->assertNull($observation->stock_ledger_id);
    }

    public function test_expiry_check_approval_is_terminal_and_rejects_second_call(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'expiry_check',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'exp_date_observed' => now()->addDays(5)->toDateString(),
            'idempotency_key' => '11111111-2222-3333-4444-555555555555',
        ])->assertStatus(201);
        $observationId = $submission->json('data.id');

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$observationId}/approve", ['remarks' => 'ok'])->assertOk();
        $this->postJson("/api/v1/observations/{$observationId}/approve", ['remarks' => 'again'])->assertStatus(409);
    }

    /**
     * Business rule (2026-10-10): a recount resubmission preserves the parent's
     * batch_no by default; diverging from it without an explicit, authorized
     * batch_correction flag is rejected, not silently applied.
     */
    public function test_recount_preserves_batch_no_by_default_and_rejects_silent_change(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-ORIGINAL',
            'physical_qty' => 17,
            'idempotency_key' => '22222222-2222-2222-2222-222222222222',
        ])->assertStatus(201);
        $parentId = $submission->json('data.id');

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$parentId}/request-recount", ['remarks' => 'recount please'])->assertOk();

        Sanctum::actingAs($this->staff, ['*']);

        // Attempting to change batch_no WITHOUT the explicit flag is rejected.
        $silentChange = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-DIFFERENT',
            'physical_qty' => 19,
            'idempotency_key' => '33333333-3333-3333-3333-333333333333',
            'parent_observation_id' => $parentId,
        ]);
        $silentChange->assertStatus(422);
        $this->assertSame(1, InventoryObservation::count());

        // Omitting batch_no entirely on the recount defaults to the parent's batch_no.
        $defaulted = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 19,
            'idempotency_key' => '44444444-4444-4444-4444-444444444444',
            'parent_observation_id' => $parentId,
        ])->assertStatus(201);
        $this->assertSame('BATCH-ORIGINAL', $defaulted->json('data.batch_no'));
        $this->assertFalse($defaulted->json('data.batch_correction_applied'));
    }

    public function test_recount_batch_correction_requires_reason_and_is_audited(): void
    {
        $this->seedSystemStock(20);

        Sanctum::actingAs($this->staff, ['*']);
        $submission = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-ORIGINAL',
            'physical_qty' => 17,
            'idempotency_key' => '55555555-5555-5555-5555-555555555555',
        ])->assertStatus(201);
        $parentId = $submission->json('data.id');

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$parentId}/request-recount", ['remarks' => 'recount please'])->assertOk();

        Sanctum::actingAs($this->staff, ['*']);

        // batch_correction=true without a reason is rejected (required_if).
        $missingReason = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-CORRECTED',
            'batch_correction' => true,
            'physical_qty' => 19,
            'idempotency_key' => '66666666-6666-6666-6666-666666666666',
            'parent_observation_id' => $parentId,
        ]);
        $missingReason->assertStatus(422);

        // Explicit, authorized, reasoned correction succeeds and is recorded.
        $corrected = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'batch_no' => 'BATCH-CORRECTED',
            'batch_correction' => true,
            'batch_correction_reason' => 'Original batch label was misread during the first count.',
            'physical_qty' => 19,
            'idempotency_key' => '77777777-7777-7777-7777-777777777777',
            'parent_observation_id' => $parentId,
        ]);
        $corrected->assertStatus(201);
        $this->assertSame('BATCH-CORRECTED', $corrected->json('data.batch_no'));
        $this->assertTrue($corrected->json('data.batch_correction_applied'));
        $this->assertSame('BATCH-ORIGINAL', $corrected->json('data.original_batch_no'));
        $this->assertSame('Original batch label was misread during the first count.', $corrected->json('data.batch_correction_reason'));

        $observation = InventoryObservation::find($corrected->json('data.id'));
        $this->assertSame($this->staff->id, $observation->submitted_by);
        $this->assertTrue((bool) $observation->batch_correction_applied);
    }
}
