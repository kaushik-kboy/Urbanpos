<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceiptNote;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchScopingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchMotera;
    private Branch $branchSatellite;
    private Branch $branchVastrapur;
    private User $managerMotera;
    private User $ownerUser;
    private Supplier $supplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);

        $this->branchMotera = Branch::create(['name' => 'Motera Branch', 'state' => 'Gujarat', 'status' => true]);
        $this->branchSatellite = Branch::create(['name' => 'Satellite Branch', 'state' => 'Gujarat', 'status' => true]);
        $this->branchVastrapur = Branch::create(['name' => 'Vastrapur Branch', 'state' => 'Gujarat', 'status' => true]);

        $this->managerMotera = User::factory()->create([
            'name' => 'Dhruvi Motera',
            'email' => 'dhruvi@example.com',
            'branch_id' => $this->branchMotera->id,
        ]);
        $this->managerMotera->assignRole('Manager');

        $this->ownerUser = User::factory()->create([
            'name' => 'Super Owner',
            'email' => 'owner@example.com',
            'branch_id' => null,
        ]);
        $this->ownerUser->assignRole('Owner');

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'phone' => '9876543210',
            'status' => true,
        ]);

        $this->item = Item::create([
            'name' => 'Royal Canin 1kg',
            'item_code' => 'RC001',
            'cost_price' => 500,
            'sell_price' => 700,
            'mrp' => 750,
            'status' => true,
        ]);
    }

    public function test_branch_scoped_user_only_sees_their_branch_purchase_invoices(): void
    {
        // Invoice for Motera
        $invMotera = PurchaseInvoice::create([
            'invoice_number' => 'PINV-MOTERA-01',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branchMotera->id,
            'total' => 1000,
            'final_amount' => 1000,
            'purchase_type' => 'Local',
            'status' => 'Posted',
        ]);

        // Invoice for Satellite
        $invSatellite = PurchaseInvoice::create([
            'invoice_number' => 'PINV-SAT-01',
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branchSatellite->id,
            'total' => 2000,
            'final_amount' => 2000,
            'purchase_type' => 'Local',
            'status' => 'Posted',
        ]);

        // Dhruvi (Motera) visits purchase invoices list
        $res = $this->actingAs($this->managerMotera)->get(route('purchase.purchase-invoices.index'));
        $res->assertOk();
        $res->assertSee('PINV-MOTERA-01');
        $res->assertDontSee('PINV-SAT-01');

        // Cannot view or print Satellite invoice directly
        $viewRes = $this->actingAs($this->managerMotera)->get(route('purchase.purchase-invoices.show', $invSatellite));
        $viewRes->assertForbidden();

        $printRes = $this->actingAs($this->managerMotera)->get(route('purchase.purchase-invoices.print', $invSatellite));
        $printRes->assertForbidden();
    }

    public function test_branch_scoped_user_only_sees_transfers_involving_their_branch(): void
    {
        // 1. Motera -> Satellite (Transfer Out from Motera)
        $stf1 = StockTransfer::create([
            'transfer_number' => 'STF-MOT-SAT-01',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchMotera->id,
            'to_branch_id' => $this->branchSatellite->id,
            'status' => 'Dispatched',
            'total_qty' => 5,
        ]);

        // 2. Satellite -> Motera (Transfer In to Motera)
        $stf2 = StockTransfer::create([
            'transfer_number' => 'STF-SAT-MOT-02',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchSatellite->id,
            'to_branch_id' => $this->branchMotera->id,
            'status' => 'Dispatched',
            'total_qty' => 3,
        ]);

        // 3. Satellite -> Vastrapur (Neither sender nor receiver is Motera)
        $stf3 = StockTransfer::create([
            'transfer_number' => 'STF-SAT-VAS-03',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchSatellite->id,
            'to_branch_id' => $this->branchVastrapur->id,
            'status' => 'Dispatched',
            'total_qty' => 10,
        ]);

        $res = $this->actingAs($this->managerMotera)->get(route('inventory.stock-transfers.index'));
        $res->assertOk();
        $res->assertSee('STF-MOT-SAT-01');
        $res->assertSee('STF-SAT-MOT-02');
        $res->assertDontSee('STF-SAT-VAS-03');

        // Dhruvi cannot view or print transfer 3
        $this->actingAs($this->managerMotera)->get(route('inventory.stock-transfers.show', $stf3))->assertForbidden();
        $this->actingAs($this->managerMotera)->get(route('inventory.stock-transfers.print', $stf3))->assertForbidden();
    }

    public function test_only_destination_branch_can_receive_transfer(): void
    {
        // Motera dispatches to Satellite
        $transfer = StockTransfer::create([
            'transfer_number' => 'STF-001',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchMotera->id,
            'to_branch_id' => $this->branchSatellite->id,
            'status' => 'Dispatched',
            'total_qty' => 5,
        ]);

        // Dhruvi (sender Motera) tries to open receive form or post receive for Satellite
        $resForm = $this->actingAs($this->managerMotera)->get(route('inventory.stock-transfers.receive-form', $transfer));
        $resForm->assertForbidden();

        $resReceive = $this->actingAs($this->managerMotera)->post(route('inventory.stock-transfers.receive', $transfer), [
            'items' => [],
        ]);
        $resReceive->assertForbidden();
    }

    public function test_only_source_branch_can_cancel_transfer(): void
    {
        // Satellite dispatches to Motera
        $transfer = StockTransfer::create([
            'transfer_number' => 'STF-002',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchSatellite->id,
            'to_branch_id' => $this->branchMotera->id,
            'status' => 'Dispatched',
            'total_qty' => 5,
        ]);

        // Dhruvi (receiver Motera) tries to cancel Satellite's dispatch
        $res = $this->actingAs($this->managerMotera)->post(route('inventory.stock-transfers.cancel', $transfer));
        $res->assertForbidden();
    }

    public function test_pending_receipt_only_shows_incoming_transfers_for_user_branch(): void
    {
        // Incoming to Motera
        StockTransfer::create([
            'transfer_number' => 'STF-IN-MOTERA',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchSatellite->id,
            'to_branch_id' => $this->branchMotera->id,
            'status' => 'Dispatched',
            'total_qty' => 5,
        ]);

        // Incoming to Satellite
        StockTransfer::create([
            'transfer_number' => 'STF-IN-SATELLITE',
            'transfer_date' => now()->toDateString(),
            'from_branch_id' => $this->branchMotera->id,
            'to_branch_id' => $this->branchSatellite->id,
            'status' => 'Dispatched',
            'total_qty' => 5,
        ]);

        $res = $this->actingAs($this->managerMotera)->get(route('inventory.stock-transfers.pending-receipt'));
        $res->assertOk();
        $res->assertSee('STF-IN-MOTERA');
        $res->assertDontSee('STF-IN-SATELLITE');
    }
}
