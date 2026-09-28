<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesDocsCovTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $cust;
    private Item $item;

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
        $this->cust = Customer::create(['name' => 'Doc Customer', 'mobile' => '9666000001', 'state' => 'Gujarat', 'status' => true]);
        $this->item = Item::create([
            'name' => 'Doc Widget', 'item_code' => 'DOC-1', 'sell_price' => 100, 'mrp' => 100, 'cost_price' => 60,
            'gst_tax_id' => $gst->id, 'tax_inclusive' => true, 'status' => true, 'allow_negative_stock' => false,
        ]);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 10]);
    }

    private function stock(): float
    {
        return (float) ItemStock::where('item_id', $this->item->id)->where('branch_id', $this->branch->id)->value('quantity');
    }

    private function docPayload(string $dateKey, float $qty = 2, array $over = []): array
    {
        return array_replace_recursive([
            $dateKey => now()->format('Y-m-d'),
            'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 100, 'gst_percent' => 18]],
        ], $over);
    }

    // ---- Quotations -------------------------------------------------------

    public function test_quotation_lifecycle_create_edit_cancel(): void
    {
        $r = $this->actingAs($this->owner)->post(route('sales.sales-quotations.store'), $this->docPayload('quotation_date', 3));
        $q = SalesQuotation::firstOrFail();
        $r->assertRedirect(route('sales.sales-quotations.show', $q));
        $this->assertSame('Draft', $q->status);
        $this->assertEquals(300.0, (float) $q->total);
        $this->assertSame(10.0, $this->stock(), 'a quotation never touches stock');

        $this->actingAs($this->owner)->get(route('sales.sales-quotations.show', $q))->assertOk()->assertSee($q->quotation_number);
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.edit', $q))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.create'))->assertOk();

        $this->actingAs($this->owner)->put(route('sales.sales-quotations.update', $q), $this->docPayload('quotation_date', 1, ['status' => 'Sent']))
            ->assertRedirect(route('sales.sales-quotations.show', $q));
        $q->refresh();
        $this->assertSame('Sent', $q->status);
        $this->assertEquals(100.0, (float) $q->total);
        $this->assertSame(1, $q->items()->count());

        $this->actingAs($this->owner)->delete(route('sales.sales-quotations.destroy', $q), ['reason' => 'customer declined'])
            ->assertRedirect(route('sales.sales-quotations.index'));
        $this->assertSame('Cancelled', $q->fresh()->status);

        $this->actingAs($this->owner)->put(route('sales.sales-quotations.update', $q), $this->docPayload('quotation_date'))->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.edit', $q))->assertSessionHasErrors('status');
    }

    public function test_quotation_converted_cannot_be_edited_or_cancelled_and_validation_applies(): void
    {
        $q = SalesQuotation::create([
            'quotation_number' => 'Q-X', 'quotation_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'status' => 'Converted', 'total' => 100,
        ]);
        $this->actingAs($this->owner)->delete(route('sales.sales-quotations.destroy', $q))->assertSessionHasErrors('status');
        $this->assertSame('Converted', $q->fresh()->status);
        $this->actingAs($this->owner)->put(route('sales.sales-quotations.update', $q), $this->docPayload('quotation_date'))->assertSessionHasErrors('status');

        $bad = $this->docPayload('quotation_date');
        $bad['items'] = [];
        $this->actingAs($this->owner)->post(route('sales.sales-quotations.store'), $bad)->assertSessionHasErrors('items');
        $this->actingAs($this->owner)->post(route('sales.sales-quotations.store'), $this->docPayload('quotation_date', 1, ['sales_type' => 'Bogus']))->assertSessionHasErrors('sales_type');
        $this->assertSame(1, SalesQuotation::count());
    }

    public function test_quotation_index_search(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-quotations.store'), $this->docPayload('quotation_date'));
        $q = SalesQuotation::firstOrFail();
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.index', ['search' => $q->quotation_number, 'branch_id' => 'all']))->assertOk()->assertSee($q->quotation_number);
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.index', ['search' => 'Doc Customer', 'branch_id' => 'all']))->assertOk()->assertSee($q->quotation_number);
        $this->actingAs($this->owner)->get(route('sales.sales-quotations.index', ['search' => 'nomatch-zzz', 'branch_id' => 'all']))->assertOk()->assertDontSee($q->quotation_number);
    }

    public function test_interstate_quotation_uses_igst_only(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-quotations.store'), $this->docPayload('quotation_date', 1, ['sales_type' => 'Interstate']));
        $q = SalesQuotation::firstOrFail();
        $this->assertGreaterThan(0, (float) $q->total_igst);
        $this->assertEquals(0.0, (float) $q->total_cgst);
        $this->assertEquals(0.0, (float) $q->total_sgst);
    }

    // ---- Sales orders -----------------------------------------------------

    public function test_order_lifecycle_and_quotation_acceptance(): void
    {
        $q = SalesQuotation::create([
            'quotation_number' => 'Q-ACC', 'quotation_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'status' => 'Sent', 'total' => 100,
        ]);
        $this->actingAs($this->owner)->get(route('sales.sales-orders.create', ['from_quotation' => $q->id]))->assertOk();

        $r = $this->actingAs($this->owner)->post(route('sales.sales-orders.store'), $this->docPayload('order_date', 4, ['from_quotation_id' => $q->id, 'advance_amount' => 50]));
        $o = SalesOrder::firstOrFail();
        $r->assertRedirect(route('sales.sales-orders.show', $o));
        $this->assertSame('Open', $o->status);
        $this->assertEquals(400.0, (float) $o->total);
        $this->assertEquals(50.0, (float) $o->advance_amount);
        $this->assertSame('Accepted', $q->fresh()->status);
        $this->assertSame(10.0, $this->stock());

        $this->actingAs($this->owner)->get(route('sales.sales-orders.show', $o))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-orders.edit', $o))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-orders.create'))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-orders.index', ['search' => $o->order_number, 'branch_id' => 'all']))->assertOk()->assertSee($o->order_number);
        $this->actingAs($this->owner)->get(route('sales.sales-orders.index', ['search' => 'Doc Customer', 'branch_id' => 'all']))->assertOk()->assertSee($o->order_number);

        $this->actingAs($this->owner)->put(route('sales.sales-orders.update', $o), $this->docPayload('order_date', 1))->assertRedirect(route('sales.sales-orders.show', $o));
        $this->assertEquals(100.0, (float) $o->fresh()->total);
        $this->assertSame(1, $o->items()->count());

        $this->actingAs($this->owner)->delete(route('sales.sales-orders.destroy', $o))->assertRedirect(route('sales.sales-orders.index'));
        $this->assertSame('Cancelled', $o->fresh()->status);
        $this->actingAs($this->owner)->put(route('sales.sales-orders.update', $o), $this->docPayload('order_date'))->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->get(route('sales.sales-orders.edit', $o))->assertSessionHasErrors('status');
    }

    public function test_converted_order_cannot_be_cancelled_and_order_validation(): void
    {
        $o = SalesOrder::create([
            'order_number' => 'O-X', 'order_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'status' => 'Converted', 'total' => 100,
        ]);
        $this->actingAs($this->owner)->delete(route('sales.sales-orders.destroy', $o))->assertSessionHasErrors('status');
        $this->assertSame('Converted', $o->fresh()->status);

        $this->actingAs($this->owner)->post(route('sales.sales-orders.store'), $this->docPayload('order_date', 1, ['advance_amount' => -5]))->assertSessionHasErrors('advance_amount');
        $this->actingAs($this->owner)->post(route('sales.sales-orders.store'), $this->docPayload('order_date', 1, ['status' => 'Weird']))->assertSessionHasErrors('status');
        $this->assertSame(1, SalesOrder::count());
    }

    // ---- Delivery notes ---------------------------------------------------

    private function dnPayload(float $qty, array $over = []): array
    {
        return array_replace_recursive([
            'delivery_date' => now()->format('Y-m-d'),
            'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id,
            'transporter_name' => 'Fast Cargo',
            'items' => [['item_id' => $this->item->id, 'ordered_qty' => $qty, 'dispatched_qty' => $qty, 'unit_price' => 100]],
        ], $over);
    }

    private function makeDn(float $qty = 3, array $over = []): SalesDeliveryNote
    {
        $this->actingAs($this->owner)->post(route('sales.delivery-notes.store'), $this->dnPayload($qty, $over))->assertSessionHasNoErrors();

        return SalesDeliveryNote::latest('id')->firstOrFail();
    }

    public function test_delivery_note_dispatch_deducts_stock_and_cancel_restores(): void
    {
        $dn = $this->makeDn(3);
        $this->assertSame(7.0, $this->stock());
        $this->assertSame('Dispatched', $dn->status);
        $this->assertEquals(3.0, (float) $dn->total_dispatched_qty);
        $this->assertEquals(300.0, (float) $dn->total_amount);
        $this->assertNotNull($dn->items()->first()->cost_at_dispatch);
        $this->assertDatabaseHas('stock_ledger', ['reference_type' => SalesDeliveryNote::class, 'reference_id' => $dn->id, 'movement_type' => 'SALES_DELIVERY']);

        $this->actingAs($this->owner)->get(route('sales.delivery-notes.show', $dn))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.delivery-notes.print', $dn))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.delivery-notes.index', ['search' => 'Fast Cargo', 'branch_id' => 'all']))->assertOk()->assertSee($dn->delivery_number);
        $this->actingAs($this->owner)->get(route('sales.delivery-notes.create'))->assertOk();

        $this->actingAs($this->owner)->delete(route('sales.delivery-notes.destroy', $dn))->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->delete(route('sales.delivery-notes.destroy', $dn), ['reason' => 'wrong address'])
            ->assertRedirect(route('sales.delivery-notes.show', $dn));

        $dn->refresh();
        $this->assertSame('Cancelled', $dn->status);
        $this->assertSame('wrong address', $dn->cancellation_reason);
        $this->assertSame($this->owner->id, (int) $dn->cancelled_by_id);
        $this->assertSame(10.0, $this->stock());

        $this->actingAs($this->owner)->delete(route('sales.delivery-notes.destroy', $dn), ['reason' => 'again'])->assertSessionHasErrors('delivery_note');
        $this->assertSame(10.0, $this->stock(), 'second cancel must not restore stock twice');
    }

    public function test_delivery_note_insufficient_stock_and_negative_stock_item(): void
    {
        $this->actingAs($this->owner)->post(route('sales.delivery-notes.store'), $this->dnPayload(50))->assertSessionHasErrors('items');
        $this->assertSame(0, SalesDeliveryNote::count());
        $this->assertSame(10.0, $this->stock());

        $this->item->update(['allow_negative_stock' => true]);
        $this->makeDn(50);
        $this->assertSame(-40.0, $this->stock());
    }

    public function test_delivery_note_posting_key_is_idempotent(): void
    {
        $this->makeDn(2, ['posting_key' => 'dn-key']);
        $this->actingAs($this->owner)->post(route('sales.delivery-notes.store'), $this->dnPayload(2, ['posting_key' => 'dn-key']))
            ->assertRedirect(route('sales.delivery-notes.index'));
        $this->assertSame(1, SalesDeliveryNote::where('posting_key', 'dn-key')->count());
        $this->assertSame(8.0, $this->stock());
    }

    public function test_delivery_note_against_order_tracks_dispatch_and_cancel_reverts_it(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-orders.store'), $this->docPayload('order_date', 6));
        $o = SalesOrder::firstOrFail();
        $line = $o->items()->first();

        $this->actingAs($this->owner)->get(route('sales.delivery-notes.create', ['from_order' => $o->id]))->assertOk();

        $dn = $this->makeDn(4, ['sales_order_id' => $o->id, 'items' => [['sales_order_item_id' => $line->id, 'ordered_qty' => 6]]]);
        $this->assertEquals(4.0, (float) $line->fresh()->dispatched_qty);
        $this->assertSame('Partially Fulfilled', $o->fresh()->status);

        $this->actingAs($this->owner)->delete(route('sales.delivery-notes.destroy', $dn), ['reason' => 'return to depot']);
        $this->assertEquals(0.0, (float) $line->fresh()->dispatched_qty);
        $this->assertSame('Open', $o->fresh()->status);
        $this->assertSame(10.0, $this->stock());
    }

    public function test_delivery_note_create_from_cancelled_order_redirects_with_error(): void
    {
        $o = SalesOrder::create([
            'order_number' => 'O-CAN', 'order_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'status' => 'Cancelled', 'total' => 100,
        ]);
        $this->actingAs($this->owner)->get(route('sales.delivery-notes.create', ['from_order' => $o->id]))
            ->assertRedirect(route('sales.sales-orders.index'))->assertSessionHasErrors('sales_order');
    }

    public function test_invoiced_delivery_note_cannot_be_cancelled(): void
    {
        $dn = $this->makeDn(2);
        $dn->update(['status' => 'Invoiced']);
        $this->actingAs($this->owner)->delete(route('sales.delivery-notes.destroy', $dn), ['reason' => 'oops'])->assertSessionHasErrors('delivery_note');
        $this->assertSame('Invoiced', $dn->fresh()->status);
        $this->assertSame(8.0, $this->stock());
        $this->assertFalse(StockLedger::where('reference_type', SalesDeliveryNote::class)->whereNotNull('reversal_of')->exists());
    }

    public function test_branch_bound_non_owner_cannot_dispatch_for_another_branch(): void
    {
        $other = Branch::create(['name' => 'Other', 'code' => 'OTH', 'state' => 'Gujarat']);
        $role = Role::firstOrCreate(['name' => 'Dispatcher', 'guard_name' => 'web']);
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'sales-delivery-notes.create', 'guard_name' => 'web']));
        $u = User::factory()->create(['branch_id' => $this->branch->id]);
        $u->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($u)->post(route('sales.delivery-notes.store'), $this->dnPayload(1, ['branch_id' => $other->id]))->assertForbidden();
        $this->assertSame(0, SalesDeliveryNote::count());
        $this->assertSame(10.0, $this->stock());

        $this->actingAs($u)->post(route('sales.delivery-notes.store'), $this->dnPayload(1))->assertSessionHasNoErrors();
        $this->assertSame($this->branch->id, (int) SalesDeliveryNote::firstOrFail()->branch_id);
        $this->assertSame(9.0, $this->stock());
    }

    // ---- E-Way bill -------------------------------------------------------

    private function bill(array $attrs = []): SalesBill
    {
        return SalesBill::create(array_merge([
            'bill_number' => 'EW-' . uniqid(), 'bill_date' => now()->toDateString(), 'customer_id' => $this->cust->id,
            'branch_id' => $this->branch->id, 'sales_type' => 'Local', 'total' => 1000, 'status' => 'Posted',
        ], $attrs));
    }

    public function test_eway_update_details_defaults_status_and_date(): void
    {
        $bill = $this->bill();
        $this->actingAs($this->owner)->from('/back')->post(route('sales.sales-bills.eway-update', $bill), [
            'eway_bill_no' => '331000123456', 'vehicle_no' => 'GJ01AB1234', 'transport_mode' => '1', 'transport_distance' => 120,
        ])->assertRedirect('/back')->assertSessionHas('success');

        $bill->refresh();
        $this->assertSame('331000123456', $bill->eway_bill_no);
        $this->assertSame('Generated', $bill->eway_status);
        $this->assertNotNull($bill->eway_bill_date);
        $this->assertSame('GJ01AB1234', $bill->vehicle_no);
    }

    public function test_eway_update_validation_rejects_bad_values(): void
    {
        $bill = $this->bill();
        $this->actingAs($this->owner)->post(route('sales.sales-bills.eway-update', $bill), [
            'transport_mode' => '9', 'transport_distance' => 99999, 'eway_status' => 'Nope', 'vehicle_type' => 'X',
        ])->assertSessionHasErrors(['transport_mode', 'transport_distance', 'eway_status', 'vehicle_type']);
        $this->assertNull($bill->fresh()->eway_bill_no);
    }

    public function test_eway_tools_index_filters_and_summary(): void
    {
        $big = $this->bill(['bill_number' => 'EW-BIG', 'total' => 75000]);
        $done = $this->bill(['bill_number' => 'EW-DONE', 'total' => 100, 'eway_bill_no' => '999888777666', 'eway_status' => 'Generated']);
        $small = $this->bill(['bill_number' => 'EW-SMALL', 'total' => 100]);

        $res = $this->actingAs($this->owner)->get(route('tools.eway-update'))->assertOk();
        $res->assertSee('EW-BIG')->assertSee('EW-DONE')->assertDontSee('EW-SMALL');
        $this->assertEquals(2, $res->viewData('totalEligibleCount'));
        $this->assertEquals(1, $res->viewData('generatedCount'));
        $this->assertEquals(1, $res->viewData('pendingCount'));

        $this->actingAs($this->owner)->get(route('tools.eway-update', ['status' => 'Pending']))->assertSee('EW-BIG')->assertDontSee('EW-DONE');
        $this->actingAs($this->owner)->get(route('tools.eway-update', ['status' => 'Generated']))->assertSee('EW-DONE')->assertDontSee('EW-BIG');
        $this->actingAs($this->owner)->get(route('tools.eway-update', ['search' => '999888']))->assertSee('EW-DONE')->assertDontSee('EW-BIG');
        $this->actingAs($this->owner)->get(route('tools.eway-update', ['from_date' => now()->addDay()->toDateString()]))->assertDontSee('EW-BIG');
        $this->actingAs($this->owner)->get(route('tools.eway-update', ['to_date' => now()->subDay()->toDateString()]))->assertDontSee('EW-BIG');
    }

    public function test_eway_json_downloads(): void
    {
        $bill = $this->bill(['bill_number' => 'EW/JSON-1']);
        $bill->items()->create(['item_id' => $this->item->id, 'qty' => 1, 'sell_price' => 100, 'net_amount' => 100]);

        $res = $this->actingAs($this->owner)->get(route('sales.sales-bills.eway-json', $bill))->assertOk();
        $this->assertStringContainsString('application/json', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('EWB_NIC_EW_JSON_1_', $res->headers->get('Content-Disposition'));
        $this->assertNotNull(json_decode($res->getContent(), true));

        $this->actingAs($this->owner)->from('/back')->post(route('tools.eway-bulk-json'), [])->assertRedirect('/back')->assertSessionHas('error');
        $this->actingAs($this->owner)->from('/back')->post(route('tools.eway-bulk-json'), ['selected_bills' => '99998,99999'])->assertRedirect('/back')->assertSessionHas('error');

        $bulk = $this->actingAs($this->owner)->post(route('tools.eway-bulk-json'), ['selected_bills' => (string) $bill->id])->assertOk();
        $this->assertStringContainsString('Bulk_EWB_NIC_Batch_1_Bills_', $bulk->headers->get('Content-Disposition'));
        $this->assertNotNull(json_decode($bulk->getContent(), true));
    }
}
