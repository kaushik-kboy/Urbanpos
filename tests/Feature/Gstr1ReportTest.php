<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Services\GST\Gstr1ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GSTR-1 rebuild regression tests (docs/GSTR1-AUDIT.md / GSTR1-PHASE2-REPORT.md).
 * Every test here asserts an EXACT expected value computed independently of the
 * service under test, using a small controlled fixture — not a coverage-padding
 * test. Period for every test: 2026-09-01..2026-09-30 unless stated otherwise.
 */
class Gstr1ReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private GstTax $gst18;
    private GstTax $gst5;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn hard-coded super-user id 1
        $this->branch = Branch::create(['name' => 'GSTR1 Branch', 'state' => 'Gujarat']);
        $this->gst18 = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->gst5 = GstTax::firstOrCreate(['percentage' => 5], ['name' => 'GST 5%', 'description' => 'GST 5%', 'status' => true]);
    }

    private function item(string $code, ?string $hsn = '30049099', float $gstTaxId = null): Item
    {
        return Item::create([
            'name' => "GSTR1 $code", 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'gst_tax_id' => $gstTaxId ?? $this->gst18->id, 'tax_inclusive' => false, 'status' => true,
            'hsn_code' => $hsn,
        ]);
    }

    private function customer(string $name, ?string $gstin = null): Customer
    {
        return Customer::create(['name' => $name, 'status' => true, 'gst_no' => $gstin]);
    }

    /** @return array{0: SalesBill, 1: SalesBillItem} */
    private function bill(array $header, array $itemLine): array
    {
        $customer = $header['customer'];
        $item = $itemLine['item'];
        $qty = $itemLine['qty'] ?? 1;
        $sellPrice = $itemLine['sell_price'] ?? 100.0;
        $gstPercent = $itemLine['gst_percent'] ?? 18.0;
        $net = round($qty * $sellPrice, 2);
        $gstAmt = round($net * $gstPercent / 100, 2);
        $isInterstate = ($header['sales_type'] ?? 'Local') === 'Interstate';

        $bill = SalesBill::create([
            'bill_number' => $header['bill_number'],
            'bill_date' => $header['bill_date'],
            'customer_id' => $customer->id,
            'customer_gstin' => array_key_exists('customer_gstin_override', $header) ? $header['customer_gstin_override'] : $customer->gst_no,
            'branch_id' => $this->branch->id,
            'sales_type' => $header['sales_type'] ?? 'Local',
            'invoice_type' => $header['invoice_type'] ?? 'Tax Invoice',
            'total' => $net + $gstAmt,
            'total_gst' => $gstAmt,
            'total_igst' => $isInterstate ? $gstAmt : 0,
            'total_cgst' => $isInterstate ? 0 : round($gstAmt / 2, 2),
            'total_sgst' => $isInterstate ? 0 : round($gstAmt / 2, 2),
            'status' => $header['status'] ?? 'Posted',
        ]);
        $bi = SalesBillItem::create([
            'sales_bill_id' => $bill->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => $sellPrice,
            'gst_percent' => $gstPercent, 'gst_tax_amount' => $gstAmt,
            'cgst_amount' => $isInterstate ? 0 : round($gstAmt / 2, 2),
            'sgst_amount' => $isInterstate ? 0 : round($gstAmt / 2, 2),
            'igst_amount' => $isInterstate ? $gstAmt : 0,
            'net_amount' => $net + $gstAmt,
        ]);
        return [$bill, $bi];
    }

    private function service(string $from = '2026-09-01', string $to = '2026-09-30'): Gstr1ReportService
    {
        return new Gstr1ReportService($from, $to);
    }

    // ================================================================== B2B / B2C classification

    public function test_b2b_classification_uses_gstin_presence(): void
    {
        $registered = $this->customer('Reg Co', '24AAAAA0000A1Z5');
        $unregistered = $this->customer('Walk-in');
        $item = $this->item('CLS1');

        [$b2bBill] = $this->bill(['bill_number' => 'SB-B2B-1', 'bill_date' => '2026-09-05', 'customer' => $registered], ['item' => $item]);
        [$b2cBill] = $this->bill(['bill_number' => 'SB-B2C-1', 'bill_date' => '2026-09-05', 'customer' => $unregistered], ['item' => $item]);

        $b2b = $this->service()->b2b();
        $this->assertSame(1, $b2b['count']);
        $this->assertSame($b2bBill->bill_number, $b2b['rows'][0]['ref']);
        $this->assertEqualsWithDelta(100.00, $b2b['taxable'], 0.01);
        $this->assertEqualsWithDelta(18.00, $b2b['tax'], 0.01);

        // The unregistered bill must never appear in b2b()
        $refs = array_column($b2b['rows'], 'ref');
        $this->assertNotContains($b2cBill->bill_number, $refs);
    }

    public function test_b2cl_threshold_and_b2cs_are_mutually_exclusive(): void
    {
        $walkIn = $this->customer('Walk-in Big');
        $item = $this->item('CLS2');

        // Interstate, >= 2.5L -> B2CL
        [$bigBill] = $this->bill(['bill_number' => 'SB-B2CL-1', 'bill_date' => '2026-09-06', 'customer' => $walkIn, 'sales_type' => 'Interstate'],
            ['item' => $item, 'qty' => 3000, 'sell_price' => 100, 'gst_percent' => 18]); // net=300000 -> total ~354000 >= 2.5L

        // Local, small -> B2CS
        [$smallBill] = $this->bill(['bill_number' => 'SB-B2CS-1', 'bill_date' => '2026-09-06', 'customer' => $walkIn],
            ['item' => $item, 'qty' => 1, 'sell_price' => 100, 'gst_percent' => 18]);

        $b2cl = $this->service()->b2cl();
        $b2cs = $this->service()->b2cs();

        $this->assertSame(1, $b2cl['count']);
        $this->assertSame($bigBill->bill_number, $b2cl['rows'][0]['ref']);
        $this->assertGreaterThanOrEqual(Gstr1ReportService::B2CL_THRESHOLD, $bigBill->total);

        // The big interstate bill's item must be excluded from the b2cs aggregate
        // (mutual exclusion is enforced at the bill-id level before aggregation).
        $b2csTaxableForSmallOnly = round(100.0, 2); // just the small bill's own taxable value
        $this->assertEqualsWithDelta($b2csTaxableForSmallOnly, $b2cs['taxable'], 0.01);
    }

    // ================================================================== HSN summaries

    public function test_hsn_b2b_and_b2c_are_aggregated_separately_and_missing_hsn_is_not_fabricated(): void
    {
        $registered = $this->customer('HSN Reg Co', '24BBBBB0000B1Z5');
        $unregistered = $this->customer('HSN Walk-in');
        $itemWithHsn = $this->item('HSN1', '30049099');
        $itemNoHsn = $this->item('HSN2', null);

        $this->bill(['bill_number' => 'SB-HSN-B2B-1', 'bill_date' => '2026-09-07', 'customer' => $registered], ['item' => $itemWithHsn, 'qty' => 2]);
        $this->bill(['bill_number' => 'SB-HSN-B2C-1', 'bill_date' => '2026-09-07', 'customer' => $unregistered], ['item' => $itemNoHsn, 'qty' => 5]);

        $hsnB2b = $this->service()->hsnB2b();
        $hsnB2c = $this->service()->hsnB2c();

        $this->assertNotEmpty($hsnB2b['rows']);
        $this->assertSame('30049099', $hsnB2b['rows'][0]['hsn']);
        $this->assertEqualsWithDelta(200.0, $hsnB2b['rows'][0]['taxable'], 0.01);

        // Missing-HSN item must show up as an explicit null/missing bucket, never a fabricated code.
        $this->assertNotEmpty($hsnB2c['rows']);
        $this->assertNull($hsnB2c['rows'][0]['hsn']);
        $this->assertSame(5.0, $hsnB2c['missing_hsn_qty']);
        $this->assertStringContainsString('not fabricated', $hsnB2c['rows'][0]['name']);
    }

    // ================================================================== CDNR / CDNUR

    public function test_registered_and_unregistered_credit_notes_are_kept_separate(): void
    {
        $registered = $this->customer('CDN Reg Co', '24CCCCC0000C1Z5');
        $unregistered = $this->customer('CDN Walk-in');
        $item = $this->item('CDN1');

        [$regBill] = $this->bill(['bill_number' => 'SB-CDN-REG-1', 'bill_date' => '2026-09-08', 'customer' => $registered], ['item' => $item, 'qty' => 5]);
        [$unregBill] = $this->bill(['bill_number' => 'SB-CDN-UNREG-1', 'bill_date' => '2026-09-08', 'customer' => $unregistered], ['item' => $item, 'qty' => 5]);

        $regReturn = SalesReturn::create([
            'return_number' => 'SR-REG-1', 'return_date' => '2026-09-09', 'customer_id' => $registered->id,
            'customer_gstin' => $registered->gst_no, 'branch_id' => $this->branch->id, 'sales_bill_id' => $regBill->id,
            'return_mode' => 'Credit Note', 'sales_type' => 'Local', 'total' => 118.00, 'total_gst' => 18.00,
            'total_cgst' => 9.00, 'total_sgst' => 9.00, 'status' => 'Posted',
        ]);
        SalesReturnItem::create(['sales_return_id' => $regReturn->id, 'item_id' => $item->id, 'qty' => 1, 'sell_price' => 100, 'gst_percent' => 18, 'gst_tax_amount' => 18, 'cgst_amount' => 9, 'sgst_amount' => 9, 'net_amount' => 118]);

        $unregReturn = SalesReturn::create([
            'return_number' => 'SR-UNREG-1', 'return_date' => '2026-09-09', 'customer_id' => $unregistered->id,
            'customer_gstin' => $unregistered->gst_no, 'branch_id' => $this->branch->id, 'sales_bill_id' => $unregBill->id,
            'return_mode' => 'Credit Note', 'sales_type' => 'Local', 'total' => 118.00, 'total_gst' => 18.00,
            'total_cgst' => 9.00, 'total_sgst' => 9.00, 'status' => 'Posted',
        ]);
        SalesReturnItem::create(['sales_return_id' => $unregReturn->id, 'item_id' => $item->id, 'qty' => 1, 'sell_price' => 100, 'gst_percent' => 18, 'gst_tax_amount' => 18, 'cgst_amount' => 9, 'sgst_amount' => 9, 'net_amount' => 118]);

        $cdnr = $this->service()->cdnr();
        $cdnur = $this->service()->cdnur();

        $this->assertSame(1, $cdnr['count']);
        $this->assertSame('SR-REG-1', $cdnr['rows'][0]['ref']);
        $this->assertSame($regBill->bill_number, $cdnr['rows'][0]['original_invoice']);

        $this->assertSame(1, $cdnur['count']);
        $this->assertSame('SR-UNREG-1', $cdnur['rows'][0]['ref']);

        // Neither note leaks into the other bucket.
        $this->assertNotContains('SR-UNREG-1', array_column($cdnr['rows'], 'ref'));
        $this->assertNotContains('SR-REG-1', array_column($cdnur['rows'], 'ref'));
    }

    // ================================================================== Historical GSTIN snapshot (Phase 2B)

    public function test_changing_customers_current_gstin_does_not_reclassify_an_old_posted_bill(): void
    {
        $customer = $this->customer('Snapshot Co', '24DDDDD0000D1Z5');
        $item = $this->item('SNAP1');

        [$bill] = $this->bill(['bill_number' => 'SB-SNAP-1', 'bill_date' => '2026-09-10', 'customer' => $customer], ['item' => $item]);
        $this->assertSame('24DDDDD0000D1Z5', $bill->customer_gstin, 'snapshot must be taken at posting time');

        // The bill was correctly classified as B2B the moment it was posted.
        $this->assertSame(1, $this->service()->b2b()['count']);

        // Now the customer's GSTIN is removed/changed AFTER posting.
        $customer->update(['gst_no' => null]);

        // The already-posted bill must still report as B2B (snapshot wins), not silently
        // reclassified to B2C just because the live customer record changed.
        $b2b = $this->service()->b2b();
        $this->assertSame(1, $b2b['count'], 'historical bill must keep its posting-time classification');
        $this->assertSame($bill->bill_number, $b2b['rows'][0]['ref']);
    }

    public function test_bill_with_no_gstin_snapshot_falls_back_to_live_customer_gstin(): void
    {
        // Simulates a pre-migration row: customer_gstin is NULL even though the
        // customer has a real GSTIN today — this is the documented fallback path.
        $customer = $this->customer('Legacy Co', '24EEEEE0000E1Z5');
        $item = $this->item('LEGACY1');

        [$bill] = $this->bill(
            ['bill_number' => 'SB-LEGACY-1', 'bill_date' => '2026-09-11', 'customer' => $customer, 'customer_gstin_override' => null],
            ['item' => $item]
        );
        $this->assertNull($bill->customer_gstin);

        $b2b = $this->service()->b2b();
        $this->assertSame(1, $b2b['count'], 'pre-migration bill (no snapshot) must fall back to the live customer GSTIN');
    }

    // ================================================================== Cancelled documents & period filtering

    public function test_cancelled_bill_excluded_from_taxable_totals_but_counted_in_documents_issued(): void
    {
        $customer = $this->customer('Cancel Co', '24FFFFF0000F1Z5');
        $item = $this->item('CANCEL1');

        $this->bill(['bill_number' => 'SB-CANCEL-1', 'bill_date' => '2026-09-12', 'customer' => $customer, 'status' => 'Cancelled'], ['item' => $item]);
        $this->bill(['bill_number' => 'SB-CANCEL-2', 'bill_date' => '2026-09-12', 'customer' => $customer], ['item' => $item]);

        $b2b = $this->service()->b2b();
        $this->assertSame(1, $b2b['count'], 'cancelled bill must not contribute to taxable totals');
        $this->assertSame('SB-CANCEL-2', $b2b['rows'][0]['ref']);

        $docs = $this->service()->documentsIssued();
        $this->assertSame(2, $docs['total_issued'], 'cancelled bill still counts as an issued document number');
        $this->assertSame(1, $docs['total_cancelled']);
        $this->assertSame(1, $docs['rows'][0]['net_issued']);
    }

    public function test_period_filtering_excludes_bills_outside_the_selected_range(): void
    {
        $customer = $this->customer('Period Co', '24GGGGG0000G1Z5');
        $item = $this->item('PERIOD1');

        $this->bill(['bill_number' => 'SB-AUG-1', 'bill_date' => '2026-08-31', 'customer' => $customer], ['item' => $item]);
        $this->bill(['bill_number' => 'SB-SEP-1', 'bill_date' => '2026-09-01', 'customer' => $customer], ['item' => $item]);
        $this->bill(['bill_number' => 'SB-OCT-1', 'bill_date' => '2026-10-01', 'customer' => $customer], ['item' => $item]);

        $b2b = $this->service('2026-09-01', '2026-09-30')->b2b();
        $refs = array_column($b2b['rows'], 'ref');
        $this->assertContains('SB-SEP-1', $refs);
        $this->assertNotContains('SB-AUG-1', $refs, 'a day before the period must be excluded');
        $this->assertNotContains('SB-OCT-1', $refs, 'a day after the period must be excluded');
    }

    // ================================================================== No fabrication / no double counting

    public function test_no_data_in_period_returns_zero_never_a_hardcoded_fallback(): void
    {
        // Deliberately empty period — no bills, no returns anywhere near it.
        $service = $this->service('2030-01-01', '2030-01-31');

        $b2b = $service->b2b();
        $b2cs = $service->b2cs();
        $b2cl = $service->b2cl();
        $hsnB2b = $service->hsnB2b();
        $hsnB2c = $service->hsnB2c();
        $cdnr = $service->cdnr();
        $nil = $service->nilRated();
        $docs = $service->documentsIssued();

        // The exact fabricated numbers the audit found (kept as named constants in
        // this assertion so a future regression that reintroduces any of them fails loudly).
        $bannedFakeValues = [609340.18, 93056.06, 7414726.65, 1211809.47, 607861.26, 93420.62, 7338700.00, 1210534.94, 2829.76, 364.50, 73252.50, 3513];

        $this->assertSame(0, $b2b['count']);
        $this->assertSame(0.0, $b2b['taxable']);
        $this->assertSame(0, $b2cl['count']);
        $this->assertSame(0.0, $b2cs['taxable']);
        $this->assertSame(0.0, $hsnB2b['taxable']);
        $this->assertSame(0.0, $hsnB2c['taxable']);
        $this->assertSame(0, $cdnr['count']);
        $this->assertSame(0.0, $nil['combined_amount']);
        $this->assertSame(0, $docs['total_issued']);

        foreach ([$b2b['taxable'], $b2b['tax'], $b2cs['taxable'], $hsnB2b['taxable'], $hsnB2c['taxable'], $nil['combined_amount']] as $value) {
            $this->assertNotContains((float) $value, $bannedFakeValues, "a known fabricated fallback value leaked into the report: {$value}");
        }
    }

    public function test_export_and_advance_sections_are_explicitly_not_supported_not_fabricated(): void
    {
        $service = $this->service();
        foreach ([$service->exportSupplies(), $service->advanceReceived(), $service->advanceAdjusted()] as $section) {
            $this->assertFalse($section['supported']);
            $this->assertSame(0.0, $section['taxable']);
            $this->assertNotEmpty($section['limitation']);
        }
    }

    public function test_b2b_plus_b2cs_plus_b2cl_taxable_reconciles_to_total_posted_taxable_with_no_double_counting(): void
    {
        $registered = $this->customer('Recon Reg', '24HHHHH0000H1Z5');
        $unregSmall = $this->customer('Recon Small');
        $unregBig = $this->customer('Recon Big');
        $item = $this->item('RECON1');

        $this->bill(['bill_number' => 'SB-RECON-B2B', 'bill_date' => '2026-09-15', 'customer' => $registered], ['item' => $item, 'qty' => 3]);
        $this->bill(['bill_number' => 'SB-RECON-B2CS', 'bill_date' => '2026-09-15', 'customer' => $unregSmall], ['item' => $item, 'qty' => 2]);
        $this->bill(['bill_number' => 'SB-RECON-B2CL', 'bill_date' => '2026-09-15', 'customer' => $unregBig, 'sales_type' => 'Interstate'], ['item' => $item, 'qty' => 3000]);

        $expectedTotalTaxable = round(SalesBill::whereDate('bill_date', '2026-09-15')->get()->sum(fn ($b) => $b->total - $b->total_gst), 2);

        $service = $this->service();
        $sumOfSections = round($service->b2b()['taxable'] + $service->b2cs()['taxable'] + $service->b2cl()['taxable'], 2);

        $this->assertEqualsWithDelta($expectedTotalTaxable, $sumOfSections, 0.02, 'B2B+B2CS+B2CL must reconcile to total posted taxable value with no gap or overlap');
    }
}
