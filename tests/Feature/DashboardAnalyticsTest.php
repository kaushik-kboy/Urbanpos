<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\TillSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $manager;
    private User $cashier;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('Cashier');
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('home'));
        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_view_dashboard_with_all_components(): void
    {
        $response = $this->actingAs($this->owner)->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Executive Dashboard');
        $response->assertSee('Fast Action Command Center');
        $response->assertSee('New POS Bill');
        $response->assertSee('New Quotation');
        $response->assertSee('New GRN Receipt');
        $response->assertSee('30-Day Sales');
        $response->assertSee('Revenue Trend');
        $response->assertSee('Sales by Category');
        $response->assertSee('Top 10 Fast-Moving Products');
        $response->assertSee("Hourly Sales Velocity");
        $response->assertSee('Active Till Shifts');
    }

    public function test_manager_and_cashier_can_view_dashboard(): void
    {
        $mgrResponse = $this->actingAs($this->manager)->get(route('home'));
        $mgrResponse->assertStatus(200);
        $mgrResponse->assertSee('Executive Dashboard');

        $cashierResponse = $this->actingAs($this->cashier)->get(route('home'));
        $cashierResponse->assertStatus(200);
        $cashierResponse->assertSee('Executive Dashboard');
    }

    public function test_dashboard_provides_expected_chart_and_metric_payload(): void
    {
        $response = $this->actingAs($this->owner)->get(route('home'));

        $response->assertStatus(200);
        $response->assertViewHasAll([
            'todaySales', 'todayBillsCount',
            'monthSales', 'monthBillsCount', 'salesGrowthPct', 'monthProfit',
            'monthPurchase', 'monthReturns',
            'stockValue', 'lowStockItems', 'outOfStockItems',
            'totalCustomers', 'totalItems',
            'trendLabels', 'trendRevenue', 'trendBills',
            'topItemLabels', 'topItemQty', 'topItemRevenue',
            'categoryLabels', 'categoryAmounts',
            'hourlyLabels', 'hourlyRevenue', 'hourlyBills',
            'activeTills', 'recentBills', 'recentQuotations',
            'openQuotationsCount', 'openOrdersCount',
        ]);

        $trendLabels = $response->viewData('trendLabels');
        $this->assertCount(30, $trendLabels);

        $hourlyLabels = $response->viewData('hourlyLabels');
        $this->assertCount(15, $hourlyLabels); // 8 AM to 10 PM
    }

    public function test_dashboard_reflects_new_sales_bill_in_today_and_month_metrics(): void
    {
        $customer = Customer::first() ?? Customer::create([
            'name' => 'Dashboard Test Customer',
            'mobile' => '9999988888',
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'SB-TEST-DASH-001',
            'bill_date' => now()->format('Y-m-d'),
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'round_off' => 0.00,
            'total' => 590.00,
            'status' => 'Posted',
        ]);

        $response = $this->actingAs($this->owner)->get(route('home', ['branch_id' => $this->branch->id]));
        $response->assertStatus(200);

        $todaySales = $response->viewData('todaySales');
        $this->assertGreaterThanOrEqual(590.00, $todaySales);
    }

    public function test_dashboard_displays_active_open_till_sessions(): void
    {
        $register = Register::first() ?? Register::create([
            'name' => 'REG-DASH-01',
            'branch_id' => $this->branch->id,
            'code' => 'REG01',
        ]);

        $till = TillSession::create([
            'register_id' => $register->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id,
            'opening_cash' => 2500.00,
            'opened_at' => now(),
            'status' => 'Open',
        ]);

        $response = $this->actingAs($this->owner)->get(route('home', ['branch_id' => $this->branch->id]));
        $response->assertStatus(200);
        $response->assertSee('REG-DASH-01');
        $response->assertSee('2,500.00');
    }

    public function test_branch_filtering_scoping_for_owner(): void
    {
        // Consolidated
        $resAll = $this->actingAs($this->owner)->get(route('home', ['branch_id' => 'all']));
        $resAll->assertStatus(200);
        $this->assertNull($resAll->viewData('branchId'));

        // Specific Branch
        $resBranch = $this->actingAs($this->owner)->get(route('home', ['branch_id' => $this->branch->id]));
        $resBranch->assertStatus(200);
        $this->assertEquals($this->branch->id, $resBranch->viewData('branchId'));
    }
}
