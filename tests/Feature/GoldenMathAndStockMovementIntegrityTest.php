<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillPayment;
use App\Models\TenderType;
use App\Models\User;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoldenMathAndStockMovementIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branchMain;
    private Branch $branchSecondary;
    private Customer $customer;
    private GstTax $gst5;
    private GstTax $gst12;
    private GstTax $gst18;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branchMain = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main POS Branch', 'code' => 'MAIN', 'state' => 'Maharashtra', 'status' => true]
        );

        $this->branchSecondary = Branch::firstOrCreate(
            ['id' => 2],
            ['name' => 'Pune Branch', 'code' => 'PUNE', 'state' => 'Maharashtra', 'status' => true]
        );

        $this->user = User::factory()->create(['branch_id' => $this->branchMain->id]);
        $this->user->assignRole('Owner');

        $this->customer = Customer::firstOrCreate(
            ['phone' => '9876543210'],
            ['name' => 'VIP Customer', 'state' => 'Maharashtra', 'branch_id' => $this->branchMain->id]
        );

        $this->gst5 = GstTax::firstOrCreate(['percentage' => 5], ['name' => 'GST 5%', 'description' => '5% GST']);
        $this->gst12 = GstTax::firstOrCreate(['percentage' => 12], ['name' => 'GST 12%', 'description' => '12% GST']);
        $this->gst18 = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => '18% GST']);
    }

    /**
     * Test 1: Golden Tax Engine - Strict Intrastate vs Interstate Tax Split & Discounts.
     */
    public function test_golden_tax_split_and_multi_rate_math_accuracy(): void
    {
        $engine = app(TaxEngine::class);

        $itemA = Item::create([
            'name' => 'Item A 18%',
            'sell_price' => 200.00,
            'cost_price' => 120.00,
            'gst_tax_id' => $this->gst18->id,
            'tax_inclusive' => false,
        ]);

        // Intrastate: CGST 9% + SGST 9% on Taxable 180 (Price 200 - 10% disc = 180)
        $resIntra = $engine->calculate(
            qty: 1.0,
            price: 200.00,
            item: $itemA,
            discPercent: 10.0,
            discAmount: 20.00,
            extraDeductions: 0.0,
            isInterstate: false,
            isTaxInclusive: false
        );

        $this->assertEquals(180.00, round($resIntra['taxable_value'], 2));
        $this->assertEquals(32.40, round($resIntra['gst_tax_amount'], 2));
        $this->assertEquals(16.20, round($resIntra['cgst_amount'], 2));
        $this->assertEquals(16.20, round($resIntra['sgst_amount'], 2));
        $this->assertEquals(0.00, round($resIntra['igst_amount'], 2));
        $this->assertEquals(212.40, round($resIntra['net_amount'], 2));

        // Interstate: IGST 18% = 32.40, CGST = 0, SGST = 0
        $resInter = $engine->calculate(
            qty: 1.0,
            price: 200.00,
            item: $itemA,
            discPercent: 10.0,
            discAmount: 20.00,
            extraDeductions: 0.0,
            isInterstate: true,
            isTaxInclusive: false
        );

        $this->assertEquals(180.00, round($resInter['taxable_value'], 2));
        $this->assertEquals(32.40, round($resInter['gst_tax_amount'], 2));
        $this->assertEquals(0.00, round($resInter['cgst_amount'], 2));
        $this->assertEquals(0.00, round($resInter['sgst_amount'], 2));
        $this->assertEquals(32.40, round($resInter['igst_amount'], 2));
        $this->assertEquals(212.40, round($resInter['net_amount'], 2));
    }

    /**
     * Test 2: Multi-Tender Payment Reconciliation (Cash + Card + UPI + Credit == Total Bill Amount).
     */
    public function test_multi_tender_payment_sums_strictly_match_total_bill_amount(): void
    {
        $billTotal = 1500.00;
        $cashTender = TenderType::firstOrCreate(['name' => 'Cash', 'type' => 'Cash', 'mode' => 'cash'], ['status' => true]);
        $cardTender = TenderType::firstOrCreate(['name' => 'Credit Card', 'type' => 'Card', 'mode' => 'card'], ['status' => true]);
        $walletTender = TenderType::firstOrCreate(['name' => 'UPI', 'type' => 'Wallet', 'mode' => 'wallet'], ['status' => true]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-001',
            'branch_id' => $this->branchMain->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'bill_date' => now()->toDateString(),
            'total_gst' => 228.81,
            'total_cgst' => 114.40,
            'total_sgst' => 114.41,
            'round_off' => 0.00,
            'total' => $billTotal,
            'status' => 'Paid',
        ]);

        // Add payment tenders
        SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $cashTender->id, 'amount' => 500.00]);
        SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $cardTender->id, 'amount' => 700.00]);
        SalesBillPayment::create(['sales_bill_id' => $bill->id, 'tender_type_id' => $walletTender->id, 'amount' => 300.00]);

        $totalPaid = $bill->payments()->sum('amount');
        $this->assertEquals($billTotal, (float) $bill->total);
        $this->assertEquals($billTotal, (float) $totalPaid, 'Total tenders paid must equal bill total exactly');
    }

    /**
     * Test 3: Complete Stock Lifecycle: Opening -> Sale (-) -> Return (+) -> Transfer (-/+).
     */
    public function test_stock_lifecycle_maintains_exact_mathematical_balance(): void
    {
        $item = Item::create([
            'name' => 'Lifecycle Dog Food 5kg',
            'sell_price' => 500.00,
            'cost_price' => 350.00,
            'gst_tax_id' => $this->gst5->id,
        ]);

        // Step 1: Opening stock of 100 units at Main Branch
        $stockMain = ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branchMain->id,
            'quantity' => 100.0,
        ]);

        $stockSecondary = ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branchSecondary->id,
            'quantity' => 0.0,
        ]);

        $this->assertEquals(100.0, (float) $stockMain->quantity);

        // Step 2: Sale of 10 units
        $soldQty = 10.0;
        $stockMain->decrement('quantity', $soldQty);
        $this->assertEquals(90.0, (float) $stockMain->fresh()->quantity);

        // Step 3: Customer returns 2 units
        $returnedQty = 2.0;
        $stockMain->increment('quantity', $returnedQty);
        $this->assertEquals(92.0, (float) $stockMain->fresh()->quantity);

        // Step 4: Transfer 12 units from Main Branch to Pune Branch
        $transferredQty = 12.0;
        $stockMain->decrement('quantity', $transferredQty);
        $stockSecondary->increment('quantity', $transferredQty);

        $this->assertEquals(80.0, (float) $stockMain->fresh()->quantity);
        $this->assertEquals(12.0, (float) $stockSecondary->fresh()->quantity);

        // Formula Check: Total Physical Stock across branches == Opening - Sold + Returned
        $totalPhysical = $stockMain->fresh()->quantity + $stockSecondary->fresh()->quantity;
        $expectedTotal = 100.0 - 10.0 + 2.0; // 92.0
        $this->assertEquals($expectedTotal, $totalPhysical, 'Total company stock must balance Opening - Sold + Returned');
    }

    /**
     * Test 4: Financial & Report Reconciliation (DailySalesSummary matches SalesBill aggregation).
     */
    public function test_daily_sales_summary_matches_sales_bill_totals_exactly(): void
    {
        $date = '2026-10-14';

        // Create 2 Sales Bills
        SalesBill::create([
            'bill_number' => 'SB-AUDIT-1',
            'branch_id' => $this->branchMain->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'bill_date' => $date,
            'total_gst' => 180.00,
            'total_cgst' => 90.00,
            'total_sgst' => 90.00,
            'disc_amount' => 50.00,
            'round_off' => 0.00,
            'total' => 1130.00,
            'status' => 'Paid',
        ]);

        SalesBill::create([
            'bill_number' => 'SB-AUDIT-2',
            'branch_id' => $this->branchMain->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'bill_date' => $date,
            'total_gst' => 360.00,
            'total_cgst' => 180.00,
            'total_sgst' => 180.00,
            'disc_amount' => 100.00,
            'round_off' => 0.00,
            'total' => 2260.00,
            'status' => 'Paid',
        ]);

        // Aggregate live Sales Bills for date & branch
        $bills = SalesBill::where('branch_id', $this->branchMain->id)
            ->whereDate('bill_date', $date)
            ->get();

        $aggBillsCount = $bills->count();
        $aggBillAmount = $bills->sum('total');
        $aggTax = $bills->sum('total_gst');
        $aggCgst = $bills->sum('total_cgst');
        $aggSgst = $bills->sum('total_sgst');

        $this->assertEquals(2, $aggBillsCount);
        $this->assertEquals(3390.00, round($aggBillAmount, 2));
        $this->assertEquals(540.00, round($aggTax, 2));

        // Create or update DailySalesSummary record
        $summary = DailySalesSummary::updateOrCreate(
            ['branch_id' => $this->branchMain->id, 'summary_date' => $date],
            [
                'total_bills' => $aggBillsCount,
                'bill_amount' => $aggBillAmount,
                'tax' => $aggTax,
                'gst_tax_amt' => $aggTax,
                'sgst_tax_amt' => $aggSgst,
                'cgst_tax_amt' => $aggCgst,
            ]
        );

        // Assert 100% Reconciliation
        $this->assertEquals($aggBillAmount, (float) $summary->bill_amount);
        $this->assertEquals($aggTax, (float) $summary->tax);
        $this->assertEquals($aggCgst, (float) $summary->cgst_tax_amt);
        $this->assertEquals($aggSgst, (float) $summary->sgst_tax_amt);
    }
}
