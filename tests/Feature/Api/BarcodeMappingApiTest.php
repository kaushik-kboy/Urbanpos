<?php

namespace Tests\Feature\Api;

use App\Models\BarcodeMappingRequest;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BarcodeMappingApiTest extends TestCase
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
            'name' => 'Dog Treats 250g',
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
    }

    public function test_staff_can_submit_mapping_request_and_it_does_not_touch_master_data(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->postJson('/api/v1/barcode-mapping-requests', [
            'scanned_barcode' => '1234567890123',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertStatus(201);
        $this->assertSame('pending', $response->json('data.status'));

        $this->item->refresh();
        $this->assertNull($this->item->ean_upc_code);
    }

    public function test_manager_approval_sets_ean_upc_code_when_item_unmapped(): void
    {
        $request = BarcodeMappingRequest::create([
            'scanned_barcode' => '9999999999999',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/approve", []);

        $response->assertOk();
        $this->assertSame('approved', $response->json('data.status'));

        $this->item->refresh();
        $this->assertSame('9999999999999', $this->item->ean_upc_code);
    }

    public function test_approval_onto_item_already_mapped_to_different_barcode_requires_force(): void
    {
        $this->item->update(['ean_upc_code' => '1111111111111']);

        $request = BarcodeMappingRequest::create([
            'scanned_barcode' => '2222222222222',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->manager, ['*']);

        $withoutForce = $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/approve", []);
        $withoutForce->assertStatus(422);

        $this->item->refresh();
        $this->assertSame('1111111111111', $this->item->ean_upc_code, 'Existing mapping must not be silently overwritten.');

        $withForce = $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/approve", ['force' => true]);
        $withForce->assertOk();

        $this->item->refresh();
        $this->assertSame('2222222222222', $this->item->ean_upc_code);
    }

    public function test_reject_requires_remarks_and_does_not_touch_item(): void
    {
        $request = BarcodeMappingRequest::create([
            'scanned_barcode' => '3333333333333',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/reject", [])->assertStatus(422);

        $response = $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/reject", [
            'remarks' => 'Wrong product suggested',
        ]);
        $response->assertOk();
        $this->assertSame('rejected', $response->json('data.status'));

        $this->item->refresh();
        $this->assertNull($this->item->ean_upc_code);
    }

    public function test_second_approve_on_already_rejected_request_returns_409(): void
    {
        $request = BarcodeMappingRequest::create([
            'scanned_barcode' => '4444444444444',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->manager, ['*']);
        $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/reject", ['remarks' => 'no'])->assertOk();

        $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/approve", [])->assertStatus(409);
    }

    public function test_staff_cannot_review_mapping_requests(): void
    {
        $request = BarcodeMappingRequest::create([
            'scanned_barcode' => '5555555555555',
            'suggested_item_id' => $this->item->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->staff, ['*']);

        $this->getJson('/api/v1/barcode-mapping-requests')->assertStatus(403);
        $this->postJson("/api/v1/barcode-mapping-requests/{$request->id}/approve", [])->assertStatus(403);
    }
}
