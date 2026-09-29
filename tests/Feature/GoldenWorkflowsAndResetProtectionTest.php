<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoldenWorkflowsAndResetProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra', 'status' => true]
        );

        GstTax::firstOrCreate(
            ['percentage' => 18],
            ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');
    }

    public function test_all_18_golden_workflows_render_successfully(): void
    {
        $workflows = [
            '1. Purchase Invoice Create' => route('purchase.purchase-invoices.create'),
            '2. Purchase Return Create' => route('purchase.purchase-returns.create'),
            '3. Sales Bill Create' => route('sales.sales-bills.create'),
            '4. Sales Return Create' => route('sales.sales-returns.create'),
            '5. Stock Transfer Create' => route('inventory.stock-transfers.create'),
            '6. Damage Stock Create' => route('inventory.damage-stocks.create'),
            '7. Stock Update Create' => route('inventory.stock-updates.create'),
            '8. Purchase Order Create' => route('purchase.purchase-orders.create'),
            '9. Sales Order Create' => route('sales.sales-orders.create'),
            '10. Sales Quotation Create' => route('sales.sales-quotations.create'),
            '11. Delivery Note Create' => route('sales.delivery-notes.create'),
            '12. Opening Stock Create' => route('inventory.opening-stocks.create'),
            '13. Master Items Index' => route('master.items.index'),
            '14. Master Categories Index' => route('master.item-categories.index'),
            '15. Master Brands Index' => route('master.brands.index'),
            '16. GST Purchase Summary' => route('reports.gst-purchase-summary'),
            '17. GSTR-1 Page' => route('tools.gst.gstr-1.page'),
        ];

        foreach ($workflows as $name => $uri) {
            $resp = $this->actingAs($this->manager)->get($uri);
            $this->assertTrue(
                $resp->isOk(),
                "Failed to render workflow '{$name}' at URI: {$uri}. Status: {$resp->getStatusCode()}"
            );
        }
    }

    public function test_reset_table_buttons_present_across_all_dynamic_modules(): void
    {
        $buttonMap = [
            'Sales Bill' => [route('sales.sales-bills.create'), 'sb-btn-reset-table'],
            'Purchase Invoice' => [route('purchase.purchase-invoices.create'), 'pinv-btn-reset-table'],
            'Purchase Return' => [route('purchase.purchase-returns.create'), 'pr-btn-reset-table'],
            'Sales Return' => [route('sales.sales-returns.create'), 'sr-btn-reset-table'],
            'Stock Transfer' => [route('inventory.stock-transfers.create'), 'btn-reset-table'],
            'Damage Stock' => [route('inventory.damage-stocks.create'), 'btn-reset-table'],
            'Stock Update' => [route('inventory.stock-updates.create'), 'su-btn-reset-table'],
            'Purchase Order' => [route('purchase.purchase-orders.create'), 'po-btn-reset-table'],
            'Sales Order' => [route('sales.sales-orders.create'), 'so-btn-reset-table'],
            'Sales Quotation' => [route('sales.sales-quotations.create'), 'sq-btn-reset-table'],
            'Delivery Note' => [route('sales.delivery-notes.create'), 'sdn-btn-reset-table'],
            'Opening Stock' => [route('inventory.opening-stocks.create'), 'btn-reset-table'],
        ];

        foreach ($buttonMap as $module => [$uri, $expectedId]) {
            $resp = $this->actingAs($this->manager)->get($uri);
            $resp->assertOk();
            $content = $resp->getContent();
            $this->assertTrue(
                str_contains($content, "id=\"{$expectedId}\"") || str_contains($content, "id='{$expectedId}'"),
                "Module {$module} is missing reset button #{$expectedId}."
            );
        }
    }

    public function test_sales_bill_tender_modal_mode_choices_and_hotkeys(): void
    {
        $resp = $this->actingAs($this->manager)->get(route('sales.sales-bills.create'));
        $resp->assertOk();
        $content = $resp->getContent();

        $this->assertStringContainsString('tender-mode-pill', $content);
        $this->assertStringContainsString('data-mode="cash"', $content);
        $this->assertStringContainsString('data-mode="card"', $content);
        $this->assertStringContainsString('data-mode="credit"', $content);
        $this->assertStringContainsString('data-mode="upi"', $content);
        $this->assertStringContainsString('Alt+C', $content);
        $this->assertStringContainsString('Alt+D', $content);
        $this->assertStringContainsString('Alt+E', $content);
        $this->assertStringContainsString('Alt+U', $content);
    }

    public function test_global_sidebar_and_timezone_configurations(): void
    {
        $this->assertTrue(config('adminlte.sidebar_collapse'), 'Sidebar collapse must be true globally.');
        $this->assertEquals('Asia/Kolkata', config('app.timezone'), 'App timezone must be canonical Asia/Kolkata.');
    }
}
