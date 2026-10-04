<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DamageStock;
use App\Models\OpeningStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\StockUpdate;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalCompactTransactionUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    private function assertCompactUiContract(
        string $content,
        string $totalId,
        string $badgeId,
        string $saveBtnId,
        ?string $tableSelector = null
    ): void {
        // 1. Assets
        $this->assertStringContainsString('transaction-compact-layout.css', $content);
        $this->assertStringContainsString('transaction-layout-engine.js', $content);

        // 2. Containers & Grids
        $this->assertStringContainsString('tx-items-scroll-container', $content);
        $this->assertStringContainsString('tx-header-fields-grid', $content);

        // 3. Rich Action Footer
        $this->assertStringContainsString('tx-rich-footer', $content);
        $this->assertStringContainsString('id="' . $totalId . '"', $content);
        $this->assertStringContainsString('id="' . $badgeId . '"', $content);
        $this->assertStringContainsString('id="' . $saveBtnId . '"', $content);

        if ($tableSelector) {
            $this->assertStringContainsString($tableSelector, $content);
        }
    }

    // ==========================================
    // PHASE 1: SALES MODULES
    // ==========================================

    public function test_sales_quotations_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('sales.sales-quotations.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-sq-final-total',
            'sq-total-items-badge',
            'sq-main-save-btn',
            'sq-items-table'
        );

        // Edit
        $customer = Customer::create([
            'name' => 'SQ Test Customer',
            'mobile' => '9876543291',
            'branch_id' => $this->branch->id,
        ]);
        $sq = SalesQuotation::create([
            'quotation_number' => 'SQ-TEST-001',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'quotation_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('sales.sales-quotations.edit', $sq));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-sq-final-total',
            'sq-total-items-badge',
            'sq-main-save-btn',
            'sq-items-table'
        );
    }

    public function test_sales_orders_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('sales.sales-orders.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-so-final-total',
            'so-total-items-badge',
            'so-main-save-btn',
            'so-items-table'
        );

        // Edit
        $customer = Customer::create([
            'name' => 'SO Test Customer',
            'mobile' => '9876543292',
            'branch_id' => $this->branch->id,
        ]);
        $so = SalesOrder::create([
            'order_number' => 'SO-TEST-001',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Open',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('sales.sales-orders.edit', $so));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-so-final-total',
            'so-total-items-badge',
            'so-main-save-btn',
            'so-items-table'
        );
    }

    public function test_sales_returns_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('sales.sales-returns.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-sr-final-total',
            'sr-total-items-badge',
            'sr-main-save-btn',
            'sr-items-table'
        );

        // Edit
        $customer = Customer::create([
            'name' => 'SR Test Customer',
            'mobile' => '9876543293',
            'branch_id' => $this->branch->id,
        ]);
        $sr = SalesReturn::create([
            'return_number' => 'SR-TEST-001',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'return_date' => now()->format('Y-m-d'),
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('sales.sales-returns.edit', $sr));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-sr-final-total',
            'sr-total-items-badge',
            'sr-main-save-btn',
            'sr-items-table'
        );
    }

    public function test_sales_delivery_notes_create_renders_compact_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('sales.delivery-notes.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-sdn-final-total',
            'sdn-total-items-badge',
            'sdn-main-save-btn',
            'sdn-items-table'
        );
    }

    // ==========================================
    // PHASE 2: PURCHASE MODULES
    // ==========================================

    public function test_purchase_orders_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-orders.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-po-final-total',
            'po-total-items-badge',
            'po-main-save-btn',
            'po-items-table'
        );

        // Edit
        $supplier = Supplier::create([
            'name' => 'PO Test Supplier',
            'phone' => '9876543210',
            'state' => 'Gujarat',
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'po_date' => now()->format('Y-m-d'),
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('purchase.purchase-orders.edit', $po));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-po-final-total',
            'po-total-items-badge',
            'po-main-save-btn',
            'po-items-table'
        );
    }

    public function test_purchase_returns_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-returns.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-pr-final-total',
            'pr-total-items-badge',
            'pr-main-save-btn',
            'pr-items-table'
        );

        // Edit
        $supplier = Supplier::create([
            'name' => 'PR Test Supplier',
            'phone' => '9876543211',
            'state' => 'Gujarat',
        ]);
        $pr = PurchaseReturn::create([
            'return_number' => 'PR-TEST-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'return_date' => now()->format('Y-m-d'),
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('purchase.purchase-returns.edit', $pr));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-pr-final-total',
            'pr-total-items-badge',
            'pr-main-save-btn',
            'pr-items-table'
        );
    }

    public function test_goods_receipt_notes_create_renders_compact_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-receipt-notes.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-grn-final-total',
            'grn-total-items-badge',
            'grn-main-save-btn',
            'grn-items-table'
        );
    }

    public function test_purchase_indents_create_renders_compact_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-indents.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-indent-final-total',
            'indent-total-items-badge',
            'indent-main-save-btn',
            'indent-items-table'
        );
    }

    public function test_purchase_invoices_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-invoices.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-pinv-final-total',
            'pinv-total-items-badge',
            'pinv-main-save-btn',
            'pinv-items-table'
        );

        // Edit
        $supplier = Supplier::create([
            'name' => 'PI Test Supplier',
            'phone' => '9876543212',
            'state' => 'Gujarat',
        ]);
        $pinv = PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'invoice_date' => now()->format('Y-m-d'),
            'subtotal' => 100,
            'final_amount' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('purchase.purchase-invoices.edit', $pinv));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-pinv-final-total',
            'pinv-total-items-badge',
            'pinv-main-save-btn',
            'pinv-items-table'
        );
    }

    // ==========================================
    // PHASE 3: INVENTORY MOVEMENT MODULES
    // ==========================================

    public function test_stock_transfers_create_renders_compact_ui(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.stock-transfers.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-st-total-cost',
            'st-total-items-badge',
            'st-main-save-btn',
            'items-table'
        );
    }

    public function test_opening_stocks_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('inventory.opening-stocks.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-os-final-total',
            'os-total-items-badge',
            'os-main-save-btn',
            'items-table'
        );

        // Edit
        $os = OpeningStock::create([
            'entry_number' => 'OS-TEST-001',
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'entry_date' => now()->format('Y-m-d'),
            'total_quantity' => 10,
            'total_cost' => 100,
            'grand_net' => 100,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('inventory.opening-stocks.edit', $os));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-os-final-total',
            'os-total-items-badge',
            'os-main-save-btn',
            'items-table'
        );
    }

    public function test_damage_stocks_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('inventory.damage-stocks.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-ds-final-total',
            'ds-total-items-badge',
            'ds-main-save-btn',
            'items-table'
        );

        // Edit
        $ds = DamageStock::create([
            'damage_number' => 'DS-TEST-001',
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'entry_date' => now()->format('Y-m-d'),
            'total_quantity' => 5,
            'total_cost' => 50,
            'total_amount' => 50,
            'wastage_type' => 'Damage',
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('inventory.damage-stocks.edit', $ds));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-ds-final-total',
            'ds-total-items-badge',
            'ds-main-save-btn',
            'items-table'
        );
    }

    public function test_stock_updates_create_and_edit_renders_compact_ui(): void
    {
        // Create
        $response = $this->actingAs($this->user)->get(route('inventory.stock-updates.create'));
        $response->assertOk();
        $this->assertCompactUiContract(
            $response->getContent(),
            'display-su-total-qty',
            'su-total-items-badge',
            'su-main-save-btn',
            'items-table'
        );

        // Edit
        $su = StockUpdate::create([
            'update_number' => 'SU-TEST-001',
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'entry_date' => now()->format('Y-m-d'),
            'status' => 'Pending',
            'total_items' => 1,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('inventory.stock-updates.edit', $su));
        $editResponse->assertOk();
        $this->assertCompactUiContract(
            $editResponse->getContent(),
            'display-su-total-qty',
            'su-total-items-badge',
            'su-main-save-btn',
            'items-table'
        );
    }

    // ==========================================
    // PHASE 4: FORM SUBMISSION & VALIDATION SAFETY
    // ==========================================

    public function test_sales_quotations_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('sales.sales-quotations.store'), []);
        $response->assertSessionHasErrors(['customer_id']);
    }

    public function test_purchase_orders_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('purchase.purchase-orders.store'), []);
        $response->assertSessionHasErrors(['supplier_id']);
    }

    public function test_stock_transfers_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('inventory.stock-transfers.store'), []);
        $response->assertSessionHasErrors();
    }

    public function test_opening_stocks_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('inventory.opening-stocks.store'), []);
        $response->assertSessionHasErrors();
    }

    public function test_damage_stocks_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('inventory.damage-stocks.store'), []);
        $response->assertSessionHasErrors();
    }

    public function test_stock_updates_validates_compulsory_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('inventory.stock-updates.store'), []);
        $response->assertSessionHasErrors(['branch_id']);
    }
}
