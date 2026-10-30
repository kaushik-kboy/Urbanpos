<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosItemLookupActiveBatchExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_list_uses_active_unexpired_batch_expiry_instead_of_depleted_expired_batch(): void
    {
        $branch = Branch::create([
            'name' => 'Motera Test Branch',
            'code' => 'MOTERA',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $user = User::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $gst = GstTax::firstOrCreate(['name' => '18%'], ['percentage' => 18, 'status' => 1, 'description' => 'GST 18%']);

        $item = Item::create([
            'name' => 'Royal Canin Test Puppy 15 kgs',
            'item_code' => 'TEST1645',
            'status' => 1,
            'sell_price' => 12370.00,
            'mrp' => 12370.00,
            'gst_tax_id' => $gst->id,
            'allow_negative_stock' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 1.000,
            'sell_price' => 12370.00,
            'mrp' => 12370.00,
        ]);

        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'supplier_code' => 'SUPP-001',
            'status' => 1,
        ]);

        // Old purchase invoice from 2026-10-26 (Expired and 0 remaining in stock ledger)
        $piOld = PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-001',
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-10-01',
            'total' => 8000.00,
            'status' => 'Posted',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $piOld->id,
            'item_id' => $item->id,
            'batch_no' => 'OLD-BATCH-EXPIRED',
            'exp_date' => '2026-10-26',
            'qty' => 1.000,
            'cost_price' => 8000.00,
            'sell_price' => 12370.00,
            'mrp' => 12370.00,
            'net_amount' => 8000.00,
        ]);

        // Stock ledger shows old batch is fully sold/consumed (qty_in 1, qty_out 1 => rem 0)
        StockLedger::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'batch_no' => 'OLD-BATCH-EXPIRED',
            'exp_date' => '2026-10-26',
            'qty_in' => 1.000,
            'qty_out' => 1.000,
            'movement_type' => 'PURCHASE_RECEIPT',
            'document_date' => '2026-10-01',
            'posted_at' => now(),
            'mrp' => 12370.00,
        ]);

        // Active batch with 1 remaining stock expiring in 2027-02-25
        StockLedger::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'batch_no' => 'R1645-7578',
            'exp_date' => '2027-02-25',
            'qty_in' => 1.000,
            'qty_out' => 0.000,
            'movement_type' => 'OPENING',
            'document_date' => '2026-10-15',
            'posted_at' => now(),
            'mrp' => 12370.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('sales.sales-bills.item-list', [
            'search' => 'Test Puppy',
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertEquals('2027-02-25', $items[0]['exp_date']);
        $this->assertEquals(1, $items[0]['qty']);
    }
}
