<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FinancialYear;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private Branch $branch;
    private Supplier $supplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'RET-TEST-001'],
            [
                'name' => 'Return Test Product',
                'cost_price' => 100,
                'sell_price' => 150,
                'mrp' => 160,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
            ]
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'Return Test Supplier'],
            ['phone' => '9988776655', 'state' => 'Maharashtra', 'credit_limit' => 50000]
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');
    }

    public function test_purchase_return_reduces_stock_and_posts_correct_journal(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-001',
            'remarks' => 'Returning defective items',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'cost_price' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), $payload);
        $response->assertRedirect(route('purchase.purchase-returns.index'));

        $return = PurchaseReturn::where('supplier_id', $this->supplier->id)
            ->where('supplier_debit_note_no', 'DN-001')
            ->first();

        $this->assertNotNull($return);
        $this->assertEquals(500, $return->total - $return->total_gst); // taxable 500
        $this->assertEquals(90, $return->total_gst); // 18% of 500
        $this->assertEquals(590, $return->total);

        // Verify Stock Ledger: movement_type = PURCHASE_RETURN, qty_out = 5
        $stockMovement = StockLedger::where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals('PURCHASE_RETURN', $stockMovement->movement_type);
        $this->assertEquals(5.0, (float) $stockMovement->qty_out);

        // Verify Journal Entry: Debit Supplier 590, Credit Purchase 500, Credit GST 90
        $journal = JournalEntry::with('lines.ledger')
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->whereNull('reversal_of')
            ->first();

        $this->assertNotNull($journal);
        $this->assertEquals('Purchase Return', $journal->voucher_type);
        $this->assertEquals(590, (float) $journal->total_debit);
        $this->assertEquals(590, (float) $journal->total_credit);

        $supplierLine = $journal->lines->first(fn ($l) => $l->debit == 590);
        $this->assertNotNull($supplierLine);
        $this->assertEquals($this->supplier->name, $supplierLine->ledger->name);

        $purchaseLine = $journal->lines->first(fn ($l) => $l->credit == 500);
        $this->assertNotNull($purchaseLine);
        $this->assertEquals('Purchase Account', $purchaseLine->ledger->name);

        $gstLine = $journal->lines->first(fn ($l) => $l->credit == 90);
        $this->assertNotNull($gstLine);
        $this->assertEquals('GST Input', $gstLine->ledger->name);
    }

    public function test_purchase_return_cancellation_reverses_stock_and_journal(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'supplier_debit_note_no' => 'DN-CANCEL-001',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'cost_price' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
            ],
        ];

        $this->actingAs($this->manager)->post(route('purchase.purchase-returns.store'), $payload);

        $return = PurchaseReturn::where('supplier_debit_note_no', 'DN-CANCEL-001')->first();
        $this->assertNotNull($return);

        // Cancel the return
        $deleteResponse = $this->actingAs($this->manager)->delete(route('purchase.purchase-returns.destroy', $return));
        $deleteResponse->assertRedirect(route('purchase.purchase-returns.index'));

        // Stock reversal must exist
        $reversalStock = StockLedger::where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->whereNotNull('reversal_of')
            ->first();

        $this->assertNotNull($reversalStock);
        $this->assertEquals(2.0, (float) $reversalStock->qty_in); // restores stock

        // Journal reversal must exist
        $reversalJournal = JournalEntry::where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->whereNotNull('reversal_of')
            ->first();

        $this->assertNotNull($reversalJournal);
    }

    public function test_cash_bank_book_report_renders_and_returns_ok(): void
    {
        $response = $this->actingAs($this->manager)->get(route('finance.reports.cash-bank-book'));
        $response->assertStatus(200);
        $response->assertSee('Cash & Bank Book');
        $response->assertSee('Opening Balance');
        $response->assertSee('Closing Balance');
    }

    public function test_purchase_return_views_render(): void
    {
        $this->actingAs($this->manager)->get(route('purchase.purchase-returns.index'))->assertStatus(200);
        $this->actingAs($this->manager)->get(route('purchase.purchase-returns.create'))->assertStatus(200);
    }

    public function test_sales_return_bill_items_ajax_endpoint(): void
    {
        $customer = \App\Models\Customer::firstOrCreate(['phone' => '9999988888'], ['name' => 'Test Customer']);
        $bill = \App\Models\SalesBill::create([
            'bill_number' => 'BILL-AJAX-01',
            'bill_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 150,
            'total_gst' => 0,
            'status' => 'Posted',
        ]);

        $bill->items()->create([
            'item_id' => $this->item->id,
            'qty' => 1,
            'sell_price' => 150,
            'net_amount' => 150,
        ]);

        $response = $this->actingAs($this->manager)->get(route('sales.sales-returns.bill-items', $bill));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'customer_id', 'branch_id', 'sales_type', 'items' => [
                '*' => ['item_id', 'item_name', 'qty', 'sell_price', 'net_amount']
            ]
        ]);
    }

    public function test_sales_bill_show_and_thermal_receipt_render(): void
    {
        $customer = \App\Models\Customer::firstOrCreate(['phone' => '9999977777'], ['name' => 'Receipt Customer']);
        $bill = \App\Models\SalesBill::create([
            'bill_number' => 'BILL-RCPT-01',
            'bill_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 200,
            'total_gst' => 0,
            'status' => 'Posted',
        ]);

        $bill->items()->create([
            'item_id' => $this->item->id,
            'qty' => 2,
            'sell_price' => 100,
            'net_amount' => 200,
        ]);

        $showResponse = $this->actingAs($this->manager)->get(route('sales.sales-bills.show', $bill));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('BILL-RCPT-01');
        $showResponse->assertSee('Thermal Receipt (80mm)');

        $receiptResponse = $this->actingAs($this->manager)->get(route('sales.sales-bills.receipt', $bill));
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee('URBAN PETS');
        $receiptResponse->assertSee('BILL-RCPT-01');
        $receiptResponse->assertSee('GRAND TOTAL');
    }
}
