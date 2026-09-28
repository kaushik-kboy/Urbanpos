<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Item;
use App\Models\OpeningStock;
use App\Models\OpeningStockItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\StockLedger;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinSalesLookupTest extends TestCase
{
    use FinHelper;

    private function mkItem(string $name, string $code, array $over = []): Item
    {
        return Item::create(array_merge([
            'name' => $name, 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'gst_tax_id' => $this->gst->id, 'status' => true, 'allow_negative_stock' => false,
        ], $over));
    }

    private function pi(Item $item, string $exp, array $line = [], ?Branch $branch = null): PurchaseInvoice
    {
        $inv = PurchaseInvoice::create([
            'invoice_number' => 'LK-'.uniqid(), 'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supp->id,
            'branch_id' => ($branch ?? $this->branch)->id, 'purchase_type' => 'Local', 'total' => 100, 'status' => 'Posted',
        ]);
        PurchaseInvoiceItem::create(array_merge([
            'purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'exp_date' => $exp, 'qty' => 10,
            'cost_price' => 50, 'sell_price' => 0, 'mrp' => 0,
        ], $line));

        return $inv;
    }

    private function list(array $q = []): array
    {
        return $this->getJson(route('sales.sales-bills.item-list', array_merge(['branch_id' => $this->branch->id], $q)))->assertOk()->json('items');
    }

    // ---- itemList ----------------------------------------------------------

    public function test_item_list_hides_out_of_stock_unless_show_all_or_negative_allowed(): void
    {
        $inStock = $this->mkItem('Alpha Food', 'AL-1');
        $out = $this->mkItem('Beta Food', 'BE-1');
        $neg = $this->mkItem('Gamma Food', 'GA-1', ['allow_negative_stock' => true]);
        $inactive = $this->mkItem('Delta Food', 'DE-1', ['status' => false]);
        $this->seedStock($inStock, 5);
        $this->seedStock($inactive, 5);

        $names = collect($this->list())->pluck('name')->all();
        $this->assertSame(['Alpha Food', 'Gamma Food'], $names);
        $this->assertNotContains('Delta Food', collect($this->list(['show_all' => 1]))->pluck('name')->all(), 'inactive items never listed');

        $all = collect($this->list(['show_all' => 1]));
        $this->assertSame(['Alpha Food', 'Beta Food', 'Fin Item', 'Gamma Food'], $all->pluck('name')->all());
        $row = $all->firstWhere('name', 'Alpha Food');
        $this->assertEquals(5.0, $row['qty']);
        $this->assertSame('AL-1', $row['code']);
        $this->assertEquals(18.0, $row['gst_percent']);
        $this->assertEquals(100.0, $row['sell_price']);
        $this->assertTrue($all->firstWhere('name', 'Gamma Food')['allow_negative_stock']);
    }

    public function test_item_list_is_scoped_to_the_requested_branch_stock(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $i = $this->mkItem('Scoped Item', 'SC-1');
        $this->seedStock($i, 7, $b2);
        $this->assertSame([], $this->list());
        $rows = $this->getJson(route('sales.sales-bills.item-list', ['branch_id' => $b2->id]))->json('items');
        $this->assertEquals(7.0, $rows[0]['qty']);
    }

    public function test_item_list_customer_filter_only_returns_previously_bought_items_ignoring_cancelled(): void
    {
        $bought = $this->mkItem('Bought Item', 'BO-1');
        $cancelledOnly = $this->mkItem('Cancelled Item', 'CA-1');
        $never = $this->mkItem('Never Item', 'NE-1');
        foreach ([$bought, $cancelledOnly, $never] as $i) {
            $this->seedStock($i, 3);
        }
        $mk = function (Item $i, string $status) {
            $b = SalesBill::create(['bill_number' => 'CB-'.uniqid(), 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 100, 'status' => $status]);
            SalesBillItem::create(['sales_bill_id' => $b->id, 'item_id' => $i->id, 'qty' => 1, 'sell_price' => 100, 'net_amount' => 100]);
        };
        $mk($bought, 'Posted');
        $mk($cancelledOnly, 'Cancelled');

        $this->assertSame(['Bought Item'], collect($this->list(['customer_id' => $this->cust->id]))->pluck('name')->all());
        $this->assertCount(3, $this->list());
    }

    public function test_item_list_search_ranks_exact_code_then_barcode_then_prefix_then_name(): void
    {
        $byName = $this->mkItem('Zed Widget AB12 special', 'ZZ-9');
        $prefix = $this->mkItem('Prefix Holder', 'AB12-PREFIX');
        $exactBarcode = $this->mkItem('Barcode Holder', 'BB-1', ['ean_upc_code' => 'AB12']);
        $exactCode = $this->mkItem('Code Holder', 'AB12');
        foreach ([$byName, $prefix, $exactBarcode, $exactCode] as $i) {
            $this->seedStock($i, 1);
        }

        $names = collect($this->list(['search' => 'AB12']))->pluck('name')->all();
        $this->assertSame(['Code Holder', 'Barcode Holder', 'Prefix Holder', 'Zed Widget AB12 special'], $names);

        // barcode is never substring-matched (only exact / prefix)
        $bar = $this->mkItem('Plain Thing', 'PL-1', ['ean_upc_code' => '8901234567890']);
        $this->seedStock($bar, 1);
        $this->assertSame([], $this->list(['search' => '45678']));
        $this->assertSame(['Plain Thing'], collect($this->list(['search' => '89012']))->pluck('name')->all());
    }

    public function test_item_list_code_filter_alone_and_combined_with_search(): void
    {
        $a = $this->mkItem('Kibble Small', 'KB-100', ['ean_upc_code' => '8900001']);
        $b = $this->mkItem('Kibble Large', 'KB-200', ['ean_upc_code' => '8900002']);
        $c = $this->mkItem('Toy Ball', 'TY-1');
        foreach ([$a, $b, $c] as $i) {
            $this->seedStock($i, 2);
        }
        $this->assertSame(['Kibble Small', 'Kibble Large'], collect($this->list(['code' => 'KB-']))->pluck('name')->sortByDesc(fn ($n) => $n === 'Kibble Small')->values()->all());
        $this->assertSame(['Kibble Large'], collect($this->list(['code' => '8900002']))->pluck('name')->all());
        $this->assertSame(['Kibble Large'], collect($this->list(['code' => 'KB-', 'search' => 'Large']))->pluck('name')->all());
        $this->assertSame([], $this->list(['code' => 'KB-', 'search' => 'Toy']));
    }

    public function test_item_list_expiry_from_branch_purchase_with_price_override_and_expiry_filter(): void
    {
        $i = $this->mkItem('Expiring Item', 'EX-1');
        $this->seedStock($i, 4);
        $this->pi($i, '2027-05-20', ['sell_price' => 150, 'mrp' => 175]);
        $this->pi($i, '2026-12-01', ['sell_price' => 140, 'mrp' => 165]);

        $row = $this->list()[0];
        $this->assertSame('2026-12-01', $row['exp_date'], 'earliest expiry wins');
        $this->assertEquals(140.0, $row['sell_price'], 'price comes from the earliest-expiry batch');
        $this->assertEquals(165.0, $row['mrp']);

        $this->assertCount(1, $this->list(['expiry' => '2026-12']));
        $this->assertCount(0, $this->list(['expiry' => '2031']));
    }

    public function test_item_list_falls_back_to_other_branch_expiry_and_keeps_master_price_when_batch_price_is_zero(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $i = $this->mkItem('Fallback Item', 'FB-1');
        $this->seedStock($i, 4);
        $this->pi($i, '2028-01-15', ['sell_price' => 0, 'mrp' => 0], $b2);

        $row = $this->list()[0];
        $this->assertSame('2028-01-15', $row['exp_date']);
        $this->assertEquals(100.0, $row['sell_price']);
        $this->assertEquals(120.0, $row['mrp']);

        $noExp = $this->mkItem('No Expiry', 'NX-1');
        $this->seedStock($noExp, 1);
        $r = collect($this->list())->firstWhere('name', 'No Expiry');
        $this->assertNull($r['exp_date']);
        // an item without any expiry is dropped once an expiry filter is set
        $this->assertSame(['Fallback Item'], collect($this->list(['expiry' => '2028']))->pluck('name')->all());
    }

    // ---- lookupItem --------------------------------------------------------

    private function lookup(array $q): array
    {
        return $this->getJson(route('sales.sales-bills.lookup-item', array_merge(['branch_id' => $this->branch->id], $q)))->assertOk()->json();
    }

    public function test_lookup_resolution_order_and_not_found(): void
    {
        $i = $this->mkItem('Lookup Shampoo', 'LK-CODE', ['ean_upc_code' => '8901234567890']);
        $this->seedStock($i, 3);

        $this->assertSame($i->id, $this->lookup(['item_id' => $i->id])['item']['id']);
        $this->assertSame($i->id, $this->lookup(['query' => 'LK-CODE'])['item']['id']);
        $this->assertSame($i->id, $this->lookup(['q' => '8901234567890'])['item']['id']);
        $this->assertSame($i->id, $this->lookup(['query' => (string) $i->id])['item']['id'], 'numeric query falls back to primary key');
        $this->assertSame($i->id, $this->lookup(['query' => 'Shampoo'])['item']['id']);
        $this->assertFalse($this->lookup(['query' => 'Shampoo', 'exact_match_only' => 1])['found']);
        $this->assertFalse($this->lookup(['query' => 'nothing-matches'])['found']);
        $this->assertFalse($this->lookup([])['found']);

        $i->update(['status' => false]);
        $this->assertFalse($this->lookup(['item_id' => $i->id])['found']);
    }

    public function test_lookup_reports_stock_and_adds_back_quantity_already_on_the_edited_bill(): void
    {
        $i = $this->mkItem('Stocked', 'ST-1');
        $this->seedStock($i, 6);
        $bill = SalesBill::create(['bill_number' => 'LKB-1', 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 100, 'status' => 'Posted']);
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $i->id, 'qty' => 2, 'sell_price' => 100, 'net_amount' => 200]);

        $this->assertEquals(6.0, (float) $this->lookup(['item_id' => $i->id])['item']['stock']);
        $r = $this->lookup(['item_id' => $i->id, 'sales_bill_id' => $bill->id]);
        $this->assertEquals(8.0, (float) $r['item']['stock']);
        $this->assertEquals(18.0, $r['item']['gst_percent']);
        $this->assertSame('Not Required', $r['item']['batch_expiry_details']);
        $this->assertSame([], $r['batches']);
        $this->assertNull($r['item']['exp_date']);
    }

    public function test_lookup_fifo_allocates_physical_stock_across_purchase_batches_newest_consumed_last(): void
    {
        $i = $this->mkItem('Fifo Item', 'FI-1');
        // 10 bought expiring 2026-12, 10 bought expiring 2027-06; 12 already sold -> 8 left, all in the later batch
        $this->pi($i, '2026-12-31', ['qty' => 10, 'sell_price' => 110, 'mrp' => 130]);
        $this->pi($i, '2027-06-30', ['qty' => 10, 'sell_price' => 120, 'mrp' => 140]);
        $this->seedStock($i, 8);

        $r = $this->lookup(['item_id' => $i->id]);
        $this->assertSame('2026-12-31', $r['item']['exp_date']);
        $this->assertCount(2, $r['batches']);
        [$first, $second] = $r['batches'];
        $this->assertSame('2026-12-31', $first['exp_date']);
        $this->assertEquals(0.0, $first['qty']);
        $this->assertEquals(10.0, $first['in_qty']);
        $this->assertSame('2027-06-30', $second['exp_date']);
        $this->assertEquals(8.0, $second['qty']);
        $this->assertEquals(120.0, $second['sell_price']);
        $this->assertSame('purchase', $second['source']);

        // partially consumed first batch
        $this->seedStock($i, 14);
        $r = $this->lookup(['item_id' => $i->id]);
        $this->assertEquals(4.0, $r['batches'][0]['qty']);
        $this->assertEquals(10.0, $r['batches'][1]['qty']);
    }

    public function test_lookup_zero_stock_zeroes_every_batch_unless_negative_stock_allowed(): void
    {
        $i = $this->mkItem('Empty Item', 'EM-1');
        $this->pi($i, '2027-01-01', ['qty' => 5]);
        $this->seedStock($i, 0);
        $r = $this->lookup(['item_id' => $i->id]);
        $this->assertEquals(0.0, $r['batches'][0]['qty']);

        $neg = $this->mkItem('Neg Item', 'NG-1', ['allow_negative_stock' => true]);
        $this->pi($neg, '2027-01-01', ['qty' => 5]);
        $this->seedStock($neg, 5);
        $this->assertTrue($this->lookup(['item_id' => $neg->id])['item']['allow_negative_stock']);
        $this->assertEquals(5.0, $this->lookup(['item_id' => $neg->id])['batches'][0]['qty']);
    }

    public function test_lookup_batches_from_opening_stock_and_cross_branch_purchase_fallback(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $i = $this->mkItem('Opening Item', 'OP-1');
        $os = OpeningStock::create(['entry_number' => 'OSK-'.uniqid(), 'branch_id' => $this->branch->id, 'entry_date' => now()->toDateString(), 'status' => 'Posted']);
        OpeningStockItem::create(['opening_stock_id' => $os->id, 'item_id' => $i->id, 'exp_date' => '2027-03-03', 'qty' => 6, 'cost_price' => 40, 'sell_price' => 90, 'mrp' => 99]);
        $this->seedStock($i, 6);

        $r = $this->lookup(['item_id' => $i->id]);
        $this->assertSame('2027-03-03', $r['batches'][0]['exp_date']);
        $this->assertEquals(6.0, $r['batches'][0]['qty']);
        $this->assertEquals(90.0, $r['batches'][0]['sell_price']);

        // purchase recorded only in ANOTHER branch still supplies batch metadata
        $j = $this->mkItem('Elsewhere Item', 'EL-1');
        $this->pi($j, '2027-09-09', ['qty' => 4, 'sell_price' => 111], $b2);
        $this->seedStock($j, 4);
        $r = $this->lookup(['item_id' => $j->id]);
        $this->assertSame('2027-09-09', $r['batches'][0]['exp_date']);
        $this->assertEquals(111.0, $r['batches'][0]['sell_price']);

        // opening stock only in another branch: fallback too
        $k = $this->mkItem('Other Opening', 'OO-1');
        $os2 = OpeningStock::create(['entry_number' => 'OSK-'.uniqid(), 'branch_id' => $b2->id, 'entry_date' => now()->toDateString(), 'status' => 'Posted']);
        OpeningStockItem::create(['opening_stock_id' => $os2->id, 'item_id' => $k->id, 'exp_date' => '2028-02-02', 'qty' => 3, 'cost_price' => 40, 'sell_price' => 0, 'mrp' => 0]);
        $this->seedStock($k, 3);
        $r = $this->lookup(['item_id' => $k->id]);
        $this->assertSame('2028-02-02', $r['batches'][0]['exp_date']);
        $this->assertEquals(100.0, $r['batches'][0]['sell_price'], 'zero batch price falls back to master price');
        $this->assertEquals(120.0, $r['batches'][0]['mrp']);
    }

    public function test_lookup_uses_tracked_stock_ledger_batches_over_fifo(): void
    {
        $i = $this->mkItem('Ledger Item', 'LG-1');
        $this->pi($i, '2026-11-30', ['qty' => 10, 'sell_price' => 100]);
        $this->seedStock($i, 9);
        $mk = fn (string $exp, float $in, float $out) => StockLedger::create([
            'item_id' => $i->id, 'branch_id' => $this->branch->id, 'exp_date' => $exp, 'movement_type' => 'PURCHASE_RECEIPT',
            'qty_in' => $in, 'qty_out' => $out, 'unit_cost' => 50, 'value_in' => 0, 'value_out' => 0,
            'running_balance_qty' => 0, 'running_balance_value' => 0, 'document_date' => now()->toDateString(), 'posted_at' => now(),
        ]);
        $mk('2026-11-30', 10, 4);   // 6 left in the purchase batch
        $mk('2027-04-04', 3, 0);    // batch that exists only in the ledger

        $r = $this->lookup(['item_id' => $i->id]);
        $byExp = collect($r['batches'])->keyBy('exp_date');
        $this->assertEquals(6.0, $byExp['2026-11-30']['qty']);
        $this->assertSame('purchase', $byExp['2026-11-30']['source']);
        $this->assertEquals(3.0, $byExp['2027-04-04']['qty']);
        $this->assertSame('ledger', $byExp['2027-04-04']['source']);
        $this->assertSame(['2026-11-30', '2027-04-04'], array_column($r['batches'], 'exp_date'));
    }

    // ---- WhatsApp / receipts -----------------------------------------------

    private function waBill(?string $mobile = '9876543210'): SalesBill
    {
        $this->cust->update(['mobile' => $mobile]);

        return SalesBill::create(['bill_number' => 'WA-'.uniqid(), 'bill_date' => now(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 250, 'status' => 'Posted']);
    }

    private function waSettings(array $over = []): void
    {
        WhatsAppSetting::current()->update(array_merge(['api_url' => 'https://wa.test', 'app_key' => 'AK', 'auth_key' => 'UK', 'template_name' => null, 'is_active' => true], $over));
    }

    public function test_send_whatsapp_success_posts_direct_message_to_sanitised_number(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'data' => ['status' => 'sent', 'mid' => 'wamid.1']], 200)]);
        $this->waSettings();
        $bill = $this->waBill();

        $r = $this->postJson(route('sales.sales-bills.send-whatsapp', $bill))->assertOk();
        $this->assertTrue($r->json('success'));
        $this->assertSame('919876543210', $r->json('phone'));
        $this->assertSame('wamid.1', $r->json('wamid'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/whatsapp/message') && str_contains((string) $req->body(), '919876543210') && str_contains((string) $req->body(), $bill->bill_number));
        Http::assertSentCount(1);

        // override phone wins over the customer's
        $this->postJson(route('sales.sales-bills.send-whatsapp', $bill), ['phone' => '9123456789'])->assertOk()->assertJsonPath('phone', '919123456789');
    }

    public function test_send_whatsapp_template_mode_sends_template_fields(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'data' => ['status' => 'sent']], 200)]);
        $this->waSettings(['template_name' => 'urban_tax_invoice', 'template_lang' => 'en']);
        $bill = $this->waBill();
        $this->postJson(route('sales.sales-bills.send-whatsapp', $bill))->assertOk()->assertJsonPath('success', true);
        Http::assertSent(fn ($req) => str_contains((string) $req->body(), 'urban_tax_invoice')
            && str_contains((string) $req->body(), 'button_url')
            && str_contains((string) $req->body(), app(ChatOnClickWhatsAppService::class)->generateReceiptHash($bill)));
    }

    public function test_send_whatsapp_failures_json_and_redirect_flavours(): void
    {
        // no credentials
        Http::fake(['wa.test/*' => Http::response(['success' => true], 200)]);
        $this->waSettings(['app_key' => null, 'auth_key' => null]);
        config(['services.chatonclick.appkey' => null, 'services.chatonclick.authkey' => null]);
        $bill = $this->waBill();
        $this->postJson(route('sales.sales-bills.send-whatsapp', $bill))->assertStatus(422)->assertJsonPath('success', false);
        $this->from(route('sales.sales-bills.show', $bill))->post(route('sales.sales-bills.send-whatsapp', $bill))
            ->assertRedirect(route('sales.sales-bills.show', $bill))->assertSessionHasErrors('whatsapp');
        Http::assertNothingSent();

        // invalid phone
        $this->waSettings();
        $noPhone = $this->waBill(null);
        $this->postJson(route('sales.sales-bills.send-whatsapp', $noPhone))->assertStatus(422)->assertJsonPath('error', 'Customer has no valid 10-digit mobile number on file.');

    }

    public function test_send_whatsapp_api_rejection_is_surfaced(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => false, 'error' => 'Bad key'], 401)]);
        $this->waSettings();
        $this->postJson(route('sales.sales-bills.send-whatsapp', $this->waBill()))->assertStatus(422)->assertJsonPath('error', 'Bad key');
    }

    public function test_send_whatsapp_delivery_failed_status_is_an_error(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'data' => ['status' => 'failed']], 200)]);
        $this->waSettings();
        $this->postJson(route('sales.sales-bills.send-whatsapp', $this->waBill()))->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_send_whatsapp_success_flashes_status_for_form_posts(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'data' => ['status' => 'sent']], 200)]);
        $this->waSettings();
        $bill = $this->waBill();
        $this->from(route('sales.sales-bills.show', $bill))->post(route('sales.sales-bills.send-whatsapp', $bill))
            ->assertRedirect(route('sales.sales-bills.show', $bill))
            ->assertSessionHas('status', fn ($s) => str_contains($s, '919876543210'));
    }

    public function test_receipt_and_public_receipt_hash_protection(): void
    {
        $bill = $this->waBill();
        $this->get(route('sales.sales-bills.receipt', $bill))->assertOk()->assertViewHas('isPublicGuest', false);

        $hash = app(ChatOnClickWhatsAppService::class)->generateReceiptHash($bill);
        auth()->logout();
        $this->get(route('sales-bills.public-receipt', [$bill, $hash]))->assertOk()->assertViewHas('isPublicGuest', true);
        $this->get(route('sales-bills.public-receipt', [$bill, 'deadbeefdeadbeef']))->assertForbidden();
        // a hash minted for another bill does not open this one
        $other = $this->waBill();
        $this->get(route('sales-bills.public-receipt', [$other, $hash]))->assertForbidden();
    }

    public function test_lookup_merges_purchases_with_the_same_expiry_into_one_batch(): void
    {
        $i = $this->mkItem('Merge Item', 'MG-1');
        $this->pi($i, '2027-07-07', ['qty' => 4, 'sell_price' => 105, 'mrp' => 125]);
        $this->pi($i, '2027-07-07', ['qty' => 6, 'sell_price' => 110, 'mrp' => 0]);
        $this->seedStock($i, 10);

        $r = $this->lookup(['item_id' => $i->id]);
        $this->assertCount(1, $r['batches']);
        $this->assertEquals(10.0, $r['batches'][0]['in_qty']);
        $this->assertEquals(10.0, $r['batches'][0]['qty']);
        $this->assertEquals(110.0, $r['batches'][0]['sell_price'], 'latest positive batch price wins');
        $this->assertEquals(120.0, $r['batches'][0]['mrp'], 'a zero batch mrp resolves to the item master mrp');
    }
}
