<?php

namespace Tests\Feature;

use App\Exports\GstSalesTaxwiseExport;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GST Sales Taxwise export (docs/GST-SALES-TAXWISE-EXPORT.md). Every test
 * asserts against the export's own query() output — the exact same rows
 * Laravel Excel would iterate to build the .xlsx — computed against a small
 * controlled fixture with independently-known expected values, not against
 * the live QA dataset (that reconciliation was done manually and separately,
 * see the report doc).
 */
class GstSalesTaxwiseExportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Branch $branch2;
    private GstTax $gst18;
    private GstTax $gst5;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create();
        $this->branch = Branch::create(['name' => 'GSTPW Branch 1', 'state' => 'Gujarat']);
        $this->branch2 = Branch::create(['name' => 'GSTPW Branch 2', 'state' => 'Maharashtra']);
        $this->gst18 = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->gst5 = GstTax::firstOrCreate(['percentage' => 5], ['name' => 'GST 5%', 'description' => 'GST 5%', 'status' => true]);
    }

    private function item(string $code): Item
    {
        return Item::create([
            'name' => "GSTPW $code", 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'gst_tax_id' => $this->gst18->id, 'tax_inclusive' => false, 'status' => true,
        ]);
    }

    private function customer(string $name, ?string $gstin = null, ?string $state = 'Gujarat'): Customer
    {
        return Customer::create(['name' => $name, 'status' => true, 'gst_no' => $gstin, 'state' => $state]);
    }

    /** Bill with one line per given [gst_percent, net_amount, gst_tax_amount, cgst, sgst, igst]. */
    private function billWithLines(array $header, array $lines): SalesBill
    {
        $totalGst = array_sum(array_column($lines, 1)) > 0 ? array_sum(array_map(fn ($l) => $l[1], $lines)) : 0;
        // $lines rows are [gst_percent, taxable, gst_tax_amount, cgst, sgst, igst]
        $totalGst = array_sum(array_column($lines, 2));
        $totalTaxable = array_sum(array_column($lines, 1));
        $bill = SalesBill::create($header + [
            'branch_id' => $this->branch->id,
            'total' => round($totalTaxable + $totalGst, 2),
            'total_gst' => $totalGst,
            'status' => 'Posted',
            'sales_type' => $header['sales_type'] ?? 'Local',
        ]);
        foreach ($lines as [$rate, $taxable, $tax, $cgst, $sgst, $igst]) {
            SalesBillItem::create([
                'sales_bill_id' => $bill->id, 'item_id' => $this->item('X' . uniqid())->id,
                'qty' => 1, 'sell_price' => $taxable, 'gst_percent' => $rate, 'gst_tax_amount' => $tax,
                'cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst,
                'net_amount' => round($taxable + $tax, 2),
            ]);
        }
        return $bill->fresh();
    }

    private function export(string $from = '2026-09-01', string $to = '2026-09-30', ?int $branchId = null, ?string $search = null): array
    {
        $export = new GstSalesTaxwiseExport($from, $to, $branchId, $search);
        $rows = $export->query()->get();
        return $rows->keyBy('bill_number')->all();
    }

    // ================================================================== One bill = one row

    public function test_bill_with_three_gst_rates_produces_exactly_one_row_with_all_breakup_correct(): void
    {
        $cust = $this->customer('Mixed Rate Co', '24AAAAA0000A1Z5');
        $bill = $this->billWithLines(
            ['bill_number' => 'TW-MIX-1', 'bill_date' => '2026-09-05', 'customer_id' => $cust->id, 'customer_gstin' => $cust->gst_no],
            [
                [0, 1000.00, 0.00, 0, 0, 0],
                [5, 2000.00, 100.00, 50, 50, 0],
                [18, 5000.00, 900.00, 450, 450, 0],
            ]
        );

        $rows = $this->export();
        $this->assertCount(1, $rows, 'exactly one Excel row for this one bill, regardless of 3 GST rates');
        $r = $rows['TW-MIX-1'];

        $this->assertEqualsWithDelta(1000.00, $r->taxable_0_amount, 0.01);
        $this->assertEqualsWithDelta(2000.00, $r->taxable_5_amount, 0.01);
        $this->assertEqualsWithDelta(5000.00, $r->taxable_18_amount, 0.01);
        $this->assertEqualsWithDelta(50.00, $r->cgst_5_amt, 0.01);
        $this->assertEqualsWithDelta(50.00, $r->sgst_5_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->igst_5_amt, 0.01);
        $this->assertEqualsWithDelta(450.00, $r->cgst_18_amt, 0.01);
        $this->assertEqualsWithDelta(450.00, $r->sgst_18_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->igst_18_amt, 0.01);
        $this->assertEqualsWithDelta((float) $bill->total, $r->total_amount, 0.01, 'Total amount must be the real bill total, not a reconstructed sum');
        $this->assertSame('Mixed Rate Co', $r->customer_name);
        $this->assertSame('24AAAAA0000A1Z5', $r->gst_no);
        $this->assertSame('Gujarat', $r->state_name);
    }

    public function test_multiple_items_at_the_same_rate_aggregate_into_one_row(): void
    {
        $cust = $this->customer('Same Rate Co');
        $this->billWithLines(
            ['bill_number' => 'TW-SAME-1', 'bill_date' => '2026-09-06', 'customer_id' => $cust->id, 'customer_gstin' => null],
            [
                [18, 1000.00, 180.00, 90, 90, 0],
                [18, 500.00, 90.00, 45, 45, 0],
                [18, 250.00, 45.00, 22.50, 22.50, 0],
            ]
        );

        $rows = $this->export();
        $this->assertCount(1, $rows);
        $r = $rows['TW-SAME-1'];
        $this->assertEqualsWithDelta(1750.00, $r->taxable_18_amount, 0.01, '1000+500+250 aggregated into one bucket');
        $this->assertEqualsWithDelta(157.50, $r->cgst_18_amt, 0.01, '90+45+22.50');
        $this->assertEqualsWithDelta(157.50, $r->sgst_18_amt, 0.01);
    }

    // ================================================================== Single-rate cases

    public function test_only_18_percent_bill_has_zero_in_0_and_5_buckets(): void
    {
        $cust = $this->customer('Only18 Co');
        $this->billWithLines(
            ['bill_number' => 'TW-18ONLY', 'bill_date' => '2026-09-07', 'customer_id' => $cust->id, 'customer_gstin' => null],
            [[18, 1000.00, 180.00, 90, 90, 0]]
        );
        $r = $this->export()['TW-18ONLY'];
        $this->assertEqualsWithDelta(0.0, $r->taxable_0_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, $r->taxable_5_amount, 0.01);
        $this->assertEqualsWithDelta(1000.00, $r->taxable_18_amount, 0.01);
    }

    public function test_only_5_percent_bill_has_zero_in_0_and_18_buckets(): void
    {
        $cust = $this->customer('Only5 Co');
        $this->billWithLines(
            ['bill_number' => 'TW-5ONLY', 'bill_date' => '2026-09-08', 'customer_id' => $cust->id, 'customer_gstin' => null],
            [[5, 800.00, 40.00, 20, 20, 0]]
        );
        $r = $this->export()['TW-5ONLY'];
        $this->assertEqualsWithDelta(0.0, $r->taxable_0_amount, 0.01);
        $this->assertEqualsWithDelta(800.00, $r->taxable_5_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, $r->taxable_18_amount, 0.01);
    }

    // ================================================================== Interstate (IGST) vs intra-state (CGST/SGST)

    public function test_interstate_bill_uses_igst_not_cgst_sgst(): void
    {
        $cust = $this->customer('Interstate Co', '27BBBBB0000B1Z5', 'Maharashtra');
        $this->billWithLines(
            ['bill_number' => 'TW-INTER-1', 'bill_date' => '2026-09-09', 'customer_id' => $cust->id, 'customer_gstin' => $cust->gst_no, 'sales_type' => 'Interstate'],
            [[18, 1000.00, 180.00, 0, 0, 180]]
        );
        $r = $this->export()['TW-INTER-1'];
        $this->assertEqualsWithDelta(180.00, $r->igst_18_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->cgst_18_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->sgst_18_amt, 0.01);
        $this->assertSame('Maharashtra', $r->state_name);
    }

    // ================================================================== Historical GSTIN snapshot reuse

    public function test_gst_no_uses_posting_time_snapshot_not_current_customer_record(): void
    {
        $cust = $this->customer('Snapshot Co', '24CCCCC0000C1Z5');
        $this->billWithLines(
            ['bill_number' => 'TW-SNAP-1', 'bill_date' => '2026-09-10', 'customer_id' => $cust->id, 'customer_gstin' => $cust->gst_no],
            [[18, 100.00, 18.00, 9, 9, 0]]
        );
        $cust->update(['gst_no' => null]);
        $r = $this->export()['TW-SNAP-1'];
        $this->assertSame('24CCCCC0000C1Z5', $r->gst_no, 'export must use the snapshot taken at posting time, not the now-changed live customer record');
    }

    // ================================================================== Filters

    public function test_status_filter_excludes_draft_and_cancelled_bills(): void
    {
        $cust = $this->customer('Status Co');
        $this->billWithLines(['bill_number' => 'TW-DRAFT', 'bill_date' => '2026-09-11', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        SalesBill::where('bill_number', 'TW-DRAFT')->update(['status' => 'Draft']);
        $this->billWithLines(['bill_number' => 'TW-CANCEL', 'bill_date' => '2026-09-11', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        SalesBill::where('bill_number', 'TW-CANCEL')->update(['status' => 'Cancelled']);
        $this->billWithLines(['bill_number' => 'TW-POSTED', 'bill_date' => '2026-09-11', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);

        $rows = $this->export();
        $this->assertArrayNotHasKey('TW-DRAFT', $rows);
        $this->assertArrayNotHasKey('TW-CANCEL', $rows);
        $this->assertArrayHasKey('TW-POSTED', $rows);
    }

    public function test_date_filter_excludes_bills_outside_the_period(): void
    {
        $cust = $this->customer('Date Co');
        $this->billWithLines(['bill_number' => 'TW-AUG', 'bill_date' => '2026-08-31', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        $this->billWithLines(['bill_number' => 'TW-SEP', 'bill_date' => '2026-09-15', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        $this->billWithLines(['bill_number' => 'TW-OCT', 'bill_date' => '2026-10-01', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);

        $rows = $this->export('2026-09-01', '2026-09-30');
        $this->assertArrayNotHasKey('TW-AUG', $rows);
        $this->assertArrayHasKey('TW-SEP', $rows);
        $this->assertArrayNotHasKey('TW-OCT', $rows);
    }

    public function test_branch_filter_only_includes_matching_branch(): void
    {
        $cust = $this->customer('Branch Co');
        $bill1 = $this->billWithLines(['bill_number' => 'TW-BR1', 'bill_date' => '2026-09-12', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        SalesBill::where('bill_number', 'TW-BR1')->update(['branch_id' => $this->branch->id]);
        $bill2 = $this->billWithLines(['bill_number' => 'TW-BR2', 'bill_date' => '2026-09-12', 'customer_id' => $cust->id], [[18, 100, 18, 9, 9, 0]]);
        SalesBill::where('bill_number', 'TW-BR2')->update(['branch_id' => $this->branch2->id]);

        $rows = $this->export('2026-09-01', '2026-09-30', $this->branch2->id);
        $this->assertArrayNotHasKey('TW-BR1', $rows);
        $this->assertArrayHasKey('TW-BR2', $rows);
    }

    public function test_search_filter_matches_bill_number_or_customer_name(): void
    {
        $custA = $this->customer('Findable Customer');
        $custB = $this->customer('Other Customer');
        $this->billWithLines(['bill_number' => 'TW-SEARCH-A', 'bill_date' => '2026-09-13', 'customer_id' => $custA->id], [[18, 100, 18, 9, 9, 0]]);
        $this->billWithLines(['bill_number' => 'TW-SEARCH-B', 'bill_date' => '2026-09-13', 'customer_id' => $custB->id], [[18, 100, 18, 9, 9, 0]]);

        $rows = $this->export('2026-09-01', '2026-09-30', null, 'Findable');
        $this->assertArrayHasKey('TW-SEARCH-A', $rows);
        $this->assertArrayNotHasKey('TW-SEARCH-B', $rows);
    }

    // ================================================================== Column spec / mapping decisions

    public function test_exported_columns_exactly_match_the_required_26_column_spec_in_order(): void
    {
        $export = new GstSalesTaxwiseExport('2026-09-01', '2026-09-30');
        $this->assertSame([
            'Bill No', 'Bill Date', 'Customer Name', 'GST No.', 'State Name',
            'taxable_0_amount', 'taxable_5_amount', 'taxable_18_amount',
            'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
            'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
            'Total amount',
            'Inv Noble_0_amount', 'taxable_5_amount', 'taxable_18_amount',
            'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
            'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
            'Total amount', 'Inv No',
        ], $export->headings());
    }

    public function test_columns_16_to_25_mirror_columns_6_to_15_and_column_26_repeats_bill_no(): void
    {
        $cust = $this->customer('Mirror Co');
        $this->billWithLines(
            ['bill_number' => 'TW-MIRROR-1', 'bill_date' => '2026-09-14', 'customer_id' => $cust->id],
            [[5, 200.00, 10.00, 5, 5, 0], [18, 300.00, 54.00, 27, 27, 0]]
        );
        $export = new GstSalesTaxwiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('sales_bills.bill_number', 'TW-MIRROR-1')->first();
        $mapped = $export->map($row);

        // 0-indexed: [5]=taxable_0(col6) ... [14]=Total(col15); [15]=Inv Noble_0(col16) ... [24]=Total(col25); [25]=Inv No(col26)
        $this->assertEquals($mapped[5], $mapped[15], 'col16 (Inv Noble_0_amount) mirrors col6 (taxable_0_amount)');
        $this->assertEquals($mapped[6], $mapped[16], 'col17 mirrors col7 (taxable_5_amount)');
        $this->assertEquals($mapped[7], $mapped[17], 'col18 mirrors col8 (taxable_18_amount)');
        $this->assertEquals($mapped[14], $mapped[24], 'col25 mirrors col15 (Total amount)');
        $this->assertSame($mapped[0], $mapped[25], 'col26 (Inv No) repeats col1 (Bill No)');
    }

    // ================================================================== Zero-value formatting (the real bug found+fixed this pass)

    public function test_zero_tax_buckets_are_exported_as_a_real_zero_not_a_blank_cell(): void
    {
        // A 0%-only bill: every 5%/18%/igst/cgst/sgst column should be a real
        // "0.00", not blank — this specifically regression-guards the
        // Laravel-Excel/PhpSpreadsheet bug found while verifying this export
        // (a raw PHP int(0)/float(0.0) cell value silently wrote as NULL;
        // fixed by formatting every amount via number_format() first).
        $cust = $this->customer('Zero Co');
        $this->billWithLines(
            ['bill_number' => 'TW-ZERO-1', 'bill_date' => '2026-09-15', 'customer_id' => $cust->id],
            [[0, 500.00, 0.00, 0, 0, 0]]
        );
        $export = new GstSalesTaxwiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('sales_bills.bill_number', 'TW-ZERO-1')->first();
        $mapped = $export->map($row);

        foreach ([6, 7, 8, 9, 10, 11, 12, 13] as $idx) { // taxable_5, taxable_18, igst_5, sgst_5, cgst_5, igst_18, cgst_18, sgst_18
            $this->assertSame('0.00', $mapped[$idx], "column index {$idx} must be the string \"0.00\", not a raw 0/null");
        }
    }
}
