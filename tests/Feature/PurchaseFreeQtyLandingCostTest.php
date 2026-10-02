<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseFreeQtyLandingCostTest extends TestCase
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
     * Test that Free Qty reduces the effective unit cost (Landing Cost),
     * posts accurate physical stock in StockLedger, and updates Item & ItemStock landing_cost.
     */
    public function test_free_qty_computes_accurate_landing_cost_and_stock_ledger_valuation(): void
    {
        $item = Item::create([
            'name' => 'Pro Organic Green Tea 100g',
            'item_code' => 'TEA001',
            'cost_price' => 100.00,
            'landing_cost' => 100.00,
            'sell_price' => 150.00,
            'mrp' => 180.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Purchase: 10 billed + 2 free @ Rs 100 per billed unit
        // Basic Cost = 10 * 100 = 1,000
        // Total physical units = 12
        // Effective Landing Cost = 1,000 / 12 = 83.3333
        // Taxable = 1,000, GST 18% = 180, Total Net = 1,180
        $payload = [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => 1180.00,
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

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-invoices.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchase.purchase-invoices.index'));

        $invoice = PurchaseInvoice::with('items')->latest('id')->firstOrFail();
        $this->assertEquals(1180.00, (float) $invoice->total);
        $this->assertEquals(12.00, (float) $invoice->total_qty);

        // Verify PurchaseInvoiceItem effective_cost
        $itemLine = $invoice->items->first();
        $this->assertEquals(10, (float) $itemLine->qty);
        $this->assertEquals(2, (float) $itemLine->free_qty);
        $this->assertEquals(100.00, (float) $itemLine->cost_price);
        $this->assertEquals(83.3333, round((float) $itemLine->effective_cost, 4));

        // Verify StockLedger entry: 12 units intake at effective unitCost 83.33
        $ledger = StockLedger::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->where('item_id', $item->id)
            ->firstOrFail();

        $this->assertEquals(12.00, (float) $ledger->qty_in);
        $this->assertEquals(83.33, round((float) $ledger->unit_cost, 2));
        // Total intake value in ledger must be ~1,000 (not 12 * 100 = 1,200!)
        $this->assertEquals(1000.00, round((float) $ledger->value_in, 2));

        // Verify Item master prices
        $item->refresh();
        $this->assertEquals(100.00, (float) $item->cost_price);
        $this->assertEquals(83.33, round((float) $item->landing_cost, 2));

        // Verify ItemStock
        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertEquals(12.00, (float) $stock->quantity);
        $this->assertEquals(83.33, round((float) $stock->landing_cost, 2));
    }

    /**
     * Test combined Free Qty and line discount calculation:
     * 10 billed + 2 free @ 100 with 10% discount = Rs 900 net cost for 12 units => Landing Cost = 75.00
     */
    public function test_free_qty_with_discount_computes_exact_landing_cost(): void
    {
        $item = Item::create([
            'name' => 'Premium Almond Milk 1L',
            'item_code' => 'MLK002',
            'cost_price' => 100.00,
            'landing_cost' => 100.00,
            'sell_price' => 140.00,
            'mrp' => 150.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Base = 10 * 100 = 1000, 10% disc = 100, Base after disc = 900
        // Total units = 10 + 2 = 12
        // Effective Landing Cost = 900 / 12 = 75.00
        // Taxable = 900, GST 18% = 162, Total Net = 1,062
        $payload = [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => 1062.00,
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'free_qty' => 2,
                    'cost_price' => 100.00,
                    'sell_price' => 140.00,
                    'mrp' => 150.00,
                    'disc_percent' => 10,
                    'disc_amount' => 100.00,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-invoices.store'), $payload);
        $response->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::with('items')->latest('id')->firstOrFail();
        $itemLine = $invoice->items->first();
        $this->assertEquals(75.00, round((float) $itemLine->effective_cost, 2));

        $item->refresh();
        $this->assertEquals(75.00, round((float) $item->landing_cost, 2));

        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertEquals(12.00, (float) $stock->quantity);
        $this->assertEquals(75.00, round((float) $stock->landing_cost, 2));
    }

    /**
     * Test that selling price below nominal cost price (100) is accepted
     * when Free Qty brings the effective landing cost (83.33) below the selling price (95).
     */
    public function test_sell_price_validation_respects_landing_cost_with_free_qty(): void
    {
        $item = Item::create([
            'name' => 'Whole Wheat Bread 400g',
            'item_code' => 'BRD003',
            'cost_price' => 100.00,
            'sell_price' => 95.00,
            'mrp' => 110.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // With 10 billed + 2 free @ 100, landing cost is 83.33.
        // Sell price of Rs 95.00 is greater than Landing Cost (83.33), so it is profitable!
        // Taxable = 1,000, GST 18% = 180, Total Net = 1,180
        $payload = [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => 1180.00,
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'free_qty' => 2,
                    'cost_price' => 100.00,
                    'sell_price' => 95.00,
                    'mrp' => 110.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-invoices.store'), $payload);
        $response->assertSessionHasNoErrors();
    }

    /**
     * Test that Scheme ItemDiscAmt and OtherDiscAmt are proportionally allocated across
     * all invoice items, reducing each item's effective landing cost and updating stock valuation.
     */
    public function test_scheme_item_disc_and_other_disc_proportionally_reduce_landing_cost_and_update_valuation(): void
    {
        $item1 = Item::create([
            'name' => 'Pet Supplement Drops 50ml',
            'item_code' => 'PET001',
            'cost_price' => 100.00,
            'landing_cost' => 100.00,
            'sell_price' => 150.00,
            'mrp' => 180.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $item2 = Item::create([
            'name' => 'Pet Dental Chew Bone Large',
            'item_code' => 'PET002',
            'cost_price' => 100.00,
            'landing_cost' => 100.00,
            'sell_price' => 150.00,
            'mrp' => 180.00,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Item 1: 10 billed @ 100 = 1,000 base
        // Item 2: 10 billed + 2 free @ 100 = 1,000 base (12 total units)
        // Total Base = 2,000
        // Header discounts: Scheme Disc = Rs 200, Other Disc = Rs 100 => Total Header Disc = Rs 300
        // Proportional Allocation:
        // Item 1: 50% of 300 = Rs 150. Net Cost = 1,000 - 150 = Rs 850. Landing Cost = 850 / 10 = Rs 85.00
        // Item 2: 50% of 300 = Rs 150. Net Cost = 1,000 - 150 = Rs 850. Landing Cost = 850 / 12 = Rs 70.8333
        // Taxable: 850 + 850 = 1,700. GST 18% = 306. Net Total = 2,006.00
        $payload = [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'scheme_item_disc_amt' => 200.00,
            'other_disc_amt' => 100.00,
            'supplier_inv_amount' => 2006.00,
            'items' => [
                [
                    'item_id' => $item1->id,
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
                    'item_id' => $item2->id,
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

        $response = $this->actingAs($this->user)->post(route('purchase.purchase-invoices.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchase.purchase-invoices.index'));

        $invoice = PurchaseInvoice::with('items')->latest('id')->firstOrFail();
        $this->assertEquals(2006.00, (float) $invoice->total);
        $this->assertEquals(22.00, (float) $invoice->total_qty);

        // Item 1 verification
        $line1 = $invoice->items->firstWhere('item_id', $item1->id);
        $this->assertEquals(85.00, round((float) $line1->effective_cost, 2));

        $item1->refresh();
        $this->assertEquals(85.00, round((float) $item1->landing_cost, 2));

        $stock1 = ItemStock::where('item_id', $item1->id)->where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertEquals(85.00, round((float) $stock1->landing_cost, 2));

        $ledger1 = StockLedger::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->where('item_id', $item1->id)
            ->firstOrFail();
        $this->assertEquals(10.0, (float) $ledger1->qty_in);
        $this->assertEquals(85.00, round((float) $ledger1->unit_cost, 2));
        $this->assertEquals(850.00, round((float) $ledger1->value_in, 2));

        // Item 2 verification
        $line2 = $invoice->items->firstWhere('item_id', $item2->id);
        $this->assertEquals(70.8333, round((float) $line2->effective_cost, 4));

        $item2->refresh();
        $this->assertEquals(70.83, round((float) $item2->landing_cost, 2));

        $stock2 = ItemStock::where('item_id', $item2->id)->where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertEquals(70.83, round((float) $stock2->landing_cost, 2));

        $ledger2 = StockLedger::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->where('item_id', $item2->id)
            ->firstOrFail();
        $this->assertEquals(12.0, (float) $ledger2->qty_in);
        $this->assertEquals(70.83, round((float) $ledger2->unit_cost, 2));
        $this->assertEquals(850.00, round((float) $ledger2->value_in, 2));
    }
}
