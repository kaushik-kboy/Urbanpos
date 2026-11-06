<?php

namespace Tests\Feature;

use App\Models\ReplenishmentSalesRule;
use App\Models\ReplenishmentStockRule;
use App\Services\ReplenishmentCalculationService;
use Database\Seeders\ReplenishmentRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplenishmentCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReplenishmentRulesSeeder::class);
    }

    /**
     * Test sales rule resolution matches the V1 specification.
     */
    public function test_sales_rules_resolve_correctly(): void
    {
        $highest = ReplenishmentSalesRule::resolveForVelocity(25.5);
        $this->assertNotNull($highest);
        $this->assertEquals('Highest Moving', $highest->rule_name);
        $this->assertEquals(30, $highest->target_cover_days);

        $fast = ReplenishmentSalesRule::resolveForVelocity(14.0);
        $this->assertNotNull($fast);
        $this->assertEquals('Fast Moving', $fast->rule_name);
        $this->assertEquals(25, $fast->target_cover_days);

        $normal = ReplenishmentSalesRule::resolveForVelocity(7.5);
        $this->assertNotNull($normal);
        $this->assertEquals('Normal Moving', $normal->rule_name);
        $this->assertEquals(20, $normal->target_cover_days);

        $slow = ReplenishmentSalesRule::resolveForVelocity(2.0);
        $this->assertNotNull($slow);
        $this->assertEquals('Slow Moving', $slow->rule_name);
        $this->assertEquals(15, $slow->target_cover_days);

        $dead = ReplenishmentSalesRule::resolveForVelocity(0.5);
        $this->assertNotNull($dead);
        $this->assertEquals('Dead Stock', $dead->rule_name);
        $this->assertEquals(7, $dead->target_cover_days);

        // Boundary float tests (ensuring no floating-point gaps slip through)
        $this->assertEquals('Dead Stock', ReplenishmentSalesRule::resolveForVelocity(0.995)->rule_name);
        $this->assertEquals('Slow Moving', ReplenishmentSalesRule::resolveForVelocity(4.995)->rule_name);
        $this->assertEquals('Normal Moving', ReplenishmentSalesRule::resolveForVelocity(9.995)->rule_name);
        $this->assertEquals('Fast Moving', ReplenishmentSalesRule::resolveForVelocity(19.995)->rule_name);
    }

    /**
     * Test stock status condition resolution matches the V1 specification.
     */
    public function test_stock_rules_resolve_correctly(): void
    {
        $critical = ReplenishmentStockRule::resolveForCoverDays(2.0);
        $this->assertNotNull($critical);
        $this->assertEquals('Critical', $critical->stock_type);
        $this->assertEquals('danger', $critical->color_code);

        $low = ReplenishmentStockRule::resolveForCoverDays(8.0);
        $this->assertNotNull($low);
        $this->assertEquals('Low', $low->stock_type);
        $this->assertEquals('warning', $low->color_code);

        $healthy = ReplenishmentStockRule::resolveForCoverDays(20.0);
        $this->assertNotNull($healthy);
        $this->assertEquals('Healthy', $healthy->stock_type);
        $this->assertEquals('success', $healthy->color_code);

        $overstock = ReplenishmentStockRule::resolveForCoverDays(45.0);
        $this->assertNotNull($overstock);
        $this->assertEquals('Overstock', $overstock->stock_type);
        $this->assertEquals('info', $overstock->color_code);

        // Boundary float tests (ensuring no float gaps slip between integer days)
        $this->assertEquals('Low', ReplenishmentStockRule::resolveForCoverDays(3.5)->stock_type);
        $this->assertEquals('Healthy', ReplenishmentStockRule::resolveForCoverDays(10.5)->stock_type);
        $this->assertEquals('Overstock', ReplenishmentStockRule::resolveForCoverDays(30.5)->stock_type);
    }

    /**
     * Test dashboard summary execution.
     */
    public function test_dashboard_summary_executes_smoothly(): void
    {
        $service = app(ReplenishmentCalculationService::class);
        $summary = $service->getDashboardSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('critical_count', $summary);
        $this->assertArrayHasKey('low_count', $summary);
        $this->assertArrayHasKey('healthy_count', $summary);
        $this->assertArrayHasKey('overstock_count', $summary);
        $this->assertArrayHasKey('suggested_po_value', $summary);
    }

    /**
     * Test dynamic updating of stock condition rules.
     */
    public function test_stock_condition_rules_can_be_updated_dynamically(): void
    {
        config(['replenishment.enabled' => true]);
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->post(route('inventory.replenishment.settings.update'), [
            'sales_rules' => [],
            'stock_rules' => [
                'critical_max' => 5,
                'low_max' => 15,
                'healthy_max' => 45,
            ],
        ]);

        $response->assertRedirect();

        $critical = ReplenishmentStockRule::where('stock_type', 'Critical')->first();
        $this->assertEquals(0, $critical->min_cover_days);
        $this->assertEquals(5, $critical->max_cover_days);

        $low = ReplenishmentStockRule::where('stock_type', 'Low')->first();
        $this->assertEquals(6, $low->min_cover_days);
        $this->assertEquals(15, $low->max_cover_days);

        $healthy = ReplenishmentStockRule::where('stock_type', 'Healthy')->first();
        $this->assertEquals(16, $healthy->min_cover_days);
        $this->assertEquals(45, $healthy->max_cover_days);

        $overstock = ReplenishmentStockRule::where('stock_type', 'Overstock')->first();
        $this->assertEquals(46, $overstock->min_cover_days);
        $this->assertNull($overstock->max_cover_days);

        // Verify resolution reflects the new custom dynamic thresholds
        $this->assertEquals('Critical', ReplenishmentStockRule::resolveForCoverDays(4.5)->stock_type);
        $this->assertEquals('Low', ReplenishmentStockRule::resolveForCoverDays(12.0)->stock_type);
        $this->assertEquals('Healthy', ReplenishmentStockRule::resolveForCoverDays(30.0)->stock_type);
        $this->assertEquals('Overstock', ReplenishmentStockRule::resolveForCoverDays(50.0)->stock_type);
    }
}
