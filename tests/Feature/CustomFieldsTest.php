<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Test Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->ownerUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->ownerUser->assignRole('Owner');
        $this->actingAs($this->ownerUser);
    }

    public function test_gst_gstr_2_download_route_is_defined_and_accessible(): void
    {
        $response = $this->get(route('tools.gst.gstr-2-download', ['type' => '2A']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition') ?? '', 'GSTR_2A_Reconciliation'));
    }

    public function test_custom_fields_index_page_loads_properly(): void
    {
        $response = $this->get(route('tools.custom-fields.index'));

        $response->assertStatus(200);
        $response->assertSee('Custom Fields Builder');
        $response->assertSee('Customer Fields');
        $response->assertSee('Item Master Fields');
    }

    public function test_can_create_customer_and_item_custom_field_definitions(): void
    {
        // 1. Create a Customer Text Field: Pet Microchip Number
        $resCust = $this->post(route('tools.custom-fields.store'), [
            'module' => 'Customer',
            'field_name' => 'Pet Microchip Number',
            'field_type' => 'text',
            'is_required' => 0,
            'sort_order' => 1,
            'status' => 1,
        ]);

        $resCust->assertRedirect();
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'Customer',
            'field_name' => 'Pet Microchip Number',
            'field_key' => 'pet_microchip_number',
            'field_type' => 'text',
        ]);

        // 2. Create an Item Field: Shelf Rack Location
        $resItem = $this->post(route('tools.custom-fields.store'), [
            'module' => 'Item',
            'field_name' => 'Rack / Shelf Location',
            'field_type' => 'text',
            'is_required' => 0,
            'sort_order' => 1,
            'status' => 1,
        ]);

        $resItem->assertRedirect();
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'Item',
            'field_name' => 'Rack / Shelf Location',
            'field_key' => 'rack_shelf_location',
        ]);
    }

    public function test_can_create_select_field_with_options(): void
    {
        $response = $this->post(route('tools.custom-fields.store'), [
            'module' => 'Customer',
            'field_name' => 'Dog Breed Category',
            'field_type' => 'select',
            'options' => "Golden Retriever, Labrador, Beagle, Husky",
            'is_required' => 1,
            'sort_order' => 2,
        ]);

        $response->assertRedirect();
        $def = CustomFieldDefinition::where('field_key', 'dog_breed_category')->first();
        $this->assertNotNull($def);
        $this->assertEquals(['Golden Retriever', 'Labrador', 'Beagle', 'Husky'], $def->options);
        $this->assertTrue($def->is_required);
    }

    public function test_customer_creation_persists_and_retrieves_custom_fields(): void
    {
        $chipDef = CustomFieldDefinition::create([
            'module' => 'Customer',
            'field_name' => 'Pet Microchip Number',
            'field_key' => 'pet_microchip_number',
            'field_type' => 'text',
            'status' => true,
        ]);

        $dobDef = CustomFieldDefinition::create([
            'module' => 'Customer',
            'field_name' => 'Vaccination Due Date',
            'field_key' => 'vaccination_due_date',
            'field_type' => 'date',
            'status' => true,
        ]);

        $postData = [
            'name' => 'Rohan Sharma',
            'mobile' => '9876543210',
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 50000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 30,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 1,
            'customer_type' => 'RETAIL INVOICE',
            'custom_fields' => [
                'pet_microchip_number' => 'CHIP-998811',
                'vaccination_due_date' => '2026-10-15',
            ],
        ];

        $response = $this->post(route('master.customers.store'), $postData);
        $response->assertRedirect(route('master.customers.index'));

        $customer = Customer::where('mobile', '9876543210')->first();
        $this->assertNotNull($customer);

        // Verify values stored in polymorphic custom_field_values table
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $chipDef->id,
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
            'value' => 'CHIP-998811',
        ]);

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $dobDef->id,
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
            'value' => '2026-10-15',
        ]);

        // Verify model helper methods
        $this->assertEquals('CHIP-998811', $customer->getCustomFieldValue('pet_microchip_number'));
        $this->assertEquals('2026-10-15', $customer->getCustomFieldValue('vaccination_due_date'));

        // Test updating custom fields
        $updateData = array_merge($postData, [
            'name' => 'Rohan Sharma Updated',
            'custom_fields' => [
                'pet_microchip_number' => 'CHIP-UPDATED-001',
                'vaccination_due_date' => '2026-11-20',
            ],
        ]);

        $updateResponse = $this->put(route('master.customers.update', $customer), $updateData);
        $updateResponse->assertRedirect(route('master.customers.index'));

        $customer->refresh();
        $this->assertEquals('CHIP-UPDATED-001', $customer->getCustomFieldValue('pet_microchip_number'));
        $this->assertEquals('2026-11-20', $customer->getCustomFieldValue('vaccination_due_date'));
    }

    public function test_item_creation_persists_custom_fields(): void
    {
        $rackDef = CustomFieldDefinition::create([
            'module' => 'Item',
            'field_name' => 'Rack / Shelf Location',
            'field_key' => 'rack_shelf_location',
            'field_type' => 'text',
            'status' => true,
        ]);

        $itemData = [
            'name' => 'Royal Canin Maxi Puppy 4kg',
            'product_type' => 'Standard',
            'cost_price' => 1200,
            'landing_cost' => 1200,
            'sell_price' => 1500,
            'mrp' => 1600,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
            'custom_fields' => [
                'rack_shelf_location' => 'Row-3, Shelf-B',
            ],
        ];

        $response = $this->post(route('master.items.store'), $itemData);
        $response->assertRedirect(route('master.items.index'));

        $item = Item::where('name', 'Royal Canin Maxi Puppy 4kg')->first();
        $this->assertNotNull($item);

        $this->assertEquals('Row-3, Shelf-B', $item->getCustomFieldValue('rack_shelf_location'));
    }

    public function test_can_toggle_and_delete_custom_field_definition(): void
    {
        $field = CustomFieldDefinition::create([
            'module' => 'Customer',
            'field_name' => 'Temporary Test Field',
            'field_key' => 'temp_test_field',
            'field_type' => 'text',
            'status' => true,
        ]);

        // Toggle Status
        $toggleRes = $this->post(route('tools.custom-fields.toggle', $field));
        $toggleRes->assertRedirect();
        $this->assertFalse($field->fresh()->status);

        // Delete Field
        $delRes = $this->delete(route('tools.custom-fields.destroy', $field));
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('custom_field_definitions', ['id' => $field->id]);
    }
}
