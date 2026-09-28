<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use Tests\TestCase;

class FinInventoryMoreTest extends TestCase
{
    use FinHelper;

    private function pi(Item $item, string $exp, Branch $branch, float $qty = 10): PurchaseInvoice
    {
        $inv = PurchaseInvoice::create([
            'invoice_number' => 'INV-'.uniqid(), 'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supp->id,
            'branch_id' => $branch->id, 'purchase_type' => 'Local', 'total' => 100, 'status' => 'Posted',
        ]);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $item->id, 'exp_date' => $exp, 'qty' => $qty, 'cost_price' => 50, 'sell_price' => 80, 'mrp' => 90]);

        return $inv;
    }

    // ---- Barcode printing --------------------------------------------------

    public function test_barcode_printing_index_lists_branch_invoices_and_loads_selected_invoice_items(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $noEan = Item::create(['name' => 'No Barcode', 'item_code' => 'NB-1', 'sell_price' => 30, 'mrp' => 35, 'cost_price' => 10, 'status' => true]);
        $inv = $this->pi($this->item, '2027-01-01', $this->branch, 7.4);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $noEan->id, 'qty' => 0.2, 'cost_price' => 10, 'sell_price' => 30, 'mrp' => 35]);
        $foreign = $this->pi($this->item, '2027-01-01', $b2);

        $r = $this->get(route('inventory.barcode-printing.index', ['branch_id' => $this->branch->id, 'purchase_invoice_id' => $inv->id]))->assertOk();
        $this->assertSame([$inv->id], $r->viewData('invoices')->pluck('id')->all(), 'only the selected branch invoices are offered');
        $lines = $r->viewData('selectedItems')->keyBy('id');
        $this->assertCount(2, $lines);
        $this->assertSame('8901000000011', $lines[$this->item->id]['barcode']);
        $this->assertSame(7, $lines[$this->item->id]['qty'], 'label qty is the rounded purchase qty');
        $this->assertSame(str_pad((string) $noEan->id, 8, '0', STR_PAD_LEFT), $lines[$noEan->id]['barcode'], 'items without EAN get a padded id barcode');
        $this->assertSame(1, $lines[$noEan->id]['qty'], 'label qty is never below 1');
        $this->assertEquals(80, $lines[$this->item->id]['sell_price']);

        // unknown invoice -> nothing selected, no error
        $this->assertCount(0, $this->get(route('inventory.barcode-printing.index', ['purchase_invoice_id' => 999999]))->assertOk()->viewData('selectedItems'));
        $this->assertNotContains($foreign->id, $r->viewData('invoices')->pluck('id')->all());
    }

    // ---- Price fixing screens ----------------------------------------------

    public function test_price_fixing_tabs_and_filters(): void
    {
        $brand = Brand::create(['name' => 'Royal', 'status' => true]);
        $a = Item::create(['name' => 'Kibble Royal', 'item_code' => 'PF-1', 'ean_upc_code' => '8905550001', 'brand_id' => $brand->id, 'cost_price' => 10, 'sell_price' => 20, 'mrp' => 25, 'status' => true]);
        $b = Item::create(['name' => 'Toy Ball', 'item_code' => 'PF-2', 'ean_upc_code' => '8905550002', 'cost_price' => 10, 'sell_price' => 20, 'mrp' => 25, 'status' => true]);
        $this->seedStock($a, 3);

        $ids = fn (string $route, array $q = []) => $this->get(route($route, array_merge(['branch_id' => $this->branch->id], $q)))->assertOk();

        $r = $ids('inventory.price-fixing.index');
        $this->assertSame('markup_markdown', $r->viewData('tab'));
        $this->assertContains($a->id, $r->viewData('items')->pluck('id')->all());
        $this->assertContains($b->id, $r->viewData('items')->pluck('id')->all());
        $this->assertCount(4, $r->viewData('defaultPriceLevels'));

        $this->assertSame('price_levels', $ids('inventory.price-fixing.price-level')->viewData('tab'));
        $this->assertSame('price_level_items', $ids('inventory.price-fixing.price-level-items')->viewData('tab'));
        $this->assertSame('markup_markdown', $ids('inventory.price-fixing.markup-markdown')->viewData('tab'));

        $byBrand = $ids('inventory.price-fixing.index', ['brand_id' => $brand->id])->viewData('items')->pluck('id')->all();
        $this->assertSame([$a->id], $byBrand);
        $this->assertSame([$b->id], $ids('inventory.price-fixing.index', ['search' => '8905550002'])->viewData('items')->pluck('id')->all());
        $this->assertSame([$a->id], $ids('inventory.price-fixing.index', ['search' => 'Kibble'])->viewData('items')->pluck('id')->all());
        $this->assertSame([], $ids('inventory.price-fixing.index', ['category_value_id' => 999999])->viewData('items')->pluck('id')->all());
    }

    // ---- Stock transfer item list expiry fallback --------------------------

    public function test_transfer_item_list_falls_back_to_other_branch_expiry(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $i = Item::create(['name' => 'Transfer Thing', 'item_code' => 'TT-1', 'sell_price' => 10, 'mrp' => 12, 'cost_price' => 5, 'status' => true]);
        $this->seedStock($i, 6);
        $this->pi($i, '2029-05-05', $b2);

        $rows = $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->branch->id, 'search' => 'Transfer Thing']))->assertOk()->json('items');
        $this->assertCount(1, $rows);
        $this->assertSame('2029-05-05', $rows[0]['exp_date']);
        $this->assertEquals(6, $rows[0]['qty']);
        // expiry filter now matches through the fallback
        $this->assertCount(1, $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->branch->id, 'search' => 'Transfer Thing', 'expiry' => '2029']))->json('items'));
        $this->assertCount(0, $this->getJson(route('inventory.stock-transfers.item-list', ['branch_id' => $this->branch->id, 'search' => 'Transfer Thing', 'expiry' => '2031']))->json('items'));
    }
}
