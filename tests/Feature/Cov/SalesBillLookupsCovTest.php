<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesBillLookupsCovTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Branch $branch2;
    private Customer $cust;
    private Item $a;
    private Item $b;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'state' => 'Gujarat']);
        $this->branch2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);
        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');
        $this->cust = Customer::create(['name' => 'Fav Customer', 'mobile' => '9555000001', 'state' => 'Gujarat', 'status' => true]);

        $mk = fn ($n, $c) => Item::create(['name' => $n, 'item_code' => $c, 'sell_price' => 100, 'mrp' => 100, 'cost_price' => 60,
            'gst_tax_id' => $gst->id, 'status' => true]);
        $this->a = $mk('Fav A', 'FAV-A');
        $this->b = $mk('Fav B', 'FAV-B');
    }

    private function bill(string $no, array $lines, string $status = 'Posted'): SalesBill
    {
        $bill = SalesBill::create([
            'bill_number' => $no, 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'total' => 0, 'status' => $status,
        ]);
        foreach ($lines as [$item, $qty]) {
            $bill->items()->create(['item_id' => $item->id, 'qty' => $qty, 'sell_price' => 100, 'net_amount' => $qty * 100]);
        }

        return $bill;
    }

    public function test_customer_favorites_regression_returns_json_ranked_by_bill_count_ignores_cancelled_and_reports_stock(): void
    {
        $this->bill('F-1', [[$this->a, 2], [$this->b, 10]]);
        $this->bill('F-2', [[$this->a, 1]]);
        $this->bill('F-3', [[$this->b, 500]], 'Cancelled');
        ItemStock::create(['item_id' => $this->a->id, 'branch_id' => $this->branch->id, 'quantity' => 7]);
        // b has no stock in the requested branch but 4 elsewhere -> falls back to total across branches
        ItemStock::create(['item_id' => $this->b->id, 'branch_id' => $this->branch2->id, 'quantity' => 4]);

        $res = $this->actingAs($this->owner)->getJson('/pos/customer-favorites/' . $this->cust->id . '?branch_id=' . $this->branch->id)->assertOk();
        $items = collect($res->json('items'));

        $this->assertTrue($res->json('success'));
        $this->assertSame([$this->a->id, $this->b->id], $items->pluck('id')->all(), 'A appears on 2 bills, B on 1');
        $a = $items->firstWhere('id', $this->a->id);
        $b = $items->firstWhere('id', $this->b->id);
        $this->assertEquals(3, $a['total_qty']);
        $this->assertEquals(2, $a['bills_count']);
        $this->assertEquals(7, $a['stock']);
        $this->assertEquals(10, $b['total_qty'], 'cancelled bill quantity must be excluded');
        $this->assertEquals(4, $b['stock']);
        $this->assertEquals(18, $a['gst_percentage']);
    }

    public function test_customer_favorites_empty_for_customer_without_purchases(): void
    {
        $other = Customer::create(['name' => 'Nobody', 'mobile' => '9555000002', 'state' => 'Gujarat', 'status' => true]);
        $this->actingAs($this->owner)->getJson('/pos/customer-favorites/' . $other->id)->assertOk()->assertJson(['success' => true, 'items' => []]);
    }

    public function test_customer_invoices_lists_bills_newest_first_with_urls(): void
    {
        $b1 = $this->bill('I-1', [[$this->a, 1], [$this->b, 1]]);
        $b2 = $this->bill('I-2', [[$this->a, 1]]);

        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-invoices', $this->cust->id))->assertOk();
        $this->assertSame($this->cust->id, $res->json('customer_id'));
        $this->assertSame(2, $res->json('total_invoices'));
        $this->assertSame(['I-2', 'I-1'], collect($res->json('invoices'))->pluck('bill_number')->all());
        $this->assertSame(2, $res->json('invoices.1.items'));
        $this->assertSame(route('sales.sales-bills.edit', $b1), $res->json('invoices.1.edit_url'));

        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-invoices', 99999))->assertNotFound();
    }

    public function test_pos_terminal_renders_and_loads_bill_for_edit(): void
    {
        $bill = $this->bill('P-1', [[$this->a, 1]]);
        $this->actingAs($this->owner)->get(route('pos.terminal'))->assertOk();
        $res = $this->actingAs($this->owner)->get(route('pos.terminal', ['edit_id' => $bill->id]))->assertOk();
        $this->assertSame($bill->id, $res->viewData('editBill')->id);
        $this->assertSame($this->cust->id, $res->viewData('defaultCustomerId'));

        $this->actingAs($this->owner)->get(route('pos.terminal', ['edit_id' => 99999]))->assertNotFound();
    }

    public function test_pos_terminal_requires_login(): void
    {
        $this->get(route('pos.terminal'))->assertRedirect(route('login'));
    }
}
