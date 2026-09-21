<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\PurchaseOrder;
use App\Models\SalesBill;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UniversalCustomFieldsTest extends TestCase
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

    public function test_can_create_custom_field_definitions_across_all_categories(): void
    {
        // 1. Masters category - Supplier
        $resSupplier = $this->post(route('tools.custom-fields.store'), [
            'module' => 'Supplier',
            'field_name' => 'Vendor Tier Rating',
            'field_type' => 'select',
            'options' => 'Gold, Silver, Bronze',
            'is_required' => 0,
            'status' => 1,
        ]);
        $resSupplier->assertRedirect(route('tools.custom-fields.index', ['tab' => 'Supplier']));

        // 2. Sales category - SalesBill
        $resSales = $this->post(route('tools.custom-fields.store'), [
            'module' => 'SalesBill',
            'field_name' => 'Doctor Prescription Number',
            'field_type' => 'text',
            'is_required' => 0,
            'status' => 1,
        ]);
        $resSales->assertRedirect(route('tools.custom-fields.index', ['tab' => 'SalesBill']));

        // 3. Purchase category - PurchaseOrder
        $resPurchase = $this->post(route('tools.custom-fields.store'), [
            'module' => 'PurchaseOrder',
            'field_name' => 'Transporter Bilty Number',
            'field_type' => 'text',
            'is_required' => 0,
            'status' => 1,
        ]);
        $resPurchase->assertRedirect(route('tools.custom-fields.index', ['tab' => 'PurchaseOrder']));

        // 4. Inventory category - StockTransfer
        $resInventory = $this->post(route('tools.custom-fields.store'), [
            'module' => 'StockTransfer',
            'field_name' => 'Vehicle Dispatch Number',
            'field_type' => 'text',
            'is_required' => 0,
            'status' => 1,
        ]);
        $resInventory->assertRedirect(route('tools.custom-fields.index', ['tab' => 'StockTransfer']));

        // Verify definitions exist in DB
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'Supplier',
            'field_key' => 'vendor_tier_rating',
        ]);
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'SalesBill',
            'field_key' => 'doctor_prescription_number',
        ]);
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'PurchaseOrder',
            'field_key' => 'transporter_bilty_number',
        ]);
        $this->assertDatabaseHas('custom_field_definitions', [
            'module' => 'StockTransfer',
            'field_key' => 'vehicle_dispatch_number',
        ]);
    }

    public function test_supplier_creation_persists_custom_fields(): void
    {
        $def = CustomFieldDefinition::create([
            'module' => 'Supplier',
            'field_name' => 'Vendor Tax Rep',
            'field_key' => 'vendor_tax_rep',
            'field_type' => 'text',
            'status' => true,
        ]);

        $postData = [
            'name' => 'Royal Canin Distributors',
            'currency' => 'INR',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'credit_limit' => 200000,
            'credit_balance' => 0,
            'credit_days' => 45,
            'gst_type' => 'Regular',
            'mail_type' => 'None',
            'status' => 1,
            'custom_fields' => [
                'vendor_tax_rep' => 'Amit Verma (Tax Head)',
            ],
        ];

        $response = $this->post(route('master.suppliers.store'), $postData);
        $response->assertRedirect(route('master.suppliers.index'));

        $supplier = Supplier::where('name', 'Royal Canin Distributors')->first();
        $this->assertNotNull($supplier);

        // Verify stored in polymorphic custom_field_values table
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $def->id,
            'entity_type' => Supplier::class,
            'entity_id' => $supplier->id,
            'value' => 'Amit Verma (Tax Head)',
        ]);

        $this->assertEquals('Amit Verma (Tax Head)', $supplier->getCustomFieldValue('vendor_tax_rep'));
    }

    public function test_sales_bill_persists_custom_fields_via_trait_lifecycle(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Walkin Customer',
            'mobile' => '9999999991',
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'status' => 1,
        ]);

        $def = CustomFieldDefinition::create([
            'module' => 'SalesBill',
            'field_name' => 'Delivery Slot',
            'field_key' => 'delivery_slot',
            'field_type' => 'text',
            'status' => true,
        ]);

        $bill = new SalesBill([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'bill_number' => 'SB-TEST-001',
            'bill_date' => now(),
            'total_amount' => 500.00,
            'final_total' => 500.00,
        ]);

        // Simulate request input
        request()->merge([
            'custom_fields' => [
                'delivery_slot' => 'Evening 4PM - 7PM',
            ],
        ]);

        $bill->save();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $def->id,
            'entity_type' => SalesBill::class,
            'entity_id' => $bill->id,
            'value' => 'Evening 4PM - 7PM',
        ]);

        $this->assertEquals('Evening 4PM - 7PM', $bill->getCustomFieldValue('delivery_slot'));
    }

    public function test_purchase_order_persists_custom_fields_via_trait_lifecycle(): void
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier PO',
            'purchase_type' => 'Local',
            'purchase_mode' => 'Credit',
            'currency' => 'INR',
            'status' => 1,
        ]);

        $def = CustomFieldDefinition::create([
            'module' => 'PurchaseOrder',
            'field_name' => 'Gate Pass Number',
            'field_key' => 'gate_pass_number',
            'field_type' => 'text',
            'status' => true,
        ]);

        $po = new PurchaseOrder([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-001',
            'po_date' => now(),
            'total_amount' => 12000.00,
        ]);

        request()->merge([
            'custom_fields' => [
                'gate_pass_number' => 'GP-2026-9901',
            ],
        ]);

        $po->save();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $def->id,
            'entity_type' => PurchaseOrder::class,
            'entity_id' => $po->id,
            'value' => 'GP-2026-9901',
        ]);

        $this->assertEquals('GP-2026-9901', $po->getCustomFieldValue('gate_pass_number'));
    }

    public function test_stock_transfer_persists_custom_fields_via_trait_lifecycle(): void
    {
        $branch2 = Branch::firstOrCreate(
            ['id' => 2],
            ['name' => 'Secondary Branch', 'code' => 'SEC', 'state' => 'Maharashtra']
        );

        $def = CustomFieldDefinition::create([
            'module' => 'StockTransfer',
            'field_name' => 'Vehicle Seal No',
            'field_key' => 'vehicle_seal_no',
            'field_type' => 'text',
            'status' => true,
        ]);

        $st = new StockTransfer([
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $branch2->id,
            'transfer_number' => 'ST-TEST-001',
            'transfer_date' => now(),
            'status' => 'Dispatched',
        ]);

        request()->merge([
            'custom_fields' => [
                'vehicle_seal_no' => 'SEAL-MH-4402',
            ],
        ]);

        $st->save();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $def->id,
            'entity_type' => StockTransfer::class,
            'entity_id' => $st->id,
            'value' => 'SEAL-MH-4402',
        ]);

        $this->assertEquals('SEAL-MH-4402', $st->getCustomFieldValue('vehicle_seal_no'));
    }

    public function test_models_without_custom_fields_save_normally_with_zero_impact(): void
    {
        // Clear request
        request()->replace([]);

        $customer = \App\Models\Customer::create([
            'name' => 'Walkin Customer 2',
            'mobile' => '9999999992',
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'status' => 1,
        ]);

        $bill = new SalesBill([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'bill_number' => 'SB-CLEAN-001',
            'bill_date' => now(),
            'total_amount' => 100.00,
            'final_total' => 100.00,
        ]);
        $bill->save();

        $this->assertDatabaseHas('sales_bills', [
            'bill_number' => 'SB-CLEAN-001',
        ]);
        $this->assertDatabaseMissing('custom_field_values', [
            'entity_type' => SalesBill::class,
            'entity_id' => $bill->id,
        ]);
    }
}
