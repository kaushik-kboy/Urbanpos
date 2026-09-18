<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\FinancialYear;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\LoyaltyProgram;
use App\Models\SalesBill;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private Branch $branch;
    private CustomerCategory $loyaltyCategory;
    private CustomerCategory $nonLoyaltyCategory;
    private Customer $loyaltyCustomer;
    private Customer $nonLoyaltyCustomer;
    private Item $item;
    private LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        FinancialYear::firstOrCreate(
            ['start_date' => '2026-04-01'],
            ['end_date' => '2027-03-31', 'name' => '2026-2027', 'is_locked' => false]
        );

        $this->loyaltyCategory = CustomerCategory::firstOrCreate(
            ['name' => 'VIP Members'],
            ['enable_loyalty' => true, 'status' => true]
        );
        $this->loyaltyCategory->update(['enable_loyalty' => true]);

        $this->nonLoyaltyCategory = CustomerCategory::firstOrCreate(
            ['name' => 'Wholesale No Loyalty'],
            ['enable_loyalty' => false, 'status' => true]
        );
        $this->nonLoyaltyCategory->update(['enable_loyalty' => false]);

        $this->loyaltyCustomer = Customer::firstOrCreate(
            ['phone' => '9988776655'],
            [
                'name' => 'Loyalty Active User',
                'customer_category_id' => $this->loyaltyCategory->id,
                'state' => 'Maharashtra',
                'credit_limit' => 50000,
            ]
        );
        $this->loyaltyCustomer->update(['customer_category_id' => $this->loyaltyCategory->id]);

        $this->nonLoyaltyCustomer = Customer::firstOrCreate(
            ['phone' => '9988776656'],
            [
                'name' => 'No Loyalty User',
                'customer_category_id' => $this->nonLoyaltyCategory->id,
                'state' => 'Maharashtra',
                'credit_limit' => 50000,
            ]
        );
        $this->nonLoyaltyCustomer->update(['customer_category_id' => $this->nonLoyaltyCategory->id]);

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'LOYAL-ITEM-001'],
            [
                'name' => 'Loyalty Reward Product',
                'cost_price' => 100,
                'sell_price' => 200,
                'mrp' => 220,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
            ]
        );

        // Create active program: 1 pt per ₹100 spend, min 10 pts to redeem, ₹1/pt
        $this->program = LoyaltyProgram::create([
            'name' => 'Standard Club Rewards',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
            'based_on' => 'Bill Amount',
            'min_points_redeem' => 10,
            'amount_per_point' => 1.00,
            'points_per_hundred' => 1.00,
            'roundoff' => true,
            'status' => true,
        ]);
    }

    public function test_manager_can_create_loyalty_program_with_slabs(): void
    {
        $response = $this->actingAs($this->manager)->post(route('master.loyalty-programs.store'), [
            'name' => 'Festival Mega Rewards',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'based_on' => 'Bill Amount',
            'min_points_redeem' => 25,
            'amount_per_point' => 2.00,
            'points_per_hundred' => 2.00,
            'roundoff' => 1,
            'status' => 1,
            'rules' => [
                [
                    'min_bill_amount' => 500,
                    'max_bill_amount' => 1000,
                    'points_earned' => 20,
                ],
                [
                    'min_bill_amount' => 1001,
                    'max_bill_amount' => 5000,
                    'points_earned' => 60,
                ],
            ],
        ]);

        $response->assertRedirect(route('master.loyalty-programs.index'));
        $created = LoyaltyProgram::where('name', 'Festival Mega Rewards')->first();
        $this->assertNotNull($created);
        $this->assertCount(2, $created->rules);
        $this->assertEquals(2.00, (float) $created->amount_per_point);
    }

    public function test_sales_bill_accrues_points_only_for_loyalty_enabled_customers(): void
    {
        // 1. Create Sales Bill for loyaltyCustomer of ₹500
        $bill = SalesBill::create([
            'bill_number' => 'SB-LOYAL-001',
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->loyaltyCustomer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 500.00,
            'status' => 'Posted',
        ]);

        $loyaltyService = app(LoyaltyService::class);
        $pointRecord = $loyaltyService->accruePointsForBill($bill);

        $this->assertNotNull($pointRecord);
        $this->assertEquals(5.00, (float) $pointRecord->points); // ₹500 / 100 * 1 = 5 pts
        $this->assertEquals(5.00, (float) $this->loyaltyCustomer->loyaltyBalance());

        // 2. Create Sales Bill for nonLoyaltyCustomer of ₹500
        $nonLoyalBill = SalesBill::create([
            'bill_number' => 'SB-NON-LOYAL-001',
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->nonLoyaltyCustomer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 500.00,
            'status' => 'Posted',
        ]);

        $nonPointRecord = $loyaltyService->accruePointsForBill($nonLoyalBill);
        $this->assertNull($nonPointRecord);
        $this->assertEquals(0.00, (float) $this->nonLoyaltyCustomer->loyaltyBalance());
    }

    public function test_loyalty_points_redemption_and_cancellation_reversal(): void
    {
        $loyaltyService = app(LoyaltyService::class);

        // Give customer 100 points via manual adjustment
        $loyaltyService->manualAdjustment(
            customerId: $this->loyaltyCustomer->id,
            direction: 'Add',
            points: 100,
            remarks: 'Initial Welcome Points',
            userId: $this->manager->id
        );

        $this->assertEquals(100.00, (float) $this->loyaltyCustomer->loyaltyBalance());

        // Create Sales Bill and redeem 30 points
        $bill = SalesBill::create([
            'bill_number' => 'SB-LOYAL-002',
            'bill_date' => now()->toDateString(),
            'customer_id' => $this->loyaltyCustomer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 300.00,
            'status' => 'Posted',
        ]);

        // Accrue points (3 pts) and redeem points (30 pts)
        $loyaltyService->accruePointsForBill($bill);
        $loyaltyService->redeemPointsForBill($bill, 30.0, 30.00);

        // Net balance: 100 + 3 - 30 = 73 points
        $this->assertEquals(73.00, (float) $this->loyaltyCustomer->loyaltyBalance());
        $this->assertEquals(3.00, $bill->pointsEarned());
        $this->assertEquals(30.00, $bill->pointsRedeemed());

        // Cancel the bill: reverses both earned (3 pts deducted) and redeemed (30 pts refunded)
        $loyaltyService->reverseBillPoints($bill);

        // Net balance restored: 73 - 3 + 30 = 100 points
        $this->assertEquals(100.00, (float) $this->loyaltyCustomer->loyaltyBalance());
    }

    public function test_manager_can_adjust_points_manually_with_insufficient_balance_guard(): void
    {
        // 1. Add 50 points
        $response = $this->actingAs($this->manager)->post(route('master.loyalty-points.store'), [
            'customer_id' => $this->loyaltyCustomer->id,
            'direction' => 'Add',
            'points' => 50,
            'remarks' => 'Manual credit adjustment',
        ]);

        $response->assertRedirect(route('master.loyalty-points.index'));
        $this->assertEquals(50.00, (float) $this->loyaltyCustomer->loyaltyBalance());

        // 2. Try to deduct 60 points (should fail validation because balance is 50)
        $failResponse = $this->actingAs($this->manager)->post(route('master.loyalty-points.store'), [
            'customer_id' => $this->loyaltyCustomer->id,
            'direction' => 'Deduct',
            'points' => 60,
            'remarks' => 'Excess deduction',
        ]);

        $failResponse->assertSessionHasErrors('points');
        $this->assertEquals(50.00, (float) $this->loyaltyCustomer->loyaltyBalance());

        // 3. Deduct 20 points (should succeed)
        $successResponse = $this->actingAs($this->manager)->post(route('master.loyalty-points.store'), [
            'customer_id' => $this->loyaltyCustomer->id,
            'direction' => 'Deduct',
            'points' => 20,
            'remarks' => 'Correct deduction',
        ]);

        $successResponse->assertRedirect(route('master.loyalty-points.index'));
        $this->assertEquals(30.00, (float) $this->loyaltyCustomer->loyaltyBalance());
    }

    public function test_loyalty_views_and_reports_render(): void
    {
        $this->actingAs($this->manager)->get(route('master.loyalty-programs.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('master.loyalty-programs.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('master.loyalty-programs.edit', $this->program))->assertOk();
        $this->actingAs($this->manager)->get(route('master.loyalty-points.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('sales.sales-bills.customer-loyalty', $this->loyaltyCustomer))->assertOk();

        $reportResponse = $this->actingAs($this->manager)->get(route('finance.reports.customer-loyalty'));
        $reportResponse->assertOk();
        $reportResponse->assertSee('Customer Loyalty Details Report');
    }
}
