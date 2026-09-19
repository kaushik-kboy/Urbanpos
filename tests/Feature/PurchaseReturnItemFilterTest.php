<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseReturnItemFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Branch $branch;
    private Supplier $supplierA;
    private Supplier $supplierB;
    private Item $itemSupplierA;
    private Item $itemSupplierB;
    private Item $itemInvoicedA;
    private PurchaseInvoice $invoiceA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->supplierA = Supplier::create([
            'name' => 'Supplier Filter Test A',
            'phone' => '9100000001',
            'state' => 'Maharashtra',
            'credit_limit' => 50000,
        ]);

        $this->supplierB = Supplier::create([
            'name' => 'Supplier Filter Test B',
            'phone' => '9100000002',
            'state' => 'Maharashtra',
            'credit_limit' => 50000,
        ]);

        // Item 1: Directly belongs to Supplier A
        $this->itemSupplierA = Item::create([
            'name' => 'Product of Supplier A',
            'item_code' => 'PROD-SUP-A-01',
            'ean_upc_code' => '8901111111111',
            'supplier_id' => $this->supplierA->id,
            'cost_price' => 120,
            'sell_price' => 180,
            'mrp' => 200,
            'gst_tax_id' => $gst->id,
            'tax_inclusive' => false,
            'status' => true,
        ]);

        // Item 2: Belongs to Supplier B
        $this->itemSupplierB = Item::create([
            'name' => 'Product of Supplier B',
            'item_code' => 'PROD-SUP-B-01',
            'ean_upc_code' => '8902222222222',
            'supplier_id' => $this->supplierB->id,
            'cost_price' => 200,
            'sell_price' => 250,
            'mrp' => 300,
            'gst_tax_id' => $gst->id,
            'tax_inclusive' => false,
            'status' => true,
        ]);

        // Item 3: Belongs to Supplier A via a Purchase Invoice history
        $this->itemInvoicedA = Item::create([
            'name' => 'Invoice Product of Supplier A',
            'item_code' => 'PROD-INV-A-01',
            'ean_upc_code' => '8903333333333',
            'supplier_id' => null, // Not directly tagged on item
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 100,
            'gst_tax_id' => $gst->id,
            'tax_inclusive' => false,
            'status' => true,
        ]);

        // Create Purchase Invoice for Supplier A with itemInvoicedA
        $this->invoiceA = PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-FILTER-001',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplierA->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'status' => 'Received',
            'total' => 590,
            'total_gst' => 90,
            'total_qty' => 10,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $this->invoiceA->id,
            'item_id' => $this->itemInvoicedA->id,
            'qty' => 10,
            'cost_price' => 50,
            'gst_percent' => 18,
            'disc_percent' => 0,
            'disc_amount' => 0,
            'total' => 590,
        ]);

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Owner');
    }

    public function test_item_list_filters_by_supplier(): void
    {
        // When supplier A is passed, return items of supplier A (both direct and past invoiced), but NOT supplier B
        $response = $this->actingAs($this->manager)->getJson(route('purchase.purchase-returns.item-list', [
            'supplier_id' => $this->supplierA->id,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk();
        $items = $response->json('items');
        $itemIds = collect($items)->pluck('id')->all();

        $this->assertContains($this->itemSupplierA->id, $itemIds, 'Supplier A direct product must be included');
        $this->assertContains($this->itemInvoicedA->id, $itemIds, 'Supplier A past invoiced product must be included');
        $this->assertNotContains($this->itemSupplierB->id, $itemIds, 'Supplier B product must NOT be included');
    }

    public function test_item_list_filters_strictly_by_purchase_invoice(): void
    {
        // When invoiceA is passed, ONLY itemInvoicedA must be returned; itemSupplierA (not on invoice) must NOT be returned
        $response = $this->actingAs($this->manager)->getJson(route('purchase.purchase-returns.item-list', [
            'supplier_id' => $this->supplierA->id,
            'purchase_invoice_id' => $this->invoiceA->id,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk();
        $items = $response->json('items');
        $itemIds = collect($items)->pluck('id')->all();

        $this->assertContains($this->itemInvoicedA->id, $itemIds, 'Invoiced product must be included');
        $this->assertNotContains($this->itemSupplierA->id, $itemIds, 'Non-invoiced product must NOT be included even if same supplier');
        $this->assertNotContains($this->itemSupplierB->id, $itemIds, 'Foreign supplier product must NOT be included');
        $this->assertEquals('invoice', $response->json('source'));
    }

    public function test_lookup_item_prevents_selecting_wrong_supplier_product(): void
    {
        // Looking up Supplier B's product while Supplier A is selected -> should return 404
        $response = $this->actingAs($this->manager)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplierA->id,
            'query' => $this->itemSupplierB->item_code,
            'branch_id' => $this->branch->id,
        ]));

        $response->assertStatus(404);
        $this->assertFalse($response->json('success'));

        // Looking up Supplier A's product while Supplier A is selected -> should succeed
        $responseA = $this->actingAs($this->manager)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplierA->id,
            'query' => $this->itemSupplierA->item_code,
            'branch_id' => $this->branch->id,
        ]));

        $responseA->assertOk();
        $this->assertTrue($responseA->json('success'));
        $this->assertEquals($this->itemSupplierA->id, $responseA->json('id'));
    }

    public function test_store_validation_rejects_foreign_supplier_items(): void
    {
        // Attempting to return an item from Supplier B to Supplier A
        $payload = [
            'supplier_id' => $this->supplierA->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->itemSupplierB->id,
                    'qty' => 1,
                    'cost_price' => 200,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), $payload);
        $response->assertSessionHasErrors('items');
    }

    public function test_store_validation_rejects_items_not_on_selected_invoice(): void
    {
        // Attempting to return itemSupplierA when returning against invoiceA (which only has itemInvoicedA)
        $payload = [
            'supplier_id' => $this->supplierA->id,
            'purchase_invoice_id' => $this->invoiceA->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->itemSupplierA->id,
                    'qty' => 1,
                    'cost_price' => 120,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), $payload);
        $response->assertSessionHasErrors('items');
    }
}
