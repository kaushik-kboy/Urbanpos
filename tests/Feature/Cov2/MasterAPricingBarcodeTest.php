<?php

namespace Tests\Feature\Cov2;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAPricingBarcodeTest extends TestCase
{
    use RefreshDatabase, MasterAHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnFirstUser();
    }

    private function item(array $o = []): Item
    {
        return Item::create(array_merge([
            'name' => 'PB Item '.uniqid(), 'product_type' => 'Standard',
            'cost_price' => 10, 'landing_cost' => 10, 'sell_price' => 15, 'mrp' => 20,
        ], $o));
    }

    // ------------------------------------------------------------ search

    public function test_search_returns_empty_for_blank_query(): void
    {
        $this->asRole('Owner');
        $this->item();
        $this->getJson(route('master.item-price-change.search', ['q' => '  ']))->assertOk()->assertExactJson([]);
        $this->getJson(route('master.item-price-change.search'))->assertOk()->assertExactJson([]);
    }

    public function test_search_matches_name_barcode_alias_and_formats_text(): void
    {
        $this->asRole('Owner');
        $brand = Brand::create(['name' => 'ZetaBrand', 'status' => true]);
        $a = $this->item(['name' => 'Searchable Kibble', 'ean_upc_code' => '8901234500001', 'brand_id' => $brand->id]);
        $b = $this->item(['name' => 'Other Thing', 'alias' => 'kibblealias']);
        $this->item(['name' => 'Unrelated Toy']);

        $r = $this->getJson(route('master.item-price-change.search', ['q' => 'Kibble']))->assertOk();
        $ids = collect($r->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
        $row = collect($r->json())->firstWhere('id', $a->id);
        $this->assertSame('Searchable Kibble (Barcode: 8901234500001, Brand: ZetaBrand)', $row['text']);
        $this->assertSame('8901234500001', $row['barcode']);
        $this->assertEquals(15, $row['sell_price']);
        $this->assertEquals(20, $row['mrp']);
        $rowB = collect($r->json())->firstWhere('id', $b->id);
        $this->assertSame($b->name, $rowB['text']); // no extras appended

        $byBarcode = $this->getJson(route('master.item-price-change.search', ['q' => '89012345']))->assertOk()->json();
        $this->assertSame([$a->id], collect($byBarcode)->pluck('id')->all());
    }

    // ------------------------------------------------------------ index

    public function test_index_with_item_id_and_branches_excluding_global(): void
    {
        $this->asRole('Owner');
        $item = $this->item();
        $b1 = $this->makeBranch(['name' => 'PB Shop A']);
        $this->makeBranch(['name' => 'GLOBAL']);
        $this->makeBranch(['name' => 'PB Closed', 'status' => false]);
        ItemStock::create(['item_id' => $item->id, 'branch_id' => $b1->id, 'quantity' => 4, 'sell_price' => 17, 'mrp' => 22]);

        $r = $this->get(route('master.item-price-change.index', ['item_id' => $item->id]))->assertOk();
        $this->assertSame($item->id, $r->viewData('selectedItem')->id);
        $names = $r->viewData('branches')->pluck('name');
        $this->assertTrue($names->contains('PB Shop A'));
        $this->assertFalse($names->contains('GLOBAL'));
        $this->assertFalse($names->contains('PB Closed'));
        $this->assertSame(17.0, (float) $r->viewData('stocksByBranch')[$b1->id]->sell_price);
    }

    public function test_index_search_and_brand_filter_narrow_the_list(): void
    {
        $this->asRole('Owner');
        $brand = Brand::create(['name' => 'FilterBrand', 'status' => true]);
        $hit = $this->item(['name' => 'Zebra Chew', 'brand_id' => $brand->id]);
        $this->item(['name' => 'Zebra Bone']);
        $this->item(['name' => 'Plain']);

        $r = $this->get(route('master.item-price-change.index', ['search' => 'Zebra', 'brand_id' => $brand->id]))->assertOk();
        $this->assertSame([$hit->id], $r->viewData('items')->pluck('id')->all());
        $this->assertNotNull($r->viewData('selectedItem'));
        $this->assertStringContainsString('Zebra', $r->viewData('selectedItem')->name);
        $this->assertSame('FilterBrand', $r->viewData('brands')[$brand->id]);
    }

    public function test_index_falls_back_to_active_global_when_no_other_branch_is_active(): void
    {
        $this->asRole('Owner');
        Branch::query()->update(['status' => false]);
        $only = $this->makeBranch(['name' => 'GLOBAL']);
        $this->item(['name' => 'Aaa First']);

        $r = $this->get(route('master.item-price-change.index'))->assertOk();
        $this->assertNotNull($r->viewData('selectedItem'));
        $this->assertSame([$only->id], $r->viewData('branches')->pluck('id')->all());
    }

    public function test_index_with_no_items_has_null_selected_item(): void
    {
        $this->asRole('Owner');
        Item::query()->delete();
        $r = $this->get(route('master.item-price-change.index'))->assertOk();
        $this->assertNull($r->viewData('selectedItem'));
        $this->assertCount(0, $r->viewData('stocksByBranch'));
    }

    // ------------------------------------------------------------ update

    public function test_update_creates_and_updates_branch_stock_prices_with_audit_and_item_defaults(): void
    {
        $this->asRole('Owner');
        $item = $this->item(['name' => 'Repriced']);
        $b1 = $this->makeBranch();
        $b2 = $this->makeBranch();
        ItemStock::create(['item_id' => $item->id, 'branch_id' => $b1->id, 'quantity' => 9, 'cost_price' => 1, 'landing_cost' => 1, 'sell_price' => 2, 'mrp' => 3]);

        $res = $this->post(route('master.item-price-change.update'), [
            'item_id' => $item->id,
            'prices' => [
                $b1->id => ['cost_price' => 11, 'landing_cost' => 12, 'sell_price' => 30, 'mrp' => 35],
                $b2->id => ['sell_price' => 40, 'mrp' => 40],
            ],
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('status', 'Branch prices updated successfully for "Repriced"!');

        $s1 = ItemStock::where('item_id', $item->id)->where('branch_id', $b1->id)->first();
        $this->assertEquals(30, $s1->sell_price);
        $this->assertEquals(9, $s1->quantity); // quantity untouched
        $s2 = ItemStock::where('item_id', $item->id)->where('branch_id', $b2->id)->first();
        $this->assertNotNull($s2);
        $this->assertEquals(40, $s2->sell_price);
        $this->assertEquals(0, $s2->cost_price);

        $item->refresh();
        $this->assertEquals(11, $item->cost_price); // defaults come from the first submitted branch
        $this->assertEquals(35, $item->mrp);

        $audit = AuditLog::where('auditable_type', ItemStock::class)->where('auditable_id', $s1->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('update', $audit->action);
        $this->assertEquals(2, (float) $audit->old_values['sell_price']);
        $this->assertEquals(30, (float) $audit->new_values['sell_price']);
        $this->assertSame(2, AuditLog::where('auditable_type', ItemStock::class)->count());
    }

    public function test_update_rejects_sell_price_above_mrp_and_changes_nothing(): void
    {
        $this->asRole('Owner');
        $item = $this->item();
        $b = $this->makeBranch();

        $this->from('/x')->post(route('master.item-price-change.update'), [
            'item_id' => $item->id,
            'prices' => [$b->id => ['sell_price' => 50, 'mrp' => 40]],
        ])->assertRedirect('/x')->assertSessionHasErrors('prices');

        $this->assertSame(0, ItemStock::where('item_id', $item->id)->count());
        $this->assertEquals(15, $item->fresh()->sell_price);
        $this->assertSame(0, AuditLog::where('auditable_type', ItemStock::class)->count());
    }

    public function test_update_validation_missing_fields(): void
    {
        $this->asRole('Owner');
        $item = $this->item();
        $b = $this->makeBranch();

        $this->post(route('master.item-price-change.update'), [])->assertSessionHasErrors(['item_id', 'prices']);
        $this->post(route('master.item-price-change.update'), ['item_id' => 999999999, 'prices' => [$b->id => ['sell_price' => 1, 'mrp' => 1]]])
            ->assertSessionHasErrors('item_id');
        $this->post(route('master.item-price-change.update'), ['item_id' => $item->id, 'prices' => [$b->id => ['sell_price' => -1, 'mrp' => 'abc']]])
            ->assertSessionHasErrors(['prices.'.$b->id.'.sell_price', 'prices.'.$b->id.'.mrp']);
    }

    public function test_update_is_owner_only_manager_and_cashier_forbidden(): void
    {
        $item = $this->item();
        $b = $this->makeBranch();
        $payload = ['item_id' => $item->id, 'prices' => [$b->id => ['sell_price' => 99, 'mrp' => 99]]];

        $this->asRole('Manager');
        $this->post(route('master.item-price-change.update'), $payload)->assertForbidden();
        $this->asRole('Cashier');
        $this->post(route('master.item-price-change.update'), $payload)->assertForbidden();
        $this->assertEquals(15, $item->fresh()->sell_price);
        $this->assertSame(0, ItemStock::count());
    }

    public function test_price_change_pages_require_login(): void
    {
        $this->get(route('master.item-price-change.index'))->assertRedirect(route('login'));
        $this->post(route('master.item-price-change.update'), [])->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ barcode print

    private function invoiceWith(Item $item, array $lineOver = []): PurchaseInvoice
    {
        $branch = $this->makeBranch(['name' => 'Inv Branch']);
        $sup = Supplier::create(['name' => 'S '.uniqid(), 'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit',
            'credit_limit' => 0, 'credit_balance' => 0, 'credit_days' => 0, 'status' => true, 'gst_type' => 'Un Register', 'mail_type' => 'None']);
        $pi = PurchaseInvoice::create(['invoice_number' => 'PI-'.uniqid(), 'invoice_date' => now()->toDateString(), 'supplier_id' => $sup->id, 'branch_id' => $branch->id, 'total' => 0, 'status' => 'Posted']);
        PurchaseInvoiceItem::create(array_merge(['purchase_invoice_id' => $pi->id, 'item_id' => $item->id, 'qty' => 3, 'cost_price' => 10, 'sell_price' => 0, 'mrp' => 0, 'net_amount' => 30], $lineOver));

        return $pi;
    }

    public function test_print_labels_for_single_item_repeats_by_qty(): void
    {
        $this->asRole('Manager');
        $item = $this->item(['name' => 'Label Item', 'ean_upc_code' => '111222333']);
        $r = $this->get(route('master.barcodes.print', ['item_id' => $item->id, 'qty' => 4, 'format' => '38x25']))->assertOk();
        $labels = $r->viewData('labels');
        $this->assertCount(4, $labels);
        $this->assertSame('111222333', $labels[0]['barcode']);
        $this->assertSame('38x25', $r->viewData('format'));
        $this->assertSame(config('app.name', 'UrbanPOS'), $r->viewData('storeName'));
    }

    public function test_print_labels_qty_floors_at_one_and_barcode_falls_back_to_code_then_id(): void
    {
        $this->asRole('Manager');
        $coded = $this->item(['item_code' => 'CODE77']);
        $bare = $this->item();

        $r = $this->get(route('master.barcodes.print', ['item_id' => $coded->id, 'qty' => 0]))->assertOk();
        $this->assertCount(1, $r->viewData('labels'));
        $this->assertSame('CODE77', $r->viewData('labels')[0]['barcode']);
        $this->assertSame('50x38_2up', $r->viewData('format')); // default

        $r2 = $this->get(route('master.barcodes.print', ['item_id' => $bare->id]))->assertOk();
        $this->assertSame((string) $bare->id, $r2->viewData('labels')[0]['barcode']);
    }

    public function test_print_labels_missing_item_or_invoice_is_404_and_no_params_is_empty(): void
    {
        $this->asRole('Manager');
        $this->get(route('master.barcodes.print', ['item_id' => 987654321]))->assertNotFound();
        $this->get(route('master.barcodes.print', ['purchase_invoice_id' => 987654321]))->assertNotFound();
        $r = $this->get(route('master.barcodes.print'))->assertOk();
        $this->assertSame([], $r->viewData('labels'));
    }

    public function test_print_labels_from_purchase_invoice_uses_line_prices_item_fallback_and_branch_store_name(): void
    {
        $this->asRole('Manager');
        $item = $this->item(['name' => 'PI Item', 'mrp' => 99, 'sell_price' => 88]);
        $pi = $this->invoiceWith($item, ['qty' => 2.4, 'mrp' => 50, 'sell_price' => 0, 'exp_date' => '2027-05-09']);

        $r = $this->get(route('master.barcodes.print', ['purchase_invoice_id' => $pi->id]))->assertOk();
        $labels = $r->viewData('labels');
        $this->assertCount(2, $labels); // round(2.4) = 2
        $this->assertSame(50.0, $labels[0]['mrp']);        // line mrp wins
        $this->assertSame(88.0, $labels[0]['sell_price']); // line sell 0 -> item price
        $this->assertSame('2027-05-09', $labels[0]['exp_date']);
        $this->assertNotSame('', (string) $r->viewData('storeName'));
    }

    public function test_tspl_two_up_pairs_labels_and_handles_odd_count(): void
    {
        $this->asRole('Manager');
        $item = $this->item(['name' => 'Two-Up: Chew!', 'ean_upc_code' => 'AB-12 34', 'sell_price' => 15.5, 'mrp' => 20]);
        $res = $this->get(route('master.barcodes.tspl', ['item_id' => $item->id, 'qty' => 3, 'format' => '50x50_2up']))->assertOk();
        $body = $res->getContent();
        $this->assertStringContainsString("SIZE 102 mm, 50 mm\r\n", $body);
        $this->assertSame(2, substr_count($body, "CLS\r\n"));    // 3 labels -> 2 rows
        $this->assertSame(2, substr_count($body, "PRINT 1\r\n"));
        $this->assertSame(3, substr_count($body, 'BARCODE '));
        $this->assertStringContainsString('"AB1234"', $body);        // barcode sanitised
        $this->assertStringContainsString('"Two-Up Chew"', $body);   // name sanitised
        $this->assertStringContainsString('Rs. 15.50', $body);
        $this->assertStringContainsString('TEXT 430,10', $body);
        $this->assertStringContainsString('attachment; filename="labels_tsc_te244.prn"', $res->headers->get('Content-Disposition'));
    }

    public function test_tspl_row_height_by_format_and_38x25_is_two_up(): void
    {
        $this->asRole('Manager');
        $item = $this->item();
        $q = ['item_id' => $item->id, 'qty' => 2];
        $this->assertStringContainsString('SIZE 102 mm, 38 mm', $this->get(route('master.barcodes.tspl', $q + ['format' => '50x38_2up']))->getContent());
        $this->assertStringContainsString('SIZE 102 mm, 63.5 mm', $this->get(route('master.barcodes.tspl', $q + ['format' => '102x64']))->getContent());
        $b = $this->get(route('master.barcodes.tspl', $q + ['format' => '38x25']))->getContent();
        $this->assertStringContainsString('SIZE 102 mm, 25 mm', $b);
        $this->assertSame(1, substr_count($b, "CLS\r\n")); // 2-up: both labels on one row
    }

    public function test_tspl_single_up_prints_mrp_only_when_above_price_and_expiry_when_set(): void
    {
        $this->asRole('Manager');
        $item = $this->item(['name' => 'Single Up', 'ean_upc_code' => 'X9', 'sell_price' => 15, 'mrp' => 20]);
        $body = $this->get(route('master.barcodes.tspl', ['item_id' => $item->id, 'format' => '102x64']))->getContent();
        $this->assertStringContainsString('MRP: Rs. 20.00', $body);
        $this->assertStringContainsString('PRICE: Rs. 15.00', $body);
        $this->assertStringNotContainsString('EXP:', $body);

        $same = $this->item(['name' => 'Same Price', 'sell_price' => 20, 'mrp' => 20]);
        $b2 = $this->get(route('master.barcodes.tspl', ['item_id' => $same->id, 'format' => '102x64']))->getContent();
        $this->assertStringNotContainsString('MRP: Rs.', $b2);

        $pi = $this->invoiceWith($item, ['qty' => 1, 'exp_date' => '2028-01-31', 'mrp' => 20, 'sell_price' => 15]);
        $b3 = $this->get(route('master.barcodes.tspl', ['purchase_invoice_id' => $pi->id, 'format' => '102x64']))->getContent();
        $this->assertStringContainsString('EXP: 2028-01-31', $b3);
    }
}
