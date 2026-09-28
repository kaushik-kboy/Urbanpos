<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\User;
use Tests\TestCase;

class FinSalesDocsMoreTest extends TestCase
{
    use FinHelper;

    private function sell(Customer $c, float $qty = 5, ?Item $item = null): SalesBill
    {
        $item ??= $this->item;
        $this->seedStock($item, $this->stockOf($item) + $qty);
        $this->post(route('sales.sales-bills.store'), [
            'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $c->id, 'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ])->assertSessionHasNoErrors();

        return SalesBill::latest('id')->firstOrFail();
    }

    private function retPayload(SalesBill $bill, float $qty, array $over = [], ?Item $item = null): array
    {
        return array_replace_recursive([
            'return_date' => now()->format('Y-m-d'), 'customer_id' => $bill->customer_id, 'branch_id' => $this->branch->id,
            'sales_bill_id' => $bill->id, 'return_mode' => 'Credit Note', 'sales_type' => 'Local',
            'items' => [['item_id' => ($item ?? $this->item)->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ], $over);
    }

    // ---- Sales returns -----------------------------------------------------

    public function test_returns_index_filters_by_search_customer_mode_and_dates(): void
    {
        $other = Customer::create(['name' => 'Other Buyer', 'mobile' => '9888000002', 'phone' => '0790011223', 'customer_code' => 'OB-1', 'state' => 'Gujarat', 'status' => true]);
        $b1 = $this->sell($this->cust);
        $b2 = $this->sell($other);
        $this->post(route('sales.sales-returns.store'), $this->retPayload($b1, 1, ['return_date' => '2026-03-05']))->assertSessionHasNoErrors();
        $this->post(route('sales.sales-returns.store'), $this->retPayload($b2, 1, ['return_mode' => 'Cash', 'return_date' => '2026-04-05']))->assertSessionHasNoErrors();
        $r1 = SalesReturn::where('customer_id', $this->cust->id)->firstOrFail();
        $r2 = SalesReturn::where('customer_id', $other->id)->firstOrFail();

        $ids = fn (array $q) => $this->get(route('sales.sales-returns.index', $q))->assertOk()->viewData('salesReturns')->pluck('id')->sort()->values()->all();
        $this->assertSame([$r1->id, $r2->id], $ids([]));
        $this->assertSame([$r2->id], $ids(['customer_id' => $other->id]));
        $this->assertSame([$r2->id], $ids(['return_mode' => 'Cash']));
        $this->assertSame([$r1->id], $ids(['search' => $r1->return_number]));
        $this->assertSame([$r1->id], $ids(['search' => $b1->bill_number]));
        $this->assertSame([$r2->id], $ids(['search' => 'Other Buyer']));
        $this->assertSame([$r2->id], $ids(['search' => '0790011']));
        $this->assertSame([$r2->id], $ids(['search' => 'OB-1']));
        $this->assertSame([$r2->id], $ids(['date_from' => '2026-04-01']));
        $this->assertSame([$r1->id], $ids(['date_to' => '2026-03-31']));
        $this->assertSame([], $ids(['branch_id' => 999999]));
        $this->assertSame([$r1->id, $r2->id], $ids(['branch_id' => 'all']));
    }

    public function test_return_replay_with_same_posting_key_returns_first_document_and_restocks_once(): void
    {
        $bill = $this->sell($this->cust);
        $before = $this->stockOf($this->item);
        $p = $this->retPayload($bill, 2, ['posting_key' => 'RET-KEY-1']);

        $this->post(route('sales.sales-returns.store'), $p)->assertSessionHasNoErrors();
        $first = SalesReturn::firstOrFail();
        $this->assertSame($before + 2, $this->stockOf($this->item));

        $this->post(route('sales.sales-returns.store'), $p)->assertSessionHasNoErrors();
        $this->assertSame(1, SalesReturn::count());
        $this->assertSame($first->id, SalesReturn::value('id'));
        $this->assertSame($before + 2, $this->stockOf($this->item), 'replay must not restock again');
    }

    public function test_return_rejects_items_the_customer_never_bought_and_items_not_on_the_bill(): void
    {
        $bill = $this->sell($this->cust);
        $other = Item::create(['name' => 'Never Bought', 'item_code' => 'NB-1', 'sell_price' => 50, 'mrp' => 50, 'cost_price' => 20, 'gst_tax_id' => $this->gst->id, 'status' => true]);

        $this->post(route('sales.sales-returns.store'), $this->retPayload($bill, 1, [], $other))
            ->assertSessionHasErrors('items');
        // no bill selected: limited to the customer's purchase history
        $noBill = $this->retPayload($bill, 1, [], $other);
        $noBill['sales_bill_id'] = null;
        $this->post(route('sales.sales-returns.store'), $noBill)
            ->assertSessionHasErrors(['items' => "Return quantity for 'Never Bought' cannot exceed the remaining returnable quantity of 0 (No original bill selected: limited to what this customer bought minus what was already returned)."]);
        $this->assertSame(0, SalesReturn::count());
    }

    public function test_return_without_bill_cannot_exceed_what_the_customer_bought(): void
    {
        $bill = $this->sell($this->cust, 3);
        $p = $this->retPayload($bill, 4);
        $p['sales_bill_id'] = null;
        $this->post(route('sales.sales-returns.store'), $p)->assertSessionHasErrors('items');
        $this->assertSame(0, SalesReturn::count());

        $p = $this->retPayload($bill, 3);
        $p['sales_bill_id'] = null;
        $this->post(route('sales.sales-returns.store'), $p)->assertSessionHasNoErrors();
        $this->assertSame(1, SalesReturn::count());

        // pool exhausted: any further no-bill return is refused
        $p['items'][0]['qty'] = 1;
        $this->post(route('sales.sales-returns.store'), $p)->assertSessionHasErrors('items');
        $this->assertSame(1, SalesReturn::count());
    }

    public function test_return_create_injects_preset_customer_and_bill_into_dropdowns(): void
    {
        $inactive = Customer::create(['name' => 'Dormant', 'mobile' => '9555000001', 'state' => 'Gujarat', 'status' => false]);
        $cancelled = SalesBill::create(['bill_number' => 'CXL-1', 'bill_date' => now(), 'customer_id' => $inactive->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 10, 'status' => 'Cancelled']);
        $live = SalesBill::create(['bill_number' => 'LIVE-1', 'bill_date' => now(), 'customer_id' => $inactive->id, 'branch_id' => $this->branch->id, 'payment_type' => 'Cash', 'total' => 10, 'status' => 'Posted']);

        $r = $this->get(route('sales.sales-returns.create', ['customer_id' => $inactive->id, 'sales_bill_id' => $cancelled->id]))->assertOk();
        $this->assertSame('Dormant (9555000001)', $r->viewData('customers')[$inactive->id]);
        $bills = $r->viewData('salesBills');
        $this->assertSame('LIVE-1', $bills[$live->id]);
        $this->assertSame('CXL-1', $bills[$cancelled->id], 'a preset bill is offered even when cancelled');
        $this->assertSame([], $this->get(route('sales.sales-returns.create'))->viewData('salesBills'));
    }

    // ---- Sales orders / delivery notes -------------------------------------

    private function order(string $no, Customer $c, string $date, string $status = 'Open', ?Branch $branch = null): SalesOrder
    {
        return SalesOrder::create([
            'order_number' => $no, 'order_date' => $date, 'customer_id' => $c->id, 'branch_id' => ($branch ?? $this->branch)->id, 'status' => $status,
        ]);
    }

    public function test_orders_index_filters(): void
    {
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $anil = Customer::create(['name' => 'Anil Orders', 'mobile' => '9111000001', 'phone' => '0111222333', 'state' => 'Gujarat', 'status' => true]);
        $this->order('SO-A', $this->cust, '2026-01-10');
        $this->order('SO-B', $anil, '2026-02-10', 'Cancelled');
        $this->order('SO-C', $this->cust, '2026-03-10', 'Open', $b2);

        $nums = fn (array $q) => $this->get(route('sales.sales-orders.index', $q))->assertOk()->viewData('orders')->pluck('order_number')->sort()->values()->all();
        $this->assertSame(['SO-A', 'SO-B'], $nums([]));
        $this->assertSame(['SO-A', 'SO-B', 'SO-C'], $nums(['branch_id' => 'all']));
        $this->assertSame(['SO-C'], $nums(['branch_id' => $b2->id]));
        $this->assertSame(['SO-B'], $nums(['search' => 'Anil']));
        $this->assertSame(['SO-B'], $nums(['search' => '0111222']));
        $this->assertSame(['SO-A'], $nums(['search' => 'SO-A']));
        $this->assertSame(['SO-B'], $nums(['date_from' => '2026-02-01']));
        $this->assertSame(['SO-A'], $nums(['date_to' => '2026-01-31']));
        $this->assertSame(['SO-B'], $nums(['customer_id' => $anil->id]));
        $this->assertSame(['SO-B'], $nums(['status' => 'Cancelled']));
    }

    public function test_order_create_from_quotation_carries_items_and_customer(): void
    {
        $this->seedStock($this->item, 10);
        $this->post(route('sales.sales-quotations.store'), [
            'quotation_date' => now()->toDateString(), 'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => 3, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ])->assertSessionHasNoErrors();
        $q = SalesQuotation::firstOrFail();

        $r = $this->get(route('sales.sales-orders.create', ['from_quotation' => $q->id]))->assertOk();
        $this->assertSame($q->id, $r->viewData('sourceQuotation')->id);
        $this->assertSame([$this->item->id], $r->viewData('items')->pluck('id')->all());
        $this->assertSame('Fin Customer (9777000001)', $r->viewData('customers')[$this->cust->id]);
        $this->get(route('sales.sales-orders.create', ['from_quotation' => 999999]))->assertNotFound();
    }

    public function test_order_store_accepts_expected_delivery_date_and_stores_it_normalised(): void
    {
        $this->post(route('sales.sales-orders.store'), [
            'order_date' => now()->format('Y-m-d'), 'expected_delivery_date' => now()->addDays(5)->format('Y-m-d'),
            'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'advance_amount' => 50,
            'items' => [['item_id' => $this->item->id, 'qty' => 1, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ])->assertSessionHasNoErrors();
        $o = SalesOrder::firstOrFail();
        $this->assertSame(now()->addDays(5)->toDateString(), $o->expected_delivery_date->toDateString());
        $this->assertEquals(50.0, (float) $o->advance_amount);
        $this->assertSame('Open', $o->status);
    }

    private function dn(string $no, Customer $c, string $date, array $over = []): SalesDeliveryNote
    {
        return SalesDeliveryNote::create(array_merge([
            'delivery_number' => $no, 'delivery_date' => $date, 'customer_id' => $c->id, 'branch_id' => $this->branch->id, 'status' => 'Dispatched',
        ], $over));
    }

    public function test_delivery_notes_index_filters_and_branch_scoping_for_non_owners(): void
    {
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $anil = Customer::create(['name' => 'Anil DN', 'mobile' => '9111000002', 'phone' => '0999888777', 'state' => 'Gujarat', 'status' => true]);
        $order = $this->order('SO-DN1', $this->cust, '2026-01-01');
        $this->dn('DN-A', $this->cust, '2026-01-10', ['reference_no' => 'REF-XYZ', 'sales_order_id' => $order->id]);
        $this->dn('DN-B', $anil, '2026-02-10', ['transporter_name' => 'Fast Cargo', 'vehicle_no' => 'GJ01AB1234', 'lr_no' => 'LR-55', 'status' => 'Cancelled']);
        $this->dn('DN-C', $this->cust, '2026-03-10', ['branch_id' => $b2->id]);

        $nums = fn (array $q) => $this->get(route('sales.delivery-notes.index', $q))->assertOk()->viewData('deliveryNotes')->pluck('delivery_number')->sort()->values()->all();
        $this->assertSame(['DN-A', 'DN-B'], $nums([]));
        $this->assertSame(['DN-A', 'DN-B', 'DN-C'], $nums(['branch_id' => 'all']));
        $this->assertSame(['DN-A'], $nums(['search' => 'REF-XYZ']));
        $this->assertSame(['DN-B'], $nums(['search' => 'Fast Cargo']));
        $this->assertSame(['DN-B'], $nums(['search' => 'GJ01AB']));
        $this->assertSame(['DN-B'], $nums(['search' => 'LR-55']));
        $this->assertSame(['DN-B'], $nums(['search' => 'Anil DN']));
        $this->assertSame(['DN-B'], $nums(['search' => '0999888']));
        $this->assertSame(['DN-A'], $nums(['search' => 'SO-DN1']));
        $this->assertSame(['DN-B'], $nums(['date_from' => '2026-02-01']));
        $this->assertSame(['DN-A'], $nums(['date_to' => '2026-01-31']));
        $this->assertSame(['DN-B'], $nums(['customer_id' => $anil->id]));
        $this->assertSame(['DN-B'], $nums(['status' => 'Cancelled']));

        // a branch-bound non-owner only ever sees their own branch, whatever filter they send
        $clerk = User::factory()->create(['branch_id' => $b2->id]);
        $clerk->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'sales-delivery-notes.view', 'guard_name' => 'web']));
        $this->actingAs($clerk);
        $seen = $this->get(route('sales.delivery-notes.index', ['branch_id' => $this->branch->id]))->assertOk()->viewData('deliveryNotes')->pluck('delivery_number')->all();
        $this->assertSame(['DN-C'], $seen);
        $create = $this->get(route('sales.delivery-notes.create'))->assertOk();
        $this->assertSame([$b2->id], array_keys($create->viewData('branches')->all()));
    }

    public function test_delivery_note_has_no_dead_edit_route_and_order_edit_injects_late_customer(): void
    {
        for ($i = 0; $i < 22; $i++) {
            Customer::create(['name' => sprintf('AAA %02d', $i), 'mobile' => '94000000'.sprintf('%02d', $i), 'state' => 'Gujarat', 'status' => true]);
        }
        $late = Customer::create(['name' => 'ZZZ Late', 'mobile' => '9666666666', 'state' => 'Gujarat', 'status' => true]);
        $n = $this->dn('DN-LATE', $late, now()->toDateString());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('sales.delivery-notes.edit'), 'delivery notes have no edit action; the dead route used to 500');
        $this->get('/sales/delivery-notes/'.$n->id.'/edit')->assertNotFound();
        $this->get(route('sales.delivery-notes.show', $n))->assertOk();

        $o = $this->order('SO-LATE', $late, now()->toDateString());
        $this->assertSame('ZZZ Late (9666666666)', $this->get(route('sales.sales-orders.edit', $o))->assertOk()->viewData('customers')[$late->id]);
    }
}
