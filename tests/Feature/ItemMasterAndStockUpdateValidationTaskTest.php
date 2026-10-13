<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\FormFieldValidationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemMasterAndStockUpdateValidationTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FormFieldValidationSeeder::class);

        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('Owner');
    }

    public function test_item_create_view_does_not_prefill_pricing_with_zero(): void
    {
        $response = $this->actingAs($this->admin)->get(route('master.items.create'));

        $response->assertOk();
        // Check that pricing inputs are not value="0"
        $response->assertDontSee('name="cost_price" value="0"', false);
        $response->assertDontSee('name="landing_cost" value="0"', false);
        $response->assertDontSee('name="sell_price" value="0"', false);
        $response->assertDontSee('name="mrp" value="0"', false);

        // Check that status and store_pickup have unselected placeholder options
        $response->assertSee('Select Status');
        $response->assertSee('Select Store Pickup');
    }

    public function test_item_master_validations_fail_when_required_pricing_and_status_are_empty(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        // Ensure cost_price, landing_cost, sell_price, mrp, status, store_pickup are required
        FormFieldValidation::where('module_key', 'items')
            ->whereIn('field_name', ['cost_price', 'landing_cost', 'sell_price', 'mrp', 'status', 'store_pickup'])
            ->update(['is_required' => true]);

        $payload = [
            'name' => 'New Test Item',
            'product_type' => 'Standard',
            'cost_price' => '',
            'landing_cost' => '',
            'sell_price' => '',
            'mrp' => '',
            'status' => '',
            'store_pickup' => '',
            'tax_inclusive' => '0',
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => '0',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('master.items.store'), $payload);

        $response->assertSessionHasErrors(['cost_price', 'landing_cost', 'sell_price', 'mrp', 'status', 'store_pickup']);
    }

    public function test_item_can_be_edited_without_ean_upc_code_when_not_required(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        // ean_upc_code and item_code are not required
        FormFieldValidation::where('module_key', 'items')
            ->whereIn('field_name', ['ean_upc_code', 'item_code'])
            ->update(['is_required' => false]);

        $item = Item::create([
            'name' => 'Existing Item No Barcode',
            'product_type' => 'Standard',
            'cost_price' => 100,
            'landing_cost' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'status' => true,
            'store_pickup' => false,
            'tax_inclusive' => false,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => false,
            'ean_upc_code' => null,
            'item_code' => null,
        ]);

        $updatePayload = [
            'name' => 'Existing Item No Barcode Updated',
            'product_type' => 'Standard',
            'cost_price' => 110,
            'landing_cost' => 110,
            'sell_price' => 160,
            'mrp' => 160,
            'status' => '1',
            'store_pickup' => '0',
            'tax_inclusive' => '0',
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => '0',
            'ean_upc_code' => '', // Left empty by user
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('master.items.update', $item), $updatePayload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('master.items.index'));

        $item->refresh();
        $this->assertSame('Existing Item No Barcode Updated', $item->name);
        $this->assertNull($item->ean_upc_code);
    }

    public function test_item_can_be_edited_preserving_its_own_existing_barcode(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = Item::create([
            'name' => 'Existing Barcoded Item',
            'product_type' => 'Standard',
            'cost_price' => 50,
            'landing_cost' => 50,
            'sell_price' => 80,
            'mrp' => 80,
            'status' => true,
            'store_pickup' => false,
            'tax_inclusive' => false,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => false,
            'ean_upc_code' => 'BARCODE123456',
        ]);

        $updatePayload = [
            'name' => 'Existing Barcoded Item Renamed',
            'product_type' => 'Standard',
            'cost_price' => 50,
            'landing_cost' => 50,
            'sell_price' => 80,
            'mrp' => 80,
            'status' => '1',
            'store_pickup' => '0',
            'tax_inclusive' => '0',
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => '0',
            'ean_upc_code' => 'BARCODE123456', // Same barcode should pass unique rule ignore
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('master.items.update', $item), $updatePayload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('master.items.index'));

        $item->refresh();
        $this->assertSame('Existing Barcoded Item Renamed', $item->name);
        $this->assertSame('BARCODE123456', $item->ean_upc_code);
    }

    public function test_form_validations_page_does_not_lock_item_code_as_core(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('tools.form-validations.index', ['group' => 'master', 'module' => 'items']));

        $response->assertOk();

        // item_code should have an editable switch, not locked disabled core
        $itemCodeField = FormFieldValidation::where('module_key', 'items')->where('field_name', 'item_code')->first();
        $this->assertNotNull($itemCodeField);

        // id="req_{$itemCodeField->id}" should not be disabled
        $response->assertDontSee('id="req_' . $itemCodeField->id . '"' . "\n" . '                                                               name="fields[' . $itemCodeField->id . '][is_required]" value="1"' . "\n" . '                                                               checked disabled', false);
    }

    public function test_stock_update_create_contains_tab_navigation_to_open_item_modal(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('inventory.stock-updates.create'));

        $response->assertOk();
        $response->assertSee('checkAndOpenSuModal($nextCode)', false);
        $response->assertSee('checkAndOpenSuModal($newCode)', false);
    }
}
