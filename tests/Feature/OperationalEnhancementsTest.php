<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\ReplenishmentCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Owner');
        $this->actingAs($admin);
    }
    public function test_service_item_can_be_billed_without_negative_stock_error()
    {
        $branch = Branch::first() ?? Branch::create(['name' => 'Test Branch', 'code' => 'TB', 'status' => 1]);

        $service = Item::create([
            'name' => 'Pickup and Drop Service - 5',
            'item_code' => 'TEST_SRV_01',
            'product_type' => 'Service',
            'cost_price' => 1.0,
            'sell_price' => 500.0,
            'mrp' => 500.0,
            'status' => true,
            'allow_negative_stock' => false, // even if false, isService() should allow it
        ]);

        $this->assertTrue($service->isService());

        // StockLedgerService::post outgoing movement with 0 stock should NOT throw negative stock error
        $stockLedger = app(StockLedgerService::class);
        $row = $stockLedger->post(
            itemId: $service->id,
            branchId: $branch->id,
            movementType: 'SALE',
            qtyDelta: -1.0,
            unitCost: 1.0,
            referenceType: Item::class,
            referenceId: $service->id,
            documentDate: now()->toDateString(),
        );

        $this->assertNotNull($row);
        $this->assertEquals(1.0, $row->qty_out);
    }

    public function test_customer_address_can_be_updated_and_cleared_from_pos_modal()
    {
        $admin = User::first() ?? User::factory()->create();
        $this->actingAs($admin);

        $customer = Customer::create([
            'name' => 'POS Address Test User',
            'mobile' => '9999888877',
            'status' => true,
        ]);

        // 1. Add address from POS quick customer modal
        $response = $this->postJson(route('sales.sales-bills.quick-customer-update', $customer->id), [
            'name' => 'POS Address Test User',
            'mobile' => '9999888877',
            'address1' => 'Shop 42, CG Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'google_map_link' => 'https://maps.google.com/?q=23.03,72.56',
        ]);

        $response->assertOk();
        $customer->refresh();
        $this->assertEquals('Shop 42, CG Road', $customer->address1);
        $this->assertEquals(1, $customer->addresses()->count());
        $primary = $customer->addresses()->where('is_primary', true)->first();
        $this->assertEquals('Shop 42, CG Road', $primary->address_line);
        $this->assertEquals('https://maps.google.com/?q=23.03,72.56', $primary->google_map_link);

        // 2. Clear/empty address from POS quick customer modal
        $response2 = $this->postJson(route('sales.sales-bills.quick-customer-update', $customer->id), [
            'name' => 'POS Address Test User',
            'mobile' => '9999888877',
            'address1' => '', // emptied
            'google_map_link' => '',
        ]);

        $response2->assertOk();
        $customer->refresh();
        $this->assertNull($customer->address1);
        $this->assertEquals(0, $customer->addresses()->count());
    }

    public function test_supplier_po_print_format_renders_supplier_details_and_item_details()
    {
        $admin = User::where('email', 'admin@urbanpos.com')->first() ?? User::first();
        $this->actingAs($admin);

        $branch = Branch::first() ?? Branch::create(['name' => 'Main Motera', 'code' => 'MM', 'status' => 1]);
        $supplier = Supplier::create([
            'name' => 'Apex Pet Supplies',
            'address' => 'Plot 101, GIDC Naroda, Ahmedabad',
            'phone' => '9876543210',
            'status' => true,
        ]);

        $item = Item::create([
            'name' => 'Royal Canin Maxi Adult 15kg',
            'item_code' => 'RC_MAXI_15',
            'product_type' => 'Standard',
            'cost_price' => 7500,
            'sell_price' => 9500,
            'mrp' => 9500,
            'status' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-0099',
            'po_date' => now()->toDateString(),
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'status' => 'Active',
            'total' => 19000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty' => 2,
            'cost_price' => 7500,
            'sell_price' => 9500,
            'mrp' => 9500,
            'net_amount' => 15000,
        ]);

        // Request supplier-facing format
        $response = $this->get(route('purchase.purchase-orders.print', ['purchase_order' => $po->id, 'format' => 'supplier']));
        $response->assertOk();
        $response->assertSee('Apex Pet Supplies');
        $response->assertSee('9876543210');
        $response->assertSee('PO-TEST-0099');
        $response->assertSee('RC_MAXI_15');
        $response->assertSee('Royal Canin Maxi Adult 15kg');
        $response->assertSee('9,500.00'); // MRP
        $response->assertSee('Order Qty');
    }
}
