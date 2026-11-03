<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\ReplenishmentSalesRule;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ReplenishmentRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmartReplenishmentUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Branch $branch;

    protected Branch $otherBranch;

    protected Supplier $supplier;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['replenishment.enabled' => true]);
        $this->seed(ReplenishmentRulesSeeder::class);

        // Ensure permissions and roles
        $permission = Permission::firstOrCreate(['name' => 'smart-replenishment.view', 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permission);

        $this->branch = Branch::create([
            'name' => 'Urban Pets - Motera Test',
            'status' => true,
        ]);

        $this->otherBranch = Branch::create([
            'name' => 'Urban Pets - Satellite Test',
            'status' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Mars International Test',
            'status' => true,
        ]);

        $this->item = Item::create([
            'name' => 'Royal Canin Maxi Test 15kg',
            'item_code' => 'RC-TEST-15',
            'cost_price' => 2500.00,
            'sell_price' => 3200.00,
            'mrp' => 3200.00,
            'supplier_id' => $this->supplier->id,
            'status' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole($ownerRole);
    }

    public function test_dashboard_index_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.index'));
        $response->assertStatus(200);
        $response->assertSee('Smart Replenishment Studio');
        $response->assertSee('Stock Replenishment Analysis');
    }

    public function test_ajax_data_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('inventory.replenishment.data', [
            'branch_id' => $this->branch->id,
            'status' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'per_page',
        ]);
    }

    public function test_sync_trigger_endpoint_executes_successfully(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.sync'), [
            'branch_id' => $this->branch->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_settings_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.settings'));
        $response->assertStatus(200);
        $response->assertSee('Replenishment Rules & Settings');
        $response->assertSee('Sales Movement Rules');
    }

    public function test_settings_update_modifies_rules(): void
    {
        $rule = ReplenishmentSalesRule::first();
        $this->assertNotNull($rule);

        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.settings.update'), [
            'sales_rules' => [
                $rule->id => 35,
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(35, $rule->fresh()->target_cover_days);
    }

    public function test_settings_update_via_form_post_redirects_with_success(): void
    {
        $rule = ReplenishmentSalesRule::first();
        $this->assertNotNull($rule);

        $response = $this->actingAs($this->user)->post(route('inventory.replenishment.settings.update'), [
            'sales_rules' => [
                $rule->id => 40,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(40, $rule->fresh()->target_cover_days);
    }

    public function test_transfers_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.transfers'));
        $response->assertStatus(200);
        $response->assertSee('Smart Inter-Branch Matchmaker');
    }

    public function test_supplier_orders_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.supplier-orders'));
        $response->assertStatus(200);
        $response->assertSee('Supplier-wise Order Recommendations');
    }

    public function test_generate_po_pipeline_creates_purchase_order_and_items(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.generate-po'), [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 10,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_items' => 1,
            'total_qty' => 10,
        ]);

        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals(10, $po->total_qty);
        $this->assertEquals(25000.00, (float) $po->total);
        $this->assertCount(1, $po->items);
        $this->assertEquals($this->item->id, $po->items->first()->item_id);
    }

    public function test_create_transfer_pipeline_dispatches_stock_transfer(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('inventory.replenishment.create-transfer'), [
            'from_branch_id' => $this->otherBranch->id,
            'to_branch_id' => $this->branch->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_items' => 1,
            'total_qty' => 5,
        ]);

        $transfer = StockTransfer::where('from_branch_id', $this->otherBranch->id)->latest('id')->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(5, $transfer->total_qty);
        $this->assertEquals('Dispatched', $transfer->status);
        $this->assertCount(1, $transfer->items);
        $this->assertEquals($this->item->id, $transfer->items->first()->item_id);
    }

    public function test_export_endpoint_downloads_excel_file(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.export', [
            'branch_id' => $this->branch->id,
            'status' => 'all',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_feature_flag_disabled_returns_404(): void
    {
        config(['replenishment.enabled' => false]);

        $response = $this->actingAs($this->user)->get(route('inventory.replenishment.index'));
        $response->assertStatus(404);
    }
}
