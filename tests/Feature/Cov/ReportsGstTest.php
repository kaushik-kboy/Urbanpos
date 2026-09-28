<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstSetting;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GST\EInvoiceService;
use App\Services\GST\EWayBillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E-Invoice / E-Way Bill services (payload building, status transitions; all outbound HTTP faked)
 * and the GST hub dashboard controller (GSTR-1 / 3B / 9 / 2A-2B, IRN generation, exports).
 */
class ReportsGstTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Customer $b2b;
    private Customer $b2c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        User::factory()->create(); // burn hard-coded super-user id 1
        $u = User::factory()->create();
        $u->assignRole('Owner');
        $this->actingAs($u);

        $this->branch = Branch::create([
            'name' => 'Urban Pets HQ', 'state' => 'Gujarat', 'gst_no' => '24aaecu3183g1zn',
            'address_line1' => '12 Market Road', 'city' => 'Ahmedabad', 'postal_code' => '380 015', 'status' => true,
        ]);
        $this->b2b = Customer::create([
            'name' => 'Mumbai Pet Mart', 'gst_no' => '27aaaaa0000a1z5', 'state' => 'Maharashtra',
            'address1' => '5 Linking Road', 'city' => 'Mumbai', 'postal_code' => '400001', 'status' => true, 'phone' => '9820000000',
        ]);
        $this->b2c = Customer::create(['name' => 'Walkin Wendy', 'state' => 'Gujarat', 'status' => true]);
    }

    private function bill(string $no, string $date, Customer $c, float $total, float $gst, array $extra = []): SalesBill
    {
        return SalesBill::create($extra + [
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $this->branch->id,
            'sales_type' => 'Local', 'total' => $total, 'total_gst' => $gst, 'status' => 'Posted', 'total_qty' => 1,
        ]);
    }

    private function line(SalesBill $b, Item $item, array $x = []): SalesBillItem
    {
        return SalesBillItem::create($x + [
            'sales_bill_id' => $b->id, 'item_id' => $item->id, 'qty' => 2, 'sell_price' => 100,
            'gst_percent' => 18, 'gst_tax_amount' => 36, 'net_amount' => 236, 'cgst_amount' => 18, 'sgst_amount' => 18, 'igst_amount' => 0,
        ]);
    }

    private function dogFood(): Item
    {
        return Item::create(['name' => 'Dog Food', 'item_code' => 'DF1', 'hsn_code' => '23091000', 'sell_price' => 100, 'cost_price' => 60, 'status' => true]);
    }

    // ================================================================ EWayBillService

    public function test_resolve_state_code_from_gstin_name_and_default(): void
    {
        $svc = new EWayBillService();
        $this->assertSame(27, $svc->resolveStateCode('27AAAAA0000A1Z5', null));
        $this->assertSame(24, $svc->resolveStateCode(' 24AAAAA0000A1Z5', 'Kerala'), 'GSTIN prefix wins over state name');
        $this->assertSame(32, $svc->resolveStateCode(null, 'kerala'));
        $this->assertSame(29, $svc->resolveStateCode('URP', 'Karnataka'), 'non numeric GSTIN falls back to name');
        $this->assertSame(9, $svc->resolveStateCode('99XXXX', 'uttar pradesh'), 'unknown prefix falls back to name');
        $this->assertSame(7, $svc->resolveStateCode(null, 'Atlantis'), 'unknown -> Delhi default');
        $this->assertSame(7, $svc->resolveStateCode(null, null));
    }

    public function test_normalize_uom_maps_to_government_codes(): void
    {
        $svc = new EWayBillService();
        $this->assertSame('NOS', $svc->normalizeUom(null));
        $this->assertSame('NOS', $svc->normalizeUom(' pcs '));
        $this->assertSame('KGS', $svc->normalizeUom('Kg'));
        $this->assertSame('GMS', $svc->normalizeUom('gram'));
        $this->assertSame('MLT', $svc->normalizeUom('ML'));
        $this->assertSame('BTL', $svc->normalizeUom('Bottle'));
        $this->assertSame('PAC', $svc->normalizeUom('Packet'));
        $this->assertSame('DOZ', $svc->normalizeUom('doz'), 'unknown short codes pass through');
        $this->assertSame('NOS', $svc->normalizeUom('Truckload'), 'unknown long names default to NOS');
    }

    public function test_eway_entry_interstate_uses_igst_and_cleans_transport_details(): void
    {
        $item = $this->dogFood();
        $bill = $this->bill('INV-7/26 X#', '2026-09-15', $this->b2b, 236, 36, [
            'total_igst' => 36, 'vehicle_no' => 'gj 01 ab-1234', 'transporter_id' => ' 24abcde1234f1z5', 'transporter_name' => ' Fast Cargo ',
            'transport_doc_no' => ' LR-9 ', 'transport_mode' => 1, 'transport_distance' => 350, 'vehicle_type' => 'O', 'transport_doc_date' => '2026-09-14',
        ]);
        $this->line($bill, $item, ['igst_amount' => 36, 'cgst_amount' => 0, 'sgst_amount' => 0]);

        $e = (new EWayBillService())->buildBillEntry($bill->fresh());
        $this->assertSame('24aaecu3183g1zn', $e['fromGstin']);
        $this->assertSame('27AAAAA0000A1Z5', $e['toGstin']);
        $this->assertSame(24, $e['fromStateCode']);
        $this->assertSame(27, $e['toStateCode']);
        $this->assertSame('INV-7/26X', $e['docNo']);
        $this->assertSame('15/09/2026', $e['docDate']);
        $this->assertSame('14/09/2026', $e['transDocDate']);
        $this->assertSame('GJ01AB1234', $e['vehicleNo']);
        $this->assertSame('24ABCDE1234F1Z5', $e['transporterId']);
        $this->assertSame('Fast Cargo', $e['transporterName']);
        $this->assertSame('350', $e['transDistance']);
        $this->assertSame('O', $e['vehicleType']);
        $this->assertSame(380015, $e['fromPincode']);
        $this->assertSame(400001, $e['toPincode']);
        $this->assertSame(200.0, $e['totalValue']);       // net 236 - gst 36
        $this->assertSame(36.0, $e['igstValue']);
        $this->assertSame(236.0, $e['totInvValue']);
        $this->assertCount(1, $e['itemList']);
        $line = $e['itemList'][0];
        $this->assertSame(23091000, $line['hsnCode']);
        $this->assertSame(200.0, $line['taxableAmount']);
        $this->assertSame([0, 0, 18.0], [$line['sgstRate'], $line['cgstRate'], $line['igstRate']]);
    }

    public function test_eway_entry_intrastate_splits_gst_and_defaults_apply(): void
    {
        $item = Item::create(['name' => 'Mystery', 'sell_price' => 10, 'status' => true]); // no hsn
        $bill = $this->bill('INV-8', '2026-09-16', $this->b2c, 236, 36, ['total_cgst' => 18, 'total_sgst' => 18]);
        $this->line($bill, $item);

        $e = (new EWayBillService())->buildBillEntry($bill->fresh());
        $this->assertSame('URP', $e['toGstin']);
        $this->assertSame(24, $e['toStateCode']);
        $line = $e['itemList'][0];
        $this->assertSame(2309, $line['hsnCode']);
        $this->assertSame([9.0, 9.0, 0], [$line['sgstRate'], $line['cgstRate'], $line['igstRate']]);
        $this->assertSame('20', $e['transDistance']);
        $this->assertSame('1', $e['transMode']);
        $this->assertSame('R', $e['vehicleType']);
        $this->assertSame('', $e['transDocDate']);
        $this->assertSame(18.0, $e['cgstValue']);
    }

    public function test_eway_payload_falls_back_to_single_line_and_supports_collections(): void
    {
        $noItems = $this->bill('INV-9', '2026-09-17', $this->b2c, 118, 18, ['total_qty' => 3]);
        $svc = new EWayBillService();

        $single = $svc->generatePayload($noItems);
        $this->assertSame('1.0.0621', $single['version']);
        $this->assertCount(1, $single['billLists']);
        $line = $single['billLists'][0]['itemList'][0];
        $this->assertSame(100.0, $line['taxableAmount']);
        $this->assertSame(3.0, $line['quantity']);
        $this->assertSame([9.0, 9.0, 0], [$line['sgstRate'], $line['cgstRate'], $line['igstRate']]);
        $this->assertSame(100.0, $single['billLists'][0]['totalValue']);

        $other = $this->bill('INV-10', '2026-09-17', $this->b2b, 59, 9);
        $bulk = $svc->generatePayload(collect([$noItems, $other]));
        $this->assertSame(['INV-9', 'INV-10'], array_column($bulk['billLists'], 'docNo'));
        $this->assertSame(18.0, $bulk['billLists'][1]['itemList'][0]['igstRate']); // 27 != 24 => interstate even with no igst recorded
    }

    // ================================================================ EInvoiceService

    public function test_einvoice_payload_b2b_interstate(): void
    {
        $item = $this->dogFood();
        $bill = $this->bill('INV-1/26 #A', '2026-09-15 10:00:00', $this->b2b, 236, 36, ['total_igst' => 36, 'disc_amount' => 4, 'round_off' => 0.3]);
        $this->line($bill, $item, ['igst_amount' => 36, 'cgst_amount' => 0, 'sgst_amount' => 0, 'disc_amount' => 4]);

        $p = app(EInvoiceService::class)->buildPayload($bill->fresh());
        $this->assertSame('1.1', $p['Version']);
        $this->assertSame('B2B', $p['TranDtls']['SupTyp']);
        $this->assertSame('INV-1/26A', $p['DocDtls']['No']);
        $this->assertSame('15/09/2026', $p['DocDtls']['Dt']);
        $this->assertSame('24AAECU3183G1ZN', $p['SellerDtls']['Gstin']);
        $this->assertSame('24', $p['SellerDtls']['Stcd']);
        $this->assertSame(380015, $p['SellerDtls']['Pin']);
        $this->assertSame('27AAAAA0000A1Z5', $p['BuyerDtls']['Gstin']);
        $this->assertSame('27', $p['BuyerDtls']['Pos']);
        $this->assertSame('Mumbai', $p['BuyerDtls']['Loc']);
        $li = $p['ItemList'][0];
        $this->assertSame('23091000', $li['HsnCd']);
        $this->assertSame(200.0, $li['TotAmt']);   // 2 x 100
        $this->assertSame(4.0, $li['Discount']);
        $this->assertSame(200.0, $li['AssAmt']);   // net 236 - gst 36
        $this->assertSame(18.0, $li['GstRt']);
        $this->assertSame([36.0, 0.0, 0.0], [$li['IgstAmt'], $li['CgstAmt'], $li['SgstAmt']]);
        $this->assertSame(236.0, $li['TotItemVal']);
        $this->assertSame('NOS', $li['Unit']);
        $v = $p['ValDtls'];
        $this->assertSame([200.0, 36.0, 0.0, 0.0, 4.0, 0.3, 236.0], [$v['AssVal'], $v['IgstVal'], $v['CgstVal'], $v['SgstVal'], $v['Discount'], $v['RndOffAmt'], $v['TotInvVal']]);
    }

    public function test_einvoice_payload_b2c_intrastate_and_fallback_line(): void
    {
        $svc = app(EInvoiceService::class);
        $item = $this->dogFood();
        $bill = $this->bill('INV-2', '2026-09-15', $this->b2c, 236, 36, ['total_cgst' => 18, 'total_sgst' => 18]);
        $this->line($bill, $item);
        $p = $svc->buildPayload($bill->fresh());
        $this->assertSame('B2C', $p['TranDtls']['SupTyp']);
        $this->assertSame('URP', $p['BuyerDtls']['Gstin']);
        $this->assertSame('24', $p['BuyerDtls']['Stcd']);
        $li = $p['ItemList'][0];
        $this->assertSame([0.0, 18.0, 18.0], [$li['IgstAmt'], $li['CgstAmt'], $li['SgstAmt']]);
        $this->assertSame(200.0, $p['ValDtls']['AssVal']);
        $this->assertSame([18.0, 18.0], [$p['ValDtls']['CgstVal'], $p['ValDtls']['SgstVal']]);

        // bill without lines -> one synthesized retail line
        $bare = $this->bill('INV-3', '2026-09-15', $this->b2c, 118, 18, ['total_cgst' => 9, 'total_sgst' => 9, 'total_qty' => 4]);
        $p = $svc->buildPayload($bare->fresh());
        $this->assertCount(1, $p['ItemList']);
        $li = $p['ItemList'][0];
        $this->assertSame(100.0, $li['AssAmt']);
        $this->assertSame(18.0, $li['GstRt']);
        $this->assertSame(4.0, $li['Qty']);
        $this->assertSame([0.0, 9.0, 9.0], [$li['IgstAmt'], $li['CgstAmt'], $li['SgstAmt']]);
        $this->assertSame(118.0, $li['TotItemVal']);
        $this->assertSame(100.0, $p['ValDtls']['AssVal']);
        $this->assertSame(118.0, $p['ValDtls']['TotInvVal']);
    }

    public function test_upload_in_mock_mode_generates_deterministic_irn_and_completes_bill(): void
    {
        Http::fake();
        $bill = $this->bill('INV/55', '2026-09-15', $this->b2c, 118, 18);

        $res = app(EInvoiceService::class)->uploadToGovernment($bill);

        $this->assertTrue($res['success']);
        $expectedIrn = hash('sha256', '24AAECU3183G1ZN|INV|INV/55|2026-27');
        $this->assertSame($expectedIrn, $res['irn']);
        $this->assertSame('11' . date('y') . str_pad((string) $bill->id, 11, '0', STR_PAD_LEFT), $res['ack_no']);
        $bill->refresh();
        $this->assertSame($expectedIrn, $bill->irn);
        $this->assertSame('Completed', $bill->einvoice_status);
        $this->assertNull($bill->einvoice_error);
        $this->assertNotNull($bill->einvoice_synced_at);
        $qr = json_decode($bill->signed_qr_code, true);
        $this->assertSame('INV/55', $qr['docNo']);
        $this->assertEquals(118, $qr['totVal']);
        $this->assertSame($expectedIrn, $qr['irn']);
        Http::assertNothingSent();
    }

    public function test_upload_live_gsp_success_sends_credentials_and_payload(): void
    {
        GstSetting::current()->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC', 'is_sandbox' => true]);
        Http::fake(['api-sandbox.co.in/*' => Http::response([
            'Irn' => str_repeat('a', 64), 'AckNo' => '112233445566', 'AckDt' => '2026-09-15 10:30:00', 'SignedQRCode' => 'SIGNED-QR',
        ], 200)]);
        $bill = $this->bill('INV-L1', '2026-09-15', $this->b2c, 118, 18);

        $res = app(EInvoiceService::class)->uploadToGovernment($bill);

        $this->assertTrue($res['success']);
        $bill->refresh();
        $this->assertSame(str_repeat('a', 64), $bill->irn);
        $this->assertSame('112233445566', $bill->ack_no);
        $this->assertSame('SIGNED-QR', $bill->signed_qr_code);
        $this->assertSame('Completed', $bill->einvoice_status);
        $this->assertSame('2026-09-15 10:30:00', $bill->ack_date->format('Y-m-d H:i:s'));
        Http::assertSent(function (HttpRequest $r) {
            return $r->url() === 'https://api-sandbox.co.in/gsp/v1.1/invoice'
                && $r->hasHeader('client_id', 'CID')
                && $r->hasHeader('client_secret', 'SEC')
                && $r['DocDtls']['No'] === 'INV-L1'
                && $r['ValDtls']['TotInvVal'] == 118;
        });
    }

    public function test_upload_live_gsp_production_endpoint_when_not_sandbox(): void
    {
        GstSetting::current()->update(['gsp_provider' => 'nic_direct', 'client_id' => 'CID', 'client_secret' => 'SEC', 'is_sandbox' => false]);
        Http::fake(['api.einvoice.gst.gov.in/*' => Http::response(['irn' => 'IRN1', 'ack_no' => '9', 'signed_qr_code' => 'Q'], 200)]);
        $bill = $this->bill('INV-L2', '2026-09-15', $this->b2c, 118, 18);

        $this->assertTrue(app(EInvoiceService::class)->uploadToGovernment($bill)['success']);
        $this->assertSame('IRN1', $bill->fresh()->irn);
        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://api.einvoice.gst.gov.in/'));
    }

    public function test_upload_live_gsp_rejection_marks_bill_failed_with_reason(): void
    {
        GstSetting::current()->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC']);
        Http::fake(['*' => Http::response(['message' => 'Duplicate IRN'], 400)]);
        $bill = $this->bill('INV-F1', '2026-09-15', $this->b2c, 118, 18);

        $res = app(EInvoiceService::class)->uploadToGovernment($bill);

        $this->assertFalse($res['success']);
        $this->assertSame('Duplicate IRN', $res['error']);
        $bill->refresh();
        $this->assertSame('Failed', $bill->einvoice_status);
        $this->assertSame('Duplicate IRN', $bill->einvoice_error);
        $this->assertNull($bill->irn);
    }

    public function test_upload_live_gsp_timeout_is_reported_as_gateway_timeout(): void
    {
        GstSetting::current()->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC']);
        Http::fake(function () {
            throw new ConnectionException('cURL error 28');
        });
        $bill = $this->bill('INV-F2', '2026-09-15', $this->b2c, 118, 18);

        $res = app(EInvoiceService::class)->uploadToGovernment($bill);
        $this->assertFalse($res['success']);
        $this->assertStringStartsWith('GSP Gateway Timeout:', $res['error']);
        $this->assertSame('Failed', $bill->fresh()->einvoice_status);
    }

    public function test_failed_bill_can_be_retried_and_becomes_completed(): void
    {
        $gst = GstSetting::current();
        $gst->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC']);
        Http::fake(['*' => Http::response('boom', 500)]);
        $bill = $this->bill('INV-R1', '2026-09-15', $this->b2c, 118, 18);
        $svc = app(EInvoiceService::class);
        $this->assertFalse($svc->uploadToGovernment($bill)['success']);
        $this->assertSame('Failed', $bill->fresh()->einvoice_status);

        $gst->update(['gsp_provider' => 'mock']);
        $this->assertTrue($svc->uploadToGovernment($bill->fresh())['success']);
        $bill->refresh();
        $this->assertSame('Completed', $bill->einvoice_status);
        $this->assertNull($bill->einvoice_error);
        $this->assertNotNull($bill->irn);
    }

    public function test_upload_batch_counts_completed_and_failed(): void
    {
        $ok = $this->bill('INV-B1', '2026-09-15', $this->b2c, 118, 18);
        $ok2 = $this->bill('INV-B2', '2026-09-15', $this->b2c, 118, 18);
        $svc = app(EInvoiceService::class);
        $r = $svc->uploadBatch(collect([$ok, $ok2]));
        $this->assertSame(['total' => 2, 'completed' => 2, 'failed' => 0, 'errors' => []], $r);

        GstSetting::current()->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC']);
        Http::fake(['*' => Http::response(['message' => 'Invalid GSTIN'], 422)]);
        $bad = $this->bill('INV-B3', '2026-09-15', $this->b2c, 118, 18);
        $r = $svc->uploadBatch(collect([$bad]));
        $this->assertSame(1, $r['failed']);
        $this->assertSame(['Bill #INV-B3: Invalid GSTIN'], $r['errors']);
    }

    // ================================================================ dashboard: hub / returns

    public function test_hub_returns_metrics_exclude_cancelled_and_tabs_partition_bills(): void
    {
        $big = $this->bill('H-BIG', '2026-09-10', $this->b2c, 60000, 6000);
        $gstn = $this->bill('H-GSTN', '2026-09-11', $this->b2b, 500, 50);
        $failed = $this->bill('H-FAIL', '2026-09-12', $this->b2c, 200, 20, ['einvoice_status' => 'Failed', 'einvoice_error' => 'Bad HSN']);
        $done = $this->bill('H-DONE', '2026-09-13', $this->b2c, 300, 30, ['einvoice_status' => 'Completed', 'irn' => str_repeat('b', 64)]);
        $this->bill('H-SMALL', '2026-09-14', $this->b2c, 100, 10);
        $this->bill('H-CAN', '2026-09-14', $this->b2b, 9000, 900, ['status' => 'Cancelled']);
        $this->bill('H-OLD', '2026-06-14', $this->b2c, 7000, 700);

        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'HP-1', 'invoice_date' => '2026-09-05', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 1180, 'total_gst' => 180, 'status' => 'Posted']);
        PurchaseInvoice::create(['invoice_number' => 'HP-C', 'invoice_date' => '2026-09-05', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 5000, 'total_gst' => 500, 'status' => 'Cancelled']);

        $q = ['from_date' => '2026-09-01', 'to_date' => '2026-09-30'];
        $r = $this->get(route('tools.integrations-gst', $q))->assertOk();
        $this->assertSame(5, $r->viewData('gstr1Count'));
        $this->assertEquals(6110.0, $r->viewData('gstr1TaxCollected'));
        $this->assertEquals(54990.0, $r->viewData('gstr1Taxable'));  // 61100 - 6110
        $this->assertSame(1, $r->viewData('gstr2Count'));
        $this->assertEquals(180.0, $r->viewData('gstr2TaxPaid'));
        $this->assertEquals(1000.0, $r->viewData('gstr2Taxable'));
        $this->assertEquals(5930.0, $r->viewData('gstr3bTaxPayable'));

        // tab counts cover the eligible set (>= threshold, GST customer, failed, IRN) and never the cancelled bill
        $this->assertSame([2, 1, 1], [$r->viewData('pendingCount'), $r->viewData('failedCount'), $r->viewData('completedCount')]);
        $this->assertSame('pending', $r->viewData('tab'));
        $this->assertSame(['H-BIG', 'H-GSTN'], $r->viewData('bills')->pluck('bill_number')->sort()->values()->all());
    }

    public function test_hub_tabs_and_search_filters(): void
    {
        $this->bill('T-BIG', '2026-09-10', $this->b2c, 60000, 6000);
        $this->bill('T-GSTN', '2026-09-11', $this->b2b, 500, 50);
        $this->bill('T-FAIL', '2026-09-12', $this->b2c, 200, 20, ['einvoice_status' => 'Failed', 'einvoice_error' => 'Bad HSN']);
        $this->bill('T-DONE', '2026-09-13', $this->b2c, 300, 30, ['einvoice_status' => 'Completed', 'irn' => str_repeat('b', 64)]);
        $this->bill('T-SMALL', '2026-09-14', $this->b2c, 100, 10);
        $q = ['from_date' => '2026-09-01', 'to_date' => '2026-09-30'];

        $names = fn ($r) => $r->viewData('bills')->pluck('bill_number')->sort()->values()->all();

        $r = $this->get(route('tools.integrations-gst', $q))->assertOk();
        $this->assertSame(['T-BIG', 'T-GSTN'], $names($r));
        $this->assertSame([2, 1, 1], [$r->viewData('pendingCount'), $r->viewData('failedCount'), $r->viewData('completedCount')]);

        $this->assertSame(['T-FAIL'], $names($this->get(route('tools.integrations-gst', $q + ['tab' => 'failed']))));
        $this->assertSame(['T-DONE'], $names($this->get(route('tools.integrations-gst', $q + ['tab' => 'completed']))));
        $this->assertSame(['T-BIG'], $names($this->get(route('tools.integrations-gst', $q + ['search' => 'BIG']))));
        $this->assertSame(['T-GSTN'], $names($this->get(route('tools.integrations-gst', $q + ['customer' => '27AAAAA']))));
        $this->assertSame([], $names($this->get(route('tools.integrations-gst', ['from_date' => '2026-01-01', 'to_date' => '2026-01-31']))));
        $this->assertSame('pending', $this->get(route('tools.integrations-gst', $q + ['tab' => 'bogus']))->viewData('tab'));
    }

    public function test_hub_estimates_tax_when_no_gst_recorded(): void
    {
        $this->bill('E-1', '2026-09-10', $this->b2c, 118, 0);
        $r = $this->get(route('tools.integrations-gst', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']));
        $this->assertEquals(18.0, $r->viewData('gstr1TaxCollected'));
        $this->assertEquals(100.0, $r->viewData('gstr1Taxable'));

        // ...but line-level GST wins over the 18/118 estimate when present
        $b = SalesBill::where('bill_number', 'E-1')->first();
        $this->line($b, $this->dogFood(), ['gst_tax_amount' => 12, 'net_amount' => 118]);
        $r = $this->get(route('tools.integrations-gst', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']));
        $this->assertEquals(12.0, $r->viewData('gstr1TaxCollected'));
        $this->assertEquals(106.0, $r->viewData('gstr1Taxable'));
    }

    public function test_gstr1_view_splits_b2b_b2cs_b2cl_credit_notes_and_documents(): void
    {
        $this->bill('G-B2B1', '2026-08-05', $this->b2b, 1180, 180);
        $this->bill('G-B2B2', '2026-08-06', $this->b2b, 590, 90);
        $this->bill('G-B2C1', '2026-08-07', $this->b2c, 236, 36);
        $this->bill('G-B2CL', '2026-08-08', $this->b2c, 300000, 45762.71, ['sales_type' => 'Interstate', 'total_igst' => 45762.71]);
        $this->bill('G-EXEMPT', '2026-08-09', $this->b2c, 5000, 0, ['invoice_type' => 'Exempted']);
        $this->bill('G-CAN', '2026-08-10', $this->b2b, 9999, 999, ['status' => 'Cancelled']);
        $this->bill('G-SEP', '2026-09-10', $this->b2b, 7777, 777);
        SalesReturn::create(['return_number' => 'GR-1', 'return_date' => '2026-08-12', 'customer_id' => $this->b2b->id, 'branch_id' => $this->branch->id, 'total' => 118, 'total_gst' => 18]);
        SalesReturn::create(['return_number' => 'GR-2', 'return_date' => '2026-08-13', 'customer_id' => $this->b2c->id, 'branch_id' => $this->branch->id, 'total' => 59, 'total_gst' => 9]);

        $r = $this->get(route('tools.gst.gstr-1.page', ['from_date' => '2026-08-01', 'to_date' => '2026-08-31']))->assertOk();
        $this->assertSame(2, $r->viewData('b2bCount'));
        $this->assertEquals(1500.0, $r->viewData('b2bTaxable'));    // 1770 - 270
        $this->assertEquals(270.0, $r->viewData('b2bTax'));
        $this->assertSame(1, $r->viewData('b2clCount'));
        $this->assertEquals(254237.29, round($r->viewData('b2clTaxable'), 2));
        $this->assertEquals(45762.71, $r->viewData('b2clTax'));
        // B2CS = other B2C (G-B2C1 + G-EXEMPT), tax 36 recorded
        $this->assertEquals(36.0, $r->viewData('b2csTax'));
        $this->assertEquals(5200.0, $r->viewData('b2csTaxable')); // (236+5000) - 36
        $this->assertSame(1, $r->viewData('cdnrCount'));
        $this->assertEquals(100.0, $r->viewData('cdnrValue'));
        $this->assertEquals(18.0, $r->viewData('cdnrTax'));
        $this->assertSame(1, $r->viewData('cdnurCount'));
        $this->assertEquals(50.0, $r->viewData('cdnurValue'));
        $this->assertEquals(5000.0, $r->viewData('nilRatedAmount'));
        // Documents Issued now genuinely covers both series in this period, per
        // the GSTR-1 rule (sales-bill numbers AND credit-note/sales-return
        // numbers, not bills only): 6 bills (incl. the cancelled one) + GR-1/GR-2
        // returns = 8. The previous "6" only ever counted bills.
        $this->assertSame(8, $r->viewData('docIssuedTotal'));
        $this->assertSame(1, $r->viewData('cancelledCount'));
    }

    public function test_gstr1_section_view_uses_db_rows_and_search(): void
    {
        $food = $this->dogFood();
        $toy = Item::create(['name' => 'Toy', 'hsn_code' => '95030010', 'sell_price' => 50, 'status' => true]);
        $b1 = $this->bill('S-B2B', '2026-08-05', $this->b2b, 472, 72, ['total_igst' => 72]);
        $this->line($b1, $food, ['qty' => 2, 'net_amount' => 236, 'gst_tax_amount' => 36, 'igst_amount' => 36, 'cgst_amount' => 0, 'sgst_amount' => 0]);
        $this->line($b1, $food, ['qty' => 2, 'net_amount' => 236, 'gst_tax_amount' => 36, 'igst_amount' => 36, 'cgst_amount' => 0, 'sgst_amount' => 0]);
        $b2 = $this->bill('S-B2C', '2026-08-06', $this->b2c, 105, 5);
        $this->line($b2, $toy, ['qty' => 1, 'gst_percent' => 5, 'net_amount' => 105, 'gst_tax_amount' => 5, 'cgst_amount' => 2.5, 'sgst_amount' => 2.5]);
        $q = ['from_date' => '2026-08-01', 'to_date' => '2026-08-31'];

        $r = $this->get(route('tools.gst.gstr-1.section', ['section' => 'b2b-hsn'] + $q))->assertOk();
        $rows = $r->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('23091000', $rows[0]['hsn']);
        $this->assertEquals(4.0, $rows[0]['qty']);
        $this->assertEquals(472.0, $rows[0]['total']);
        $this->assertEquals(400.0, $rows[0]['taxable']);
        $this->assertEquals(72.0, $rows[0]['igst']);
        $this->assertEquals(18.0, $rows[0]['rate']);

        $rows = $this->get(route('tools.gst.gstr-1.section', ['section' => 'b2c_hsn'] + $q))->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('95030010', $rows[0]['hsn']);
        $this->assertEquals(100.0, $rows[0]['taxable']);
        $this->assertEquals(2.5, $rows[0]['cgst']);
        $this->assertSame('b2c-hsn', $this->get(route('tools.gst.gstr-1.section', ['section' => 'b2c_hsn'] + $q))->viewData('sectionLabel'));

        $this->assertCount(0, $this->get(route('tools.gst.gstr-1.section', ['section' => 'b2c-hsn', 'search' => '2309'] + $q))->viewData('rows'));
    }

    public function test_gstr1_details_json_buckets(): void
    {
        $this->bill('J-B2B', '2026-09-05', $this->b2b, 1180, 180);
        $this->bill('J-B2CS', '2026-09-06', $this->b2c, 236, 36);
        // B2CL is inter-state B2C above the threshold (both conditions, per the
        // real GSTR-1 rule confirmed in docs/GSTR1-AUDIT.md) — not value alone.
        $this->bill('J-B2CL', '2026-09-07', $this->b2c, 300000, 45000, ['sales_type' => 'Interstate', 'total_igst' => 45000]);
        $this->bill('J-CAN', '2026-09-08', $this->b2b, 5000, 500, ['status' => 'Cancelled']);

        $this->getJson(route('tools.gst.gstr-1', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']))
            ->assertOk()
            ->assertJson([
                'total_invoices' => 3,
                'total_turnover' => 301416,
                'b2b' => ['count' => 1, 'taxable' => 1000, 'tax' => 180, 'total' => 1180],
                'b2cs' => ['count' => 1, 'taxable' => 200, 'tax' => 36, 'total' => 236],
                'b2cl' => ['count' => 1, 'taxable' => 255000, 'tax' => 45000, 'total' => 300000],
            ]);
    }

    public function test_gstr3b_uses_actual_gst_split_and_excludes_cancelled(): void
    {
        $this->bill('3B-1', '2026-09-05', $this->b2c, 1180, 180, ['total_cgst' => 90, 'total_sgst' => 90]);
        $this->bill('3B-2', '2026-09-06', $this->b2b, 1180, 180, ['total_igst' => 180]);
        $this->bill('3B-C', '2026-09-06', $this->b2c, 9999, 999, ['status' => 'Cancelled', 'total_cgst' => 500, 'total_sgst' => 499]);
        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => '3BP-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 590, 'total_gst' => 90, 'total_cgst' => 45, 'total_sgst' => 45, 'status' => 'Posted']);
        PurchaseInvoice::create(['invoice_number' => '3BP-C', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 5900, 'total_gst' => 900, 'status' => 'Cancelled']);

        $j = $this->getJson(route('tools.gst.gstr-3b', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']))->assertOk()->json();
        $this->assertEquals(2000.0, $j['table_3_1']['taxable']);       // 2360 - 360
        $this->assertEquals([90.0, 90.0, 180.0, 360.0], [$j['table_3_1']['cgst'], $j['table_3_1']['sgst'], $j['table_3_1']['igst'], $j['table_3_1']['total_tax']]);
        $this->assertEquals(500.0, $j['table_4']['taxable']);
        $this->assertEquals([45.0, 45.0, 0.0, 90.0], [$j['table_4']['cgst'], $j['table_4']['sgst'], $j['table_4']['igst'], $j['table_4']['total_itc']]);
        $this->assertEquals(270.0, $j['table_6_1']['tax_payable']);    // 360 - 90
        $this->assertEquals(90.0, $j['table_6_1']['itc_utilized']);
    }

    public function test_gstr3b_estimates_when_no_tax_data_and_never_negative_payable(): void
    {
        $this->bill('3E-1', '2026-09-05', $this->b2c, 118, 0);
        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => '3EP-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 1180, 'total_gst' => 0, 'status' => 'Posted']);

        $j = $this->getJson(route('tools.gst.gstr-3b', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']))->json();
        $this->assertEquals(100.0, $j['table_3_1']['taxable']);
        $this->assertEquals(18.0, $j['table_3_1']['total_tax']);
        $this->assertEquals([1.8, 8.1, 8.1], [$j['table_3_1']['igst'], $j['table_3_1']['cgst'], $j['table_3_1']['sgst']]); // 10/45/45 estimate
        $this->assertEquals(180.0, $j['table_4']['total_itc']);
        $this->assertEquals(0.0, $j['table_6_1']['tax_payable']);
        $this->assertEquals(18.0, $j['table_6_1']['itc_utilized']);
    }

    public function test_gstr9_sync_uses_financial_year_window(): void
    {
        $this->bill('9-IN1', '2026-04-01', $this->b2c, 1180, 180);
        $this->bill('9-IN2', '2027-03-31', $this->b2c, 590, 90);
        $this->bill('9-BEFORE', '2026-03-31', $this->b2c, 5000, 500);
        $this->bill('9-AFTER', '2027-04-01', $this->b2c, 5000, 500);
        $this->bill('9-CAN', '2026-05-01', $this->b2c, 5000, 500, ['status' => 'Cancelled']);
        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => '9P-1', 'invoice_date' => '2026-08-01', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 236, 'total_gst' => 36, 'status' => 'Posted']);
        PurchaseInvoice::create(['invoice_number' => '9P-C', 'invoice_date' => '2026-08-01', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 2360, 'total_gst' => 360, 'status' => 'Cancelled']);

        $j = $this->postJson(route('tools.gst.gstr-9-sync'), ['year' => 2026])->assertOk()->json();
        $this->assertSame('success', $j['status']);
        $this->assertSame('2026-27', $j['financial_year']);
        $this->assertSame('2026-04-01 to 2027-03-31', $j['period']);
        $this->assertEquals(1770.0, $j['annual_turnover']);
        $this->assertEquals(1500.0, $j['annual_taxable_turnover']);
        $this->assertEquals(270.0, $j['annual_tax_collected']);
        $this->assertEquals(36.0, $j['annual_itc_claimed']);
        $this->assertEquals(234.0, $j['annual_net_tax_paid']);
    }

    public function test_gstr2_upload_validation_and_reconciliation_message(): void
    {
        $good = UploadedFile::fake()->createWithContent('gstr2b.json', json_encode(['b2b' => array_fill(0, 10, ['ctin' => 'X'])]));
        $this->from('/x')->post(route('tools.gst.gstr-2-upload'), ['gstr_file' => $good, 'type' => '2b'])
            ->assertRedirect('/x')
            ->assertSessionHas('status', 'GSTR-2B uploaded successfully! Reconciled 10 supplier invoices: 9 Matched with Purchase Register, 1 Pending match.');

        $bad = UploadedFile::fake()->createWithContent('bad.json', 'not-json{');
        $this->from('/x')->post(route('tools.gst.gstr-2-upload'), ['gstr_file' => $bad, 'type' => '2a'])
            ->assertSessionHas('error', 'Invalid JSON file uploaded for GSTR-2A.');

        $this->from('/x')->post(route('tools.gst.gstr-2-upload'), ['gstr_file' => $good, 'type' => '3c'])->assertSessionHasErrors('type');
        $this->from('/x')->post(route('tools.gst.gstr-2-upload'), ['type' => '2a'])->assertSessionHasErrors('gstr_file');
    }

    public function test_gstr2_download_csv_contains_purchase_rows(): void
    {
        $sup = Supplier::create(['name' => 'Wholesale Co', 'gst_no' => '24ZZZZZ9999Z1Z1', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'DL-1', 'invoice_date' => '2026-09-08', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 1180, 'total_gst' => 180, 'total_cgst' => 90, 'total_sgst' => 90, 'status' => 'Posted']);

        $resp = $this->get(route('tools.gst.gstr-2-download', ['type' => '2b']))->assertOk();
        $this->assertStringContainsString('GSTR_2B_Reconciliation_', $resp->headers->get('Content-Disposition'));
        $csv = $resp->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame('2B Match Status', end($lines[0]));
        $this->assertSame(['24ZZZZZ9999Z1Z1', 'Wholesale Co', 'DL-1', '08/09/2026', '1,180.00', '1,000.00', '90.00', '90.00', '0.00', 'Matched in Books'], $lines[1]);
    }

    // ================================================================ dashboard: bill actions

    public function test_bill_details_json(): void
    {
        $bill = $this->bill('BD-1', '2026-09-15 14:30:00', $this->b2b, 236, 36, ['total_igst' => 36, 'einvoice_status' => 'Failed', 'einvoice_error' => 'Bad pin']);
        $this->line($bill, $this->dogFood(), ['igst_amount' => 36, 'cgst_amount' => 0, 'sgst_amount' => 0]);

        $j = $this->getJson(route('tools.einvoice.details', $bill))->assertOk()->json();
        $this->assertSame('BD-1', $j['bill_number']);
        $this->assertSame('15-09-2026 02:30 PM', $j['bill_date']);
        $this->assertEquals(200.0, $j['total_taxable']);
        $this->assertSame('Mumbai Pet Mart', $j['customer_name']);
        $this->assertSame('27aaaaa0000a1z5', $j['customer_gstin']);
        $this->assertSame('Failed', $j['einvoice_status']);
        $this->assertSame('Bad pin', $j['einvoice_error']);
        $this->assertCount(1, $j['items']);
        $this->assertEquals(200.0, $j['items'][0]['taxable']);
        $this->assertEquals(36.0, $j['items'][0]['igst']);
        $this->assertSame('23091000', $j['items'][0]['hsn_code']);

        $walk = $this->bill('BD-2', '2026-09-15', $this->b2c, 10, 0);
        $j = $this->getJson(route('tools.einvoice.details', $walk))->json();
        $this->assertSame('Unregistered (B2C)', $j['customer_gstin']);
        $this->assertSame('Pending', $j['einvoice_status']);
    }

    public function test_generate_irn_batch_in_mock_mode_and_empty_selection(): void
    {
        $a = $this->bill('IRN-A', '2026-09-15', $this->b2c, 118, 18);
        $b = $this->bill('IRN-B', '2026-09-15', $this->b2b, 236, 36);
        $untouched = $this->bill('IRN-C', '2026-09-15', $this->b2c, 118, 18);

        $this->from('/hub')->post(route('tools.einvoice.generate-irn'), ['bill_ids' => [$a->id, $b->id]])
            ->assertRedirect('/hub')
            ->assertSessionHas('status', 'Batch IRN Processed: 2 Generated successfully.');
        $this->assertSame('Completed', $a->fresh()->einvoice_status);
        $this->assertSame('Completed', $b->fresh()->einvoice_status);
        $this->assertNotSame($a->fresh()->irn, $b->fresh()->irn);
        $this->assertNull($untouched->fresh()->irn);

        // comma separated string form + failure message
        GstSetting::current()->update(['gsp_provider' => 'cleartax', 'client_id' => 'CID', 'client_secret' => 'SEC']);
        Http::fake(['*' => Http::response(['message' => 'nope'], 400)]);
        $this->from('/hub')->post(route('tools.einvoice.generate-irn'), ['bill_ids' => $untouched->id . ',0'])
            ->assertSessionHas('status', 'Batch IRN Processed: 0 Generated successfully. (1 Failed. Check Failed tab for details.)');
        $this->assertSame('Failed', $untouched->fresh()->einvoice_status);

        $this->from('/hub')->post(route('tools.einvoice.generate-irn'), [])
            ->assertSessionHas('error', 'Please select at least one invoice to generate IRN.');
    }

    public function test_export_json_single_batch_and_empty(): void
    {
        $a = $this->bill('EX/A', '2026-09-15', $this->b2c, 118, 18);
        $b = $this->bill('EX-B', '2026-09-15', $this->b2b, 236, 36);

        $single = $this->post(route('tools.einvoice.export-json'), ['bill_ids' => (string) $a->id])->assertOk();
        $this->assertStringContainsString('EINV_EX_A_', $single->headers->get('Content-Disposition'));
        $doc = json_decode($single->getContent(), true);
        $this->assertSame('EX/A', $doc['DocDtls']['No']);
        $this->assertSame(118.0, (float) $doc['ValDtls']['TotInvVal']);

        $batch = $this->post(route('tools.einvoice.export-json'), ['bill_ids' => [$a->id, $b->id]])->assertOk();
        $this->assertStringContainsString('EINV_BATCH_2_BILLS_', $batch->headers->get('Content-Disposition'));
        $docs = json_decode($batch->getContent(), true);
        $this->assertCount(2, $docs);
        $this->assertSame('B2B', collect($docs)->firstWhere('DocDtls.No', 'EX-B')['TranDtls']['SupTyp']);

        $this->from('/hub')->post(route('tools.einvoice.export-json'), [])->assertSessionHas('error', 'Please select at least one invoice to export JSON.');
    }

    public function test_download_errors_csv_lists_only_failed_bills(): void
    {
        $this->bill('ER-OK', '2026-09-15', $this->b2c, 118, 18);
        $this->bill('ER-1', '2026-09-16', $this->b2b, 236, 36, ['einvoice_status' => 'Failed', 'einvoice_error' => 'Invalid buyer GSTIN']);
        $this->bill('ER-2', '2026-09-17', $this->b2c, 50, 5, ['einvoice_status' => 'Failed']);

        $csv = $this->get(route('tools.einvoice.download-errors'))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertCount(3, $lines);
        $this->assertSame(['ER-2', '17/09/2026', 'Walkin Wendy', 'URP', '50.00', 'Failed', 'Missing HSN or invalid GSTIN format'], $lines[1]);
        $this->assertSame(['ER-1', '16/09/2026', 'Mumbai Pet Mart', '27aaaaa0000a1z5', '236.00', 'Failed', 'Invalid buyer GSTIN'], $lines[2]);
    }

    public function test_update_settings_validates_and_persists_flags(): void
    {
        $this->post(route('tools.einvoice.settings'), ['gstin' => '24AAECU3183G1ZN', 'gsp_provider' => 'bogus', 'auto_upload_threshold' => 10])
            ->assertSessionHasErrors('gsp_provider');
        $this->post(route('tools.einvoice.settings'), ['gsp_provider' => 'mock', 'auto_upload_threshold' => -5])
            ->assertSessionHasErrors(['gstin', 'auto_upload_threshold']);

        $this->from('/hub')->post(route('tools.einvoice.settings'), [
            'gstin' => '27AAAAA0000A1Z5', 'gsp_provider' => 'cleartax', 'client_id' => 'ID9', 'client_secret' => 'S9',
            'auto_upload_threshold' => 25000, 'is_sandbox' => 1,
        ])->assertRedirect('/hub')->assertSessionHas('status');

        $s = GstSetting::current()->fresh();
        $this->assertSame('27AAAAA0000A1Z5', $s->gstin);
        $this->assertSame('cleartax', $s->gsp_provider);
        $this->assertSame('ID9', $s->client_id);
        $this->assertEquals(25000, $s->auto_upload_threshold);
        $this->assertTrue($s->is_sandbox);
        $this->assertFalse($s->auto_upload_enabled, 'unchecked checkbox is stored as false');
    }

    public function test_purchase_side_gst_is_estimated_when_not_recorded(): void
    {
        $sup = Supplier::create(['name' => 'S', 'status' => true]);
        PurchaseInvoice::create(['invoice_number' => 'PE-1', 'invoice_date' => '2026-09-05', 'supplier_id' => $sup->id, 'branch_id' => $this->branch->id, 'total' => 1180, 'total_gst' => 0, 'status' => 'Posted']);
        $this->bill('SE-1', '2026-09-06', $this->b2c, 118, 0);

        $r = $this->get(route('tools.integrations-gst', ['from_date' => '2026-09-01', 'to_date' => '2026-09-30']));
        $this->assertEquals(180.0, $r->viewData('gstr2TaxPaid'));
        $this->assertEquals(1000.0, $r->viewData('gstr2Taxable'));
        $this->assertEquals(0.0, $r->viewData('gstr3bTaxPayable'));   // 18 collected < 180 paid

        $j = $this->postJson(route('tools.gst.gstr-9-sync'), ['year' => 2026])->json();
        $this->assertEquals(118.0, $j['annual_turnover']);
        $this->assertEquals(18.0, $j['annual_tax_collected']);
        $this->assertEquals(100.0, $j['annual_taxable_turnover']);
        $this->assertEquals(180.0, $j['annual_itc_claimed']);
        $this->assertEquals(0.0, $j['annual_net_tax_paid']);
    }
}
