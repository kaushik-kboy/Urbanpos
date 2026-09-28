<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use Tests\TestCase;

class FinPurchaseMoreTest extends TestCase
{
    use FinHelper {
        setUp as protected finSetUp;
    }

    private Item $item2;

    protected function setUp(): void
    {
        $this->finSetUp();
        $this->item2 = Item::create(['name' => 'Fin Item B', 'item_code' => 'FIN-2', 'cost_price' => 50, 'sell_price' => 80, 'mrp' => 90, 'gst_tax_id' => $this->gst->id, 'status' => true]);
    }

    private function payload(array $over = [], array $lines = []): array
    {
        $lines = $lines ?: [['item_id' => $this->item->id, 'qty' => 10, 'cost_price' => 100, 'sell_price' => 150, 'mrp' => 160]];

        return array_merge([
            'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supp->id, 'branch_id' => $this->branch->id,
            'purchase_type' => 'Local', 'c_form' => 'No Forms',
            'items' => array_map(fn ($l) => array_merge(['free_qty' => 0], $l), $lines),
        ], $over);
    }

    private function create(array $over = [], array $lines = []): PurchaseInvoice
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->payload($over, $lines))->assertSessionHasNoErrors();

        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    public function test_header_discount_is_spread_across_lines_pro_rata_with_remainder_on_last_line(): void
    {
        // taxable bases: 10 x 100 = 1000 and 10 x 50 = 500; header discount 150 splits 100 / 50
        $inv = $this->create(['other_disc_amt' => 150], [
            ['item_id' => $this->item->id, 'qty' => 10, 'cost_price' => 100, 'sell_price' => 150, 'mrp' => 160],
            ['item_id' => $this->item2->id, 'qty' => 10, 'cost_price' => 50, 'sell_price' => 80, 'mrp' => 90],
        ]);
        $gst = $inv->items()->orderBy('id')->pluck('gst_tax_amount')->map(fn ($v) => (float) $v)->all();
        $this->assertEquals([162.0, 81.0], $gst, 'GST is levied on the discounted taxable value of each line');
        $this->assertEquals(243.0, (float) $inv->total_gst);
        $this->assertEquals(1593.0, (float) $inv->total);
        $this->assertEquals(20.0, (float) $inv->total_qty);
    }

    public function test_update_switching_supplier_is_checked_against_the_new_suppliers_credit_limit(): void
    {
        $inv = $this->create();
        $tight = Supplier::create(['name' => 'Tight Supplier', 'phone' => '9777000077', 'state' => 'Gujarat', 'credit_limit' => 100]);

        $this->put(route('purchase.purchase-invoices.update', $inv), $this->payload(['supplier_id' => $tight->id]))
            ->assertSessionHasErrors('credit_limit');
        $this->assertSame($this->supp->id, $inv->fresh()->supplier_id, 'a blocked switch leaves the invoice untouched');

        $roomy = Supplier::create(['name' => 'Roomy Supplier', 'phone' => '9777000078', 'state' => 'Gujarat', 'credit_limit' => 1000000]);
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->payload(['supplier_id' => $roomy->id]))->assertSessionHasNoErrors();
        $this->assertSame($roomy->id, $inv->fresh()->supplier_id);
    }

    public function test_update_same_supplier_only_the_delta_counts_against_the_limit(): void
    {
        $limited = Supplier::create(['name' => 'Limited Supplier', 'phone' => '9777000079', 'state' => 'Gujarat', 'credit_limit' => 2000]);
        $inv = $this->create(['supplier_id' => $limited->id]);   // 1180 incl. GST, within 2000
        // re-saving the same amount adds nothing even though outstanding + full total would exceed the limit
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->payload(['supplier_id' => $limited->id]))->assertSessionHasNoErrors();
        // growing it beyond the limit by more than the headroom is refused
        $this->put(route('purchase.purchase-invoices.update', $inv), $this->payload(['supplier_id' => $limited->id], [['item_id' => $this->item->id, 'qty' => 20, 'cost_price' => 100, 'sell_price' => 150, 'mrp' => 160]]))
            ->assertSessionHasErrors('credit_limit');
        $this->assertEquals(1180.0, (float) $inv->fresh()->total);
    }

    public function test_update_rejects_duplicate_supplier_invoice_number_and_duplicate_grn_but_allows_own(): void
    {
        $a = $this->create(['supplier_inv_no' => 'SUP-A', 'supplier_inv_amount' => 1180]);
        $b = $this->create(['supplier_inv_no' => 'SUP-B', 'supplier_inv_amount' => 1180]);

        $this->put(route('purchase.purchase-invoices.update', $b), $this->payload(['supplier_inv_no' => 'sup-a', 'supplier_inv_amount' => 1180]))
            ->assertSessionHasErrors('supplier_inv_no');
        $this->assertSame('SUP-B', $b->fresh()->supplier_inv_no);

        $this->put(route('purchase.purchase-invoices.update', $b), $this->payload(['supplier_inv_no' => 'sup-b', 'supplier_inv_amount' => 1180]))
            ->assertSessionHasNoErrors();

        $this->put(route('purchase.purchase-invoices.update', $b), $this->payload(['supplier_inv_no' => 'SUP-B', 'supplier_inv_amount' => 1180, 'grn_number' => $a->grn_number]))
            ->assertSessionHasErrors('grn_number');
    }

    public function test_item_list_fills_missing_prices_and_expiry_from_other_branch_purchases(): void
    {
        $b2 = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $bare = Item::create(['name' => 'Bare Item', 'item_code' => 'BARE-1', 'cost_price' => 0, 'sell_price' => 0, 'mrp' => 0, 'gst_tax_id' => $this->gst->id, 'status' => true]);
        $inv = PurchaseInvoice::create(['invoice_number' => 'FB-1', 'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supp->id, 'branch_id' => $b2->id, 'purchase_type' => 'Local', 'total' => 1, 'status' => 'Posted']);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $bare->id, 'exp_date' => '2028-08-08', 'qty' => 1, 'cost_price' => 42, 'sell_price' => 77, 'mrp' => 88]);

        $rows = $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Bare Item', 'branch_id' => $this->branch->id]))->assertOk()->json('items');
        $this->assertCount(1, $rows);
        $this->assertSame('2028-08-08', $rows[0]['exp_date']);
        $this->assertEquals(42, $rows[0]['cost_price']);
        $this->assertEquals(77, $rows[0]['sell_price']);
        $this->assertEquals(88, $rows[0]['mrp']);

        // an item that already has master prices keeps them
        $this->item->update(['cost_price' => 60, 'sell_price' => 100, 'mrp' => 120]);
        $inv2 = PurchaseInvoice::create(['invoice_number' => 'FB-2', 'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supp->id, 'branch_id' => $b2->id, 'purchase_type' => 'Local', 'total' => 1, 'status' => 'Posted']);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv2->id, 'item_id' => $this->item->id, 'exp_date' => '2028-09-09', 'qty' => 1, 'cost_price' => 1, 'sell_price' => 2, 'mrp' => 3]);
        $row = $this->getJson(route('purchase.purchase-invoices.item-list', ['search' => 'Fin Item', 'branch_id' => $this->branch->id]))->json('items');
        $mine = collect($row)->firstWhere('id', $this->item->id);
        $this->assertSame('2028-09-09', $mine['exp_date']);
        $this->assertEquals(60, $mine['cost_price']);
        $this->assertEquals(100, $mine['sell_price']);
    }
}
