<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the new GET /v1/observations Manager/Owner cross-staff audit endpoint
 * (History & Audit screen backing API) — distinct from mine() (own-only) and
 * pending() (pending-only). See URBANPETS_API_DOCUMENTATION.md for the contract.
 */
class ObservationIndexApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private Item $item;
    private User $staffA;
    private User $staffB;
    private User $manager;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['name' => 'Branch A', 'status' => true]);
        $this->branchB = Branch::create(['name' => 'Branch B', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Dog Food 1kg',
            'item_code' => 'DF001',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 90,
            'status' => true,
        ]);

        $this->staffA = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->staffA->assignRole('Staff');

        $this->staffB = User::factory()->create(['branch_id' => $this->branchB->id]);
        $this->staffB->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->manager->assignRole('Manager');

        $this->owner = User::factory()->create(['branch_id' => null]);
        $this->owner->assignRole('Owner');
    }

    private function submit(User $staff, Branch $branch, string $idempotencyKey, array $overrides = []): InventoryObservation
    {
        Sanctum::actingAs($staff, ['*']);

        $response = $this->postJson('/api/v1/observations', array_merge([
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $branch->id,
            'physical_qty' => 10,
            'idempotency_key' => $idempotencyKey,
        ], $overrides));

        $response->assertStatus(201);

        return InventoryObservation::find($response->json('data.id'));
    }

    public function test_staff_gets_403_on_audit_endpoint(): void
    {
        Sanctum::actingAs($this->staffA, ['*']);

        $this->getJson('/api/v1/observations')->assertStatus(403);
    }

    public function test_manager_sees_only_their_branch_by_default(): void
    {
        $this->submit($this->staffA, $this->branchA, '11111111-1111-1111-1111-111111111111');
        $this->submit($this->staffB, $this->branchB, '22222222-2222-2222-2222-222222222222');

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/observations');
        $response->assertOk();

        $branchIds = collect($response->json('data'))->pluck('branch_id')->unique()->all();
        $this->assertEquals([$this->branchA->id], $branchIds);
    }

    public function test_owner_sees_all_branches(): void
    {
        $this->submit($this->staffA, $this->branchA, '33333333-3333-3333-3333-333333333333');
        $this->submit($this->staffB, $this->branchB, '44444444-4444-4444-4444-444444444444');

        Sanctum::actingAs($this->owner, ['*']);
        $response = $this->getJson('/api/v1/observations');
        $response->assertOk();

        $branchIds = collect($response->json('data'))->pluck('branch_id')->unique()->sort()->values()->all();
        $this->assertEquals([$this->branchA->id, $this->branchB->id], $branchIds);
    }

    public function test_manager_cannot_access_a_branch_they_do_not_belong_to_via_explicit_param(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->getJson('/api/v1/observations?branch_id='.$this->branchB->id);
        $response->assertStatus(403);
    }

    public function test_owner_can_filter_to_an_explicit_branch(): void
    {
        $this->submit($this->staffA, $this->branchA, '55555555-5555-5555-5555-555555555555');
        $this->submit($this->staffB, $this->branchB, '66666666-6666-6666-6666-666666666666');

        Sanctum::actingAs($this->owner, ['*']);
        $response = $this->getJson('/api/v1/observations?branch_id='.$this->branchB->id);
        $response->assertOk();

        $branchIds = collect($response->json('data'))->pluck('branch_id')->unique()->all();
        $this->assertEquals([$this->branchB->id], $branchIds);
    }

    public function test_type_filter_works(): void
    {
        $this->submit($this->staffA, $this->branchA, '77777777-7777-7777-7777-777777777777', ['type' => 'stock_count']);
        $this->submit($this->staffA, $this->branchA, '88888888-8888-8888-8888-888888888888', [
            'type' => 'expiry_check',
            'exp_date_observed' => now()->addDays(10)->toDateString(),
            'physical_qty' => null,
        ]);

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/observations?type=expiry_check');
        $response->assertOk();

        $types = collect($response->json('data'))->pluck('type')->unique()->all();
        $this->assertEquals(['expiry_check'], $types);
    }

    public function test_status_filter_works(): void
    {
        $pending = $this->submit($this->staffA, $this->branchA, '99999999-9999-9999-9999-999999999999');
        $toApprove = $this->submit($this->staffA, $this->branchA, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$toApprove->id}/approve", ['approved_qty' => 10])->assertOk();

        $response = $this->getJson('/api/v1/observations?status=approved');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($toApprove->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_date_range_filter_works(): void
    {
        $inRange = $this->submit($this->staffA, $this->branchA, 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $inRange->forceFill(['created_at' => '2026-01-15 10:00:00'])->save();

        $outOfRange = $this->submit($this->staffA, $this->branchA, 'cccccccc-cccc-cccc-cccc-cccccccccccc');
        $outOfRange->forceFill(['created_at' => '2026-03-01 10:00:00'])->save();

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/observations?date_from=2026-01-01&date_to=2026-01-31');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function test_response_includes_system_qty_snapshot_and_variance_and_names(): void
    {
        $observation = $this->submit($this->staffA, $this->branchA, 'dddddddd-dddd-dddd-dddd-dddddddddddd');

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/observations');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $observation->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('system_qty_snapshot', $row);
        $this->assertArrayHasKey('variance', $row);
        $this->assertArrayHasKey('item_name', $row);
        $this->assertArrayHasKey('submitted_by_name', $row);
        $this->assertSame($this->item->name, $row['item_name']);
        $this->assertSame($this->staffA->name, $row['submitted_by_name']);
    }

    // ---- Staff history (GET /observations/mine) — own rows only, server-enforced ----

    public function test_staff_history_only_ever_contains_their_own_rows_even_from_same_branch_colleagues(): void
    {
        $colleague = User::factory()->create(['branch_id' => $this->branchA->id]);
        $colleague->assignRole('Staff');

        $mineRow = $this->submit($this->staffA, $this->branchA, 'e1e1e1e1-e1e1-4e1e-8e1e-e1e1e1e1e1e1');
        $theirs = $this->submit($colleague, $this->branchA, 'e2e2e2e2-e2e2-4e2e-8e2e-e2e2e2e2e2e2');

        Sanctum::actingAs($this->staffA, ['*']);
        // submitted_by is NOT a parameter of /mine — trying to widen the scope with it is ignored.
        $response = $this->getJson('/api/v1/observations/mine?submitted_by='.$colleague->id);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$mineRow->id], $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_staff_history_supports_type_status_and_date_filters_and_stays_blind(): void
    {
        $count = $this->submit($this->staffA, $this->branchA, 'f1f1f1f1-f1f1-4f1f-8f1f-f1f1f1f1f1f1');
        $expiry = $this->submit($this->staffA, $this->branchA, 'f2f2f2f2-f2f2-4f2f-8f2f-f2f2f2f2f2f2', [
            'type' => 'expiry_check',
            'exp_date_observed' => now()->addDays(20)->toDateString(),
            'physical_qty' => null,
        ]);
        $old = $this->submit($this->staffA, $this->branchA, 'f3f3f3f3-f3f3-4f3f-8f3f-f3f3f3f3f3f3');
        $old->forceFill(['created_at' => '2025-01-10 09:00:00'])->save();

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/observations/{$count->id}/approve", ['approved_qty' => 10])->assertOk();

        Sanctum::actingAs($this->staffA, ['*']);

        $byType = collect($this->getJson('/api/v1/observations/mine?type=expiry_check')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$expiry->id], $byType);

        $byStatus = collect($this->getJson('/api/v1/observations/mine?status=approved')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$count->id], $byStatus);

        $byDate = collect($this->getJson('/api/v1/observations/mine?date_from=2025-01-01&date_to=2025-01-31')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$old->id], $byDate);

        $all = $this->getJson('/api/v1/observations/mine');
        foreach ($all->json('data') as $row) {
            $this->assertArrayNotHasKey('system_qty_snapshot', $row);
            $this->assertArrayNotHasKey('variance', $row);
            $this->assertSame($this->item->name, $row['item_name']);
        }
    }

    public function test_history_date_range_must_be_ordered(): void
    {
        Sanctum::actingAs($this->manager, ['*']);
        $this->getJson('/api/v1/observations?date_from=2026-02-01&date_to=2026-01-01')
            ->assertStatus(422)->assertJsonValidationErrors('date_to');

        Sanctum::actingAs($this->staffA, ['*']);
        $this->getJson('/api/v1/observations/mine?date_from=2026-02-01&date_to=2026-01-01')
            ->assertStatus(422);
    }

    // ---- Manager/Owner audit (GET /observations) ----------------------------------

    public function test_audit_endpoint_is_paginated(): void
    {
        foreach (['01', '02', '03'] as $n) {
            $this->submit($this->staffA, $this->branchA, "a0a0a0a0-a0a0-4a0a-8a0a-a0a0a0a0a0{$n}");
        }

        Sanctum::actingAs($this->manager, ['*']);
        $page1 = $this->getJson('/api/v1/observations?per_page=2')->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(2, $page1->json('last_page'));
        $this->assertSame(3, $page1->json('total'));

        $page2 = $this->getJson('/api/v1/observations?per_page=2&page=2')->assertOk();
        $this->assertCount(1, $page2->json('data'));

        $this->getJson('/api/v1/observations?per_page=500')->assertStatus(422);
    }

    public function test_audit_endpoint_can_filter_to_one_staff_member_within_branch_scope(): void
    {
        $colleague = User::factory()->create(['branch_id' => $this->branchA->id]);
        $colleague->assignRole('Staff');
        $a = $this->submit($this->staffA, $this->branchA, 'b0b0b0b0-b0b0-4b0b-8b0b-b0b0b0b0b001');
        $this->submit($colleague, $this->branchA, 'b0b0b0b0-b0b0-4b0b-8b0b-b0b0b0b0b002');
        $this->submit($this->staffB, $this->branchB, 'b0b0b0b0-b0b0-4b0b-8b0b-b0b0b0b0b003');

        Sanctum::actingAs($this->manager, ['*']);
        $ids = collect($this->getJson('/api/v1/observations?submitted_by='.$this->staffA->id)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$a->id], $ids);

        // Asking for another branch's staff member yields nothing — branch scope still applies.
        $none = $this->getJson('/api/v1/observations?submitted_by='.$this->staffB->id)->assertOk()->json('data');
        $this->assertSame([], $none);
    }

    public function test_owner_combined_filters_across_branches(): void
    {
        $a = $this->submit($this->staffA, $this->branchA, 'c0c0c0c0-c0c0-4c0c-8c0c-c0c0c0c0c001');
        $b = $this->submit($this->staffB, $this->branchB, 'c0c0c0c0-c0c0-4c0c-8c0c-c0c0c0c0c002', [
            'type' => 'expiry_check',
            'exp_date_observed' => now()->addDays(5)->toDateString(),
            'physical_qty' => null,
        ]);

        Sanctum::actingAs($this->owner, ['*']);
        $rows = collect($this->getJson('/api/v1/observations?type=stock_count&status=pending')->assertOk()->json('data'));
        $this->assertEquals([$a->id], $rows->pluck('id')->all());

        $rows = collect($this->getJson('/api/v1/observations?branch_id='.$this->branchB->id.'&type=expiry_check')->assertOk()->json('data'));
        $this->assertEquals([$b->id], $rows->pluck('id')->all());
        $this->assertSame('Branch B', $rows->first()['branch_name']);
    }

    public function test_pending_queue_rows_now_carry_display_names(): void
    {
        $o = $this->submit($this->staffA, $this->branchA, 'd0d0d0d0-d0d0-4d0d-8d0d-d0d0d0d0d001');

        Sanctum::actingAs($this->manager, ['*']);
        $row = collect($this->getJson('/api/v1/observations/pending')->assertOk()->json('data'))->firstWhere('id', $o->id);

        $this->assertSame($this->item->name, $row['item_name']);
        $this->assertSame($this->staffA->name, $row['submitted_by_name']);
        $this->assertArrayHasKey('variance', $row);
    }
}
