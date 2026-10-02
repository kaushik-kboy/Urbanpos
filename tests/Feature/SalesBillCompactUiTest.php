<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBillCompactUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_sales_bill_create_renders_compact_scrollable_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('sales.sales-bills.create'));

        $response->assertOk();
        $content = $response->getContent();

        // 1. Verify scrollable container exists for items
        $this->assertStringContainsString('sb-items-scroll-container', $content);

        // 2. Verify table exists with compact styling
        $this->assertStringContainsString('id="sb-items-table"', $content);
        $this->assertStringContainsString('table-items-dense', $content);

        // 3. Verify sticky header CSS exists
        $this->assertStringContainsString('.sb-items-scroll-container #sb-items-table thead th', $content);
        $this->assertStringContainsString('position: sticky', $content);

        // 4. Verify Item Description min-width 250px
        $this->assertStringContainsString('data-col-key="item"', $content);
        $this->assertStringContainsString('min-width: 250px', $content);

        // 5. Verify compact header & additional field grids
        $this->assertStringContainsString('id="sb-header-fields-grid"', $content);
        $this->assertStringContainsString('id="sb-additional-fields-grid"', $content);

        // 6. Verify all essential fields remain intact
        $this->assertStringContainsString('name="bill_number"', $content);
        $this->assertStringContainsString('name="customer_id"', $content);
        $this->assertStringContainsString('name="user_id"', $content);
        $this->assertStringContainsString('name="bill_date"', $content);
        $this->assertStringContainsString('name="invoice_type"', $content);
        $this->assertStringContainsString('name="delivery_type"', $content);
        $this->assertStringContainsString('name="delivery_time"', $content);
        $this->assertStringContainsString('name="sales_type"', $content);
        $this->assertStringContainsString('name="payment_type"', $content);
        $this->assertStringContainsString('name="round_off"', $content);
        $this->assertStringContainsString('name="remarks"', $content);
        $this->assertStringContainsString('name="message"', $content);

        // 7. Verify auto-scroll focus listener is rendered for keyboard flow
        $this->assertStringContainsString('Auto-scroll items container when focused field moves out of visible view', $content);

        // 8. Verify rich POS footer elements
        $this->assertStringContainsString('sb-rich-footer', $content);
        $this->assertStringContainsString('id="display-sb-final-total"', $content);
        $this->assertStringContainsString('id="sb-total-items-badge"', $content);
        $this->assertStringContainsString('id="sb-main-save-btn"', $content);
        $this->assertStringContainsString('id="btn-reset-form"', $content);

        // 9. Verify removed elements as requested by user
        $this->assertStringNotContainsString('id="sb-add-row-bottom"', $content);
        $this->assertStringNotContainsString('Urban Pets UI 2.0', $content);
        $this->assertStringNotContainsString('F2 Search Item', $content);
        $this->assertStringNotContainsString('Tab Next Field', $content);
        $this->assertStringNotContainsString('Enter Confirm', $content);
    }
}
