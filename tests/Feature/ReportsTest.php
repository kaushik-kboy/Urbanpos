<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    public function test_reports_center_index_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Reports Center');
        $response->assertSee('Item Master Report');
        $response->assertSee('Supplier Master Report');
        $response->assertSee('GST Purchase Summary');
        $response->assertSee('Stock Transfer Summary');
        $response->assertSee('Damage / Wastage Stock Report');
        $response->assertSee('Tender / Payment Mode Summary');
        $response->assertSee('Audit Activity Log Viewer');
    }

    public function test_reports_center_index_filters_by_group(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.index', ['group' => 'masters']));
        $response->assertOk();
        $response->assertSee('Masters Reports');
        $response->assertSee('Item Master Report');
        $response->assertDontSee('Sales Reports</h3>', false);
    }

    public function test_item_master_report_renders(): void
    {
        Item::create(['name' => 'Report Test Item', 'cost_price' => 10, 'sell_price' => 20]);

        $response = $this->actingAs($this->owner)->get(route('reports.item-master', ['search' => 'Report Test Item']));
        $response->assertOk();
        $response->assertSee('Item Master Report');
        $response->assertSee('Report Test Item');
    }

    public function test_supplier_master_report_renders(): void
    {
        Supplier::create(['name' => 'Report Test Supplier', 'state' => 'Rajasthan']);

        $response = $this->actingAs($this->owner)->get(route('reports.supplier-master', ['search' => 'Report Test Supplier']));
        $response->assertOk();
        $response->assertSee('Supplier Master Report');
        $response->assertSee('Report Test Supplier');
    }

    public function test_gst_purchase_summary_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.gst-purchase-summary'));
        $response->assertOk();
        $response->assertSee('GST Purchase Summary (ITC)');
    }

    public function test_purchase_order_summary_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.purchase-order-summary'));
        $response->assertOk();
        $response->assertSee('Purchase Order Summary');
    }

    public function test_stock_transfer_summary_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.stock-transfer-summary'));
        $response->assertOk();
        $response->assertSee('Stock Transfer Report');
    }

    public function test_damage_stock_summary_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.damage-stock-summary'));
        $response->assertOk();
        $response->assertSee('Damage / Wastage Stock Report');
    }

    public function test_tender_summary_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.tender-summary'));
        $response->assertOk();
        $response->assertSee('Tender / Payment Mode Summary');
        $response->assertSee('Total Collections');
    }

    public function test_audit_logs_report_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.audit-logs'));
        $response->assertOk();
        $response->assertSee('Audit Activity Logs');
    }

    public function test_profit_loss_report_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finance.reports.profit-loss'));
        $response->assertOk();
        $response->assertSee('Trading and Profit & Loss Statement');
        $response->assertSee('Gross Profit');
        $response->assertSee('Net Profit / (Loss)');
    }

    public function test_day_book_report_renders_with_totals(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finance.reports.day-book'));
        $response->assertOk();
        $response->assertSee('Day Book');
        $response->assertSee('Total Vouchers');
        $response->assertSee('Total Debit');
        $response->assertSee('Total Credit');
    }
}
