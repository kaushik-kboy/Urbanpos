<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BarcodeStandardizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private GstTax $gst;
    private Item $item;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main POS Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');

        $this->gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => '18% GST']);

        $this->supplier = Supplier::create([
            'name' => 'Acme Supplies',
            'mobile' => '9876543210',
        ]);

        $this->item = Item::create([
            'name' => 'Premium Herbal Shampoo 200ml',
            'item_code' => 'SHMP001',
            'ean_upc_code' => '8901234567890',
            'supplier_id' => $this->supplier->id,
            'cost_price' => 150.00,
            'sell_price' => 220.00,
            'mrp' => 250.00,
            'gst_tax_id' => $this->gst->id,
            'status' => true,
        ]);

        $invoice = \App\Models\PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-001',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'status' => 'Approved',
            'total' => 1500,
        ]);

        $invoice->items()->create([
            'item_id' => $this->item->id,
            'qty' => 10,
            'cost_price' => 150.00,
            'sell_price' => 220.00,
            'mrp' => 250.00,
            'gst_percent' => 18,
        ]);
    }

    /**
     * Test sales bill lookup exact_match_only parameter behavior
     */
    public function test_sales_bill_lookup_exact_match_only(): void
    {
        // 1. Exact barcode lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('sales.sales-bills.lookup-item', [
            'query' => '8901234567890',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('found'));
        $this->assertEquals($this->item->id, $res->json('item.id'));

        // 2. Exact item_code lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('sales.sales-bills.lookup-item', [
            'query' => 'SHMP001',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('found'));
        $this->assertEquals($this->item->id, $res->json('item.id'));

        // 3. Partial item name search with exact_match_only => 1 fails (not found)
        $res = $this->actingAs($this->user)->getJson(route('sales.sales-bills.lookup-item', [
            'query' => 'Herbal Shampoo',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertFalse($res->json('found'));

        // 4. Partial item name search without exact_match_only succeeds
        $res = $this->actingAs($this->user)->getJson(route('sales.sales-bills.lookup-item', [
            'query' => 'Herbal Shampoo',
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('found'));
        $this->assertEquals($this->item->id, $res->json('item.id'));
    }

    /**
     * Test purchase invoice lookup exact_match_only parameter behavior
     */
    public function test_purchase_invoice_lookup_exact_match_only(): void
    {
        // 1. Exact barcode lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-invoices.lookup-item', [
            'query' => '8901234567890',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertEquals($this->item->id, $res->json('id'));

        // 2. Exact item_code lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-invoices.lookup-item', [
            'query' => 'SHMP001',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertEquals($this->item->id, $res->json('id'));

        // 3. Partial item name search with exact_match_only => 1 returns empty/null
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-invoices.lookup-item', [
            'query' => 'Herbal Shampoo',
            'exact_match_only' => 1,
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertEmpty($res->json());

        // 4. Partial item name search without exact_match_only succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-invoices.lookup-item', [
            'query' => 'Herbal Shampoo',
            'branch_id' => $this->branch->id,
        ]));
        $res->assertOk();
        $this->assertEquals($this->item->id, $res->json('id'));
    }

    /**
     * Test purchase return lookup exact_match_only parameter behavior
     */
    public function test_purchase_return_lookup_exact_match_only(): void
    {
        // 1. Exact barcode lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplier->id,
            'query' => '8901234567890',
            'exact_match_only' => 1,
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertEquals($this->item->id, $res->json('id'));

        // 2. Exact item_code lookup with exact_match_only => 1 succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplier->id,
            'query' => 'SHMP001',
            'exact_match_only' => 1,
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertEquals($this->item->id, $res->json('id'));

        // 3. Partial item name search with exact_match_only => 1 returns 404 (does not fallback to name)
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplier->id,
            'query' => 'Herbal Shampoo',
            'exact_match_only' => 1,
        ]));
        $res->assertStatus(404);

        // 4. Partial item name search without exact_match_only succeeds
        $res = $this->actingAs($this->user)->getJson(route('purchase.purchase-returns.lookup-item', [
            'supplier_id' => $this->supplier->id,
            'query' => 'Herbal Shampoo',
        ]));
        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertEquals($this->item->id, $res->json('id'));
    }
}
