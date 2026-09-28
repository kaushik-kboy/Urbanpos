<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BUG-P2: billwise-sales and sales-return-summary crashed (view read ->id on the controller's id=>label map) whenever a customer existed. */
class ReportCustomerFilterRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_with_customer_filter_render_and_list_the_customer(): void
    {
        User::factory()->create();
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $c = Customer::create(['name' => 'Filter Cust', 'mobile' => '9333333333', 'status' => true]);

        foreach (['reports.billwise-sales', 'reports.sales-return-summary'] as $route) {
            $this->actingAs($u)->get(route($route))->assertOk()->assertSee('Filter Cust')->assertSee('value="'.$c->id.'"', false);
        }
    }

    public function test_current_stock_report_is_paginated_and_totals_cover_all_rows(): void
    {
        User::factory()->create();
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $b = \App\Models\Branch::create(['name' => 'B', 'state' => 'Gujarat']);
        for ($i = 1; $i <= 130; $i++) {
            $item = \App\Models\Item::create(['name' => "Stk $i", 'cost_price' => 10, 'sell_price' => 20, 'status' => true]);
            ItemStock::create(['item_id' => $item->id, 'branch_id' => $b->id, 'quantity' => 2]);
        }
        $r = $this->actingAs($u)->get(route('reports.current-stock', ['branch_id' => $b->id]))->assertOk();
        $this->assertCount(100, $r->viewData('rows')->items());
        $this->assertEquals(130 * 2 * 10, (float) $r->viewData('totals')->cost_value);
    }
}
