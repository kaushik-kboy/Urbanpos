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
}
