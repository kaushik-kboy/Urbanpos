<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstType;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GstAndProductTypeMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('Owner');
    }

    public function test_gst_type_master_crud_and_status(): void
    {
        $this->actingAs($this->admin);

        // 1. Create GST Type
        $response = $this->post(route('master.gst-types.store'), [
            'name' => 'Special Export GST',
            'description' => 'Zero-rated export GST',
            'status' => 1,
        ]);
        $response->assertRedirect(route('master.gst-types.index'));
        $this->assertDatabaseHas('gst_types', ['name' => 'Special Export GST']);

        // 2. Edit & Update GST Type
        $gstType = GstType::where('name', 'Special Export GST')->first();
        $this->assertNotNull($gstType);

        $updateResp = $this->put(route('master.gst-types.update', $gstType), [
            'name' => 'Special Export GST Updated',
            'description' => 'Updated desc',
            'status' => 1,
        ]);
        $updateResp->assertRedirect(route('master.gst-types.index'));
        $this->assertDatabaseHas('gst_types', ['name' => 'Special Export GST Updated']);
    }

    public function test_product_type_master_crud_and_status(): void
    {
        $this->actingAs($this->admin);

        // 1. Create Product Type
        $response = $this->post(route('master.product-types.store'), [
            'name' => 'Subscription Box',
            'description' => 'Recurring monthly subscription item',
            'status' => 1,
        ]);
        $response->assertRedirect(route('master.product-types.index'));
        $this->assertDatabaseHas('product_types', ['name' => 'Subscription Box']);

        // 2. Edit & Update Product Type
        $productType = ProductType::where('name', 'Subscription Box')->first();
        $this->assertNotNull($productType);

        $updateResp = $this->put(route('master.product-types.update', $productType), [
            'name' => 'Subscription Box Deluxe',
            'description' => 'Deluxe monthly box',
            'status' => 1,
        ]);
        $updateResp->assertRedirect(route('master.product-types.index'));
        $this->assertDatabaseHas('product_types', ['name' => 'Subscription Box Deluxe']);
    }

    public function test_supplier_form_accepts_dynamic_gst_type(): void
    {
        $this->actingAs($this->admin);

        GstType::create(['name' => 'Custom SEZ Type', 'status' => 1]);

        $response = $this->post(route('master.suppliers.store'), [
            'name' => 'SEZ Vendor Private Ltd',
            'gst_type' => 'Custom SEZ Type',
            'currency' => 'INR',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'status' => 1,
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
        ]);

        $response->assertRedirect(route('master.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'SEZ Vendor Private Ltd',
            'gst_type' => 'Custom SEZ Type',
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
        ]);
    }

    public function test_item_creation_accepts_dynamic_product_type(): void
    {
        $this->actingAs($this->admin);

        ProductType::create(['name' => 'Digital Token', 'status' => 1]);

        $response = $this->post(route('master.items.store'), [
            'name' => 'Game Token 100 Pack',
            'product_type' => 'Digital Token',
            'cost_price' => 50,
            'landing_cost' => 50,
            'sell_price' => 100,
            'mrp' => 120,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $this->assertDatabaseHas('items', [
            'name' => 'Game Token 100 Pack',
            'product_type' => 'Digital Token',
        ]);
    }

    public function test_pos_item_list_endpoint_returns_branch_stock_for_f2_modal(): void
    {
        $this->actingAs($this->admin);

        $item = \App\Models\Item::create([
            'name' => 'Sunflower Oil 1L',
            'item_code' => 'SFO1001',
            'ean_upc_code' => '8901234567890',
            'product_type' => 'Standard',
            'cost_price' => 100,
            'landing_cost' => 100,
            'sell_price' => 120,
            'mrp' => 130,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
        ]);

        $response = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'Sunflower',
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);
        $this->assertEquals($item->id, $data['items'][0]['id']);
        $this->assertEquals('Sunflower Oil 1L', $data['items'][0]['name']);
    }
}
