<?php

namespace Tests\Feature;

use App\Exports\GstPurchaseSummaryInvoiceWiseExport;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GST Purchase Summary — invoice-wise export (docs/GST-PURCHASE-SUMMARY-EXPORT.md
 * §4-5). Every test asserts against the export's own query()/map() output — the
 * exact same rows/cells Laravel Excel would write to the .xlsx — against a small
 * controlled fixture with independently-known expected values. Separate from
 * the existing HSN-wise summary report/tests (ReportsCoreTest), which are
 * unaffected by this export.
 */
class GstPurchaseSummaryInvoiceWiseExportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create();
        $this->branch = Branch::create(['name' => 'GPW Branch 1', 'state' => 'Gujarat']);
        $this->branch2 = Branch::create(['name' => 'GPW Branch 2', 'state' => 'Maharashtra']);
    }

    private function item(): Item
    {
        return Item::create([
            'name' => 'GPW Item ' . uniqid(), 'item_code' => 'GPW' . uniqid(),
            'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60, 'status' => true,
        ]);
    }

    private function supplier(string $name, ?string $gstin = null, ?string $state = 'Gujarat'): Supplier
    {
        return Supplier::create(['name' => $name, 'status' => true, 'gst_no' => $gstin, 'state' => $state]);
    }

    /** Invoice with one line per given [gst_percent, taxable, gst_tax_amount, cgst, sgst, igst]. */
    private function invoiceWithLines(array $header, array $lines, float $freight = 0, float $tcs = 0): PurchaseInvoice
    {
        $totalGst = array_sum(array_column($lines, 2));
        $totalTaxable = array_sum(array_column($lines, 1));
        $inv = PurchaseInvoice::create($header + [
            'branch_id' => $this->branch->id,
            'total' => round($totalTaxable + $totalGst + $freight + $tcs, 2),
            'total_gst' => $totalGst,
            'freight' => $freight,
            'tcs_amount' => $tcs,
            'status' => 'Posted',
            'purchase_type' => $header['purchase_type'] ?? 'Local',
        ]);
        foreach ($lines as [$rate, $taxable, $tax, $cgst, $sgst, $igst]) {
            PurchaseInvoiceItem::create([
                'purchase_invoice_id' => $inv->id, 'item_id' => $this->item()->id,
                'qty' => 1, 'cost_price' => $taxable, 'sell_price' => $taxable, 'mrp' => $taxable,
                'gst_percent' => $rate, 'gst_tax_amount' => $tax,
                'cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst,
                'net_amount' => round($taxable + $tax, 2),
            ]);
        }

        return $inv->fresh();
    }

    private function export(string $from = '2026-09-01', string $to = '2026-09-30', ?int $branchId = null): array
    {
        $export = new GstPurchaseSummaryInvoiceWiseExport($from, $to, $branchId);
        $rows = $export->query()->get();

        return $rows->keyBy('invoice_number')->all();
    }

    // ================================================================== One invoice = one row

    public function test_invoice_with_two_gst_rates_produces_exactly_one_row_with_aggregated_amounts(): void
    {
        $sup = $this->supplier('Mixed Rate Supplier', '24AAAAA0000A1Z5');
        $inv = $this->invoiceWithLines(
            ['invoice_number' => 'GPW-MIX-1', 'invoice_date' => '2026-09-05', 'supplier_id' => $sup->id, 'supplier_gstin' => $sup->gst_no],
            [
                [5, 2000.00, 100.00, 50, 50, 0],
                [18, 5000.00, 900.00, 450, 450, 0],
            ],
            freight: 50, tcs: 10
        );

        $rows = $this->export();
        $this->assertCount(1, $rows, 'exactly one Excel row for this one invoice, regardless of 2 GST rates');
        $r = $rows['GPW-MIX-1'];

        $this->assertEqualsWithDelta(7000.00, $r->taxable_amount, 0.01, '2000+5000 combined into the single Taxable amount column');
        $this->assertEqualsWithDelta(500.00, $r->cgst_tax_amt, 0.01, '50+450 summed');
        $this->assertEqualsWithDelta(500.00, $r->sgst_tax_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->igst_tax_amt, 0.01);
        $this->assertEqualsWithDelta((float) $inv->total, $r->total_amount, 0.01, 'Total amount must be the real posted invoice total');
        $this->assertEqualsWithDelta(50.00, $r->freight_charges, 0.01);
        $this->assertEqualsWithDelta(10.00, $r->tcs_amt, 0.01);
        $this->assertSame('Mixed Rate Supplier', $r->supplier_name);
        $this->assertSame('24AAAAA0000A1Z5', $r->gst_no);
        $this->assertSame('Gujarat', $r->state_name);

        // Percent-list formatting (sorted ascending, ", "-joined, ".00" trimmed)
        // only happens in map() — the raw query() row just holds an
        // unsorted/unformatted CSV (or NULL if nothing qualified).
        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('pi.invoice_number', 'GPW-MIX-1')->first();
        $mapped = $export->map($row);
        $this->assertSame('5, 18', $mapped[6], 'both distinct rates present, ascending, comma-separated');
        $this->assertSame('2.5, 9', $mapped[7], 'half of 5% and half of 18%');
        $this->assertSame('2.5, 9', $mapped[9]);
        $this->assertSame('', $mapped[11], 'no interstate lines on this invoice');
    }

    public function test_single_rate_invoice_shows_one_plain_percent_not_a_list(): void
    {
        $sup = $this->supplier('Single Rate Supplier');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-SINGLE-1', 'invoice_date' => '2026-09-06', 'supplier_id' => $sup->id],
            [[18, 1000.00, 180.00, 90, 90, 0], [18, 500.00, 90.00, 45, 45, 0]]
        );

        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('pi.invoice_number', 'GPW-SINGLE-1')->first();
        $mapped = $export->map($row);

        $this->assertSame('18', $mapped[6], 'Purchase tax % is a plain "18", not "18, 18"');
        $this->assertSame('9', $mapped[7], 'SGST Perc is a plain "9"');
        $this->assertSame('9', $mapped[9], 'CGST Perc is a plain "9"');
        $this->assertEqualsWithDelta(1500.00, $row->taxable_amount, 0.01, '1000+500 aggregated');
        $this->assertEqualsWithDelta(135.00, $row->cgst_tax_amt, 0.01, '90+45');
    }

    // ================================================================== Interstate (IGST) vs intra-state (CGST/SGST)

    public function test_interstate_invoice_uses_igst_not_cgst_sgst(): void
    {
        $sup = $this->supplier('Interstate Supplier', '27BBBBB0000B1Z5', 'Maharashtra');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-INTER-1', 'invoice_date' => '2026-09-07', 'supplier_id' => $sup->id, 'purchase_type' => 'Interstate'],
            [[18, 1000.00, 180.00, 0, 0, 180]]
        );
        $r = $this->export()['GPW-INTER-1'];
        $this->assertEqualsWithDelta(180.00, $r->igst_tax_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->cgst_tax_amt, 0.01);
        $this->assertEqualsWithDelta(0.00, $r->sgst_tax_amt, 0.01);
        $this->assertSame('Maharashtra', $r->state_name);

        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('pi.invoice_number', 'GPW-INTER-1')->first();
        $mapped = $export->map($row);
        $this->assertSame('18', $mapped[11], 'IGST Perc');
        $this->assertSame('', $mapped[7], 'SGST Perc blank — no intrastate lines');
        $this->assertSame('', $mapped[9], 'CGST Perc blank — no intrastate lines');
    }

    // ================================================================== Historical GSTIN snapshot reuse

    public function test_gst_no_uses_posting_time_snapshot_not_current_supplier_record(): void
    {
        $sup = $this->supplier('Snapshot Supplier', '24CCCCC0000C1Z5');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-SNAP-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'supplier_gstin' => $sup->gst_no],
            [[18, 100.00, 18.00, 9, 9, 0]]
        );
        $sup->update(['gst_no' => null]);
        $r = $this->export()['GPW-SNAP-1'];
        $this->assertSame('24CCCCC0000C1Z5', $r->gst_no, 'export must use the snapshot taken at posting time, not the now-changed live supplier record');
    }

    public function test_gst_no_falls_back_to_live_supplier_when_snapshot_is_null(): void
    {
        $sup = $this->supplier('Legacy Supplier', '29DDDDD0000D1Z5');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-LEGACY-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id],
            [[18, 100.00, 18.00, 9, 9, 0]]
        );
        $r = $this->export()['GPW-LEGACY-1'];
        $this->assertSame('29DDDDD0000D1Z5', $r->gst_no, 'no snapshot on this row, falls back to the live supplier GSTIN');
    }

    // ================================================================== Filters

    public function test_status_filter_excludes_cancelled_invoices(): void
    {
        $sup = $this->supplier('Status Supplier');
        $this->invoiceWithLines(['invoice_number' => 'GPW-CANCEL', 'invoice_date' => '2026-09-09', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);
        PurchaseInvoice::where('invoice_number', 'GPW-CANCEL')->update(['status' => 'Cancelled']);
        $this->invoiceWithLines(['invoice_number' => 'GPW-POSTED', 'invoice_date' => '2026-09-09', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);

        $rows = $this->export();
        $this->assertArrayNotHasKey('GPW-CANCEL', $rows);
        $this->assertArrayHasKey('GPW-POSTED', $rows);
    }

    public function test_date_filter_excludes_invoices_outside_the_period(): void
    {
        $sup = $this->supplier('Date Supplier');
        $this->invoiceWithLines(['invoice_number' => 'GPW-AUG', 'invoice_date' => '2026-08-31', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);
        $this->invoiceWithLines(['invoice_number' => 'GPW-SEP', 'invoice_date' => '2026-09-15', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);
        $this->invoiceWithLines(['invoice_number' => 'GPW-OCT', 'invoice_date' => '2026-10-01', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);

        $rows = $this->export('2026-09-01', '2026-09-30');
        $this->assertArrayNotHasKey('GPW-AUG', $rows);
        $this->assertArrayHasKey('GPW-SEP', $rows);
        $this->assertArrayNotHasKey('GPW-OCT', $rows);
    }

    public function test_branch_filter_only_includes_matching_branch(): void
    {
        $sup = $this->supplier('Branch Supplier');
        $this->invoiceWithLines(['invoice_number' => 'GPW-BR1', 'invoice_date' => '2026-09-10', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);
        PurchaseInvoice::where('invoice_number', 'GPW-BR1')->update(['branch_id' => $this->branch->id]);
        $this->invoiceWithLines(['invoice_number' => 'GPW-BR2', 'invoice_date' => '2026-09-10', 'supplier_id' => $sup->id], [[18, 100, 18, 9, 9, 0]]);
        PurchaseInvoice::where('invoice_number', 'GPW-BR2')->update(['branch_id' => $this->branch2->id]);

        $rows = $this->export('2026-09-01', '2026-09-30', $this->branch2->id);
        $this->assertArrayNotHasKey('GPW-BR1', $rows);
        $this->assertArrayHasKey('GPW-BR2', $rows);
    }

    // ================================================================== Invoices with no line items still get a row

    public function test_invoice_with_zero_line_items_still_produces_one_row_with_real_zeros(): void
    {
        // A real, if odd, case found in the live QA dataset: a posted invoice
        // header with no items at all (total = 0.00). Must still appear as
        // one row — an inner join from the items table would silently drop
        // it, breaking "one purchase invoice = one row".
        $sup = $this->supplier('Empty Invoice Supplier');
        PurchaseInvoice::create([
            'invoice_number' => 'GPW-EMPTY-1', 'invoice_date' => '2026-09-13', 'supplier_id' => $sup->id,
            'branch_id' => $this->branch->id, 'status' => 'Posted', 'purchase_type' => 'Local', 'total' => 0,
        ]);

        $rows = $this->export();
        $this->assertArrayHasKey('GPW-EMPTY-1', $rows, 'a header-only invoice must still produce a row');
        $r = $rows['GPW-EMPTY-1'];
        $this->assertEqualsWithDelta(0.0, (float) $r->taxable_amount, 0.01);

        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('pi.invoice_number', 'GPW-EMPTY-1')->first();
        $mapped = $export->map($row);
        $this->assertSame('0.00', $mapped[5], 'Taxable amount is a real zero, not blank');
        $this->assertSame('', $mapped[6], 'Purchase tax % is blank — no lines to show a rate for');
        $this->assertSame('0.00', $mapped[8], 'SGST TaxAmt is a real zero, not blank');
    }

    // ================================================================== Column spec

    public function test_exported_columns_exactly_match_the_required_16_column_spec_in_order(): void
    {
        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $this->assertSame([
            'Inv No', 'Inv date', 'Supplier name', 'GST No.', 'State Name',
            'Taxable amount', 'Purchase tax %',
            'SGST Perc', 'SGST TaxAmt',
            'CGST Perc', 'CGST TaxAmt',
            'IGST Perc', 'IGST TaxAmt',
            'Total amount', 'Freight charges', 'TCS Amt',
        ], $export->headings());
    }

    public function test_freight_and_tcs_columns_come_from_the_invoice_header_not_fabricated(): void
    {
        $sup = $this->supplier('Freight Supplier');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-FREIGHT-1', 'invoice_date' => '2026-09-11', 'supplier_id' => $sup->id],
            [[18, 100, 18, 9, 9, 0]], freight: 125.50, tcs: 3.75
        );
        $r = $this->export()['GPW-FREIGHT-1'];
        $this->assertEqualsWithDelta(125.50, $r->freight_charges, 0.01);
        $this->assertEqualsWithDelta(3.75, $r->tcs_amt, 0.01);
    }

    // ================================================================== Zero-value formatting (same class of bug as the sales export)

    public function test_zero_tax_amount_columns_are_exported_as_a_real_zero_not_a_blank_cell(): void
    {
        $sup = $this->supplier('Zero Supplier');
        $this->invoiceWithLines(
            ['invoice_number' => 'GPW-ZERO-1', 'invoice_date' => '2026-09-12', 'supplier_id' => $sup->id],
            [[0, 500.00, 0.00, 0, 0, 0]]
        );
        $export = new GstPurchaseSummaryInvoiceWiseExport('2026-09-01', '2026-09-30');
        $row = $export->query()->where('pi.invoice_number', 'GPW-ZERO-1')->first();
        $mapped = $export->map($row);

        foreach ([8, 10, 12, 14, 15] as $idx) { // SGST/CGST/IGST TaxAmt, Freight, TCS
            $this->assertSame('0.00', $mapped[$idx], "column index {$idx} must be the string \"0.00\", not a raw 0/null");
        }
        $this->assertSame('0', $mapped[6], 'Purchase tax % correctly shows the real 0% rate, not blank');
        $this->assertSame('', $mapped[7], 'SGST Perc is blank (no SGST amount on this line), not "0"');
    }
}
