<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\StockCountTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Task assignment scoped by Category or Brand (master prompt §9), following the POS's
 * existing product taxonomy: brand = items.brand_id -> brands, category =
 * items.category_value_id -> item_category_values under the "CATEGORY" head (the same
 * filters Master\ItemController@index uses). Also covers the assignee lookup and the
 * blind-count fix on GET /tasks/{task}.
 */
class TaskCategoryBrandScopeApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Branch $otherBranch;
    private User $staff;
    private User $manager;
    private User $owner;
    private ItemCategoryValue $dogFood;
    private ItemCategoryValue $catFood;
    private ItemCategoryValue $departmentValue;
    private Brand $royalCanin;
    private Brand $whiskas;
    private Item $rcDog;
    private Item $rcCat;
    private Item $whiskasCat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch', 'status' => true]);
        $this->otherBranch = Branch::create(['name' => 'Other Branch', 'status' => true]);

        $this->staff = User::factory()->create(['name' => 'Alice Staff', 'branch_id' => $this->branch->id]);
        $this->staff->assignRole('Staff');

        $this->manager = User::factory()->create(['name' => 'Mona Manager', 'branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        $this->owner = User::factory()->create(['name' => 'Oscar Owner', 'branch_id' => null]);
        $this->owner->assignRole('Owner');

        $categoryHead = ItemCategory::create(['name' => 'CATEGORY', 'is_mandatory' => false, 'status' => true]);
        $departmentHead = ItemCategory::create(['name' => 'DEPARTMENT', 'is_mandatory' => false, 'status' => true]);

        $this->dogFood = ItemCategoryValue::create(['item_category_id' => $categoryHead->id, 'name' => 'Dog Food', 'status' => true]);
        $this->catFood = ItemCategoryValue::create(['item_category_id' => $categoryHead->id, 'name' => 'Cat Food', 'status' => true]);
        ItemCategoryValue::create(['item_category_id' => $categoryHead->id, 'name' => 'Retired Category', 'status' => false]);
        $this->departmentValue = ItemCategoryValue::create(['item_category_id' => $departmentHead->id, 'name' => 'Pet Food Dept', 'status' => true]);

        $this->royalCanin = Brand::create(['name' => 'Royal Canin', 'status' => true]);
        $this->whiskas = Brand::create(['name' => 'Whiskas', 'status' => true]);
        Brand::create(['name' => 'Discontinued Brand', 'status' => false]);

        $this->rcDog = $this->makeItem('RC Adult Dog 15kg', 'RC001', $this->royalCanin, $this->dogFood);
        $this->rcCat = $this->makeItem('RC Kitten 2kg', 'RC002', $this->royalCanin, $this->catFood);
        $this->whiskasCat = $this->makeItem('Whiskas Tuna 85g', 'WH001', $this->whiskas, $this->catFood);
    }

    private function makeItem(string $name, string $code, Brand $brand, ItemCategoryValue $category): Item
    {
        return Item::create([
            'name' => $name,
            'item_code' => $code,
            'brand_id' => $brand->id,
            'category_value_id' => $category->id,
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 90,
            'status' => true,
        ]);
    }

    // ---- reference data -------------------------------------------------------

    public function test_categories_endpoint_lists_only_active_values_under_the_category_head(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $names = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertEquals(['Cat Food', 'Dog Food'], $names);
        $this->assertNotContains('Pet Food Dept', $names, 'DEPARTMENT-head values are not product categories');
        $this->assertNotContains('Retired Category', $names, 'inactive values are excluded');
    }

    public function test_brands_endpoint_lists_only_active_brands(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $names = collect($this->getJson('/api/v1/brands')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertEquals(['Royal Canin', 'Whiskas'], $names);
    }

    public function test_reference_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/categories')->assertStatus(401);
        $this->getJson('/api/v1/brands')->assertStatus(401);
    }

    public function test_item_search_filters_by_category_and_brand(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $byBrand = collect($this->getJson('/api/v1/items/search?brand_id='.$this->royalCanin->id)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->rcDog->id, $this->rcCat->id], $byBrand);

        $byCategory = collect($this->getJson('/api/v1/items/search?category_value_id='.$this->catFood->id)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->rcCat->id, $this->whiskasCat->id], $byCategory);

        $both = collect($this->getJson('/api/v1/items/search?category_value_id='.$this->catFood->id.'&brand_id='.$this->royalCanin->id)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$this->rcCat->id], $both);

        $this->getJson('/api/v1/items/search?brand_id=999999')->assertStatus(422);
    }

    // ---- task creation ---------------------------------------------------------

    public function test_manager_can_assign_a_task_scoped_by_category_and_brand(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Count Royal Canin cat food',
            'category_value_id' => $this->catFood->id,
            'brand_id' => $this->royalCanin->id,
            'assigned_to' => $this->staff->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.category_value_id', $this->catFood->id);
        $response->assertJsonPath('data.category_value.name', 'Cat Food');
        $response->assertJsonPath('data.brand.name', 'Royal Canin');

        $task = StockCountTask::first();
        $this->assertEquals($this->catFood->id, $task->category_value_id);
        $this->assertEquals($this->royalCanin->id, $task->brand_id);
    }

    public function test_selected_products_inside_the_scope_are_accepted(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'RC spot check',
            'brand_id' => $this->royalCanin->id,
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->rcCat->id, 'batch_no' => 'B1']],
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data.items'));
        $this->assertSame('RC Adult Dog 15kg', $response->json('data.items.0.item.name'));
    }

    public function test_selected_product_outside_the_brand_scope_is_rejected_and_nothing_is_created(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'RC only',
            'brand_id' => $this->royalCanin->id,
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->whiskasCat->id]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
        $this->assertStringContainsString('Whiskas Tuna 85g', $response->json('message'));
        $this->assertSame(0, StockCountTask::count());
    }

    public function test_selected_product_outside_the_category_scope_is_rejected(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Dog food only',
            'category_value_id' => $this->dogFood->id,
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcCat->id]],
        ])->assertStatus(422);

        $this->assertSame(0, StockCountTask::count());
    }

    public function test_selected_products_without_a_category_or_brand_scope_are_unrestricted(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Mixed shelf',
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->whiskasCat->id]],
        ])->assertStatus(201);
    }

    // ---- multi-category / multi-brand scope (business rule, 2026-10-10) -------

    public function test_single_category_via_singular_field_is_unchanged(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Single category legacy path',
            'category_value_id' => $this->catFood->id,
            'assigned_to' => $this->staff->id,
        ])->assertStatus(201);

        $task = StockCountTask::find($response->json('data.id'));
        $this->assertEquals($this->catFood->id, $task->category_value_id);
        $this->assertEquals([$this->catFood->id], $task->categoryValues()->pluck('item_category_values.id')->all());
    }

    public function test_multiple_categories_explicitly_selected_are_accepted_and_scope_items_by_union(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Dog + Cat food',
            'category_value_ids' => [$this->dogFood->id, $this->catFood->id],
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->whiskasCat->id]],
        ]);

        $response->assertStatus(201);
        $task = StockCountTask::find($response->json('data.id'));
        // Singular column stays null — more than one category was explicitly selected.
        $this->assertNull($task->category_value_id);
        $this->assertEqualsCanonicalizing(
            [$this->dogFood->id, $this->catFood->id],
            $task->categoryValues()->pluck('item_category_values.id')->all()
        );
    }

    public function test_multiple_brands_explicitly_selected_are_accepted(): void
    {
        $otherBrand = Brand::create(['name' => 'Pedigree', 'status' => true]);
        $pedigreeItem = $this->makeItem('Pedigree Adult 10kg', 'PD001', $otherBrand, $this->dogFood);

        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'RC + Pedigree',
            'brand_ids' => [$this->royalCanin->id, $otherBrand->id],
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $pedigreeItem->id]],
        ]);

        $response->assertStatus(201);
        $task = StockCountTask::find($response->json('data.id'));
        $this->assertNull($task->brand_id);
        $this->assertEqualsCanonicalizing(
            [$this->royalCanin->id, $otherBrand->id],
            $task->brands()->pluck('brands.id')->all()
        );
    }

    public function test_mixed_multi_category_and_brand_scope_item_must_match_at_least_one(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        // whiskasCat matches catFood (in category_value_ids) even though its brand
        // (Whiskas) isn't in brand_ids — union means "at least one", not "all".
        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Union scope',
            'category_value_ids' => [$this->catFood->id],
            'brand_ids' => [$this->royalCanin->id],
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->whiskasCat->id]],
        ]);

        $response->assertStatus(201);
    }

    public function test_multi_scope_still_rejects_an_item_outside_every_selected_category_and_brand(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        // whiskasCat is neither dogFood-category nor Royal-Canin-brand.
        $response = $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Still out of scope',
            'category_value_ids' => [$this->dogFood->id],
            'brand_ids' => [$this->royalCanin->id],
            'assigned_to' => $this->staff->id,
            'items' => [['item_id' => $this->rcDog->id], ['item_id' => $this->whiskasCat->id]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Whiskas Tuna 85g', $response->json('message'));
        $this->assertSame(0, StockCountTask::count());
    }

    public function test_invalid_category_value_id_is_a_validation_error(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Bad category',
            'category_value_id' => 999999,
            'assigned_to' => $this->staff->id,
        ])->assertStatus(422)->assertJsonValidationErrors('category_value_id');
    }

    public function test_staff_still_cannot_assign_a_category_scoped_task(): void
    {
        Sanctum::actingAs($this->staff, ['*']);

        $this->postJson('/api/v1/tasks', [
            'branch_id' => $this->branch->id,
            'title' => 'Not allowed',
            'category_value_id' => $this->dogFood->id,
            'assigned_to' => $this->staff->id,
        ])->assertStatus(403);
    }

    public function test_tasks_can_be_filtered_by_category_value_id(): void
    {
        $dogTask = $this->makeTask(['category_value_id' => $this->dogFood->id, 'title' => 'Dog']);
        $catTask = $this->makeTask(['category_value_id' => $this->catFood->id, 'title' => 'Cat']);

        Sanctum::actingAs($this->manager, ['*']);
        $ids = collect($this->getJson('/api/v1/tasks?category_value_id='.$this->dogFood->id)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEquals([$dogTask->id], $ids);
        $this->assertNotContains($catTask->id, $ids);
    }

    public function test_task_list_nested_users_expose_only_safe_columns(): void
    {
        $this->makeTask([]);

        Sanctum::actingAs($this->manager, ['*']);
        $row = $this->getJson('/api/v1/tasks')->assertOk()->json('data.0');

        $this->assertEqualsCanonicalizing(['id', 'name', 'branch_id'], array_keys($row['assignee']));
        $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($row['assigner']));
    }

    // ---- assignee lookup -------------------------------------------------------

    public function test_manager_gets_assignable_users_for_their_branch_only(): void
    {
        $otherBranchStaff = User::factory()->create(['name' => 'Bob Elsewhere', 'branch_id' => $this->otherBranch->id]);
        $otherBranchStaff->assignRole('Staff');
        $inactiveStaff = User::factory()->create(['name' => 'Ina Inactive', 'branch_id' => $this->branch->id, 'is_active' => false]);
        $inactiveStaff->assignRole('Staff');
        $cashierOnly = User::factory()->create(['name' => 'Cash Ier', 'branch_id' => $this->branch->id]);
        $cashierOnly->assignRole('Cashier');

        Sanctum::actingAs($this->manager, ['*']);
        $response = $this->getJson('/api/v1/tasks/assignees?branch_id='.$this->branch->id)->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Alice Staff', $names);
        $this->assertContains('Mona Manager', $names);
        $this->assertContains('Oscar Owner', $names, 'all-branch users can be assigned anywhere, same rule store() enforces');
        $this->assertNotContains('Bob Elsewhere', $names);
        $this->assertNotContains('Ina Inactive', $names);
        $this->assertNotContains('Cash Ier', $names);

        $alice = collect($response->json('data'))->firstWhere('name', 'Alice Staff');
        $this->assertEqualsCanonicalizing(['id', 'name', 'branch_id', 'role'], array_keys($alice));
        $this->assertSame('Staff', $alice['role']);
    }

    public function test_assignee_lookup_is_forbidden_for_staff_and_for_other_branches(): void
    {
        Sanctum::actingAs($this->staff, ['*']);
        $this->getJson('/api/v1/tasks/assignees?branch_id='.$this->branch->id)->assertStatus(403);

        Sanctum::actingAs($this->manager, ['*']);
        $this->getJson('/api/v1/tasks/assignees?branch_id='.$this->otherBranch->id)->assertStatus(403);
        $this->getJson('/api/v1/tasks/assignees')->assertStatus(422);

        Sanctum::actingAs($this->owner, ['*']);
        $this->getJson('/api/v1/tasks/assignees?branch_id='.$this->otherBranch->id)->assertOk();
    }

    // ---- blind counting on task detail ----------------------------------------

    public function test_task_detail_never_exposes_system_qty_snapshot_to_the_staff_assignee(): void
    {
        $task = $this->makeTask([]);
        InventoryObservation::create([
            'type' => 'stock_count',
            'task_id' => $task->id,
            'item_id' => $this->rcDog->id,
            'branch_id' => $this->branch->id,
            'physical_qty' => 17,
            'idempotency_key' => '0f0f0f0f-0f0f-4f0f-8f0f-0f0f0f0f0f0f',
            'system_qty_snapshot' => 4321,
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->staff, ['*']);
        $staffResponse = $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();
        $this->assertCount(1, $staffResponse->json('data.observations'));
        $this->assertArrayNotHasKey('system_qty_snapshot', $staffResponse->json('data.observations.0'));
        $staffResponse->assertDontSee('4321');

        Sanctum::actingAs($this->manager, ['*']);
        $managerResponse = $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();
        $this->assertEquals(4321, (float) $managerResponse->json('data.observations.0.system_qty_snapshot'));
    }

    private function makeTask(array $overrides): StockCountTask
    {
        return StockCountTask::create(array_merge([
            'branch_id' => $this->branch->id,
            'title' => 'Task',
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'priority' => 'normal',
            'status' => 'pending',
        ], $overrides));
    }
}
