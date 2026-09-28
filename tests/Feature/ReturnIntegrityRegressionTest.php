<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the return-integrity fixes found by the concurrency harness
 * (scripts/qa/concurrency.php):
 *   BUG-002  Purchase/return numbering was max(id)+1 (duplicate numbers -> HTTP 500 under load)
 *   BUG-003  Return quantity rules ran outside any lock (over-returning under concurrency)
 *   BUG-004  A return could name another customer's / supplier's / a cancelled document
 *   BUG-005  A duplicate submit (same posting_key) surfaced as a raw 500, not an idempotent success
 * The lock itself is proven by the multi-process harness; these pin the deterministic rules.
 */
class ReturnIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Customer $otherCustomer;
    private Supplier $supplier;
    private Supplier $otherSupplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        User::factory()->create(); // burn user id 1 (hard-coded super-user)

        $this->branch = Branch::create(['name' => 'Integrity Branch', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->customer = Customer::create(['name' => 'Cust A', 'mobile' => '9000000001', 'state' => 'Gujarat', 'status' => true]);
        $this->otherCustomer = Customer::create(['name' => 'Cust B', 'mobile' => '9000000002', 'state' => 'Gujarat', 'status' => true]);
        $this->supplier = Supplier::create(['name' => 'Supp A', 'phone' => '9000000011', 'state' => 'Gujarat']);
        $this->otherSupplier = Supplier::create(['name' => 'Supp B', 'phone' => '9000000012', 'state' => 'Gujarat']);
        $this->item = Item::create([
            'name' => 'Integrity Item', 'item_code' => 'INT-1', 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'landing_cost' => 60, 'gst_tax_id' => $gst->id, 'status' => true, 'allow_negative_stock' => true,
            'batch_expiry_details' => 'Not Required',
        ]);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 100, 'cost_price' => 60]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    private function bill(Customer $customer, float $qty = 5, string $status = 'Posted'): SalesBill
    {
        $bill = SalesBill::create([
            'bill_number' => 'INT-'.Str::random(6), 'bill_date' => now()->toDateString(), 'customer_id' => $customer->id,
            'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 590, 'status' => $status,
        ]);
        SalesBillItem::create(['sales_bill_id' => $bill->id, 'item_id' => $this->item->id, 'qty' => $qty, 'sell_price' => 100, 'net_amount' => 500]);

        return $bill;
    }

    private function salesReturnPayload(SalesBill $bill, float $qty, ?string $key = null, ?Customer $customer = null): array
    {
        return [
            'posting_key' => $key ?? (string) Str::uuid(), 'return_date' => now()->toDateString(),
            'customer_id' => ($customer ?? $this->customer)->id, 'branch_id' => $this->branch->id, 'sales_bill_id' => $bill->id,
            'return_mode' => 'Cash', 'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 120, 'gst_percent' => 18]],
        ];
    }

    private function invoice(Supplier $supplier, float $qty = 10, string $status = 'Posted'): PurchaseInvoice
    {
        $inv = PurchaseInvoice::create([
            'invoice_number' => 'PI-'.Str::random(6), 'invoice_date' => now()->toDateString(), 'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id, 'purchase_type' => 'Local', 'status' => $status, 'total' => 1180, 'total_gst' => 180, 'total_qty' => $qty,
        ]);
        PurchaseInvoiceItem::create(['purchase_invoice_id' => $inv->id, 'item_id' => $this->item->id, 'qty' => $qty, 'cost_price' => 100,
            'gst_percent' => 18, 'disc_percent' => 0, 'disc_amount' => 0, 'total' => 1180]);

        return $inv;
    }

    private function purchaseReturnPayload(PurchaseInvoice $inv, float $qty, ?string $key = null, ?Supplier $supplier = null): array
    {
        return [
            'posting_key' => $key ?? (string) Str::uuid(), 'return_date' => now()->toDateString(),
            'supplier_id' => ($supplier ?? $this->supplier)->id, 'branch_id' => $this->branch->id, 'purchase_invoice_id' => $inv->id,
            'purchase_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => $qty, 'cost_price' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]],
        ];
    }

    // ---------------------------------------------------------------- BUG-004 (tampering)

    public function test_sales_return_rejects_a_bill_that_belongs_to_another_customer(): void
    {
        $foreignBill = $this->bill($this->otherCustomer);

        $this->actingAs($this->user)
            ->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($foreignBill, 1))
            ->assertStatus(422)->assertJsonValidationErrors('sales_bill_id');

        $this->assertSame(0, SalesReturn::count());
        $this->assertEquals(100, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_sales_return_rejects_a_cancelled_bill(): void
    {
        $bill = $this->bill($this->customer, 5, 'Cancelled');

        $this->actingAs($this->user)
            ->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 1))
            ->assertStatus(422)->assertJsonValidationErrors('sales_bill_id');

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_purchase_return_rejects_an_invoice_that_belongs_to_another_supplier(): void
    {
        $foreign = $this->invoice($this->otherSupplier);

        $this->actingAs($this->user)
            ->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($foreign, 1))
            ->assertStatus(422)->assertJsonValidationErrors('purchase_invoice_id');

        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_purchase_return_rejects_a_cancelled_invoice(): void
    {
        $inv = $this->invoice($this->supplier, 10, 'Cancelled');

        $this->actingAs($this->user)
            ->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($inv, 1))
            ->assertStatus(422)->assertJsonValidationErrors('purchase_invoice_id');
    }

    // ---------------------------------------------------------------- BUG-003 (quantity rules)

    public function test_sales_return_cumulative_returns_can_never_exceed_sold_quantity(): void
    {
        $bill = $this->bill($this->customer, 5);

        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 3))->assertRedirect();
        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 3))
            ->assertStatus(422)->assertJsonValidationErrors('items'); // only 2 left
        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 2))->assertRedirect();
        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 1))
            ->assertStatus(422)->assertJsonValidationErrors('items'); // fully returned

        $returned = (float) \DB::table('sales_return_items as i')->join('sales_returns as r', 'r.id', '=', 'i.sales_return_id')
            ->where('r.sales_bill_id', $bill->id)->sum('i.qty');
        $this->assertEquals(5.0, $returned);
        $this->assertEquals(105.0, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'), 'stock rises only by accepted return qty');
    }

    public function test_cancelling_a_sales_return_frees_the_quantity_again(): void
    {
        $bill = $this->bill($this->customer, 5);
        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 5))->assertRedirect();
        $return = SalesReturn::firstOrFail();

        $this->actingAs($this->user)->delete(route('sales.sales-returns.destroy', $return))->assertRedirect();

        $this->assertEquals(100.0, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'));
        $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 5))->assertRedirect();
    }

    public function test_purchase_return_cumulative_returns_can_never_exceed_purchased_quantity(): void
    {
        $inv = $this->invoice($this->supplier, 10);

        $this->actingAs($this->user)->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($inv, 6))->assertRedirect();
        $this->actingAs($this->user)->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($inv, 5))
            ->assertStatus(422)->assertJsonValidationErrors('items'); // only 4 left
        $this->actingAs($this->user)->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($inv, 4))->assertRedirect();

        $this->assertEquals(90.0, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'));
    }

    // ---------------------------------------------------------------- BUG-005 (idempotency)

    public function test_duplicate_sales_return_submit_is_idempotent_and_does_not_double_stock(): void
    {
        $bill = $this->bill($this->customer, 50);
        $key = (string) Str::uuid();

        foreach ([1, 2, 3] as $_) {
            $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), $this->salesReturnPayload($bill, 1, $key))
                ->assertRedirect();
        }

        $this->assertSame(1, SalesReturn::where('posting_key', $key)->count());
        $this->assertEquals(101.0, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_duplicate_purchase_return_submit_is_idempotent_and_does_not_double_stock(): void
    {
        $inv = $this->invoice($this->supplier, 10);
        $key = (string) Str::uuid();

        foreach ([1, 2, 3] as $_) {
            $this->actingAs($this->user)->postJson(route('purchase.purchase-returns.store'), $this->purchaseReturnPayload($inv, 10, $key))
                ->assertRedirect();
        }

        $this->assertSame(1, PurchaseReturn::where('posting_key', $key)->count());
        $this->assertEquals(90.0, (float) ItemStock::where('item_id', $this->item->id)->value('quantity'));
    }

    // ---------------------------------------------------------------- BUG-002 (numbering)

    public function test_prefixed_numbers_are_unique_sequential_and_continue_after_existing_rows(): void
    {
        $svc = app(DocumentNumberingService::class);
        $inv = $this->invoice($this->supplier, 1);
        $inv->update(['invoice_number' => 'PINV90041']);   // far above any auto-increment id, so the test is order independent

        $first = $svc->nextPrefixed('PINV', PurchaseInvoice::class, 'invoice_number', 5);
        $this->assertSame('PINV90042', $first, 'continues after the highest number already in the table');

        PurchaseInvoice::create(['invoice_number' => $first, 'invoice_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id, 'purchase_type' => 'Local', 'status' => 'Posted', 'total' => 1, 'total_gst' => 0, 'total_qty' => 1]);

        $this->assertSame('PINV90043', $svc->nextPrefixed('PINV', PurchaseInvoice::class, 'invoice_number', 5));
    }

    public function test_preview_does_not_consume_a_number(): void
    {
        $svc = app(DocumentNumberingService::class);

        $preview1 = $svc->nextPrefixed('GRN', PurchaseInvoice::class, 'grn_number', 6, false);
        $preview2 = $svc->nextPrefixed('GRN', PurchaseInvoice::class, 'grn_number', 6, false);
        $real = $svc->nextPrefixed('GRN', PurchaseInvoice::class, 'grn_number', 6);

        $this->assertSame($preview1, $preview2);
        $this->assertSame($preview1, $real);
    }

    public function test_generate_creates_a_missing_sequence_exactly_once(): void
    {
        $svc = app(DocumentNumberingService::class);

        $a = $svc->generate('sales_return', $this->branch->id);
        $b = $svc->generate('sales_return', $this->branch->id);

        $this->assertNotSame($a, $b);
        $this->assertSame(1, \DB::table('document_sequences')->where('document_type', 'sales_return')->count());
    }

    /** BUG-006: two branches own separate counters but share one unique bill_number space. */
    public function test_two_branches_never_get_the_same_document_number(): void
    {
        $svc = app(DocumentNumberingService::class);
        $b2 = Branch::create(['name' => 'Second Branch', 'state' => 'Gujarat']);

        $numbers = [];
        foreach ([$this->branch->id, $b2->id, $this->branch->id, $b2->id] as $bid) {
            $n = $svc->generate('sales_bill', $bid);
            $numbers[] = $n;
            SalesBill::create(['bill_number' => $n, 'bill_date' => now(), 'customer_id' => $this->customer->id, 'branch_id' => $bid,
                'sales_type' => 'Local', 'total' => 1, 'status' => 'Posted']);
        }

        $this->assertCount(4, array_unique($numbers));
        $this->assertSame(1, \DB::table('document_sequences')->where('series', 'gate:sales_bill')->count(), 'one gate row serialises the type');
    }
}
