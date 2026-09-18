<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Register;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\TillSession;
use App\Models\User;
use App\Models\UserTablePreference;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * High-speed core POS regression test suite.
 * Run in < 5 seconds to verify that critical business calculations,
 * stock balances, column preferences, and till cash logic remain intact.
 *
 * Uses RefreshDatabase to run migrations on in-memory SQLite (works on CI and local).
 */
class CorePosRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        // RefreshDatabase wipes seeder-created roles; recreate Owner role for tests
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main POS Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    /**
     * 1. Test TaxEngine: Exclusive & Inclusive GST Math & Line Discounts.
     */
    public function test_tax_engine_computes_accurate_taxable_gst_and_discount(): void
    {
        $gst18 = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => '18% GST']);
        $item = Item::create([
            'name' => 'Regression Biscuit 100g',
            'sell_price' => 100.00,
            'cost_price' => 70.00,
            'mrp' => 110.00,
            'gst_tax_id' => $gst18->id,
            'tax_inclusive' => false,
        ]);

        $engine = app(TaxEngine::class);

        // Exclusive GST Test: Qty 2 @ Rs 100 with 10% line discount
        // Gross = 200, Disc = 20 => Taxable = 180, GST 18% = 32.40, Net = 212.40
        $resExclusive = $engine->calculate(
            qty: 2.0,
            price: 100.00,
            item: $item,
            discPercent: 10.0,
            discAmount: 20.00,
            extraDeductions: 0.0,
            isInterstate: false,
            isTaxInclusive: false
        );

        $this->assertEquals(180.00, round($resExclusive['taxable_value'], 2));
        $this->assertEquals(32.40, round($resExclusive['gst_tax_amount'], 2));
        $this->assertEquals(212.40, round($resExclusive['net_amount'], 2));

        // Inclusive GST Test: Qty 1 @ Rs 118 inclusive with 0 discount
        // Net = 118, Taxable = 100, GST = 18
        $resInclusive = $engine->calculate(
            qty: 1.0,
            price: 118.00,
            item: $item,
            discPercent: 0.0,
            discAmount: 0.0,
            extraDeductions: 0.0,
            isInterstate: false,
            isTaxInclusive: true
        );

        $this->assertEquals(100.00, round($resInclusive['taxable_value'], 2));
        $this->assertEquals(18.00, round($resInclusive['gst_tax_amount'], 2));
        $this->assertEquals(118.00, round($resInclusive['net_amount'], 2));
    }

    /**
     * 2. Test Dynamic Column Visibility & Priority Sequence Engine API.
     */
    public function test_dynamic_column_customizer_preference_lifecycle(): void
    {
        $this->actingAs($this->user);

        $tableKey = 'master.item-categories';
        $preferences = [
            ['key' => 'col-name', 'order' => 1, 'visible' => true],
            ['key' => 'col-status', 'order' => 2, 'visible' => false],
            ['key' => 'col-actions', 'order' => 3, 'visible' => true],
        ];

        // Store preferences
        $storeResponse = $this->postJson(route('tools.table-preferences.store'), [
            'table_key' => $tableKey,
            'preferences' => $preferences,
        ]);
        $storeResponse->assertOk();
        $storeResponse->assertJsonPath('status', 'success');

        // Fetch preferences
        $getResponse = $this->getJson(route('tools.table-preferences.get', ['table_key' => $tableKey]));
        $getResponse->assertOk();
        $getResponse->assertJsonPath('status', 'success');
        $this->assertCount(3, $getResponse->json('preferences'));
        $this->assertEquals(false, $getResponse->json('preferences.1.visible'));

        // Reset preferences
        $resetResponse = $this->postJson(route('tools.table-preferences.reset'), [
            'table_key' => $tableKey,
        ]);
        $resetResponse->assertOk();
        $this->assertNull(UserTablePreference::getForUser($this->user->id, $tableKey));
    }

    /**
     * 3. Test Purchase Invoice requires matching supplier inv amount and increments stock.
     */
    public function test_purchase_invoice_increments_stock_and_respects_supplier_amount(): void
    {
        $this->actingAs($this->user);

        $supplier = Supplier::create([
            'name' => 'Regression Supplier Ltd',
            'state' => 'Maharashtra',
        ]);

        $item = Item::create([
            'name' => 'Regression Juice Can',
            'cost_price' => 50.00,
            'sell_price' => 80.00,
            'mrp' => 85.00,
        ]);

        // Purchase 10 units @ 50.00 = 500.00 total
        $response = $this->post(route('purchase.purchase-invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => 500.00,
            'items' => [
                ['item_id' => $item->id, 'qty' => 10, 'cost_price' => 50.00],
            ],
        ]);

        $response->assertRedirect(route('purchase.purchase-invoices.index'));

        // Verify stock was incremented to 10
        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(10.0, (float) $stock->quantity);
    }

    /**
     * 4. Test Stock Transfer dispatch and receipt between branches.
     */
    public function test_stock_transfer_dispatch_and_receive_updates_branch_quantities(): void
    {
        $this->actingAs($this->user);

        $branchB = Branch::create(['name' => 'Branch B Depot', 'state' => 'Maharashtra']);

        $item = Item::create([
            'name' => 'Transfer Test Item',
            'cost_price' => 100.00,
        ]);

        // Seed 30 units in source branch
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'quantity' => 30.0,
            'cost_price' => 100.00,
        ]);

        // Dispatch 10 units
        $dispatchResponse = $this->post(route('inventory.stock-transfers.store'), [
            'transfer_date' => now()->format('Y-m-d'),
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $branchB->id,
            'items' => [
                ['item_id' => $item->id, 'qty' => 10],
            ],
        ]);

        $dispatchResponse->assertRedirect(route('inventory.stock-transfers.index'));

        $transfer = StockTransfer::latest('id')->first();
        $this->assertEquals('Dispatched', $transfer->status);

        // Source branch stock must now be 20
        $sourceStock = ItemStock::where('item_id', $item->id)->where('branch_id', $this->branch->id)->first();
        $this->assertEquals(20.0, (float) $sourceStock->quantity);

        // Receive the 10 units at destination branch
        $line = $transfer->items->first();
        $receiveResponse = $this->post(route('inventory.stock-transfers.receive', $transfer), [
            'items' => [
                ['id' => $line->id, 'received_qty' => 10],
            ],
        ]);
        $receiveResponse->assertRedirect(route('inventory.stock-transfers.pending-receipt'));

        // Destination branch stock must now be 10
        $destStock = ItemStock::where('item_id', $item->id)->where('branch_id', $branchB->id)->first();
        $this->assertNotNull($destStock);
        $this->assertEquals(10.0, (float) $destStock->quantity);
    }

    /**
     * 5. Test Till Session cash opening, movements, and closing variance calculation.
     */
    public function test_till_session_lifecycle_and_variance_calculation(): void
    {
        $this->actingAs($this->user);

        $register = Register::create([
            'branch_id' => $this->branch->id,
            'name' => 'Counter 1 Reg',
        ]);

        // Open session with 500 opening cash
        $openResponse = $this->post(route('till.sessions.open'), [
            'register_id' => $register->id,
            'opening_cash' => 500.00,
        ]);
        $session = TillSession::latest('id')->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->isOpen());

        // Cash movement: Rs 100 in, Rs 50 out
        $this->post(route('till.sessions.cash-movements', $session), ['type' => 'In', 'amount' => 100.00]);
        $this->post(route('till.sessions.cash-movements', $session), ['type' => 'Out', 'amount' => 50.00]);

        // Expected cash = 500 + 100 - 50 = 550.00.
        // User counts physical cash = 560.00 (Variance = +10.00 excess)
        $closeResponse = $this->post(route('till.sessions.close', $session), [
            'actual_cash' => 560.00,
        ]);
        $closeResponse->assertRedirect(route('till.sessions.show', $session));

        $session->refresh();
        $this->assertEquals('Closed', $session->status);
        $this->assertEquals(550.00, (float) $session->expected_cash);
        $this->assertEquals(560.00, (float) $session->actual_cash);
        $this->assertEquals(10.00, (float) $session->variance);
    }
}
