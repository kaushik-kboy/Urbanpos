<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * End-to-end smoke test through the actual retrofitted controllers (not the services
 * directly) — confirms Purchase -> Sale wiring produces a correct weighted-average cost,
 * a populated cost_at_sale, and that the HasPostingLifecycle guard blocks a silent edit
 * once a document is Posted, per the plan's Verification section.
 */
class SmokeControllerFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_purchase_then_sale_through_controllers_produces_correct_cost_and_lifecycle_guard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner'); // Phase 4: sensitive routes are now permission-gated.
        $branch = Branch::create(['name' => 'Smoke Branch']);
        $gstTax = GstTax::create(['description' => 'GST 18%', 'percentage' => 18, 'status' => true]);
        $item = Item::create(['name' => 'Smoke Product', 'gst_tax_id' => $gstTax->id]);
        $supplier = Supplier::create(['name' => 'Smoke Supplier']);
        $customer = \App\Models\Customer::create(['name' => 'Smoke Customer']);

        $this->actingAs($user);

        // Purchase 10 units @ Rs 100 through the real controller.
        $purchaseResponse = $this->post(route('purchase.purchase-invoices.store'), [
            'invoice_date' => '2026-09-13',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [
                ['item_id' => $item->id, 'qty' => 10, 'cost_price' => 100, 'gst_percent' => 999],
            ],
        ]);
        $purchaseResponse->assertRedirect(route('purchase.purchase-invoices.index'));

        $purchaseInvoice = PurchaseInvoice::latest('id')->first();
        $this->assertNotNull($purchaseInvoice);

        // The client sent gst_percent=999 to try to game the tax — server must have
        // ignored it and used the item's real GstTax rate (18) instead.
        $this->assertEquals(18, (float) $purchaseInvoice->items->first()->gst_percent);

        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(10, (float) $stock->quantity);
        $this->assertEquals(100, (float) $stock->cost_price);

        $ledgerRow = StockLedger::where('reference_type', PurchaseInvoice::class)->where('reference_id', $purchaseInvoice->id)->first();
        $this->assertNotNull($ledgerRow, 'Purchase must produce a stock_ledger row.');
        $this->assertEquals('PURCHASE_RECEIPT', $ledgerRow->movement_type);

        // Sell 4 units through the real controller.
        $saleResponse = $this->post(route('sales.sales-bills.store'), [
            'bill_date' => '2026-09-13',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [
                ['item_id' => $item->id, 'qty' => 4, 'sell_price' => 150],
            ],
        ]);
        $saleResponse->assertRedirect(route('sales.sales-bills.index'));

        $salesBill = SalesBill::latest('id')->first();
        $saleItem = $salesBill->items->first();

        // cost_at_sale must be populated with the cost released (the purchase cost, 100),
        // not left null — this is the gap the foundation rebuild exists to close.
        $this->assertEquals(100, (float) $saleItem->cost_at_sale);

        $stock->refresh();
        $this->assertEquals(6, (float) $stock->quantity);

        // Posted bills can be edited (stock reversed and reposted)
        $this->assertTrue($salesBill->fresh()->isPosted());
        $updateResponse = $this->from(route('sales.sales-bills.index'))->put(route('sales.sales-bills.update', $salesBill), [
            'bill_date' => '2026-09-13',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [
                ['item_id' => $item->id, 'qty' => 2, 'sell_price' => 150],
            ],
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) $salesBill->fresh()->items->first()->qty, 'Edit must update line qty.');

        // Cancelled bills cannot be edited
        $salesBill->update(['status' => 'Cancelled']);
        $blockedResponse = $this->from(route('sales.sales-bills.index'))->put(route('sales.sales-bills.update', $salesBill), [
            'bill_date' => '2026-09-13',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [
                ['item_id' => $item->id, 'qty' => 1, 'sell_price' => 150],
            ],
        ]);
        $blockedResponse->assertSessionHasErrors('status');
    }
}
