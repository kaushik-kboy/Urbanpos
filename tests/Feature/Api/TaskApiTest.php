<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\ItemCategory;
use App\Models\StockCountTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch', 'status' => true]);

        $this->staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');
    }

    public function test_manager_can_create_task_assigned_to_staff(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 3',
            'section' => 'Rack 3',
            'assigned_to' => $this->staff->id,
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, StockCountTask::count());
    }

    public function test_staff_cannot_create_task(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 3',
            'assigned_to' => $this->staff->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_assign_task_to_staff_from_a_different_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch', 'status' => true]);
        $otherStaff = User::factory()->create(['branch_id' => $otherBranch->id]);
        $otherStaff->assignRole('Staff');

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 3',
            'assigned_to' => $otherStaff->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_staff_only_sees_own_tasks_manager_sees_branch_tasks(): void
    {
        $otherStaff = User::factory()->create(['branch_id' => $this->branch->id]);
        $otherStaff->assignRole('Staff');

        $taskForStaff = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'For Staff',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $taskForOther = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'For Other Staff',
            'assigned_to' => $otherStaff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->staff, ['*']);
        $mine = $this->getJson('/api/v1/tasks');
        $mine->assertOk();
        $ids = collect($mine->json('data'))->pluck('id')->all();
        $this->assertContains($taskForStaff->id, $ids);
        $this->assertNotContains($taskForOther->id, $ids);

        Sanctum::actingAs($this->manager, ['*']);
        $all = $this->getJson('/api/v1/tasks');
        $all->assertOk();
        $allIds = collect($all->json('data'))->pluck('id')->all();
        $this->assertContains($taskForStaff->id, $allIds);
        $this->assertContains($taskForOther->id, $allIds);
    }

    public function test_valid_status_transitions_succeed_and_invalid_ones_are_rejected(): void
    {
        $task = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 1',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->staff, ['*']);

        // pending -> completed directly is invalid.
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])->assertStatus(422);

        // pending -> in_progress (assignee) is valid.
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();

        // in_progress -> completed (assignee) is valid.
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])->assertOk();

        $task->refresh();
        $this->assertSame('completed', $task->status);

        // completed -> cancelled is invalid for anyone.
        Sanctum::actingAs($this->manager, ['*']);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled'])->assertStatus(422);
    }

    public function test_only_manager_can_cancel_a_task_not_the_assignee(): void
    {
        $task = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 2',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->staff, ['*']);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled'])->assertStatus(403);

        Sanctum::actingAs($this->manager, ['*']);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled'])->assertOk();
    }

    public function test_manager_cannot_cancel_a_task_from_a_different_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch', 'status' => true]);
        $otherManager = User::factory()->create(['branch_id' => $otherBranch->id]);
        $otherManager->assignRole('Manager');

        $task = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 2',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($otherManager, ['*']);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled'])->assertStatus(403);

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_manager_can_create_task_with_brand_id_alongside_category_id(): void
    {
        $brand = Brand::create(['name' => 'Royal Canin', 'status' => true]);
        $category = ItemCategory::create(['name' => 'Dog Food']);

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Royal Canin dog food',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'assigned_to' => $this->staff->id,
        ]);

        $response->assertStatus(201);
        $this->assertSame($brand->id, $response->json('data.brand_id'));
        $this->assertSame($category->id, $response->json('data.category_id'));

        $task = StockCountTask::first();
        $this->assertEquals($brand->id, $task->brand_id);
        $this->assertEquals($category->id, $task->category_id);
    }

    public function test_task_creation_without_brand_id_still_works_unaffected(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Rack 4',
            'assigned_to' => $this->staff->id,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.brand_id'));
    }

    public function test_tasks_can_be_filtered_by_brand_id_and_category_id(): void
    {
        $brandA = Brand::create(['name' => 'Brand A', 'status' => true]);
        $brandB = Brand::create(['name' => 'Brand B', 'status' => true]);
        $categoryA = ItemCategory::create(['name' => 'Category A']);

        $taskBrandA = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'Brand A task',
            'brand_id' => $brandA->id,
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $taskBrandB = StockCountTask::create([
            'branch_id' => $this->branch->id,
            'title' => 'Brand B task',
            'brand_id' => $brandB->id,
            'category_id' => $categoryA->id,
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->manager, ['*']);

        $filteredByBrand = $this->getJson('/api/v1/tasks?brand_id='.$brandA->id);
        $filteredByBrand->assertOk();
        $brandIds = collect($filteredByBrand->json('data'))->pluck('id')->all();
        $this->assertContains($taskBrandA->id, $brandIds);
        $this->assertNotContains($taskBrandB->id, $brandIds);

        $filteredByCategory = $this->getJson('/api/v1/tasks?category_id='.$categoryA->id);
        $filteredByCategory->assertOk();
        $categoryIds = collect($filteredByCategory->json('data'))->pluck('id')->all();
        $this->assertContains($taskBrandB->id, $categoryIds);
        $this->assertNotContains($taskBrandA->id, $categoryIds);
    }
}
