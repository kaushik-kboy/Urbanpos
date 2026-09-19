<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DynamicValidationService;
use Database\Seeders\FormFieldValidationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormValidationsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Supplier $supplier;
    private Item $item;

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

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->supplier = Supplier::create([
            'name' => 'Smoke Test Supplier',
            'phone' => '02211223344',
            'mobile' => '9820000001',
            'state' => 'Maharashtra',
            'credit_limit' => 50000,
        ]);

        $this->item = Item::create([
            'name' => 'Smoke Test Product',
            'item_code' => 'SMK-ITEM-001',
            'ean_upc_code' => '8909999999999',
            'supplier_id' => $this->supplier->id,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $gst->id,
            'status' => true,
        ]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);

        // Seed all 237 form field validations
        (new FormFieldValidationSeeder())->run();
    }

    public function test_all_18_modules_form_validations_page_loads_with_http_200(): void
    {
        $allModules = [
            'purchase' => ['purchase_invoices', 'purchase_orders', 'purchase_receipt_notes', 'purchase_indents', 'purchase_returns'],
            'sales' => ['sales_bills', 'sales_returns', 'sales_quotations', 'sales_orders', 'sales_delivery_notes'],
            'inventory' => ['stock_transfers', 'opening_stocks', 'damage_stocks', 'stock_updates'],
            'master' => ['customers', 'suppliers', 'items', 'branches'],
        ];

        foreach ($allModules as $group => $modules) {
            foreach ($modules as $module) {
                $response = $this->get(route('tools.form-validations.index', [
                    'group' => $group,
                    'module' => $module,
                ]));

                $response->assertOk();
                $response->assertViewIs('tools.form-validations.index');
                $response->assertViewHas('activeModule', $module);
                $response->assertViewHas('activeGroup', $group);

                // Assert at least 3 fields exist for each module
                $fields = $response->viewData('fields');
                $this->assertGreaterThanOrEqual(3, $fields->count(), "Module '{$module}' must have configured fields.");
            }
        }
    }

    public function test_form_validations_update_saves_and_clears_cache(): void
    {
        $field = FormFieldValidation::where('module_key', 'purchase_orders')
            ->where('field_name', 'remarks')
            ->firstOrFail();

        // Update remarks to required with custom message
        $payload = [
            'module_key' => 'purchase_orders',
            'fields' => [
                $field->id => [
                    'is_required' => '1',
                    'block_future_date' => '0',
                    'is_readonly' => '0',
                    'is_unique' => '0',
                    'custom_error_message' => 'PO remarks must be entered by staff.',
                ],
            ],
        ];

        $response = $this->post(route('tools.form-validations.update'), $payload);
        $response->assertRedirect(route('tools.form-validations.index', ['module' => 'purchase_orders']));

        $field->refresh();
        $this->assertTrue((bool) $field->is_required);
        $this->assertEquals('PO remarks must be entered by staff.', $field->custom_error_message);
    }

    public function test_dynamic_required_validation_enforced_on_purchase_orders(): void
    {
        // 1. Make remarks required dynamically on purchase_orders
        FormFieldValidation::where('module_key', 'purchase_orders')
            ->where('field_name', 'remarks')
            ->update([
                'is_required' => true,
                'custom_error_message' => 'Purchase Order remarks are mandatory for audit.',
            ]);
        app(DynamicValidationService::class)->clearCache('purchase_orders');

        // 2. Submit PO without remarks -> must fail
        $payload = [
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'remarks' => '', // empty!
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'cost_price' => 100,
                    'sell_price' => 150,
                    'mrp' => 160,
                ],
            ],
        ];

        $response = $this->post(route('purchase.purchase-orders.store'), $payload);
        $response->assertSessionHasErrors(['remarks']);

        // 3. Make remarks optional again
        FormFieldValidation::where('module_key', 'purchase_orders')
            ->where('field_name', 'remarks')
            ->update(['is_required' => false]);
        app(DynamicValidationService::class)->clearCache('purchase_orders');

        // 4. Submit again -> must succeed
        $response2 = $this->post(route('purchase.purchase-orders.store'), $payload);
        $response2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_dynamic_block_future_date_enforced_on_purchase_returns(): void
    {
        FormFieldValidation::where('module_key', 'purchase_returns')
            ->where('field_name', 'return_date')
            ->update([
                'block_future_date' => true,
                'custom_error_message' => 'Cannot create purchase returns in the future.',
            ]);
        app(DynamicValidationService::class)->clearCache('purchase_returns');

        $payload = [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->addDays(5)->toDateString(), // Future date!
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'cost_price' => 100,
                ],
            ],
        ];

        $response = $this->post(route('purchase.purchase-returns.store'), $payload);
        $response->assertSessionHasErrors(['return_date']);
    }

    public function test_dynamic_unique_validation_enforced_on_suppliers(): void
    {
        // Setup phone as unique on suppliers
        FormFieldValidation::where('module_key', 'suppliers')
            ->where('field_name', 'phone')
            ->update([
                'is_unique' => true,
                'custom_error_message' => 'Supplier phone number is already registered.',
            ]);
        app(DynamicValidationService::class)->clearCache('suppliers');

        // Attempt to create another supplier with existing phone: 02211223344
        $payload = [
            'name' => 'Duplicate Phone Supplier',
            'phone' => '02211223344',
            'currency' => 'INR',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'credit_limit' => 1000,
            'credit_balance' => 0,
            'credit_days' => 30,
            'status' => 1,
            'gst_type' => 'Regular',
            'mail_type' => 'None',
        ];

        $response = $this->post(route('master.suppliers.store'), $payload);
        $response->assertSessionHasErrors(['phone']);
    }

    public function test_reset_action_restores_module_defaults(): void
    {
        // Alter purchase_indents priority rule
        FormFieldValidation::where('module_key', 'purchase_indents')
            ->where('field_name', 'remarks')
            ->update(['is_required' => true]);

        $this->post(route('tools.form-validations.reset'), ['module_key' => 'purchase_indents']);

        $field = FormFieldValidation::where('module_key', 'purchase_indents')
            ->where('field_name', 'remarks')
            ->first();

        $this->assertFalse((bool) $field->is_required, 'Remarks should be reset to default (optional).');
    }
}
