<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The legacy "same customer + same first item within 10 s" (sales) and "same supplier + amount within 30 s"
 * (purchase) content guards are gone. Duplicate-submit protection is posting_key idempotency only.
 */
class DuplicateSubmitGuardRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        User::factory()->create(); // burn hard-coded super-user id 1
        $this->branch = Branch::create(['name' => 'Dup Branch', 'code' => 'DUP', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        // Non-walk-in customer (has a mobile, name not walk/cash/retail): the old guard applied to exactly this.
        $this->customer = Customer::create(['name' => 'Regular Ravi', 'mobile' => '9876500001', 'status' => true, 'credit_limit' => 100000]);
        $this->item = Item::create([
            'name' => 'Dup Item', 'item_code' => 'DUP1', 'sell_price' => 300, 'mrp' => 300, 'cost_price' => 200,
            'gst_tax_id' => $gst->id, 'tax_inclusive' => true, 'status' => true, 'allow_negative_stock' => true,
        ]);
        ItemStock::updateOrCreate(['branch_id' => $this->branch->id, 'item_id' => $this->item->id], ['quantity' => 50]);

        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner);
    }

    private function stock(): float
    {
        return (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
    }

    private function salePayload(?string $key, float $qty = 1): array
    {
        $cash = TenderType::where('name', 'Cash')->first();

        return array_filter([
            'posting_key' => $key,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => $qty, 'sell_price' => 300, 'mrp' => 300, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]],
            'payments' => [['tender_type_id' => $cash->id, 'amount' => 300 * $qty]],
        ], fn ($v) => $v !== null);
    }

    public function test_repeat_customer_same_item_within_seconds_with_new_keys_creates_two_bills(): void
    {
        $this->post(route('sales.sales-bills.store'), $this->salePayload((string) Str::uuid()))->assertSessionHasNoErrors();
        $this->post(route('sales.sales-bills.store'), $this->salePayload((string) Str::uuid(), 2))->assertSessionHasNoErrors();

        $this->assertSame(2, SalesBill::count());
        $this->assertSame(2, SalesBill::distinct()->count('bill_number'));
    }

    public function test_same_posting_key_retry_replays_original_bill_without_error_or_second_bill(): void
    {
        $key = (string) Str::uuid();
        $this->post(route('sales.sales-bills.store'), $this->salePayload($key))->assertSessionHasNoErrors();
        $original = SalesBill::firstOrFail();
        $stockAfterFirst = $this->stock();

        // Retry lands inside the old 10 s window: it used to fail validation (422) before the replay check.
        $this->postJson(route('sales.sales-bills.store'), $this->salePayload($key))
            ->assertOk()
            ->assertJson(['success' => true, 'id' => $original->id, 'duplicate_prevented' => true]);

        $this->assertSame(1, SalesBill::count());
        $this->assertEquals($stockAfterFirst, $this->stock(), 'Retry must not deduct stock again.');
    }

    private function purchasePayload(?string $key, array $over = []): array
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Dup Supplier'], ['phone' => '9000000009', 'state' => 'Gujarat']);

        return array_filter(array_merge([
            'posting_key' => $key,
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_amount' => 1180,
            'items' => [['item_id' => $this->item->id, 'qty' => 10, 'free_qty' => 0, 'cost_price' => 100, 'sell_price' => 150, 'mrp' => 160]],
        ], $over), fn ($v) => $v !== null);
    }

    public function test_purchase_same_amount_different_keys_are_two_invoices(): void
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload((string) Str::uuid()))->assertSessionHasNoErrors();
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload((string) Str::uuid()))->assertSessionHasNoErrors();

        $this->assertSame(2, PurchaseInvoice::count());
    }

    public function test_purchase_same_posting_key_retry_creates_one_invoice_and_one_stock_posting(): void
    {
        $key = (string) Str::uuid();
        $before = $this->stock();
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload($key))->assertSessionHasNoErrors();
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload($key))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, PurchaseInvoice::where('posting_key', $key)->count());
        $this->assertSame(1, PurchaseInvoice::count());
        $this->assertEquals($before + 10, $this->stock());
    }

    public function test_purchase_create_form_carries_a_posting_key(): void
    {
        $this->get(route('purchase.purchase-invoices.create'))->assertOk()->assertSee('name="posting_key"', false);
    }

    public function test_genuine_duplicate_supplier_invoice_number_is_still_rejected(): void
    {
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload((string) Str::uuid(), ['supplier_inv_no' => 'SUP-1']))->assertSessionHasNoErrors();
        $this->post(route('purchase.purchase-invoices.store'), $this->purchasePayload((string) Str::uuid(), ['supplier_inv_no' => 'SUP-1']))->assertSessionHasErrors('supplier_inv_no');
        $this->assertSame(1, PurchaseInvoice::count());
    }
}
