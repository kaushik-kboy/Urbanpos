<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Phase5ReportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::whereHas('roles', fn ($q) => $q->where('name', 'Owner'))->firstOrFail();
        $this->actingAs($this->owner);
    }

    // -------------------------------------------------------
    //  A — Sales Margin Itemwise Report
    // -------------------------------------------------------

    public function test_sales_margin_itemwise_report_renders_ok(): void
    {
        $response = $this->get(route('reports.sales-margin-itemwise'));
        $response->assertStatus(200);
        $response->assertSee('Sales Item Margin Report');
    }

    public function test_sales_margin_itemwise_with_date_filter(): void
    {
        $response = $this->get(route('reports.sales-margin-itemwise', [
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to'   => now()->format('Y-m-d'),
        ]));
        $response->assertStatus(200);
    }

    // -------------------------------------------------------
    //  B — Sales Margin Categorywise Report
    // -------------------------------------------------------

    public function test_sales_margin_categorywise_report_renders_ok(): void
    {
        $response = $this->get(route('reports.sales-margin-category'));
        $response->assertStatus(200);
        $response->assertSee('Sales Margin by Category');
    }

    public function test_sales_margin_category_shows_chart_data(): void
    {
        $response = $this->get(route('reports.sales-margin-category'));
        $response->assertStatus(200);
        // Chart.js script should be present
        $response->assertSee('chart.js', false);
    }

    // -------------------------------------------------------
    //  C — Quotation & Order Summary Report
    // -------------------------------------------------------

    public function test_quotation_order_summary_renders_ok(): void
    {
        $response = $this->get(route('reports.quotation-order-summary'));
        $response->assertStatus(200);
        $response->assertSee('Quotation & Order Summary');
    }

    public function test_quotation_order_summary_type_filter(): void
    {
        $response = $this->get(route('reports.quotation-order-summary', ['type' => 'quotation']));
        $response->assertStatus(200);
    }

    public function test_quotation_order_summary_with_status_filter(): void
    {
        $response = $this->get(route('reports.quotation-order-summary', ['status' => 'Draft']));
        $response->assertStatus(200);
    }

    // -------------------------------------------------------
    //  D — Re-order / Low Stock Report
    // -------------------------------------------------------

    public function test_reorder_report_renders_ok(): void
    {
        $response = $this->get(route('reports.reorder-report'));
        $response->assertStatus(200);
        $response->assertSee('Re-order / Low Stock Report');
    }

    public function test_reorder_report_out_of_stock_filter(): void
    {
        $response = $this->get(route('reports.reorder-report', ['stock_status' => 'out']));
        $response->assertStatus(200);
    }

    public function test_reorder_report_custom_threshold(): void
    {
        $response = $this->get(route('reports.reorder-report', ['threshold' => 10]));
        $response->assertStatus(200);
    }

    // -------------------------------------------------------
    //  E — Barcode Printing
    // -------------------------------------------------------

    public function test_barcode_index_renders_ok(): void
    {
        $response = $this->get(route('inventory.barcode.index'));
        $response->assertStatus(200);
        $response->assertSee('Barcode Printing');
    }

    public function test_barcode_index_search_renders_ok(): void
    {
        $response = $this->get(route('inventory.barcode.index', ['search' => 'test']));
        $response->assertStatus(200);
    }

    public function test_barcode_print_redirects_with_no_items(): void
    {
        $response = $this->get(route('inventory.barcode.print'));
        // No items -> redirect back to index
        $response->assertRedirect(route('inventory.barcode.index'));
    }

    public function test_barcode_print_renders_with_valid_item(): void
    {
        $item = Item::where('status', true)->first();
        if (! $item) {
            $this->markTestSkipped('No active items in dev database.');
        }

        $response = $this->get(route('inventory.barcode.print', [
            'items' => [$item->id => ['id' => $item->id, 'qty' => 2]],
        ]));
        $response->assertStatus(200);
        $response->assertSee('JsBarcode', false);
    }

    // -------------------------------------------------------
    //  F — Reports index updated
    // -------------------------------------------------------

    public function test_reports_index_includes_new_links(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Sales Item Margin');
        $response->assertSee('Sales Margin by Category');
        $response->assertSee('Quotation & Order Summary');
        $response->assertSee('Re-order / Low Stock Report');
    }
}
