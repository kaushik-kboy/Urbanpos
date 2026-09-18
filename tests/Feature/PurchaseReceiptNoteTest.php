<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceiptNote;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseReceiptNoteTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private User $cashier;
    private Branch $branch;
    private Supplier $supplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'GRN-TEST-001'],
            [
                'name' => 'GRN Test Dog Food',
                'cost_price' => 200,
                'sell_price' => 300,
                'mrp' => 320,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
            ]
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'GRN Test Supplier'],
            ['phone' => '9900112233', 'state' => 'Gujarat', 'credit_limit' => 100000]
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('Cashier');
    }

    public function test_user_can_view_receipt_notes_index_and_create_form(): void
    {
        $this->actingAs($this->manager)
            ->get(route('purchase.purchase-receipt-notes.index'))
            ->assertOk()
            ->assertSee('Goods Receipt Notes (GRN)');

        $this->actingAs($this->manager)
            ->get(route('purchase.purchase-receipt-notes.create'))
            ->assertOk()
            ->assertSee('Create Goods Receipt Note (GRN)');
    }

    public function test_manager_can_create_direct_goods_receipt_note_and_stock_is_posted(): void
    {
        $initialStock = (float) (ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity') ?? 0);

        $postData = [
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'supplier_challan_no' => 'DC-7701',
            'supplier_challan_date' => now()->format('Y-m-d'),
            'vehicle_no' => 'GJ-01-XX-9999',
            'transporter_name' => 'Express Logistics',
            'remarks' => 'Direct delivery in good condition',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'ordered_qty' => 10,
                    'received_qty' => 10,
                    'accepted_qty' => 10,
                    'rejected_qty' => 0,
                    'unit_cost' => 200,
                    'mrp' => 320,
                    'batch_no' => 'BATCH-A1',
                    'exp_date' => now()->addMonths(12)->format('Y-m-d'),
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('purchase.purchase-receipt-notes.store'), $postData);

        $grn = PurchaseReceiptNote::where('supplier_challan_no', 'DC-7701')->first();
        $this->assertNotNull($grn);
        $response->assertRedirect(route('purchase.purchase-receipt-notes.show', $grn));

        $this->assertEquals('Received', $grn->status);
        $this->assertEquals(10, $grn->total_accepted_qty);
        $this->assertEquals(2000, $grn->total_amount);

        // Check Stock Ledger row
        $ledgerRow = StockLedger::where('reference_type', PurchaseReceiptNote::class)
            ->where('reference_id', $grn->id)
            ->first();
        $this->assertNotNull($ledgerRow);
        $this->assertEquals('PURCHASE_RECEIPT', $ledgerRow->movement_type);
        $this->assertEquals(10, $ledgerRow->qty_in);
        $this->assertEquals(200, $ledgerRow->unit_cost);

        // Check ItemStock increased
        $newStock = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals($initialStock + 10, $newStock);
    }

    public function test_goods_receipt_note_from_purchase_order_updates_received_qty_and_po_status(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'po_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'status' => 'Open',
            'total_qty' => 20,
            'total' => 4000,
        ]);

        $poItem = $po->items()->create([
            'item_id' => $this->item->id,
            'qty' => 20,
            'received_qty' => 0,
            'cost_price' => 200,
            'sell_price' => 300,
            'mrp' => 320,
            'net_amount' => 4000,
        ]);

        // Partial Receipt of 12 units
        $postData = [
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_order_id' => $po->id,
            'supplier_challan_no' => 'DC-PARTIAL-1',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'item_id' => $this->item->id,
                    'ordered_qty' => 20,
                    'received_qty' => 12,
                    'accepted_qty' => 12,
                    'rejected_qty' => 0,
                    'unit_cost' => 200,
                    'mrp' => 320,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('purchase.purchase-receipt-notes.store'), $postData);
        $response->assertSessionHasNoErrors();

        $poItem->refresh();
        $this->assertEquals(12, (float) $poItem->received_qty);
        $this->assertEquals('Open', $po->fresh()->status);

        // Second Receipt of remaining 8 units
        $postData2 = [
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_order_id' => $po->id,
            'supplier_challan_no' => 'DC-FINAL-2',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'item_id' => $this->item->id,
                    'ordered_qty' => 20,
                    'received_qty' => 8,
                    'accepted_qty' => 8,
                    'rejected_qty' => 0,
                    'unit_cost' => 200,
                    'mrp' => 320,
                ],
            ],
        ];

        $response2 = $this->actingAs($this->manager)
            ->post(route('purchase.purchase-receipt-notes.store'), $postData2);
        $response2->assertSessionHasNoErrors();

        $poItem->refresh();
        $this->assertEquals(20, (float) $poItem->received_qty);
        $this->assertEquals('Closed', $po->fresh()->status);
    }

    public function test_purchase_invoice_can_be_converted_from_receipt_note_without_duplicate_stock(): void
    {
        // 1. Create GRN for 5 units
        $rn = PurchaseReceiptNote::create([
            'receipt_number' => 'GRN-CONV-TEST',
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'total_accepted_qty' => 5,
            'total_amount' => 1000,
            'status' => 'Received',
        ]);

        $rn->items()->create([
            'item_id' => $this->item->id,
            'ordered_qty' => 5,
            'received_qty' => 5,
            'accepted_qty' => 5,
            'rejected_qty' => 0,
            'unit_cost' => 200,
            'mrp' => 320,
        ]);

        // Simulate GRN stock posting
        app(\App\Services\Inventory\StockLedgerService::class)->post(
            itemId: $this->item->id,
            branchId: $this->branch->id,
            movementType: 'PURCHASE_RECEIPT',
            qtyDelta: 5,
            unitCost: 200,
            referenceType: PurchaseReceiptNote::class,
            referenceId: $rn->id,
            documentDate: now()->toDateString(),
        );

        $stockAfterGrn = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');

        // 2. Now convert to Purchase Invoice
        $invoiceData = [
            'invoice_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_receipt_note_id' => $rn->id,
            'grn_number' => $rn->receipt_number,
            'grn_date' => now()->format('Y-m-d'),
            'supplier_inv_no' => 'SUPP-INV-99',
            'supplier_inv_date' => now()->format('Y-m-d'),
            'supplier_inv_amount' => 1180, // 1000 base + 18% GST = 1180
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'cost_price' => 200,
                    'sell_price' => 300,
                    'mrp' => 320,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)
            ->post(route('purchase.purchase-invoices.store'), $invoiceData);

        $response->assertRedirect(route('purchase.purchase-invoices.index'));

        // Assert GRN status became 'Invoiced' and linked to the invoice
        $rn->refresh();
        $this->assertEquals('Invoiced', $rn->status);
        $this->assertNotNull($rn->purchase_invoice_id);

        // Assert stock was NOT duplicated
        $stockAfterInvoice = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals($stockAfterGrn, $stockAfterInvoice);
    }

    public function test_can_cancel_unbilled_receipt_note_and_reverses_stock(): void
    {
        $initialStock = (float) (ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity') ?? 0);

        // Create GRN
        $postData = [
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'supplier_challan_no' => 'DC-TO-CANCEL',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'ordered_qty' => 7,
                    'received_qty' => 7,
                    'accepted_qty' => 7,
                    'rejected_qty' => 0,
                    'unit_cost' => 200,
                    'mrp' => 320,
                ],
            ],
        ];

        $this->actingAs($this->manager)
            ->post(route('purchase.purchase-receipt-notes.store'), $postData);

        $grn = PurchaseReceiptNote::where('supplier_challan_no', 'DC-TO-CANCEL')->firstOrFail();
        $this->assertEquals($initialStock + 7, (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity'));

        // Now cancel it
        $this->actingAs($this->manager)
            ->delete(route('purchase.purchase-receipt-notes.destroy', $grn), [
                'reason' => 'Wrong goods delivered by vendor',
            ])
            ->assertRedirect(route('purchase.purchase-receipt-notes.show', $grn));

        $grn->refresh();
        $this->assertEquals('Cancelled', $grn->status);
        $this->assertEquals('Wrong goods delivered by vendor', $grn->cancellation_reason);

        // Stock must be restored to initial
        $finalStock = (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
        $this->assertEquals($initialStock, $finalStock);
    }

    public function test_cannot_cancel_invoiced_receipt_note(): void
    {
        $inv = PurchaseInvoice::create([
            'invoice_number' => 'PI-LOCKED-001',
            'invoice_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'total' => 1000,
        ]);

        $rn = PurchaseReceiptNote::create([
            'receipt_number' => 'GRN-LOCKED-001',
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'total_accepted_qty' => 5,
            'total_amount' => 1000,
            'status' => 'Invoiced',
            'purchase_invoice_id' => $inv->id,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('purchase.purchase-receipt-notes.destroy', $rn), [
                'reason' => 'Attempting to cancel invoiced receipt',
            ])
            ->assertSessionHasErrors(['receipt_note']);

        $this->assertEquals('Invoiced', $rn->fresh()->status);
    }

    public function test_cashier_is_blocked_from_creating_receipt_notes(): void
    {
        $postData = [
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'received_qty' => 5,
                    'accepted_qty' => 5,
                    'unit_cost' => 200,
                ],
            ],
        ];

        $this->actingAs($this->cashier)
            ->post(route('purchase.purchase-receipt-notes.store'), $postData)
            ->assertForbidden();
    }

    public function test_receipt_note_print_view_renders(): void
    {
        $rn = PurchaseReceiptNote::create([
            'receipt_number' => 'GRN-PRINT-001',
            'receipt_date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'total_accepted_qty' => 5,
            'total_amount' => 1000,
            'status' => 'Received',
        ]);

        $rn->items()->create([
            'item_id' => $this->item->id,
            'ordered_qty' => 5,
            'received_qty' => 5,
            'accepted_qty' => 5,
            'rejected_qty' => 0,
            'unit_cost' => 200,
            'mrp' => 320,
        ]);

        $this->actingAs($this->manager)
            ->get(route('purchase.purchase-receipt-notes.print', $rn))
            ->assertOk()
            ->assertSee('GOODS RECEIPT NOTE')
            ->assertSee('GRN-PRINT-001');
    }
}
