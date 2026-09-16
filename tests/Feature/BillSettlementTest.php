<?php

namespace Tests\Feature;

use App\Models\BillSettlement;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillSettlementTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;
    private Branch $branch;
    private Customer $customer;
    private Supplier $supplier;
    private Ledger $cashLedger;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%']);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'SETTLE-ITEM-001'],
            [
                'name' => 'Settlement Test Product',
                'cost_price' => 100,
                'sell_price' => 200,
                'mrp' => 220,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => false,
            ]
        );

        $this->customer = Customer::firstOrCreate(
            ['name' => 'Settlement Test Customer'],
            ['phone' => '9876500112', 'state' => 'Maharashtra', 'credit_limit' => 50000]
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'Settlement Test Supplier'],
            ['phone' => '9876500334', 'state' => 'Maharashtra', 'credit_limit' => 50000]
        );

        $this->cashLedger = Ledger::firstOrCreate(
            ['name' => 'Main Cash Drawer'],
            ['ledger_group' => 'Cash in Hand', 'opening_balance' => 10000, 'opening_balance_type' => 'Debit']
        );

        $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->manager->assignRole('Manager');

        FinancialYear::firstOrCreate(
            ['start_date' => '2026-04-01'],
            ['end_date' => '2027-03-31', 'name' => '2026-2027', 'is_locked' => false]
        );
    }

    public function test_manager_can_settle_customer_credit_and_allocate_across_sales_bills(): void
    {
        // 1. Create two sales bills on credit for customer
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-SETTLE-001',
            'bill_date' => '2026-09-10',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Credit',
            'total' => 600.00,
            'status' => 'Posted',
        ]);

        $bill2 = SalesBill::create([
            'bill_number' => 'SB-SETTLE-002',
            'bill_date' => '2026-09-12',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Credit',
            'total' => 400.00,
            'status' => 'Posted',
        ]);

        // Post ledger entries for the sales bills
        app(\App\Services\Accounting\LedgerPostingService::class)->postSalesBill($bill1);
        app(\App\Services\Accounting\LedgerPostingService::class)->postSalesBill($bill2);

        $customerLedger = $this->customer->ledger;
        $this->assertNotNull($customerLedger);
        // Balance should be Dr 1000.00
        $this->assertEquals(1000.00, (float) $customerLedger->balance());

        // 2. Check unpaid bills AJAX discovery
        $ajaxResponse = $this->actingAs($this->manager)->getJson(route('finance.settlements.unpaid-bills', [
            'type' => 'Customer',
            'party_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
        ]));
        $ajaxResponse->assertOk();
        $this->assertCount(2, $ajaxResponse->json('bills'));

        // 3. Post a settlement of ₹800 (₹600 for bill 1, ₹200 for bill 2)
        $settlementResponse = $this->actingAs($this->manager)->post(route('finance.settlements.store'), [
            'settlement_type' => 'Customer',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'settlement_date' => '2026-09-16',
            'total_amount' => 800.00,
            'payment_mode' => 'Cash',
            'bank_ledger_id' => $this->cashLedger->id,
            'reference_no' => 'REC-001',
            'allocations' => [
                [
                    'bill_id' => $bill1->id,
                    'bill_amount' => 600.00,
                    'settled_amount' => 600.00,
                    'discount_amount' => 0,
                ],
                [
                    'bill_id' => $bill2->id,
                    'bill_amount' => 400.00,
                    'settled_amount' => 200.00,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $settlement = BillSettlement::latest()->first();
        $this->assertNotNull($settlement);
        $settlementResponse->assertRedirect(route('finance.settlements.show', $settlement));

        // 4. Verify Accounting Entry (Receipt Voucher)
        $this->assertNotNull($settlement->journal_entry_id);
        $voucher = JournalEntry::with('lines')->find($settlement->journal_entry_id);
        $this->assertEquals('Receipt', $voucher->voucher_type);
        $this->assertEquals(800.00, (float) $voucher->total_debit);
        $this->assertEquals(800.00, (float) $voucher->total_credit);

        // Cash ledger debited 800, Customer ledger credited 800
        $this->assertEquals(200.00, (float) $customerLedger->balance()); // 1000 - 800 = 200 remaining

        // 5. Verify Unpaid bills discovery now shows bill 1 settled and bill 2 having 200 due
        $updatedAjax = $this->actingAs($this->manager)->getJson(route('finance.settlements.unpaid-bills', [
            'type' => 'Customer',
            'party_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
        ]));
        $remainingBills = $updatedAjax->json('bills');
        $this->assertCount(1, $remainingBills);
        $this->assertEquals($bill2->id, $remainingBills[0]['id']);
        $this->assertEquals(200.00, (float) $remainingBills[0]['balance_due']);
    }

    public function test_manager_can_settle_supplier_liability_and_allocate_across_purchase_invoices(): void
    {
        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'PI-SETTLE-001',
            'invoice_date' => '2026-09-10',
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'total' => 1500.00,
            'status' => 'Posted',
        ]);

        app(\App\Services\Accounting\LedgerPostingService::class)->postPurchaseInvoice($invoice);

        $supplierLedger = $this->supplier->ledger;
        $this->assertNotNull($supplierLedger);
        // Creditors balance is -1500
        $this->assertEquals(-1500.00, (float) $supplierLedger->balance());

        // Post supplier settlement of ₹1500
        $response = $this->actingAs($this->manager)->post(route('finance.settlements.store'), [
            'settlement_type' => 'Supplier',
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'settlement_date' => '2026-09-16',
            'total_amount' => 1500.00,
            'payment_mode' => 'Bank Transfer',
            'bank_ledger_id' => $this->cashLedger->id,
            'reference_no' => 'UTR987654321',
            'allocations' => [
                [
                    'bill_id' => $invoice->id,
                    'bill_amount' => 1500.00,
                    'settled_amount' => 1500.00,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $settlement = BillSettlement::latest()->first();
        $this->assertNotNull($settlement);
        $response->assertRedirect(route('finance.settlements.show', $settlement));

        // Voucher is Payment
        $voucher = JournalEntry::find($settlement->journal_entry_id);
        $this->assertEquals('Payment', $voucher->voucher_type);
        $this->assertEquals(1500.00, (float) $voucher->total_debit);

        // Supplier liability fully cleared
        $this->assertEquals(0.00, (float) $supplierLedger->balance());
    }

    public function test_cancelling_settlement_reverses_journal_entry_and_restores_bill_balance(): void
    {
        $bill = SalesBill::create([
            'bill_number' => 'SB-SETTLE-003',
            'bill_date' => '2026-09-10',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'Credit',
            'total' => 500.00,
            'status' => 'Posted',
        ]);
        app(\App\Services\Accounting\LedgerPostingService::class)->postSalesBill($bill);

        $settlement = BillSettlement::create([
            'settlement_number' => 'SET-CST9999',
            'settlement_type' => 'Customer',
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'settlement_date' => '2026-09-16',
            'total_amount' => 500.00,
            'payment_mode' => 'Cash',
            'bank_ledger_id' => $this->cashLedger->id,
            'status' => 'Active',
        ]);
        $settlement->items()->create([
            'billable_type' => SalesBill::class,
            'billable_id' => $bill->id,
            'bill_amount' => 500.00,
            'settled_amount' => 500.00,
        ]);

        $entry = app(\App\Services\Accounting\LedgerPostingService::class)->postBillSettlement($settlement);
        $settlement->update(['journal_entry_id' => $entry->id]);

        $this->assertEquals(0.00, (float) $this->customer->ledger->balance());

        // Cancel the settlement
        $response = $this->actingAs($this->manager)->delete(route('finance.settlements.destroy', $settlement), [
            'reason' => 'Cheque bounced / cancelled',
        ]);
        $response->assertRedirect(route('finance.settlements.index'));

        $settlement->refresh();
        $this->assertEquals('Cancelled', $settlement->status);

        // Reversal was created, customer owes 500 again
        $this->assertEquals(500.00, (float) $this->customer->ledger->balance());
    }

    public function test_outstanding_aging_report_and_views_render(): void
    {
        $this->actingAs($this->manager)->get(route('finance.settlements.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('finance.settlements.create', ['type' => 'Customer']))->assertOk();
        $this->actingAs($this->manager)->get(route('finance.settlements.create', ['type' => 'Supplier']))->assertOk();

        $agingCustomer = $this->actingAs($this->manager)->get(route('finance.reports.outstanding-aging', ['party_type' => 'Customer']));
        $agingCustomer->assertOk();
        $agingCustomer->assertSee('Billwise Outstanding Aging Report');

        $agingSupplier = $this->actingAs($this->manager)->get(route('finance.reports.outstanding-aging', ['party_type' => 'Supplier']));
        $agingSupplier->assertOk();
        $agingSupplier->assertSee('Billwise Outstanding Aging Report');
    }
}
