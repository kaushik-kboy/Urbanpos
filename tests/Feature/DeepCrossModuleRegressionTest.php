<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeepCrossModuleRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Branch $branch1;
    private Branch $branch2;
    private GstTax $gst18;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra', 'status' => true]
        );

        $this->branch2 = Branch::firstOrCreate(
            ['id' => 4],
            ['name' => 'Branch Two', 'code' => 'BR2', 'state' => 'Maharashtra', 'status' => true]
        );

        $this->gst18 = GstTax::firstOrCreate(
            ['percentage' => 18],
            ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch1->id]);
        $this->manager->assignRole('Manager');
    }

    public function test_purchase_return_supplier_isolation_and_cross_supplier_blocking(): void
    {
        $supplierAnkit = Supplier::create([
            'name' => 'Ankit Supplier',
            'phone' => '9800000001',
            'state' => 'Maharashtra',
        ]);

        $supplierRahul = Supplier::create([
            'name' => 'Rahul Supplier',
            'phone' => '9800000002',
            'state' => 'Maharashtra',
        ]);

        $itemX = Item::create([
            'name' => 'Shared Item X',
            'item_code' => 'ITM-X',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Ankit: purchased 20
        $pinvAnkit = PurchaseInvoice::create([
            'invoice_number' => 'PINV-ANKIT-01',
            'supplier_id' => $supplierAnkit->id,
            'branch_id' => $this->branch1->id,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'total' => 2000,
            'subtotal' => 2000,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pinvAnkit->id,
            'item_id' => $itemX->id,
            'batch_no' => 'BATCH-A',
            'qty' => 20,
            'cost_price' => 100,
            'subtotal' => 2000,
            'net_amount' => 2000,
        ]);

        // Rahul: purchased 10
        $pinvRahul = PurchaseInvoice::create([
            'invoice_number' => 'PINV-RAHUL-01',
            'supplier_id' => $supplierRahul->id,
            'branch_id' => $this->branch1->id,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'total' => 1000,
            'subtotal' => 1000,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pinvRahul->id,
            'item_id' => $itemX->id,
            'batch_no' => 'BATCH-B',
            'qty' => 10,
            'cost_price' => 100,
            'subtotal' => 1000,
            'net_amount' => 1000,
        ]);

        // Total stock in branch = 30
        ItemStock::create([
            'item_id' => $itemX->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 30,
        ]);

        // Test 1: Ankit returns 30 qty -> MUST BE BLOCKED (Ankit only sold 20)
        $respOverReturn = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierAnkit->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-A',
                    'qty' => 30,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respOverReturn->assertSessionHasErrors('items');

        // Test 2: Ankit returns 21 qty -> MUST BE BLOCKED
        $resp21 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierAnkit->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-A',
                    'qty' => 21,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $resp21->assertSessionHasErrors('items');

        // Test 3: Ankit returns Rahul's BATCH-B -> MUST BE BLOCKED
        $respWrongBatch = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierAnkit->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-B',
                    'qty' => 1,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respWrongBatch->assertSessionHasErrors('items');

        // Test 4: Ankit returns 20 qty of BATCH-A -> PASS
        $respAnkit20 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierAnkit->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-ANKIT-01',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-A',
                    'qty' => 20,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respAnkit20->assertRedirect(route('purchase.purchase-returns.index'));

        // Test 5: Rahul returns 11 qty -> MUST BE BLOCKED (Rahul only sold 10)
        $respRahul11 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierRahul->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-B',
                    'qty' => 11,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respRahul11->assertSessionHasErrors('items');

        // Test 6: Rahul returns 10 qty of BATCH-B -> PASS
        $respRahul10 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplierRahul->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-RAHUL-01',
            'items' => [
                [
                    'item_id' => $itemX->id,
                    'batch_no' => 'BATCH-B',
                    'qty' => 10,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respRahul10->assertRedirect(route('purchase.purchase-returns.index'));
    }

    public function test_purchase_return_previous_returns_deducted(): void
    {
        $supplier = Supplier::create([
            'name' => 'Deduction Supplier',
            'phone' => '9800000003',
            'state' => 'Maharashtra',
        ]);

        $item = Item::create([
            'name' => 'Deduction Item',
            'item_code' => 'ITM-DED',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $pinv = PurchaseInvoice::create([
            'invoice_number' => 'PINV-DED-01',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'total' => 2000,
            'subtotal' => 2000,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pinv->id,
            'item_id' => $item->id,
            'batch_no' => 'BATCH-DED',
            'qty' => 20,
            'cost_price' => 100,
            'subtotal' => 2000,
            'net_amount' => 2000,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 20,
        ]);

        \App\Models\StockLedger::create([
            'branch_id' => $this->branch1->id,
            'item_id' => $item->id,
            'batch_no' => 'BATCH-DED',
            'qty_in' => 20,
            'qty_out' => 0,
            'balance_qty' => 20,
            'unit_cost' => 100,
            'movement_type' => 'PURCHASE_RECEIPT',
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $pinv->id,
            'document_number' => $pinv->invoice_number,
            'document_date' => now()->toDateString(),
            'posted_at' => now(),
        ]);

        // First return: 8 qty -> PASS (20 - 8 = 12 remaining)
        $resp1 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'purchase_invoice_id' => $pinv->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-DED-01',
            'items' => [
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-DED',
                    'qty' => 8,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $resp1->assertRedirect(route('purchase.purchase-returns.index'));

        // Second return attempt: 13 qty -> MUST BE BLOCKED (remaining is 12)
        $resp2 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'purchase_invoice_id' => $pinv->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-DED-02',
            'items' => [
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-DED',
                    'qty' => 13,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $resp2->assertSessionHasErrors('items');

        // Third return attempt: 12 qty -> PASS
        $resp3 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'purchase_invoice_id' => $pinv->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-DED-03',
            'items' => [
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-DED',
                    'qty' => 12,
                    'cost_price' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $resp3->assertRedirect(route('purchase.purchase-returns.index'));
    }

    public function test_purchase_return_discount_validation(): void
    {
        $supplier = Supplier::create([
            'name' => 'Disc Supplier',
            'phone' => '9800000004',
            'state' => 'Maharashtra',
        ]);

        $item = Item::create([
            'name' => 'Disc Item',
            'item_code' => 'ITM-DISC',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $pinv = PurchaseInvoice::create([
            'invoice_number' => 'PINV-DISC-01',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'total' => 2000,
            'subtotal' => 2000,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pinv->id,
            'item_id' => $item->id,
            'qty' => 20,
            'cost_price' => 100,
            'subtotal' => 2000,
            'net_amount' => 2000,
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 20,
        ]);

        // 1. Negative discount -> MUST BE BLOCKED
        $respNeg = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'cost_price' => 100,
                    'disc_percent' => -25,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respNeg->assertSessionHasErrors('items.0.disc_percent');

        // 2. Discount > 100 -> MUST BE BLOCKED
        $respOver = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'cost_price' => 100,
                    'disc_percent' => 105,
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $respOver->assertSessionHasErrors('items.0.disc_percent');

        // 3. Discount 25% -> PASS (Net amount correctly reflects 25% discount)
        $resp25 = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-DISC-25',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'cost_price' => 100,
                    'disc_percent' => 25,
                    'disc_amount' => 50, // 2 * 100 * 25% = 50
                    'gst_percent' => 18,
                ],
            ],
        ]);
        $resp25->assertRedirect(route('purchase.purchase-returns.index'));
        $pr = PurchaseReturn::where('supplier_debit_note_no', 'DN-DISC-25')->first();
        $this->assertNotNull($pr);
        $this->assertEquals(50.00, (float) $pr->disc_amount);
    }

    public function test_purchase_invoice_expired_date_blocked(): void
    {
        $supplier = Supplier::create([
            'name' => 'Expiry Supplier',
            'phone' => '9800000005',
            'state' => 'Maharashtra',
        ]);

        $item = Item::create([
            'name' => 'Expiry Item',
            'item_code' => 'ITM-EXP',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Attempt to create purchase invoice with yesterday's expiry -> MUST BE BLOCKED
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();
        $resp = $this->actingAs($this->manager)->post(route('purchase.purchase-invoices.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'invoice_date' => now('Asia/Kolkata')->toDateString(),
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'cost_price' => 100,
                    'exp_date' => $yesterday,
                    'gst_percent' => 18,
                ],
            ],
        ]);

        $resp->assertSessionHasErrors('items.0.exp_date');
    }

    public function test_stock_transfer_batch_independent_transfer(): void
    {
        $item = Item::create([
            'name' => 'Transfer Item',
            'item_code' => 'ITM-TRF',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        // Branch 1 has 30 total stock (Batch A = 20, Batch B = 10)
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 30,
        ]);

        \App\Models\StockLedger::create([
            'branch_id' => $this->branch1->id,
            'item_id' => $item->id,
            'batch_no' => 'BATCH-A',
            'qty_in' => 20,
            'qty_out' => 0,
            'balance_qty' => 20,
            'unit_cost' => 100,
            'movement_type' => 'PURCHASE_RECEIPT',
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'document_number' => 'INIT-A',
            'document_date' => now()->toDateString(),
            'posted_at' => now(),
        ]);

        \App\Models\StockLedger::create([
            'branch_id' => $this->branch1->id,
            'item_id' => $item->id,
            'batch_no' => 'BATCH-B',
            'qty_in' => 10,
            'qty_out' => 0,
            'balance_qty' => 10,
            'unit_cost' => 100,
            'movement_type' => 'PURCHASE_RECEIPT',
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'document_number' => 'INIT-B',
            'document_date' => now()->toDateString(),
            'posted_at' => now(),
        ]);

        // Transfer 5 from Batch A and 4 from Batch B -> PASS
        $resp = $this->actingAs($this->manager)->post(route('inventory.stock-transfers.store'), [
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-A',
                    'qty' => 5,
                ],
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-B',
                    'qty' => 4,
                ],
            ],
        ]);

        $resp->assertRedirect(route('inventory.stock-transfers.index'));
        $transfer = StockTransfer::latest()->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(9.0, (float) $transfer->total_qty);

        // Attempt to transfer more than available stock (e.g. 50) -> MUST BE BLOCKED
        $respOver = $this->actingAs($this->manager)->post(route('inventory.stock-transfers.store'), [
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $item->id,
                    'batch_no' => 'BATCH-A',
                    'qty' => 50,
                ],
            ],
        ]);
        $respOver->assertSessionHasErrors('items');
    }

    public function test_master_items_and_category_brand_filters(): void
    {
        $brand = Brand::create(['name' => 'Alpha Brand', 'status' => true]);
        $category = ItemCategory::create(['name' => 'Alpha Category', 'status' => true]);

        $item = Item::create([
            'name' => 'Alpha Item',
            'item_code' => 'ALPHA-001',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'status' => true,
        ]);

        // Verify items index page shows item code next to ID
        $respItems = $this->actingAs($this->manager)->get(route('master.items.index'));
        $respItems->assertOk();
        $respItems->assertSee('Item Code');
        $respItems->assertSee('ALPHA-001');

        // Verify brand index search filter
        $respBrand = $this->actingAs($this->manager)->get(route('master.brands.index', ['search' => 'Alpha']));
        $respBrand->assertOk();
        $respBrand->assertSee('Alpha Brand');

        // Verify category index search filter
        $respCat = $this->actingAs($this->manager)->get(route('master.item-categories.index', ['search' => 'Alpha']));
        $respCat->assertOk();
        $respCat->assertSee('Alpha Category');
    }

    public function test_cross_flow_batch_lifecycle_consistency(): void
    {
        $batchService = app(\App\Services\Inventory\BatchStockService::class);
        $stockLedger = app(\App\Services\Inventory\StockLedgerService::class);

        $item = Item::create([
            'name' => 'Lifecycle Item',
            'item_code' => 'LC-001',
            'cost_price' => 100,
            'sell_price' => 150,
            'mrp' => 160,
            'gst_tax_id' => $this->gst18->id,
            'status' => true,
        ]);

        $branchId = $this->branch1->id;

        // Step 1: Purchase Batch A (20) & Batch B (10)
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 20.0,
            unitCost: 100.0,
            referenceType: PurchaseInvoice::class,
            referenceId: 101,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-A',
        );

        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 10.0,
            unitCost: 100.0,
            referenceType: PurchaseInvoice::class,
            referenceId: 101,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-B',
        );

        $this->assertEquals(20.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(10.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 2: Sales Bill: Sell 5 from Batch A -> Batch A = 15, Batch B = 10
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'SALE',
            qtyDelta: -5.0,
            unitCost: null,
            referenceType: \App\Models\SalesBill::class,
            referenceId: 201,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-A',
        );
        $this->assertEquals(15.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(10.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 3: Stock Transfer: Transfer 3 from Batch B to Branch 2 -> Batch A = 15, Batch B = 7
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'TRANSFER_OUT',
            qtyDelta: -3.0,
            unitCost: null,
            referenceType: StockTransfer::class,
            referenceId: 301,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-B',
        );
        $this->assertEquals(15.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(7.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 4: Damage Stock: Damage 2 from Batch A -> Batch A = 13, Batch B = 7
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'DAMAGE',
            qtyDelta: -2.0,
            unitCost: null,
            referenceType: \App\Models\DamageStock::class,
            referenceId: 401,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-A',
        );
        $this->assertEquals(13.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(7.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 5: Stock Update: Adjust Batch B down from 7 to 6 (SHORTAGE of 1) -> Batch A = 13, Batch B = 6
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'SHORTAGE',
            qtyDelta: -1.0,
            unitCost: null,
            referenceType: \App\Models\StockUpdate::class,
            referenceId: 501,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-B',
        );
        $this->assertEquals(13.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(6.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 6: Purchase Return: Return 2 from Batch A -> Batch A = 11, Batch B = 6
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'PURCHASE_RETURN',
            qtyDelta: -2.0,
            unitCost: null,
            referenceType: PurchaseReturn::class,
            referenceId: 601,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-A',
        );
        $this->assertEquals(11.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(6.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);

        // Step 7: Sales Return: Customer returns 2 of Batch A -> Batch A = 13, Batch B = 6
        $stockLedger->post(
            itemId: $item->id,
            branchId: $branchId,
            movementType: 'SALE_RETURN',
            qtyDelta: 2.0,
            unitCost: 100.0,
            referenceType: \App\Models\SalesReturn::class,
            referenceId: 701,
            documentDate: now()->toDateString(),
            batchNo: 'BATCH-A',
        );
        $this->assertEquals(13.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-A')['remaining_qty']);
        $this->assertEquals(6.0, (float) $batchService->getBatchStock($item->id, $branchId, 'BATCH-B')['remaining_qty']);
    }
}
