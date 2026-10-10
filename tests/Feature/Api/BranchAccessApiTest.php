<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $this->branchA = Branch::create(['name' => 'Branch A', 'status' => true]);
        $this->branchB = Branch::create(['name' => 'Branch B', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Dog Food 1kg',
            'item_code' => 'DF001',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'status' => true,
        ]);
    }

    public function test_staff_from_branch_a_cannot_query_branch_b_batches(): void
    {
        $staffA = User::factory()->create(['branch_id' => $this->branchA->id]);
        $staffA->assignRole('Staff');

        Sanctum::actingAs($staffA, ['*']);

        $response = $this->getJson("/api/v1/items/{$this->item->id}/batches?branch_id={$this->branchB->id}");

        $response->assertStatus(403);
    }

    public function test_staff_can_query_their_own_branch_batches(): void
    {
        $staffA = User::factory()->create(['branch_id' => $this->branchA->id]);
        $staffA->assignRole('Staff');

        Sanctum::actingAs($staffA, ['*']);

        $response = $this->getJson("/api/v1/items/{$this->item->id}/batches?branch_id={$this->branchA->id}");

        $response->assertOk();
    }

    public function test_owner_can_query_any_branch(): void
    {
        $owner = User::factory()->create(['branch_id' => null]);
        $owner->assignRole('Owner');

        Sanctum::actingAs($owner, ['*']);

        $response = $this->getJson("/api/v1/items/{$this->item->id}/batches?branch_id={$this->branchB->id}");

        $response->assertOk();
    }
}
