<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $admin;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Outlet', 'code' => 'MAIN', 'state' => 'Maharashtra', 'status' => true]
        );

        $this->admin = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->admin->assignRole('Owner');

        $this->cashier = User::factory()->create([
            'email' => 'cashier@urbanpos.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole('Cashier');
    }

    /**
     * Test role-based authorization for bulk updater console.
     */
    public function test_cashier_is_forbidden_and_admin_has_access(): void
    {
        // Cashier should receive 403 Forbidden
        $cashierRes = $this->actingAs($this->cashier)->get(route('tools.bulk-updater.index'));
        $cashierRes->assertStatus(403);

        // Seed ItemCategory and ItemCategoryValue with real records
        $cat = \App\Models\ItemCategory::create(['name' => 'PET FOOD', 'status' => true]);
        \App\Models\ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'Dry Food', 'status' => true]);

        // Admin should access console successfully
        $adminRes = $this->actingAs($this->admin)->get(route('tools.bulk-updater.index'));
        $adminRes->assertStatus(200);

        // Verify every module tab loads without SQL errors
        $service = app(\App\Services\Tools\UniversalBulkUpdateService::class);
        foreach (array_keys($service->getModuleDefinitions()) as $modKey) {
            $res = $this->actingAs($this->admin)->get(route('tools.bulk-updater.index', ['module' => $modKey]));
            $res->assertStatus(200);
        }
        $adminRes->assertSee('Universal Bulk Operations Studio');
        $adminRes->assertSee('Product Items & Catalog');
    }

    /**
     * Test bulk preview and update of GST tax slabs on items (e.g. 18% to 12%).
     */
    public function test_bulk_update_item_gst_tax_slabs(): void
    {
        $gst18 = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $gst12 = GstTax::create(['description' => 'GST 12%', 'percentage' => 12, 'status' => true]);

        // Create 3 items with 18% GST and 1 item with 12% GST
        Item::create(['name' => 'Item A', 'gst_tax_id' => $gst18->id]);
        Item::create(['name' => 'Item B', 'gst_tax_id' => $gst18->id]);
        Item::create(['name' => 'Item C', 'gst_tax_id' => $gst18->id]);
        Item::create(['name' => 'Item D', 'gst_tax_id' => $gst12->id]);

        // Preview items with gst_tax_id = 18%
        $previewRes = $this->actingAs($this->admin)->postJson(route('tools.bulk-updater.preview'), [
            'module' => 'items',
            'filters' => ['gst_tax_id' => $gst18->id],
        ]);

        $previewRes->assertStatus(200);
        $previewRes->assertJson([
            'success' => true,
            'total_count' => 3,
        ]);

        // Execute bulk update: set gst_tax_id to 12%
        $executeRes = $this->actingAs($this->admin)->postJson(route('tools.bulk-updater.execute'), [
            'module' => 'items',
            'filters' => ['gst_tax_id' => $gst18->id],
            'target_field' => 'gst_tax_id',
            'target_value' => $gst12->id,
        ]);

        $executeRes->assertStatus(200);
        $executeRes->assertJson([
            'success' => true,
            'updated_count' => 3,
        ]);

        // Verify database: all 4 items now have 12% GST
        $this->assertEquals(0, Item::where('gst_tax_id', $gst18->id)->count());
        $this->assertEquals(4, Item::where('gst_tax_id', $gst12->id)->count());

        // Verify regulatory AuditLog entry was recorded
        $auditLog = AuditLog::where('action', 'bulk_update')
            ->where('auditable_type', Item::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->admin->id, $auditLog->user_id);
        $this->assertStringContainsString('gst_tax_id', $auditLog->reason ?? '');
    }

    /**
     * Test bulk update of customer credit limit and category.
     */
    public function test_bulk_update_customer_attributes(): void
    {
        $catStandard = CustomerCategory::create(['name' => 'Standard Tier', 'status' => true]);
        $catVIP = CustomerCategory::create(['name' => 'VIP Tier', 'status' => true]);

        Customer::create([
            'name' => 'Customer 1',
            'mobile' => '9999900001',
            'customer_category_id' => $catStandard->id,
            'credit_limit' => 1000,
            'status' => true,
        ]);
        Customer::create([
            'name' => 'Customer 2',
            'mobile' => '9999900002',
            'customer_category_id' => $catStandard->id,
            'credit_limit' => 1000,
            'status' => true,
        ]);

        // Bulk update credit_limit to 5000 for standard tier
        $response = $this->actingAs($this->admin)->postJson(route('tools.bulk-updater.execute'), [
            'module' => 'customers',
            'filters' => ['customer_category_id' => $catStandard->id],
            'target_field' => 'credit_limit',
            'target_value' => 5000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated_count' => 2,
        ]);

        $this->assertEquals(2, Customer::where('credit_limit', 5000)->count());
    }

    /**
     * Test universal dynamic database table mode on arbitrary table (e.g. brands).
     */
    public function test_universal_dynamic_database_table_mode(): void
    {
        Brand::create(['name' => 'Brand Alpha', 'code' => 'ALPHA', 'status' => true]);
        Brand::create(['name' => 'Brand Beta', 'code' => 'BETA', 'status' => true]);

        // Fetch columns via dynamic table column lookup
        $colRes = $this->actingAs($this->admin)->getJson(route('tools.bulk-updater.table-columns', ['table' => 'brands']));
        $colRes->assertStatus(200);
        $colRes->assertJsonFragment(['table' => 'brands']);

        // Execute bulk update using dynamic table mode
        $updateRes = $this->actingAs($this->admin)->postJson(route('tools.bulk-updater.execute'), [
            'module' => 'dynamic_table',
            'dynamic_table' => 'brands',
            'filters' => ['status' => 1],
            'target_field' => 'status',
            'target_value' => 0,
        ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJson([
            'success' => true,
            'updated_count' => 2,
        ]);

        $this->assertEquals(0, Brand::where('status', true)->count());
        $this->assertEquals(2, Brand::where('status', false)->count());
    }
}
