<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseGrnSortAndItemPriceFixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->branch = Branch::create(['name' => 'Motera', 'status' => true]);
        $this->supplier = Supplier::create(['name' => 'Test Supplier', 'status' => true]);
        $this->user = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole('Owner');
    }

    public function test_purchase_invoice_listing_defaults_to_grn_number_desc(): void
    {
        // Create 2 invoices with distinct GRN numbers
        $inv1 = PurchaseInvoice::create([
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $this->supplier->id,
            'invoice_number'  => 'PINV-TEST-001',
            'supplier_inv_no' => 'SINV-001',
            'grn_number'      => 'GRN000001',
            'invoice_date'    => '2026-10-01',
            'total'           => 100,
            'final_amount'    => 100,
            'purchase_type'   => 'Local',
            'created_by'      => $this->user->id,
        ]);

        $inv2 = PurchaseInvoice::create([
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $this->supplier->id,
            'invoice_number'  => 'PINV-TEST-002',
            'supplier_inv_no' => 'SINV-002',
            'grn_number'      => 'GRN000002',
            'invoice_date'    => '2026-09-01', // Backdated invoice date
            'total'           => 200,
            'final_amount'    => 200,
            'purchase_type'   => 'Local',
            'created_by'      => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('purchase.purchase-invoices.index'));
        $response->assertOk();

        $invoices = $response->viewData('purchaseInvoices');
        $grns = $invoices->pluck('grn_number')->all();

        // In default GRN desc order, GRN000002 must come first even though it has older invoice_date
        $this->assertEquals(['GRN000002', 'GRN000001'], $grns);

        // Test explicit sorting works via query params
        $ascResponse = $this->actingAs($this->user)->get(route('purchase.purchase-invoices.index', ['sort' => 'grn_number', 'direction' => 'asc']));
        $ascResponse->assertOk();
        $ascGrns = $ascResponse->viewData('purchaseInvoices')->pluck('grn_number')->all();
        $this->assertEquals(['GRN000001', 'GRN000002'], $ascGrns);
    }

    public function test_megmazid_and_otisoothe_pricing_alignment_logic(): void
    {
        // Create Megmazid item with old mismatched prices
        $megmazid = Item::create([
            'item_code'   => '10393',
            'name'        => 'Megmazid 600 MG Tab',
            'cost_price'  => 252.00,
            'landing_cost'=> 252.00,
            'sell_price'  => 410.00,
            'mrp'         => 410.00,
        ]);

        $megStock = ItemStock::create([
            'item_id'    => $megmazid->id,
            'branch_id'  => $this->branch->id,
            'quantity'   => 40,
            'cost_price' => 21.00,
            'landing_cost'=> 21.00,
            'sell_price' => 38.10,
            'mrp'        => 38.10,
        ]);

        // Create Otisoothe with Motera over-MRP stock
        $otisoothe = Item::create([
            'item_code'   => '2953',
            'name'        => 'Otisoothe',
            'cost_price'  => 214.00,
            'landing_cost'=> 214.00,
            'sell_price'  => 450.00,
            'mrp'         => 450.00,
        ]);

        $otiMoteraStock = ItemStock::create([
            'item_id'    => $otisoothe->id,
            'branch_id'  => 1, // Motera
            'quantity'   => 5,
            'cost_price' => 196.82,
            'landing_cost'=> 196.82,
            'sell_price' => 450.00, // Illegal: > mrp
            'mrp'        => 400.00,
        ]);

        // Run alignment migration
        $migration = require database_path('migrations/2026_10_20_120000_align_megmazid_and_otisoothe_pricing.php');
        $migration->up();

        // Verify Megmazid master & stock aligned to per-tablet rate
        $megmazid->refresh();
        $megStock->refresh();
        $this->assertEquals(21.00, (float) $megmazid->cost_price);
        $this->assertEquals(38.10, (float) $megmazid->sell_price);
        $this->assertEquals(38.10, (float) $megmazid->mrp);
        $this->assertEquals(38.10, (float) $megStock->sell_price);
        $this->assertEquals(38.10, (float) $megStock->mrp);

        // Verify Otisoothe Motera stock sell_price <= mrp
        $otiMoteraStock->refresh();
        $this->assertEquals(400.00, (float) $otiMoteraStock->sell_price);
        $this->assertEquals(400.00, (float) $otiMoteraStock->mrp);
        $this->assertLessThanOrEqual((float) $otiMoteraStock->mrp, (float) $otiMoteraStock->sell_price);
    }
}
