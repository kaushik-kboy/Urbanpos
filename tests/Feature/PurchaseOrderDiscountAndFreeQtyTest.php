<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderDiscountAndFreeQtyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private GstTax $gst18;
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

        $this->gst18 = GstTax::firstOrCreate(
            ['percentage' => 18],
            ['name' => 'GST 18%', 'description' => '18% GST', 'status' => true]
        );

        $this->supplier = Supplier::create([
            'name' => 'National Distributor',
            'mobile' => '9876543210',
            'state' => 'Maharashtra',
        ]);
    }

    /**
     * Test PO with Free Qty saves effective_cost = (base - disc) / (qty + free_qty).
     */
    public function test_purchase_order_stores_free_qty_and_computes_landing_cost(): void
    {
        $item = Item::create([
            'name' => 'Organic Honey 500g',
            'item_code' => 'HNY001',
            'cost_price' => 100.00,
            'sell_price' => 150.00,
            'mrp' => 180.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $payload = [
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'freight' => 0,
            'round_off' => 0,
            'scheme_item_disc_amt' => 0,
            'other_disc_amt' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'free_qty' => 2,
                    'cost_price' => 100.00,
                    'sell_price' => 150.00,
                    'mrp' => 180.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-orders.store'), $payload);
        $response->assertSessionHasNoErrors();

        $po = PurchaseOrder::with('items')->latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals(12.0, (float) $po->total_qty); // 10 + 2 free
        $this->assertEquals(1180.0, (float) $po->total);   // 1000 + 180 GST

        $poItem = $po->items->first();
        $this->assertNotNull($poItem);
        $this->assertEquals(2.0, (float) $poItem->free_qty);
        // Landing cost = 1000 / 12 = 83.3333
        $this->assertEquals(83.3333, (float) $poItem->effective_cost);
    }

    /**
     * Test PO proportionally allocates Scheme ItemDiscAmt and OtherDiscAmt into effective_cost.
     */
    public function test_purchase_order_allocates_scheme_and_other_discount_proportionally_to_effective_cost(): void
    {
        $itemA = Item::create([
            'name' => 'Item Alpha',
            'item_code' => 'ALP001',
            'cost_price' => 100.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $itemB = Item::create([
            'name' => 'Item Beta',
            'item_code' => 'BET001',
            'cost_price' => 200.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Item A: 10 @ 100 = 1000 base
        // Item B: 10 @ 200 = 2000 base
        // Total base = 3000
        // Scheme Disc: 150, Other Disc: 150 => Total header disc = 300 (10% of base)
        // Item A allocated disc = 100 => net cost = 900 => effective_cost = 90.00
        // Item B allocated disc = 200 => net cost = 1800 => effective_cost = 180.00
        $payload = [
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'freight' => 50,
            'round_off' => 0,
            'scheme_item_disc_amt' => 150.00,
            'scheme_item_disc_percent' => 5.0,
            'other_disc_amt' => 150.00,
            'items' => [
                [
                    'item_id' => $itemA->id,
                    'qty' => 10,
                    'free_qty' => 0,
                    'cost_price' => 100.00,
                    'sell_price' => 150.00,
                    'mrp' => 180.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
                [
                    'item_id' => $itemB->id,
                    'qty' => 10,
                    'free_qty' => 0,
                    'cost_price' => 200.00,
                    'sell_price' => 250.00,
                    'mrp' => 300.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-orders.store'), $payload);
        $response->assertSessionHasNoErrors();

        $po = PurchaseOrder::with('items')->latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals(150.00, (float) $po->scheme_item_disc_amt);
        $this->assertEquals(150.00, (float) $po->other_disc_amt);

        // Lines net = 1000 + 180 GST + 2000 + 360 GST = 3540
        // Total = 3540 + 50 freight - 150 scheme - 150 other = 3290.00
        $this->assertEquals(3290.00, (float) $po->total);

        $lineA = $po->items->firstWhere('item_id', $itemA->id);
        $lineB = $po->items->firstWhere('item_id', $itemB->id);

        $this->assertEquals(90.00, (float) $lineA->effective_cost);
        $this->assertEquals(180.00, (float) $lineB->effective_cost);
    }

    /**
     * Test items API returns effective_cost, free_qty, and header discounts for seamless PO to Invoice bridging.
     */
    public function test_purchase_order_items_api_returns_effective_cost_and_header_discounts(): void
    {
        $item = Item::create([
            'name' => 'Item Gamma',
            'item_code' => 'GAM001',
            'cost_price' => 100.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'po_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'Against C-Form',
            'status' => 'Open',
            'scheme_item_disc_amt' => 50.00,
            'scheme_item_disc_percent' => 5.0,
            'other_disc_amt' => 20.00,
            'freight' => 10.00,
            'round_off' => 0,
            'total' => 1100.00,
        ]);

        $po->items()->create([
            'item_id' => $item->id,
            'qty' => 10,
            'free_qty' => 2,
            'cost_price' => 100.00,
            'effective_cost' => 77.50,
            'sell_price' => 150.00,
            'mrp' => 180.00,
            'disc_percent' => 0,
            'disc_amount' => 0,
            'gst_percent' => 18,
            'gst_tax_amount' => 180.00,
            'net_amount' => 1180.00,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('purchase.purchase-orders.items', $po));
        $this->assertEquals(50.0, (float) $response->json('purchase_order.scheme_item_disc_amt'));
        $this->assertEquals(20.0, (float) $response->json('purchase_order.other_disc_amt'));
        $this->assertEquals(2.0, (float) $response->json('items.0.free_qty'));
        $this->assertEquals(77.5, (float) $response->json('items.0.effective_cost'));
    }
}
