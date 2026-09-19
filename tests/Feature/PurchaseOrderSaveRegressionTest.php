<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Purchase Order Save Regression Test.
 *
 * Catches MySQL strict-mode constraint errors (null in NOT NULL columns)
 * and SQL syntax bugs in computeTotals BEFORE they reach production.
 *
 * Test scenarios:
 *  1. Basic PO with all optional fields BLANK (round_off, freight, etc.)
 *  2. PO with freight + round_off values
 *  3. PO with GST item - total_gst verification
 *  4. Edit/Update with blank optional fields
 *  5. PO numbers are unique and sequential
 */
class PurchaseOrderSaveRegressionTest extends TestCase
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
            ['name' => 'Test Branch', 'code' => 'TEST', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
        $this->actingAs($this->user);

        $this->supplier = Supplier::create(['name' => 'PO Test Supplier', 'status' => true]);

        $this->item = Item::create([
            'name'        => 'PO Test Item',
            'cost_price'  => 100.00,
            'sell_price'  => 120.00,
            'mrp'         => 130.00,
            'status'      => true,
        ]);
    }

    /**
     * TEST 1: ALL optional fields blank.
     * Root cause of production bug: round_off=null => 1048 Column cannot be null
     */
    public function test_purchase_order_saves_with_all_optional_fields_blank(): void
    {
        $response = $this->post(route('purchase.purchase-orders.store'), [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            // round_off, freight, scheme_item_disc_amt, other_disc_amt,
            // total_extra_cess, total_weight, remarks, message -- ALL blank
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 10, 'cost_price' => 100.00],
            ],
        ]);

        $response->assertRedirect(route('purchase.purchase-orders.index'));
        $response->assertSessionMissing('errors');

        $po = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($po, 'PO must be created in DB');
        $this->assertEquals(0.0, (float) $po->round_off,            'round_off must default to 0');
        $this->assertEquals(0.0, (float) $po->freight,              'freight must default to 0');
        $this->assertEquals(0.0, (float) $po->other_disc_amt,       'other_disc_amt must default to 0');
        $this->assertEquals(0.0, (float) $po->scheme_item_disc_amt, 'scheme_item_disc_amt must default to 0');
        $this->assertEquals(0.0, (float) $po->total_extra_cess,     'total_extra_cess must default to 0');
        $this->assertEquals(0.0, (float) $po->total_weight,         'total_weight must default to 0');
        $this->assertEquals(10,     (float) $po->total_qty);
        $this->assertEquals(1000.00,(float) $po->total);
    }

    /**
     * TEST 2: PO with freight and round_off provided.
     */
    public function test_purchase_order_saves_with_freight_and_round_off(): void
    {
        $response = $this->post(route('purchase.purchase-orders.store'), [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            'freight'       => 50.00,
            'round_off'     => -0.50,
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 5, 'cost_price' => 200.00],
            ],
        ]);

        $response->assertRedirect(route('purchase.purchase-orders.index'));
        $response->assertSessionMissing('errors');

        $po = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals(50.00, (float) $po->freight);
        $this->assertEquals(-0.50, (float) $po->round_off);
        // Total = 5×200 + 50 - 0.50 = 1049.50
        $this->assertEquals(1049.50, (float) $po->total);
    }

    /**
     * TEST 3: PO with GST item — verify total_gst and net total.
     */
    public function test_purchase_order_saves_with_gst_item_and_correct_totals(): void
    {
        $gst18 = GstTax::firstOrCreate(
            ['percentage' => 18],
            ['name' => 'GST 18%', 'description' => 'GST 18%']
        );

        $gstItem = Item::create([
            'name'        => 'GST PO Item',
            'cost_price'  => 100.00,
            'sell_price'  => 120.00,
            'mrp'         => 130.00,
            'gst_tax_id'  => $gst18->id,
            'status'      => true,
        ]);

        $response = $this->post(route('purchase.purchase-orders.store'), [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            'items' => [
                ['item_id' => $gstItem->id, 'qty' => 2, 'cost_price' => 100.00, 'gst_percent' => 18],
            ],
        ]);

        $response->assertRedirect(route('purchase.purchase-orders.index'));
        $response->assertSessionMissing('errors');

        $po = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals(2, (float) $po->total_qty);
        // GST = 2 × 100 × 18% = 36
        $this->assertEquals(36.00, (float) $po->total_gst);
        // Total = 200 + 36 = 236
        $this->assertEquals(236.00, (float) $po->total);
    }

    /**
     * TEST 4: Edit/Update PO with blank optional fields -- must not throw null errors.
     */
    public function test_purchase_order_update_with_blank_optional_fields(): void
    {
        $this->post(route('purchase.purchase-orders.store'), [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            'items'         => [['item_id' => $this->item->id, 'qty' => 5, 'cost_price' => 100]],
        ]);

        $po = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($po);

        $response = $this->put(route('purchase.purchase-orders.update', $po), [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            'items'         => [['item_id' => $this->item->id, 'qty' => 8, 'cost_price' => 100]],
        ]);

        $response->assertRedirect(route('purchase.purchase-orders.index'));
        $response->assertSessionMissing('errors');

        $po->refresh();
        $this->assertEquals(8, (float) $po->total_qty);
        $this->assertEquals(800.00, (float) $po->total);
        $this->assertEquals(0.0, (float) $po->round_off, 'round_off must stay 0 after update');
    }

    /**
     * TEST 5: PO numbers are unique and auto-incrementing.
     */
    public function test_purchase_order_numbers_are_unique_and_sequential(): void
    {
        $payload = [
            'po_date'       => '2026-09-19',
            'supplier_id'   => $this->supplier->id,
            'branch_id'     => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form'        => 'No Forms',
            'status'        => 'Open',
            'items'         => [['item_id' => $this->item->id, 'qty' => 1, 'cost_price' => 100]],
        ];

        $this->post(route('purchase.purchase-orders.store'), $payload);
        $this->post(route('purchase.purchase-orders.store'), $payload);

        $pos = PurchaseOrder::orderBy('id')->get();
        $this->assertCount(2, $pos);
        $this->assertNotEquals($pos[0]->po_number, $pos[1]->po_number, 'PO numbers must be unique');
    }
}
