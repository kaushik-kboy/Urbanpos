<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockCountTask;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 10: GET /v1/dashboard/summary. Covers:
 *  - Staff gets a personal-only summary that NEVER exposes any system-quantity/variance
 *    figure anywhere in the payload (blind counting applies to dashboards too, per
 *    master prompt §6).
 *  - Manager/Owner get a branch-wide summary including expiry buckets and review-queue
 *    counts (the variance-relevant figures).
 *  - Branch isolation: a Manager scoped to branch A never sees branch B's numbers.
 *  - A basic query-count sanity check to catch an obvious N+1 regression.
 */
class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private Item $item;
    private User $staff;
    private User $manager;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['name' => 'Branch A', 'status' => true]);
        $this->branchB = Branch::create(['name' => 'Branch B', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Cat Food 500g',
            'item_code' => 'CF001',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 90,
            'status' => true,
        ]);

        $this->staff = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->manager->assignRole('Manager');

        $this->owner = User::factory()->create(['branch_id' => null]);
        $this->owner->assignRole('Owner');
    }

    private function seedStock(Branch $branch, float $qty, ?string $batchNo = null, ?string $expDate = null): void
    {
        app(StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $branch->id,
            movementType: 'OPENING',
            qtyDelta: $qty,
            unitCost: 50,
            referenceType: null,
            referenceId: null,
            documentDate: now()->toDateString(),
            userId: $this->manager->id,
            batchNo: $batchNo,
            expDate: $expDate,
        );
    }

    public function test_staff_summary_never_exposes_system_quantity_or_variance(): void
    {
        $this->seedStock($this->branchA, 20, 'BATCH-1', now()->addDays(10)->toDateString());

        Sanctum::actingAs($this->staff, ['*']);

        $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branchA->id,
            'physical_qty' => 17,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);

        $response = $this->getJson('/api/v1/dashboard/summary');
        $response->assertOk();

        $response->assertJsonPath('data.role', 'staff');
        $response->assertJsonMissingPath('data.system_qty_snapshot');
        $response->assertJsonMissingPath('data.variance');
        $response->assertJsonMissingPath('data.expiry_buckets');
        $response->assertJsonMissingPath('data.pending_review_count');

        // Explicit raw-body check, per the task's requirement — not just key-absence in
        // the decoded array, but the literal substring must never appear anywhere.
        $response->assertDontSee('system_qty_snapshot', false);
        $response->assertDontSee('variance', false);
        $response->assertDontSee('remaining_qty', false);

        $this->assertArrayNotHasKey('system_qty_snapshot', $response->json('data'));
        $this->assertSame(1, $response->json('data.observations.pending'));
    }

    public function test_manager_summary_includes_expiry_buckets_and_review_counts(): void
    {
        // A batch landing in the "critical" (0-30 day) bucket.
        $this->seedStock($this->branchA, 20, 'BATCH-SOON', now()->addDays(10)->toDateString());

        StockCountTask::create([
            'branch_id' => $this->branchA->id,
            'title' => 'Count Rack 1',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->staff, ['*']);
        $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branchA->id,
            'physical_qty' => 17,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/dashboard/summary');
        $response->assertOk();

        $response->assertJsonPath('data.role', 'manager');
        $response->assertJsonPath('data.pending_review_count', 1);
        $response->assertJsonPath('data.tasks.pending', 1);
        $this->assertIsArray($response->json('data.expiry_buckets'));
        $this->assertNotEmpty($response->json('data.expiry_buckets'));

        $critical = collect($response->json('data.expiry_buckets'))
            ->firstWhere('severity', 'critical');
        $this->assertNotNull($critical);
        $this->assertSame(1, $critical['count']);
        $this->assertSame('BATCH-SOON', $critical['items'][0]['batch_no']);
        $this->assertArrayNotHasKey('system_qty_snapshot', $critical['items'][0]);
    }

    public function test_manager_scoped_to_branch_a_does_not_see_branch_b_numbers(): void
    {
        StockCountTask::create([
            'branch_id' => $this->branchA->id,
            'title' => 'Task A',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $staffB = User::factory()->create(['branch_id' => $this->branchB->id]);
        $staffB->assignRole('Staff');

        StockCountTask::create([
            'branch_id' => $this->branchB->id,
            'title' => 'Task B',
            'assigned_to' => $staffB->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        // Manager is branch_id = Branch A.
        Sanctum::actingAs($this->manager, ['*']);
        $managerResponse = $this->getJson('/api/v1/dashboard/summary');
        $managerResponse->assertOk();
        $managerResponse->assertJsonPath('data.tasks.pending', 1);
        $managerResponse->assertJsonPath('data.branch_id', $this->branchA->id);

        // Manager explicitly requesting branch B (not their own) is forbidden.
        $forbidden = $this->getJson("/api/v1/dashboard/summary?branch_id={$this->branchB->id}");
        $forbidden->assertStatus(403);

        // Owner (all-branch access) sees both tasks combined.
        Sanctum::actingAs($this->owner, ['*']);
        $ownerResponse = $this->getJson('/api/v1/dashboard/summary');
        $ownerResponse->assertOk();
        $ownerResponse->assertJsonPath('data.role', 'owner');
        $ownerResponse->assertJsonPath('data.tasks.pending', 2);
    }

    public function test_dashboard_query_count_stays_within_a_reasonable_bound(): void
    {
        // Three items each with a batch, to make sure the per-item expiry-bucket lookup
        // doesn't fan out unboundedly. This is a sanity ceiling, not a strict N+1 proof —
        // BatchStockService itself issues a few queries per item by design (see
        // DashboardController::buildExpiryBuckets doc block).
        $items = [$this->item];
        for ($i = 0; $i < 2; $i++) {
            $items[] = Item::create([
                'name' => "Extra Item $i",
                'item_code' => "EX00$i",
                'cost_price' => 10,
                'sell_price' => 20,
                'mrp' => 25,
                'status' => true,
            ]);
        }

        foreach ($items as $i => $extraItem) {
            app(StockLedgerService::class)->post(
                itemId: $extraItem->id,
                branchId: $this->branchA->id,
                movementType: 'OPENING',
                qtyDelta: 10,
                unitCost: 10,
                referenceType: null,
                referenceId: null,
                documentDate: now()->toDateString(),
                userId: $this->manager->id,
                batchNo: "B$i",
                expDate: now()->addDays(15 + $i)->toDateString(),
            );
        }

        Sanctum::actingAs($this->manager, ['*']);

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/dashboard/summary');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();

        $this->assertLessThan(
            60,
            $queryCount,
            "Dashboard summary issued {$queryCount} queries for 3 items — looks like an N+1 regression."
        );
    }
}
