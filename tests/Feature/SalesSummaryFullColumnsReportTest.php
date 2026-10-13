<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DailySalesSummary;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesSummaryFullColumnsReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Motera Outlet', 'code' => 'MOT', 'state' => 'Gujarat', 'status' => true]
        );

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->user->assignRole('Owner');
    }

    public function test_sales_summary_view_renders_all_28_columns(): void
    {
        // Insert a record into daily_sales_summaries
        DailySalesSummary::create([
            'branch_id'        => $this->branch->id,
            'store_id'         => '32772',
            'store_name'       => 'Urbanpets / Motera',
            'summary_date'     => '2026-10-13',
            'bill_amount'      => 12500.50,
            'tax'              => 1906.85,
            'disc_amount'      => 450.00,
            'scheme_amount'    => 50.00,
            'cash'             => 5000.00,
            'card'             => 4000.00,
            'cheque'           => 0.00,
            'coupon'           => 100.00,
            'wallet_amt'       => 2000.00,
            'credit'           => 1400.50,
            'due'              => 0.00,
            'compliment'       => 0.00,
            'approval'         => 0.00,
            'advance_adjusted' => 0.00,
            'rounded_off'      => 0.50,
            'profit'           => 3800.00,
            'item_discount'    => 350.00,
            'bill_discount'    => 100.00,
            'freight'          => 0.00,
            'total_bills'      => 14,
            'gst_tax_amt'      => 1906.85,
            'sgst_tax_amt'     => 953.42,
            'cgst_tax_amt'     => 953.43,
            'igst_tax_amt'     => 0.00,
            'gst_cess_amt'     => 0.00,
            'redeemed_point'   => 25.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.sales-summary', [
            'from' => '2026-10-01',
            'to'   => '2026-10-20',
        ]));

        $response->assertStatus(200);

        // Verify all 28 header column names exist in the rendered HTML
        $expectedColumns = [
            'Store',
            'Date',
            'Bill amount',
            'Tax',
            'Disc. Amount',
            'Scheme amount',
            'Cash',
            'Card',
            'Cheque',
            'Coupon',
            'Wallet Amt',
            'Credit',
            'Due',
            'Compliment',
            'Approval',
            'Advance adjusted',
            'Rounded off',
            'Profit',
            'Item discount',
            'Bill discount',
            'Freight',
            'Total bills',
            'GST TaxAmt',
            'SGST TaxAmt',
            'CGST TaxAmt',
            'IGST TaxAmt',
            'GST Cess Amt',
            'Redeemed Point',
        ];

        foreach ($expectedColumns as $col) {
            $response->assertSee($col);
        }

        // Verify the values
        $response->assertSee('Urbanpets / Motera');
        $response->assertSee('13-10-2026');
        $response->assertSee('12,500.50');
        $response->assertSee('1,906.85');
        $response->assertSee('5,000.00');
        $response->assertSee('Grand Total');
    }

    public function test_sales_summary_export_csv(): void
    {
        DailySalesSummary::create([
            'branch_id'    => $this->branch->id,
            'store_name'   => 'Motera Outlet',
            'summary_date' => '2026-10-13',
            'bill_amount'  => 1000.00,
            'tax'          => 180.00,
            'disc_amount'  => 50.00,
            'total_bills'  => 2,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.sales-summary', [
            'from'   => '2026-10-01',
            'to'     => '2026-10-20',
            'export' => 'csv',
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Store', $content);
        $this->assertStringContainsString('Bill amount', $content);
        $this->assertStringContainsString('Tax', $content);
        $this->assertStringContainsString('Disc. Amount', $content);
        $this->assertStringContainsString('GST TaxAmt', $content);
        $this->assertStringContainsString('Redeemed Point', $content);
    }
}
