<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\TenderType;
use App\Models\User;
use App\Services\Tax\TaxEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesBillTaxInclusivePosTest extends TestCase
{
    use DatabaseTransactions;

    private User $cashier;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Motera Branch', 'code' => 'MOTERA', 'state' => 'Gujarat']
        );

        $this->customer = Customer::firstOrCreate(
            ['mobile' => '9876543210'],
            ['name' => 'Test POS Customer', 'status' => true, 'credit_limit' => 5000]
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%']);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'POS-TAX-INC-001'],
            [
                'name' => 'Pedigree Adult Dog Food 3kg',
                'sell_price' => 630.00,
                'mrp' => 630.00,
                'cost_price' => 450.00,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => true,
                'status' => true,
            ]
        );

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('Cashier');
    }

    public function test_tax_engine_calculates_tax_inclusive_net_amount(): void
    {
        $engine = app(TaxEngine::class);

        // Qty = 1, Sell Price = 630, Disc = 10% (63.00)
        // With tax-inclusive 18% GST:
        // Base = 630, Disc = 63 => Net Amount must be 567.00
        // Pre-tax = 567 / 1.18 = 480.51, GST = 86.49
        $res = $engine->calculate(
            qty: 1.0,
            price: 630.00,
            item: $this->item,
            discPercent: 10.0,
            discAmount: 63.00,
            extraDeductions: 0.0,
            isInterstate: false,
            isTaxInclusive: true
        );

        $this->assertEquals(567.00, $res['net_amount'], 'Net amount should be 567.00 because selling price already includes GST');
        $this->assertEquals(86.49, $res['gst_tax_amount'], 'GST tax amount should be 86.49 (inclusive portion)');
        $this->assertEquals(480.51, $res['taxable_value'], 'Taxable value before GST should be 480.51');
    }

    public function test_tender_types_seeded_correctly_for_pos(): void
    {
        $cash = TenderType::where('name', 'Cash')->first();
        $credit = TenderType::where('name', 'Credit')->first();
        $card = TenderType::where('name', 'Card')->first();
        $wallet = TenderType::where('name', 'Wallet')->with('values')->first();
        $rrn = TenderType::where('name', 'RRN')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($credit);
        $this->assertNotNull($card);
        $this->assertNotNull($wallet);
        $this->assertNotNull($rrn);

        $this->assertTrue($wallet->values->pluck('name')->contains('PINELAB'));
    }

    public function test_sales_bill_create_view_renders_tender_modal_and_read_only_columns(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('sales.sales-bills.create'));

        $response->assertOk();
        $response->assertSee('sb-tender-modal', false);
        $response->assertSee('A). Cash', false);
        $response->assertSee('B). Credit', false);
        $response->assertSee('C). Card', false);
        $response->assertSee('W). Wallet', false);
        $response->assertSee('N). RRN', false);
        $response->assertSee('PINELAB', false);
        $response->assertSee('GST Amt', false);
        $response->assertSee('sb-gst-tax-amount', false);
        $response->assertSee('btn-reset-form', false);
    }
}
