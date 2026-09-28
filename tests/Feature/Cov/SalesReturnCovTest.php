<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReturnCovTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $cust;
    private Item $item;
    private Item $other;
    private SalesBill $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        User::factory()->create(); // burn id 1
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main', 'code' => 'MAIN', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');

        $this->cust = Customer::create(['name' => 'Ret Customer', 'mobile' => '9777000001', 'state' => 'Gujarat', 'status' => true]);

        $mk = fn ($n, $c) => Item::create([
            'name' => $n, 'item_code' => $c, 'sell_price' => 100, 'mrp' => 100, 'cost_price' => 60,
            'gst_tax_id' => $gst->id, 'tax_inclusive' => true, 'status' => true, 'allow_negative_stock' => false,
        ]);
        $this->item = $mk('Ret Widget', 'RET-1');
        $this->other = $mk('Ret Other', 'RET-2');
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 10]);
        ItemStock::create(['item_id' => $this->other->id, 'branch_id' => $this->branch->id, 'quantity' => 10]);

        // bill: 5 x item, sold via the real flow (stock 10 -> 5)
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => 5, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ])->assertSessionHasNoErrors();
        $this->bill = SalesBill::firstOrFail();
    }

    private function stock(?Item $i = null): float
    {
        return (float) ItemStock::where('item_id', ($i ?? $this->item)->id)->where('branch_id', $this->branch->id)->value('quantity');
    }

    private function payload(float $qty, array $over = [], ?Item $item = null): array
    {
        return array_replace_recursive([
            'return_date' => now()->format('Y-m-d'),
            'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id,
            'sales_bill_id' => $this->bill->id,
            'return_mode' => 'Credit Note',
            'sales_type' => 'Local',
            'items' => [['item_id' => ($item ?? $this->item)->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ], $over);
    }

    private function makeReturn(float $qty = 2, array $over = []): SalesReturn
    {
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload($qty, $over))->assertSessionHasNoErrors();

        return SalesReturn::latest('id')->firstOrFail();
    }

    public function test_store_restores_stock_at_original_cost_and_posts_journal(): void
    {
        $costAtSale = (float) $this->bill->items()->first()->cost_at_sale;
        $r = $this->makeReturn(2);

        $this->assertSame(7.0, $this->stock());
        $this->assertEquals(200.0, (float) $r->total);
        $row = StockLedger::where('reference_type', SalesReturn::class)->where('reference_id', $r->id)->first();
        $this->assertSame('SALE_RETURN', $row->movement_type);
        $this->assertEquals($costAtSale, (float) $row->unit_cost);
        $this->assertTrue(JournalEntry::where('reference_type', SalesReturn::class)->where('reference_id', $r->id)->exists());
    }

    public function test_cannot_return_more_than_remaining_across_multiple_returns(): void
    {
        $this->makeReturn(3);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(3))->assertSessionHasErrors('items');
        $this->assertSame(1, SalesReturn::count());
        $this->assertSame(8.0, $this->stock());

        $this->makeReturn(2);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(1))->assertSessionHasErrors('items');
        $this->assertSame(2, SalesReturn::count());
    }

    public function test_return_rejected_for_wrong_customer_cancelled_bill_and_foreign_item(): void
    {
        $other = Customer::create(['name' => 'Other Cust', 'mobile' => '9777000002', 'state' => 'Gujarat', 'status' => true]);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(1, ['customer_id' => $other->id]))
            ->assertSessionHasErrors('sales_bill_id');

        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(1, [], $this->other))
            ->assertSessionHasErrors('items');

        $this->bill->update(['status' => 'Cancelled']);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(1))->assertSessionHasErrors('sales_bill_id');

        $this->assertSame(0, SalesReturn::count());
        $this->assertSame(5.0, $this->stock());
    }

    public function test_unlinked_return_needs_customer_to_have_bought_the_item_and_uses_current_cost(): void
    {
        $unlinked = $this->payload(1);
        unset($unlinked['sales_bill_id']);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $unlinked)->assertSessionHasNoErrors();
        $r = SalesReturn::firstOrFail();
        $this->assertNull($r->sales_bill_id);
        $this->assertSame(6.0, $this->stock());

        $bad = $this->payload(1, [], $this->other);
        unset($bad['sales_bill_id']);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $bad)->assertSessionHasErrors('items');
        $this->assertSame(10.0, $this->stock($this->other));
    }

    public function test_posting_key_makes_store_idempotent(): void
    {
        $p = $this->payload(1, ['posting_key' => 'ret-key']);
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $p)->assertRedirect(route('sales.sales-returns.index'));
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $p)->assertRedirect(route('sales.sales-returns.index'));
        $this->assertSame(1, SalesReturn::where('posting_key', 'ret-key')->count());
        $this->assertSame(6.0, $this->stock());
    }

    public function test_invalid_return_mode_and_empty_items_fail_validation(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $this->payload(1, ['return_mode' => 'Barter']))->assertSessionHasErrors('return_mode');
        $p = $this->payload(1);
        $p['items'] = [];
        $this->actingAs($this->owner)->post(route('sales.sales-returns.store'), $p)->assertSessionHasErrors('items');
        $this->assertSame(0, SalesReturn::count());
    }

    public function test_update_reverses_and_reposts_stock_and_respects_remaining_qty(): void
    {
        $r = $this->makeReturn(2); // stock 7
        $this->actingAs($this->owner)->put(route('sales.sales-returns.update', $r), $this->payload(4))
            ->assertRedirect(route('sales.sales-returns.index'));

        $this->assertSame(9.0, $this->stock());
        $r->refresh();
        $this->assertEquals(400.0, (float) $r->total);
        $this->assertSame(1, $r->items()->count());
        $this->assertTrue(StockLedger::where('reference_type', SalesReturn::class)->where('reference_id', $r->id)->whereNotNull('reversal_of')->exists());

        // own qty is excluded from "already returned" so editing to full 5 is fine; 6 is not
        $this->actingAs($this->owner)->put(route('sales.sales-returns.update', $r), $this->payload(6))->assertSessionHasErrors('items');
        $this->assertSame(9.0, $this->stock());
        $this->actingAs($this->owner)->put(route('sales.sales-returns.update', $r), $this->payload(5))->assertSessionHasNoErrors();
        $this->assertSame(10.0, $this->stock());
    }

    public function test_update_cancelled_return_is_rejected(): void
    {
        $r = $this->makeReturn(1);
        $r->update(['status' => 'Cancelled']);
        $this->actingAs($this->owner)->put(route('sales.sales-returns.update', $r), $this->payload(1))->assertSessionHasErrors('status');
    }

    public function test_destroy_reverses_stock_and_journal_and_frees_returnable_qty(): void
    {
        $r = $this->makeReturn(5);
        $this->assertSame(10.0, $this->stock());

        $this->actingAs($this->owner)->delete(route('sales.sales-returns.destroy', $r))->assertRedirect(route('sales.sales-returns.index'));

        $this->assertSame(5.0, $this->stock());
        $this->assertNull(SalesReturn::find($r->id));
        $orig = JournalEntry::where('reference_type', SalesReturn::class)->where('reference_id', $r->id)->whereNull('reversal_of')->first();
        $this->assertNotNull(JournalEntry::where('reversal_of', $orig->id)->first());

        // full qty returnable again after cancellation
        $this->makeReturn(5);
        $this->assertSame(10.0, $this->stock());
    }

    public function test_destroy_and_update_require_permissions(): void
    {
        $r = $this->makeReturn(1);
        $role = Role::firstOrCreate(['name' => 'ReturnViewer', 'guard_name' => 'web']);
        $u = User::factory()->create(['branch_id' => $this->branch->id]);
        $u->assignRole($role);

        $this->actingAs($u)->delete(route('sales.sales-returns.destroy', $r))->assertForbidden();
        $this->actingAs($u)->put(route('sales.sales-returns.update', $r), $this->payload(1))->assertForbidden();
        $this->actingAs($u)->post(route('sales.sales-returns.store'), $this->payload(1))->assertForbidden();
        $this->assertNotNull(SalesReturn::find($r->id));
        $this->assertSame(6.0, $this->stock());
    }

    public function test_index_filters(): void
    {
        $r = $this->makeReturn(1);
        $base = ['branch_id' => 'all'];
        $hit = fn (array $q) => $this->actingAs($this->owner)->get(route('sales.sales-returns.index', $q + $base));

        $hit(['search' => $r->return_number])->assertOk()->assertSee($r->return_number);
        $hit(['search' => $this->bill->bill_number])->assertOk()->assertSee($r->return_number);
        $hit(['search' => 'Ret Customer'])->assertOk()->assertSee($r->return_number);
        $hit(['search' => 'zzz-no-match'])->assertOk()->assertDontSee($r->return_number);
        $hit(['return_mode' => 'Credit Note'])->assertOk()->assertSee($r->return_number);
        $hit(['return_mode' => 'Cash'])->assertOk()->assertDontSee($r->return_number);
        $hit(['customer_id' => $this->cust->id, 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()])->assertOk()->assertSee($r->return_number);
        $hit(['date_from' => now()->addDays(2)->toDateString()])->assertOk()->assertDontSee($r->return_number);
    }

    public function test_show_print_create_edit_render(): void
    {
        $r = $this->makeReturn(1);
        $this->actingAs($this->owner)->get(route('sales.sales-returns.show', $r))->assertOk()->assertSee($r->return_number);
        $this->actingAs($this->owner)->get(route('sales.sales-returns.print', $r))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-returns.edit', $r))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-returns.create'))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-returns.create', ['customer_id' => $this->cust->id, 'sales_bill_id' => $this->bill->id]))
            ->assertOk()->assertSee($this->bill->bill_number);
    }

    public function test_bill_items_endpoint_reports_remaining_and_ignore_return(): void
    {
        $r = $this->makeReturn(2);
        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.bill-items', $this->bill))->assertOk();
        $this->assertEquals(5, $res->json('items.0.original_qty'));
        $this->assertEquals(2, $res->json('items.0.already_returned_qty'));
        $this->assertEquals(3, $res->json('items.0.remaining_qty'));
        $this->assertSame($this->cust->id, $res->json('customer_id'));

        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.bill-items', [$this->bill, 'ignore_return_id' => $r->id]))->assertOk();
        $this->assertEquals(5, $res->json('items.0.remaining_qty'));
    }

    public function test_bill_items_prorates_discount_for_remaining_qty(): void
    {
        $line = $this->bill->items()->first();
        $line->update(['disc_percent' => 0, 'disc_amount' => 50]);
        $this->makeReturn(1);
        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.bill-items', $this->bill))->assertOk();
        // 50 discount over 5 units => 10/unit, 4 remaining => 40
        $this->assertEquals(40, $res->json('items.0.disc_amount'));

        $line->update(['disc_percent' => 10]);
        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.bill-items', $this->bill))->assertOk();
        $this->assertEquals(40, $res->json('items.0.disc_amount')); // 4*100*10%
    }

    public function test_customer_bills_excludes_cancelled(): void
    {
        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-returns.customer-bills', $this->cust))->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame($this->bill->bill_number, $res->json('0.bill_number'));

        $this->bill->update(['status' => 'Cancelled']);
        $this->assertCount(0, $this->actingAs($this->owner)->getJson(route('sales.sales-returns.customer-bills', $this->cust))->json());
    }

    public function test_item_sold_qty_endpoint(): void
    {
        $this->makeReturn(2);
        $this->actingAs($this->owner)->getJson(route('sales.sales-returns.item-sold-qty'))
            ->assertJson(['total_sold' => null, 'already_returned' => 0, 'available' => null]);

        $this->actingAs($this->owner)->getJson(route('sales.sales-returns.item-sold-qty', ['item_id' => $this->item->id, 'customer_id' => $this->cust->id]))
            ->assertJson(['total_sold' => 5, 'already_returned' => 2, 'available' => 3]);

        $r = SalesReturn::first();
        $this->actingAs($this->owner)->getJson(route('sales.sales-returns.item-sold-qty', ['item_id' => $this->item->id, 'ignore_return_id' => $r->id]))
            ->assertJson(['total_sold' => 5, 'already_returned' => 0, 'available' => 5]);

        $this->bill->update(['status' => 'Cancelled']);
        $this->actingAs($this->owner)->getJson(route('sales.sales-returns.item-sold-qty', ['item_id' => $this->item->id]))
            ->assertJson(['total_sold' => 0]);
    }
}
