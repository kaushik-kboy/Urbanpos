<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GST sales summary is aggregated in SQL (perf fix); totals must match and cancelled bills must not count. */
class GstSummaryAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_by_hsn_and_rate_and_excludes_cancelled_bills(): void
    {
        User::factory()->create();
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $b = Branch::create(['name' => 'B', 'state' => 'Gujarat']);
        $c = Customer::create(['name' => 'C', 'status' => true]);
        $food = Item::create(['name' => 'Food', 'hsn_code' => '23091000', 'sell_price' => 118, 'status' => true]);
        $toy = Item::create(['name' => 'Toy', 'hsn_code' => '', 'sell_price' => 112, 'status' => true]);

        $mk = function (string $no, string $status) use ($b, $c) {
            return SalesBill::create(['bill_number' => $no, 'bill_date' => now(), 'customer_id' => $c->id, 'branch_id' => $b->id,
                'sales_type' => 'Local', 'total' => 0, 'status' => $status]);
        };
        $line = fn ($bill, $item, $rate, $net, $gst) => SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => 1,
            'sell_price' => $net, 'gst_percent' => $rate, 'net_amount' => $net, 'gst_tax_amount' => $gst]);

        $ok = $mk('G-1', 'Posted');
        $line($ok, $food, 18, 118, 18);
        $line($ok, $food, 18, 236, 36);
        $line($ok, $toy, 12, 112, 12);
        $line($mk('G-2', 'Cancelled'), $food, 18, 1180, 180); // must be ignored

        $rows = $this->actingAs($u)->get(route('reports.gst-sales-summary', ['branch_id' => $b->id]))->assertOk()->viewData('rows');

        $this->assertCount(2, $rows);
        $food18 = $rows->firstWhere('hsn_code', '23091000');
        $this->assertEquals(300.0, (float) $food18->taxable_amount);   // (118-18)+(236-36)
        $this->assertEquals(54.0, (float) $food18->gst_amount);
        $na12 = $rows->firstWhere('hsn_code', 'N/A');
        $this->assertEquals(100.0, (float) $na12->taxable_amount);
        $this->assertEquals(12.0, (float) $na12->gst_percent);
    }
}
