<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DynamicValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierDynamicValidationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Test Branch', 'code' => 'TEST', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);

        // Reset dynamic validation cache before each test
        app(DynamicValidationService::class)->clearCache('suppliers');
    }

    public function test_duplicate_gst_number_is_blocked_when_unique_toggle_is_on(): void
    {
        // Setup validation rule in DB: gstin is unique
        FormFieldValidation::updateOrCreate(
            ['module_key' => 'suppliers', 'field_name' => 'gst_no'],
            [
                'field_label' => 'GSTIN',
                'field_type' => 'text',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => true,
                'custom_error_message' => 'GSTIN is already registered.',
                'sort_order' => 3,
            ]
        );
        app(DynamicValidationService::class)->clearCache('suppliers');

        $gstin = '27AAPFU0939F1ZV';

        // 1. Create first supplier with this GST
        $response1 = $this->post(route('master.suppliers.store'), [
            'name' => 'Supplier Alpha',
            'gst_no' => $gstin,
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);
        $response1->assertRedirect(route('master.suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['name' => 'Supplier Alpha', 'gst_no' => $gstin]);

        // 2. Try creating second supplier with SAME GST -> must be rejected
        $response2 = $this->post(route('master.suppliers.store'), [
            'name' => 'Supplier Beta',
            'gst_no' => $gstin,
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);
        $response2->assertSessionHasErrors('gst_no');
        $this->assertEquals(
            'GSTIN is already registered.',
            session('errors')->first('gst_no')
        );
        $this->assertDatabaseMissing('suppliers', ['name' => 'Supplier Beta']);
    }

    public function test_editing_supplier_with_own_gst_is_allowed(): void
    {
        FormFieldValidation::updateOrCreate(
            ['module_key' => 'suppliers', 'field_name' => 'gst_no'],
            [
                'field_label' => 'GSTIN',
                'field_type' => 'text',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => true,
                'sort_order' => 3,
            ]
        );
        app(DynamicValidationService::class)->clearCache('suppliers');

        $gstin = '27AAPFU0939F1ZV';
        $supplier = Supplier::create([
            'name' => 'Supplier Alpha',
            'gst_no' => $gstin,
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        // Updating supplier's own record with the same GST must succeed (ignore own ID)
        $response = $this->put(route('master.suppliers.update', $supplier), [
            'name' => 'Supplier Alpha Updated',
            'gst_no' => $gstin,
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        $response->assertRedirect(route('master.suppliers.index'));
        $response->assertSessionMissing('errors');
        $this->assertEquals('Supplier Alpha Updated', $supplier->fresh()->name);
    }

    public function test_editing_supplier_to_use_another_suppliers_gst_is_blocked(): void
    {
        FormFieldValidation::updateOrCreate(
            ['module_key' => 'suppliers', 'field_name' => 'gst_no'],
            [
                'field_label' => 'GSTIN',
                'field_type' => 'text',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => true,
                'custom_error_message' => 'GSTIN belongs to another supplier.',
                'sort_order' => 3,
            ]
        );
        app(DynamicValidationService::class)->clearCache('suppliers');

        $supplier1 = Supplier::create([
            'name' => 'Supplier 1',
            'gst_no' => '27AAPFU0939F1ZV',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        $supplier2 = Supplier::create([
            'name' => 'Supplier 2',
            'gst_no' => '24ABCDE1234F1Z5',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        // Attempting to change Supplier 2's GST to Supplier 1's GST must fail
        $response = $this->put(route('master.suppliers.update', $supplier2), [
            'name' => 'Supplier 2',
            'gst_no' => '27AAPFU0939F1ZV',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('gst_no');
        $this->assertEquals(
            'GSTIN belongs to another supplier.',
            session('errors')->first('gst_no')
        );
    }

    public function test_alias_gstin_in_db_maps_to_gst_no_rule(): void
    {
        // Even if the DB column is named 'gstin' in form_field_validations, it maps to gst_no
        FormFieldValidation::updateOrCreate(
            ['module_key' => 'suppliers', 'field_name' => 'gstin'],
            [
                'field_label' => 'GSTIN',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => true,
                'custom_error_message' => 'GSTIN is required and must be unique.',
                'sort_order' => 3,
            ]
        );
        app(DynamicValidationService::class)->clearCache('suppliers');

        // Missing gst_no should fail required check
        $response = $this->post(route('master.suppliers.store'), [
            'name' => 'Supplier No GST',
            'gst_no' => '',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('gst_no');
        $this->assertEquals(
            'GSTIN is required and must be unique.',
            session('errors')->first('gst_no')
        );
    }

    public function test_duplicate_supplier_name_is_strictly_prevented(): void
    {
        Supplier::create([
            'name' => 'Royal Canin Distributors',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        // Attempt exact duplicate name
        $response1 = $this->post(route('master.suppliers.store'), [
            'name' => 'Royal Canin Distributors',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);
        $response1->assertSessionHasErrors('name');

        // Attempt duplicate name with whitespace
        $response2 = $this->post(route('master.suppliers.store'), [
            'name' => '  Royal Canin Distributors  ',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);
        $response2->assertSessionHasErrors('name');
    }

    public function test_duplicate_supplier_gst_is_strictly_prevented_by_default(): void
    {
        $gstin = '24ABCDE1234F1Z5';
        Supplier::create([
            'name' => 'Supplier One',
            'gst_no' => $gstin,
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);

        // Attempt duplicate GST on different supplier name
        $response = $this->post(route('master.suppliers.store'), [
            'name' => 'Supplier Two',
            'gst_no' => strtolower($gstin), // Should be normalized and still blocked as duplicate
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'gst_type' => 'Regular',
            'status' => 1,
        ]);
        $response->assertSessionHasErrors('gst_no');
        $this->assertDatabaseMissing('suppliers', ['name' => 'Supplier Two']);
    }
}
