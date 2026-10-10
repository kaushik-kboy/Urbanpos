<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\ExpiryBucketSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Business rule (2026-10-10 client decision): expiry-bucket ranges must be
 * configurable by Manager/Owner, not hardcoded/DB-only. Covers PUT
 * /v1/expiry-buckets/settings validation (gap/overlap/start-at-zero) and
 * authorization.
 */
class ExpiryBucketSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create(['name' => 'Main Branch', 'status' => true]);

        $this->staff = User::factory()->create(['branch_id' => $branch->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $branch->id]);
        $this->manager->assignRole('Manager');
    }

    public function test_defaults_are_seeded_and_readable_by_any_authenticated_user(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->getJson('/api/v1/expiry-buckets')->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();

        $this->assertEquals(['Expiring Soon', 'Expiring Later', 'Watch List'], $labels);
    }

    public function test_staff_cannot_edit_bucket_settings(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['label' => 'All', 'min_days' => 0, 'max_days' => 999, 'severity' => 'critical', 'sort_order' => 1],
            ],
        ])->assertStatus(403);
    }

    public function test_manager_can_update_bucket_ranges(): void
    {
        $existing = ExpiryBucketSetting::orderBy('sort_order')->get();

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['id' => $existing[0]->id, 'label' => 'Critical', 'min_days' => 0, 'max_days' => 15, 'severity' => 'critical', 'sort_order' => 1],
                ['id' => $existing[1]->id, 'label' => 'Warning', 'min_days' => 16, 'max_days' => 45, 'severity' => 'warning', 'sort_order' => 2],
                ['label' => 'New Watch Bucket', 'min_days' => 46, 'max_days' => 90, 'severity' => 'info', 'sort_order' => 3],
            ],
        ]);

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();
        $this->assertEquals(['Critical', 'Warning', 'New Watch Bucket'], $labels);

        // The third pre-existing bucket (not included in the submitted list) is gone.
        $this->assertSame(3, ExpiryBucketSetting::count());
        $this->assertDatabaseMissing('expiry_bucket_settings', ['label' => 'Watch List']);
    }

    public function test_rejects_overlapping_buckets(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['label' => 'A', 'min_days' => 0, 'max_days' => 30, 'severity' => 'critical', 'sort_order' => 1],
                ['label' => 'B', 'min_days' => 20, 'max_days' => 60, 'severity' => 'warning', 'sort_order' => 2],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('buckets');
    }

    public function test_rejects_a_bucket_set_that_does_not_start_at_zero(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['label' => 'A', 'min_days' => 5, 'max_days' => 30, 'severity' => 'critical', 'sort_order' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('buckets');
    }

    public function test_allows_a_gap_between_buckets(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['label' => 'A', 'min_days' => 0, 'max_days' => 10, 'severity' => 'critical', 'sort_order' => 1],
                ['label' => 'B', 'min_days' => 20, 'max_days' => 30, 'severity' => 'warning', 'sort_order' => 2],
            ],
        ])->assertOk();
    }

    public function test_rejects_min_greater_than_max(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->putJson('/api/v1/expiry-buckets/settings', [
            'buckets' => [
                ['label' => 'A', 'min_days' => 30, 'max_days' => 10, 'severity' => 'critical', 'sort_order' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('buckets');
    }
}
