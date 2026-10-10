<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the "authorised evidence-photo retrieval" gap flagged in
 * OVERNIGHT_PROGRESS_REPORT.md §11: a photo uploaded with a submission must be
 * fetchable only by an authorised reviewer in the same branch, never by an
 * unauthenticated request or a Staff member outside the submission's own branch.
 */
class ObservationPhotoApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Branch $otherBranch;
    private Item $item;
    private User $staff;
    private User $manager;
    private User $otherBranchManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->branch = Branch::create(['name' => 'Main Branch', 'status' => true]);
        $this->otherBranch = Branch::create(['name' => 'Other Branch', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Dog Treats 200g',
            'item_code' => 'DT001',
            'cost_price' => 30,
            'sell_price' => 55,
            'mrp' => 60,
            'status' => true,
        ]);

        $this->staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        $this->otherBranchManager = User::factory()->create(['branch_id' => $this->otherBranch->id]);
        $this->otherBranchManager->assignRole('Manager');
    }

    private function submitWithPhoto(): InventoryObservation
    {
        Sanctum::actingAs($this->staff, ['*']);

        $photo = UploadedFile::fake()->image('evidence.jpg', 100, 100);

        $response = $this->post('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 12,
            'idempotency_key' => '22222222-2222-2222-2222-222222222222',
            'photo' => $photo,
        ]);

        $response->assertStatus(201);

        return InventoryObservation::find($response->json('data.id'));
    }

    public function test_manager_in_the_same_branch_can_fetch_the_photo(): void
    {
        $observation = $this->submitWithPhoto();

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->get("/api/v1/observations/{$observation->id}/photo");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_staff_cannot_fetch_the_photo(): void
    {
        $observation = $this->submitWithPhoto();

        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->get("/api/v1/observations/{$observation->id}/photo");

        $response->assertStatus(403);
    }

    public function test_manager_in_a_different_branch_cannot_fetch_the_photo(): void
    {
        $observation = $this->submitWithPhoto();

        Sanctum::actingAs($this->otherBranchManager, ['*']);

        $response = $this->get("/api/v1/observations/{$observation->id}/photo");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $observation = $this->submitWithPhoto();

        // Sanctum::actingAs sets a test-wide actor, not a per-request one — forget it
        // so this request genuinely carries no auth, like a real unauthenticated call.
        auth()->forgetGuards();

        $response = $this->getJson("/api/v1/observations/{$observation->id}/photo");

        $response->assertStatus(401);
    }

    public function test_404_when_observation_has_no_photo(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->postJson('/api/v1/observations', [
            'type' => 'stock_count',
            'item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 5,
            'idempotency_key' => '33333333-3333-3333-3333-333333333333',
        ]);
        $response->assertStatus(201);
        $observationId = $response->json('data.id');

        Sanctum::actingAs($this->manager, ['*']);

        $photoResponse = $this->get("/api/v1/observations/{$observationId}/photo");

        $photoResponse->assertStatus(404);
    }
}
