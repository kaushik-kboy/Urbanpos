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

    public function test_batch_stock_service_fifo_excess_deduction_depletes_expired_batches_first_leaving_unexpired_batch_intact(): void
    {
        $branch = Branch::create([
            'name' => 'FIFO Branch',
            'code' => 'FIFO01',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $item = Item::create([
            'name' => 'Royal Canin Fit 32 Test 2KG',
            'item_code' => 'TEST6789',
            'status' => 1,
            'sell_price' => 2110.00,
            'mrp' => 2110.00,
        ]);

        // Branch physical stock is 5
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 5.000,
            'sell_price' => 2110.00,
            'mrp' => 2110.00,
        ]);

        $supplier = Supplier::create([
            'name' => 'Supplier 2',
            'supplier_code' => 'SUPP-002',
            'status' => 1,
        ]);

        // Purchase Batch 1: Expired (2026-09-09), purchased 10
        $pi1 = PurchaseInvoice::create([
            'invoice_number' => 'PI-EXPIRED-01',
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-01-01',
            'total' => 19200.00,
            'status' => 'Posted',
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi1->id,
            'item_id' => $item->id,
            'batch_no' => 'OLD-EXP-2026',
            'exp_date' => '2026-09-09',
            'qty' => 10.000,
            'cost_price' => 1500.00,
            'sell_price' => 1920.00,
            'mrp' => 1920.00,
            'net_amount' => 15000.00,
        ]);

        // Purchase Batch 2: Valid unexpired (2027-11-16), purchased 5
        $pi2 = PurchaseInvoice::create([
            'invoice_number' => 'PI-VALID-02',
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-10-15',
            'total' => 9600.00,
            'status' => 'Posted',
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi2->id,
            'item_id' => $item->id,
            'batch_no' => 'NEW-VALID-2027',
            'exp_date' => '2027-11-16',
            'qty' => 5.000,
            'cost_price' => 1600.00,
            'sell_price' => 2110.00,
            'mrp' => 2110.00,
            'net_amount' => 8000.00,
        ]);

        $batchService = app(\App\Services\Inventory\BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $branch->id);

        $expiredBatch = $batches->firstWhere('batch_no', 'OLD-EXP-2026');
        $validBatch = $batches->firstWhere('batch_no', 'NEW-VALID-2027');

        $this->assertNotNull($expiredBatch);
        $this->assertNotNull($validBatch);
        // Excess 10 was deducted from the expired batch first (FIFO)
        $this->assertEquals(0.0, (float)$expiredBatch['remaining_qty']);
        $this->assertEquals(5.0, (float)$validBatch['remaining_qty']);
    }

    public function test_fresh_stock_transfer_batch_takes_precedence_over_older_closing_stock_with_same_expiry(): void
    {
        $branchFrom = Branch::create(['name' => 'Motera Transfer Branch', 'code' => 'MOT01', 'state' => 'Gujarat', 'status' => true]);
        $branchTo   = Branch::create(['name' => 'Satellite Transfer Branch', 'code' => 'SAT01', 'state' => 'Gujarat', 'status' => true]);

        $item = Item::create([
            'name' => 'Drools Adult Chicken and Egg 20KG',
            'item_code' => 'TEST7018',
            'status' => 1,
            'sell_price' => 3799.00,
            'mrp' => 3799.00,
        ]);

        // Branch physical stock in Satellite is 2
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $branchTo->id,
            'quantity' => 2.000,
            'sell_price' => 3599.00,
            'mrp' => 3599.00,
        ]);

        // 1. Legacy Closing Stock imported with MRP 3799
        \Illuminate\Support\Facades\DB::table('closing_stocks')->insert([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'branch_id' => $branchTo->id,
            'batch_no' => 'D7018-2738',
            'expiry_date' => '2028-08-30',
            'closing_stock' => 2.000,
            'mrp' => 3799.00,
            'net_cost' => 1940.39,
        ]);

        // 2. Fresh Inward Stock Transfer received with MRP 3599
        $st = \App\Models\StockTransfer::create([
            'transfer_number' => 'ST-TEST-0041',
            'transfer_date' => '2026-10-30',
            'from_branch_id' => $branchFrom->id,
            'to_branch_id' => $branchTo->id,
            'status' => 'Received',
            'total_qty' => 2.000,
            'total_value' => 7198.00,
        ]);
        \App\Models\StockTransferItem::create([
            'stock_transfer_id' => $st->id,
            'item_id' => $item->id,
            'batch_no' => '2028-08-30',
            'exp_date' => '2028-08-30',
            'qty' => 2.000,
            'received_qty' => 2.000,
            'unit_cost' => 2287.50,
            'sell_price' => 3599.00,
            'mrp' => 3599.00,
        ]);

        $batchService = app(\App\Services\Inventory\BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $branchTo->id);

        $csBatch = $batches->firstWhere('batch_no', 'D7018-2738');
        $stBatch = $batches->firstWhere('batch_no', '2028-08-30');

        $this->assertNotNull($csBatch);
        $this->assertNotNull($stBatch);
        // Older closing stock was consumed first; freshly transferred batch has the active stock of 2 with MRP 3599
        $this->assertEquals(0.0, (float)$csBatch['remaining_qty']);
        $this->assertEquals(2.0, (float)$stBatch['remaining_qty']);
        $this->assertEquals(3599.00, (float)$stBatch['mrp']);
        $this->assertEquals(3599.00, (float)$stBatch['sell_price']);
    }
}
