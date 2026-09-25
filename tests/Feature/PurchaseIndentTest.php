<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseIndent;
use App\Models\PurchaseOrder;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseIndentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function actingAsManager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');
        $this->actingAs($manager);

        return $manager;
    }

    public function test_user_can_view_indents_index_and_create_form(): void
    {
        $this->actingAsManager();

        $indexRes = $this->get(route('purchase.purchase-indents.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Purchase Indents');

        $createRes = $this->get(route('purchase.purchase-indents.create'));
        $createRes->assertOk();
        $createRes->assertSee('Raise Purchase Indent');
    }

    public function test_staff_can_create_purchase_indent_without_stock_movement_or_accounting_journal(): void
    {
        $user = $this->actingAsManager();
        $branch = Branch::create(['name' => 'Indent Branch 1', 'status' => true]);
        $item1 = Item::create(['name' => 'Indent Test Item 1', 'item_code' => 'ITM-IND-01', 'cost_price' => 50, 'status' => true]);
        $item2 = Item::create(['name' => 'Indent Test Item 2', 'item_code' => 'ITM-IND-02', 'cost_price' => 120, 'status' => true]);

        // Pre-populate branch stock for item1
        ItemStock::create([
            'item_id' => $item1->id,
            'branch_id' => $branch->id,
            'quantity' => 15,
            'cost_price' => 50,
        ]);

        $stockLedgerCountBefore = StockLedger::count();
        $journalEntryCountBefore = JournalEntry::count();

        $payload = [
            'indent_date' => '2026-09-20',
            'required_by_date' => '2026-09-25',
            'branch_id' => $branch->id,
            'department' => 'Store / Retail',
            'priority' => 'Urgent',
            'remarks' => 'Urgent requisition for weekend demand',
            'items' => [
                [
                    'item_id' => $item1->id,
                    'requested_qty' => 10,
                    'estimated_cost' => 50,
                    'remarks' => 'Low on shelf',
                ],
                [
                    'item_id' => $item2->id,
                    'requested_qty' => 5,
                    'estimated_cost' => 120,
                    'remarks' => 'Completely out of stock',
                ],
            ],
        ];

        $res = $this->post(route('purchase.purchase-indents.store'), $payload);

        $indent = PurchaseIndent::where('department', 'Store / Retail')
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($indent);
        $res->assertRedirect(route('purchase.purchase-indents.show', $indent));

        $this->assertEquals('Pending', $indent->status);
        $this->assertEquals('Urgent', $indent->priority);
        $this->assertEquals(15.0, (float) $indent->total_requested_qty);
        $this->assertEquals(0.0, (float) $indent->total_approved_qty);
        $this->assertEquals(1100.0, (float) $indent->total_estimated_amount); // 10*50 + 5*120
        $this->assertStringStartsWith('IND', $indent->indent_number);

        // Verify items and captured live branch stock
        $this->assertCount(2, $indent->items);
        $line1 = $indent->items()->where('item_id', $item1->id)->first();
        $this->assertEquals(15.0, (float) $line1->current_stock);
        $this->assertEquals(10.0, (float) $line1->requested_qty);

        $line2 = $indent->items()->where('item_id', $item2->id)->first();
        $this->assertEquals(0.0, (float) $line2->current_stock);
        $this->assertEquals(5.0, (float) $line2->requested_qty);

        // Crucial foundation engine check: zero stock movements, zero accounting vouchers
        $this->assertEquals($stockLedgerCountBefore, StockLedger::count(), 'Purchase Indent must NEVER produce physical stock movements.');
        $this->assertEquals($journalEntryCountBefore, JournalEntry::count(), 'Purchase Indent must NEVER produce accounting financial vouchers.');
    }

    public function test_ajax_item_stock_returns_correct_branch_stock_and_cost(): void
    {
        $this->actingAsManager();
        $branch = Branch::create(['name' => 'Stock Test Branch', 'status' => true]);
        $item = Item::create(['name' => 'Stock Test Item', 'item_code' => 'STK-01', 'cost_price' => 75.50, 'status' => true]);

        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 42.5,
            'cost_price' => 75.50,
        ]);

        $res = $this->getJson(route('purchase.purchase-indents.item-stock', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
        ]));

        $res->assertOk();
        $res->assertJson([
            'item_id' => $item->id,
            'current_stock' => 42.5,
            'cost_price' => 75.50,
        ]);
    }

    public function test_manager_can_approve_indent_with_adjusted_quantities(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'Approval Branch', 'status' => true]);
        $item1 = Item::create(['name' => 'Appr Item 1', 'cost_price' => 100, 'status' => true]);
        $item2 = Item::create(['name' => 'Appr Item 2', 'cost_price' => 200, 'status' => true]);

        $indent = PurchaseIndent::create([
            'indent_number' => 'IND99901',
            'indent_date' => '2026-09-20',
            'branch_id' => $branch->id,
            'requested_by_id' => $manager->id,
            'department' => 'Warehouse',
            'priority' => 'High',
            'status' => 'Pending',
            'total_requested_qty' => 15,
            'total_approved_qty' => 0,
            'total_estimated_amount' => 2500,
            'remarks' => 'Restock needed',
        ]);

        $itemLine1 = $indent->items()->create([
            'item_id' => $item1->id,
            'current_stock' => 5,
            'requested_qty' => 10,
            'estimated_cost' => 100,
        ]);

        $itemLine2 = $indent->items()->create([
            'item_id' => $item2->id,
            'current_stock' => 2,
            'requested_qty' => 5,
            'estimated_cost' => 200,
        ]);

        $res = $this->post(route('purchase.purchase-indents.approve', $indent), [
            'items' => [
                ['id' => $itemLine1->id, 'approved_qty' => 8], // reduced from 10 to 8
                ['id' => $itemLine2->id, 'approved_qty' => 5], // kept at 5
            ],
            'remarks' => 'Budget approved with item 1 reduction.',
        ]);

        $res->assertRedirect(route('purchase.purchase-indents.show', $indent));

        $indent->refresh();
        $this->assertEquals('Approved', $indent->status);
        $this->assertEquals(13.0, (float) $indent->total_approved_qty); // 8 + 5
        $this->assertEquals(1800.0, (float) $indent->total_estimated_amount); // 8*100 + 5*200
        $this->assertEquals($manager->id, $indent->reviewed_by_id);
        $this->assertNotNull($indent->reviewed_at);

        $this->assertEquals(8.0, (float) $itemLine1->fresh()->approved_qty);
        $this->assertEquals(5.0, (float) $itemLine2->fresh()->approved_qty);
    }

    public function test_manager_can_reject_indent_with_reason(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'Reject Branch', 'status' => true]);

        $indent = PurchaseIndent::create([
            'indent_number' => 'IND99902',
            'indent_date' => '2026-09-20',
            'branch_id' => $branch->id,
            'requested_by_id' => $manager->id,
            'department' => 'Bakery',
            'priority' => 'Medium',
            'status' => 'Pending',
            'total_requested_qty' => 10,
            'total_approved_qty' => 0,
            'total_estimated_amount' => 500,
        ]);

        $res = $this->post(route('purchase.purchase-indents.reject', $indent), [
            'rejection_reason' => 'Sufficient safety stock already available at main depot.',
        ]);

        $res->assertRedirect(route('purchase.purchase-indents.show', $indent));

        $indent->refresh();
        $this->assertEquals('Rejected', $indent->status);
        $this->assertEquals('Sufficient safety stock already available at main depot.', $indent->rejection_reason);
        $this->assertEquals($manager->id, $indent->reviewed_by_id);
        $this->assertNotNull($indent->reviewed_at);
    }

    public function test_converting_approved_indent_to_purchase_order(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'Convert Branch', 'status' => true]);
        $supplier = Supplier::create(['name' => 'Convert Supplier', 'status' => true]);
        $item = Item::create(['name' => 'Convert Item', 'cost_price' => 80, 'tax_rate' => 18, 'status' => true]);

        $indent = PurchaseIndent::create([
            'indent_number' => 'IND99903',
            'indent_date' => '2026-09-20',
            'branch_id' => $branch->id,
            'requested_by_id' => $manager->id,
            'reviewed_by_id' => $manager->id,
            'reviewed_at' => now(),
            'department' => 'Store / Retail',
            'priority' => 'High',
            'status' => 'Approved',
            'total_requested_qty' => 20,
            'total_approved_qty' => 20,
            'total_estimated_amount' => 1600,
        ]);

        $indent->items()->create([
            'item_id' => $item->id,
            'current_stock' => 0,
            'requested_qty' => 20,
            'approved_qty' => 20,
            'estimated_cost' => 80,
        ]);

        // 1. Visit create PO with from_indent parameter
        $formRes = $this->get(route('purchase.purchase-orders.create', ['from_indent' => $indent->id]));
        $formRes->assertOk();
        $formRes->assertSee('Creating Purchase Order from Indent');

        // 2. Submit PO creation linked to indent
        $poRes = $this->post(route('purchase.purchase-orders.store'), [
            'po_date' => '2026-09-20',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'purchase_indent_id' => $indent->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'status' => 'Open',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 20,
                    'cost_price' => 80,
                    'gst_percent' => 18,
                ],
            ],
        ]);

        $po = PurchaseOrder::where('purchase_indent_id', $indent->id)->latest('id')->first();
        $this->assertNotNull($po);
        $poRes->assertRedirect(route('purchase.purchase-orders.index'));

        // Verify indent state updated to Converted and linked to PO
        $indent->refresh();
        $this->assertEquals('Converted', $indent->status);
        $this->assertEquals($po->id, $indent->purchase_order_id);

        // 3. Cancelling the PO reverts the indent back to Approved
        $cancelRes = $this->delete(route('purchase.purchase-orders.destroy', $po), [
            'reason' => 'Supplier unable to deliver; reverting requisition',
        ]);
        $cancelRes->assertRedirect(route('purchase.purchase-orders.index'));

        $indent->refresh();
        $this->assertEquals('Approved', $indent->status, 'Cancelling PO must revert linked Indent status back to Approved.');
        $this->assertNull($indent->purchase_order_id);
    }

    public function test_unconverted_indent_can_be_cancelled_with_reason(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'Cancel Indent Branch', 'status' => true]);

        $indent = PurchaseIndent::create([
            'indent_number' => 'IND99904',
            'indent_date' => '2026-09-20',
            'branch_id' => $branch->id,
            'requested_by_id' => $manager->id,
            'department' => 'Stationery',
            'priority' => 'Low',
            'status' => 'Pending',
            'total_requested_qty' => 5,
            'total_approved_qty' => 0,
            'total_estimated_amount' => 150,
        ]);

        $res = $this->delete(route('purchase.purchase-indents.destroy', $indent), [
            'cancellation_reason' => 'Duplicate requisition submitted by mistake',
        ]);

        $res->assertRedirect(route('purchase.purchase-indents.index'));

        $indent->refresh();
        $this->assertEquals('Cancelled', $indent->status);
        $this->assertEquals('Duplicate requisition submitted by mistake', $indent->cancellation_reason);
        $this->assertEquals($manager->id, $indent->cancelled_by_id);
        $this->assertNotNull($indent->cancelled_at);
    }

    public function test_indent_show_and_print_views_render_ok(): void
    {
        $manager = $this->actingAsManager();
        $branch = Branch::create(['name' => 'View Test Branch', 'status' => true]);
        $item = Item::create(['name' => 'Print View Item', 'cost_price' => 60, 'status' => true]);

        $indent = PurchaseIndent::create([
            'indent_number' => 'IND99905',
            'indent_date' => '2026-09-20',
            'branch_id' => $branch->id,
            'requested_by_id' => $manager->id,
            'department' => 'Store / Retail',
            'priority' => 'Medium',
            'status' => 'Approved',
            'total_requested_qty' => 8,
            'total_approved_qty' => 8,
            'total_estimated_amount' => 480,
        ]);

        $indent->items()->create([
            'item_id' => $item->id,
            'current_stock' => 12,
            'requested_qty' => 8,
            'approved_qty' => 8,
            'estimated_cost' => 60,
        ]);

        $showRes = $this->get(route('purchase.purchase-indents.show', $indent));
        $showRes->assertOk();
        $showRes->assertSee('IND99905');
        $showRes->assertSee('Print View Item');

        $printRes = $this->get(route('purchase.purchase-indents.print', $indent));
        $printRes->assertOk();
        $printRes->assertSee('IND99905');
        $printRes->assertSee('INTERNAL PURCHASE INDENT / STORE REQUISITION SLIP');
    }
}
