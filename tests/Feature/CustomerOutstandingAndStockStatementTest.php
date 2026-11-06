<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesBillPayment;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\User;
use App\Services\Reports\DynamicReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerOutstandingAndStockStatementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch1;
    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch1 = Branch::create(['name' => 'Branch 1', 'code' => 'B1', 'state' => 'Maharashtra']);
        $this->branch2 = Branch::create(['name' => 'Branch 2', 'code' => 'B2', 'state' => 'Maharashtra']);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch1->id,
        ]);
        $this->user->assignRole('Owner');
    }

    public function test_customer_outstanding_balance_calculation(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'mobile' => '9876543210',
            'status' => true,
        ]);

        $cashTender = TenderType::create(['name' => 'Cash', 'type' => 'Cash']);
        $creditTender = TenderType::create(['name' => 'Credit', 'type' => 'Credit']);

        // Bill 1: Total 1000, 400 paid via cash, 600 remaining
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-TEST-001',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch1->id,
            'status' => 'Posted',
            'total' => 1000.00,
            'payment_type' => 'Split',
            'bill_date' => now()->toDateString(),
        ]);
        SalesBillPayment::create([
            'sales_bill_id' => $bill1->id,
            'tender_type_id' => $cashTender->id,
            'amount' => 400.00,
        ]);

        // Bill 2: Total 500, fully credit (0 paid non-credit)
        $bill2 = SalesBill::create([
            'bill_number' => 'SB-TEST-002',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch1->id,
            'status' => 'Posted',
            'total' => 500.00,
            'payment_type' => 'Credit',
            'bill_date' => now()->toDateString(),
        ]);
        SalesBillPayment::create([
            'sales_bill_id' => $bill2->id,
            'tender_type_id' => $creditTender->id,
            'amount' => 500.00,
        ]);

        // Expected outstanding = (1000 - 400) + (500 - 0) = 1100.00
        $balance = $customer->getOutstandingBalance($this->branch1->id);
        $this->assertEquals(1100.00, $balance['outstanding_amount']);
        $this->assertTrue($balance['is_pending']);
        $this->assertEquals(2, $balance['unpaid_bills_count']);
        $this->assertEquals('₹ 1,100.00', $balance['outstanding_formatted']);

        // Test batch method
        $batch = Customer::getBatchOutstandingBalances([$customer->id], $this->branch1->id);
        $this->assertArrayHasKey($customer->id, $batch);
        $this->assertEquals(1100.00, $batch[$customer->id]['outstanding_amount']);
    }

    public function test_itemwise_stock_statement_report_with_transfers(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier', 'status' => true]);
        $customer = Customer::create(['name' => 'Retail Client', 'mobile' => '9999988888', 'status' => true]);

        $item = Item::create([
            'name' => 'Royal Canin Maxi',
            'item_code' => 'RC-MAXI-15',
            'cost_price' => 100.00,
            'sell_price' => 150.00,
            'status' => true,
        ]);

        // Current closing stock in branch 1 = 150
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 150.00,
            'cost_price' => 100.00,
        ]);

        $today = now()->toDateString();

        // 1. Purchase Inward in Branch 1: 50
        $pi = PurchaseInvoice::create([
            'invoice_number' => 'PI-TEST-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch1->id,
            'status' => 'Approved',
            'invoice_date' => $today,
        ]);
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $pi->id,
            'item_id' => $item->id,
            'qty' => 50.00,
            'unit_cost' => 100.00,
        ]);

        // 2. Transfer IN to Branch 1 from Branch 2: 30
        $stIn = StockTransfer::create([
            'transfer_number' => 'ST-TEST-001',
            'from_branch_id' => $this->branch2->id,
            'to_branch_id' => $this->branch1->id,
            'status' => 'Received',
            'transfer_date' => $today,
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $stIn->id,
            'item_id' => $item->id,
            'qty' => 30.00,
            'received_qty' => 30.00,
            'unit_cost' => 100.00,
        ]);

        // 3. Sales Outward from Branch 1: 40
        $sb = SalesBill::create([
            'bill_number' => 'SB-TEST-003',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch1->id,
            'status' => 'Posted',
            'bill_date' => $today,
            'total' => 6000.00,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $sb->id,
            'item_id' => $item->id,
            'qty' => 40.00,
            'sell_price' => 150.00,
        ]);

        // 4. Transfer OUT from Branch 1 to Branch 2: 20
        $stOut = StockTransfer::create([
            'transfer_number' => 'ST-TEST-002',
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'status' => 'Dispatched',
            'transfer_date' => $today,
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $stOut->id,
            'item_id' => $item->id,
            'qty' => 20.00,
            'unit_cost' => 100.00,
        ]);

        $reportService = app(DynamicReportService::class);
        $request = new \Illuminate\Http\Request([
            'from' => $today,
            'to' => $today,
            'branch_id' => $this->branch1->id,
        ]);
        $data = $reportService->generate($request, 'itemwise-stock-statement');

        $this->assertNotEmpty($data['rows']);
        $row = $data['rows']->first();
        $cells = $row['cells'];

        // Cells:
        // 0: Code, 1: Description, 2: Brand, 3: Branch, 4: Opening, 5: Inward, 6: Outward, 7: Closing, 8: Unit Cost, 9: Valuation
        $inwardCell = $cells[5];
        $outwardCell = $cells[6];
        $openingCell = $cells[4];
        $closingCell = $cells[7];

        $this->assertStringContainsString('+80', $inwardCell);
        $this->assertStringContainsString('Purch: 50', $inwardCell);
        $this->assertStringContainsString('Trf-In: 30', $inwardCell);

        $this->assertStringContainsString('-60', $outwardCell);
        $this->assertStringContainsString('Sales: 40', $outwardCell);
        $this->assertStringContainsString('Trf-Out: 20', $outwardCell);

        $this->assertStringContainsString('130', $openingCell);
        $this->assertStringContainsString('150', $closingCell);
    }

    public function test_purchase_invoice_view_contains_confirmation_guard(): void
    {
        $response = $this->actingAs($this->user)->get(route('purchase.purchase-invoices.create'));
        $response->assertOk();
        $response->assertSee('Confirm Purchase Bill');
        $response->assertSee('Are you sure you want to save this purchase bill?');
        $response->assertSee('Yes, Save Invoice');
        $response->assertSee('sweetalert2');
    }
}
