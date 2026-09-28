<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FINAL Sales Return rules:
 *   Customer -> Customer's Sales Bill -> selected bill -> "Select item from bill" -> ONLY explicitly selected
 *   items become Return Items.
 *   Remaining Returnable Qty = Original Sold Qty - Previously Returned Qty.
 *   No original bill selected: allowed, but capped by the CUSTOMER-WIDE pool
 *     (qty the customer bought on non-cancelled bills - qty they already returned on any return). Never unlimited.
 *   The pool also caps billed returns, so billed + unbilled returns cannot exceed what was bought.
 */
class SalesReturnBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $cust;
    private Customer $other;
    private Item $p;
    private Item $q;
    private SalesBill $billX;
    private SalesBill $billY;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        User::factory()->create();

        $this->branch = Branch::create(['name' => 'Rules Branch', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->cust = Customer::create(['name' => 'Rules Cust', 'mobile' => '9444000001', 'status' => true]);
        $this->other = Customer::create(['name' => 'Other Cust', 'mobile' => '9444000002', 'status' => true]);
        $mk = fn ($code) => Item::create(['name' => "Item $code", 'item_code' => $code, 'sell_price' => 100, 'mrp' => 120, 'cost_price' => 60,
            'landing_cost' => 60, 'gst_tax_id' => $gst->id, 'status' => true, 'allow_negative_stock' => true, 'batch_expiry_details' => 'Not Required']);
        $this->p = $mk('RULE-P');
        $this->q = $mk('RULE-Q');
        foreach ([$this->p, $this->q] as $i) {
            ItemStock::create(['item_id' => $i->id, 'branch_id' => $this->branch->id, 'quantity' => 100, 'cost_price' => 60]);
        }

        // Customer bought: P 5 + Q 3 on bill X, P 2 on bill Y  (pool: P=7, Q=3)
        $this->billX = $this->bill($this->cust, [[$this->p, 5], [$this->q, 3]]);
        $this->billY = $this->bill($this->cust, [[$this->p, 2]]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    private function bill(Customer $c, array $lines, string $status = 'Posted'): SalesBill
    {
        $b = SalesBill::create(['bill_number' => 'R-'.Str::random(7), 'bill_date' => now()->toDateString(), 'customer_id' => $c->id,
            'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'payment_mode' => 'Cash', 'total' => 100, 'status' => $status]);
        foreach ($lines as [$item, $qty]) {
            SalesBillItem::create(['sales_bill_id' => $b->id, 'item_id' => $item->id, 'qty' => $qty, 'sell_price' => 100, 'net_amount' => $qty * 100]);
        }

        return $b;
    }

    /** @param  array<int,array{0:Item,1:float}>  $lines */
    private function submit(array $lines, ?SalesBill $bill, ?Customer $c = null)
    {
        return $this->actingAs($this->user)->postJson(route('sales.sales-returns.store'), [
            'posting_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(), 'customer_id' => ($c ?? $this->cust)->id,
            'branch_id' => $this->branch->id, 'sales_bill_id' => $bill?->id, 'return_mode' => 'Cash', 'sales_type' => 'Local',
            'items' => array_map(fn ($l) => ['item_id' => $l[0]->id, 'qty' => $l[1], 'sell_price' => 100, 'mrp' => 120, 'gst_percent' => 18], $lines),
        ]);
    }

    private function stock(Item $i): float
    {
        return (float) ItemStock::where('item_id', $i->id)->where('branch_id', $this->branch->id)->value('quantity');
    }

    // ------------------------------------------------------------ explicit selection

    public function test_selecting_a_bill_lists_its_items_but_returns_nothing_until_items_are_explicitly_posted(): void
    {
        $r = $this->actingAs($this->user)->getJson("/sales/sales-returns/bill-items/{$this->billX->id}")->assertOk();
        $this->assertCount(2, $r->json('items'), 'the picker offers every bill item...');
        $this->assertSame(0, SalesReturn::count(), '...but nothing is returned or added by merely selecting the bill');
    }

    public function test_only_the_explicitly_selected_item_becomes_a_return_item(): void
    {
        $this->submit([[$this->q, 2]], $this->billX)->assertRedirect();

        $ret = SalesReturn::with('items')->firstOrFail();
        $this->assertCount(1, $ret->items, 'bill has P and Q, only Q was selected');
        $this->assertSame($this->q->id, $ret->items->first()->item_id);
        $this->assertEquals(102.0, $this->stock($this->q));
        $this->assertEquals(100.0, $this->stock($this->p), 'unselected item stock untouched');
    }

    // ------------------------------------------------------------ remaining = sold - returned

    public function test_remaining_returnable_qty_is_sold_minus_previously_returned_and_is_enforced(): void
    {
        $this->submit([[$this->p, 2]], $this->billX)->assertRedirect();      // P on X: 5 sold, 2 returned

        $items = collect($this->actingAs($this->user)->getJson("/sales/sales-returns/bill-items/{$this->billX->id}")->json('items'))->keyBy('item_id');
        $this->assertEquals(5, $items[$this->p->id]['original_qty']);
        $this->assertEquals(2, $items[$this->p->id]['already_returned_qty']);
        $this->assertEquals(3, $items[$this->p->id]['remaining_qty']);
        $this->assertEquals(3, $items[$this->q->id]['remaining_qty'], 'untouched item keeps its full quantity');

        $this->submit([[$this->p, 4]], $this->billX)->assertStatus(422)->assertJsonValidationErrors('items');   // 4 > 3
        $this->submit([[$this->p, 3]], $this->billX)->assertRedirect();                                           // exactly 3
        $this->submit([[$this->p, 1]], $this->billX)->assertStatus(422);                                          // fully returned

        $this->assertEquals(105.0, $this->stock($this->p), 'stock rises only by the accepted 2 + 3');
    }

    public function test_split_lines_of_the_same_item_are_summed_against_the_remainder(): void
    {
        $this->submit([[$this->p, 3], [$this->p, 3]], $this->billX)->assertStatus(422)->assertJsonValidationErrors('items'); // 6 > 5
        $this->assertSame(0, SalesReturn::count());
    }

    // ------------------------------------------------------------ NO original bill

    public function test_return_without_a_bill_is_capped_by_what_the_customer_bought(): void
    {
        $this->submit([[$this->p, 8]], null)->assertStatus(422)->assertJsonValidationErrors('items');     // bought 7 in total
        $this->assertStringContainsString('No original bill selected', $this->submit([[$this->p, 8]], null)->json('errors.items.0'));
        $this->assertSame(0, SalesReturn::count());
        $this->assertEquals(100.0, $this->stock($this->p));

        $this->submit([[$this->p, 7]], null)->assertRedirect();                                            // all 7 across bills X and Y
        $this->assertEquals(107.0, $this->stock($this->p));
        $this->submit([[$this->p, 1]], null)->assertStatus(422);                                           // pool exhausted
    }

    public function test_no_bill_return_of_an_item_the_customer_never_bought_is_rejected(): void
    {
        $never = Item::create(['name' => 'Never bought', 'item_code' => 'RULE-N', 'sell_price' => 10, 'status' => true]);

        $this->submit([[$never, 1]], null)->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_another_customers_purchases_do_not_enlarge_the_pool(): void
    {
        $this->bill($this->other, [[$this->p, 50]]);

        $this->submit([[$this->p, 8]], null)->assertStatus(422);          // cust still bought only 7
        $this->submit([[$this->p, 60]], null, $this->other)->assertStatus(422);
        $this->submit([[$this->p, 50]], null, $this->other)->assertRedirect();
    }

    public function test_cancelled_bills_do_not_count_towards_the_pool(): void
    {
        $this->bill($this->cust, [[$this->p, 100]], 'Cancelled');

        $this->submit([[$this->p, 8]], null)->assertStatus(422);
        $this->submit([[$this->p, 7]], null)->assertRedirect();
    }

    // ------------------------------------------------------------ billed + unbilled share ONE pool

    public function test_billed_returns_reduce_what_can_be_returned_without_a_bill(): void
    {
        $this->submit([[$this->p, 5]], $this->billX)->assertRedirect();   // pool P: 7 - 5 = 2

        $this->submit([[$this->p, 3]], null)->assertStatus(422);
        $this->submit([[$this->p, 2]], null)->assertRedirect();
        $this->assertEquals(107.0, $this->stock($this->p));
    }

    public function test_unbilled_returns_reduce_what_a_later_billed_return_can_take(): void
    {
        $this->submit([[$this->p, 7]], null)->assertRedirect();           // whole pool taken without a bill

        $r = $this->submit([[$this->p, 1]], $this->billX)->assertStatus(422)->assertJsonValidationErrors('items');
        $this->assertStringContainsString("this customer's bills and earlier returns", $r->json('errors.items.0'));
        $this->assertEquals(107.0, $this->stock($this->p));
    }

    // ------------------------------------------------------------ edit

    public function test_editing_an_unbilled_return_excludes_its_own_quantity_from_the_pool(): void
    {
        $this->submit([[$this->p, 4]], null)->assertRedirect();
        $ret = SalesReturn::firstOrFail();

        $payload = fn (float $qty) => [
            'return_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'sales_bill_id' => null,
            'return_mode' => 'Cash', 'sales_type' => 'Local',
            'items' => [['item_id' => $this->p->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 120, 'gst_percent' => 18]],
        ];

        $this->actingAs($this->user)->putJson(route('sales.sales-returns.update', $ret), $payload(8))->assertStatus(422);   // 8 > 7
        $this->actingAs($this->user)->putJson(route('sales.sales-returns.update', $ret), $payload(7))->assertRedirect();     // 4 -> 7 is fine
        $this->assertEquals(107.0, $this->stock($this->p), 'edit re-posts: net +7, not +11');
    }

    public function test_cancelling_a_return_gives_its_quantity_back_to_the_pool(): void
    {
        $this->submit([[$this->p, 7]], null)->assertRedirect();
        $this->submit([[$this->p, 1]], null)->assertStatus(422);

        $this->actingAs($this->user)->delete(route('sales.sales-returns.destroy', SalesReturn::firstOrFail()))->assertRedirect();

        $this->assertEquals(100.0, $this->stock($this->p));
        $this->submit([[$this->p, 7]], null)->assertRedirect();
    }
}
