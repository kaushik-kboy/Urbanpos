<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemNullablePricingTest extends TestCase
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

    public function test_item_can_be_created_without_cost_price_when_field_is_not_required(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        \App\Models\FormFieldValidation::updateOrCreate(
            ['module_key' => 'items', 'field_name' => 'cost_price'],
            [
                'field_label' => 'Cost Price',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'sort_order' => 5,
            ]
        );

        \App\Models\FormFieldValidation::updateOrCreate(
            ['module_key' => 'items', 'field_name' => 'sell_price'],
            [
                'field_label' => 'Sell Price',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'sort_order' => 6,
            ]
        );

        \App\Models\FormFieldValidation::updateOrCreate(
            ['module_key' => 'items', 'field_name' => 'mrp'],
            [
                'field_label' => 'MRP',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'sort_order' => 7,
            ]
        );

        \App\Models\FormFieldValidation::updateOrCreate(
            ['module_key' => 'items', 'field_name' => 'landing_cost'],
            [
                'field_label' => 'Landing Cost',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'sort_order' => 8,
            ]
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['description' => 'GST 18%', 'status' => true]);

        $payload = [
            'name' => 'Green color ' . uniqid(),
            'landing_cost' => 200,
            'cost_price' => null, // Omitted or not required
            'sell_price' => null,
            'mrp' => null,
            'gst_tax_id' => $gst->id,
            'hsn_code' => '67676767',
            'product_type' => 'Standard',
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 1,
            'batch_expiry_details' => 'Mandatory',
            'allow_negative_stock' => 0,
        ];

        // Store directly via controller/route
        $response = $this->actingAs($this->admin)->post(route('master.items.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('master.items.index'));

        $item = Item::where('name', $payload['name'])->first();
        $this->assertNotNull($item);
        // Cost price should default gracefully to landing cost or 0
        $this->assertEquals(200.00, (float) $item->cost_price);
        $this->assertEquals(200.00, (float) $item->landing_cost);
        $this->assertEquals(0.00, (float) $item->sell_price);
        $this->assertEquals(0.00, (float) $item->mrp);
    }
}
