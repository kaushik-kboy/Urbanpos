<?php

namespace Tests\Feature\Cov;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\SalesBill;
use App\Models\StockLedger;
use App\Models\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesBillCovTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $walkIn;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        User::factory()->create(); // burn id 1 (super-user bypass)
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main', 'code' => 'MAIN', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->owner = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->owner->assignRole('Owner');

        $this->walkIn = Customer::create(['name' => 'Walk-in Customer', 'mobile' => '9000000001', 'state' => 'Gujarat', 'status' => true]);

        $this->item = Item::create([
            'name' => 'Cov Widget', 'item_code' => 'COV-1', 'sell_price' => 100, 'mrp' => 100, 'cost_price' => 60,
            'gst_tax_id' => $gst->id, 'tax_inclusive' => true, 'status' => true, 'allow_negative_stock' => false,
        ]);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 10]);
    }

    private function payload(array $over = [], ?Item $item = null, float $qty = 2): array
    {
        $item ??= $this->item;

        return array_replace_recursive([
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [[
                'item_id' => $item->id, 'qty' => $qty, 'sell_price' => 100, 'mrp' => 100,
                'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18,
            ]],
        ], $over);
    }

    private function stock(?Item $item = null): float
    {
        return (float) ItemStock::where('item_id', ($item ?? $this->item)->id)->where('branch_id', $this->branch->id)->value('quantity');
    }

    private function createBill(array $over = [], float $qty = 2): SalesBill
    {
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload($over, null, $qty))->assertSessionHasNoErrors();

        return SalesBill::latest('id')->firstOrFail();
    }

    public function test_store_deducts_stock_posts_journal_and_sets_cost(): void
    {
        $bill = $this->createBill();

        $this->assertSame(8.0, $this->stock());
        $this->assertEquals(200.0, (float) $bill->total);
        $this->assertSame('Posted', $bill->status);
        $this->assertDatabaseHas('stock_ledger', ['item_id' => $this->item->id, 'reference_id' => $bill->id, 'movement_type' => 'SALE']);
        $this->assertTrue(JournalEntry::where('reference_type', SalesBill::class)->where('reference_id', $bill->id)->exists());
        $this->assertNotNull($bill->items()->first()->cost_at_sale);
    }

    public function test_store_rejects_insufficient_stock_and_creates_nothing(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload([], null, 50))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, SalesBill::count());
        $this->assertSame(10.0, $this->stock());
    }

    public function test_store_rejects_combined_duplicate_rows_exceeding_stock(): void
    {
        $p = $this->payload([], null, 6);
        $p['items'][] = $p['items'][0];
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $p)->assertSessionHasErrors('items');
        $this->assertSame(0, SalesBill::count());
    }

    public function test_negative_stock_item_can_oversell(): void
    {
        $this->item->update(['allow_negative_stock' => true]);
        $this->createBill([], 15);
        $this->assertSame(-5.0, $this->stock());
    }

    public function test_expired_item_is_rejected(): void
    {
        $p = $this->payload();
        $p['items'][0]['exp_date'] = now()->subDays(3)->toDateString();
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $p)->assertSessionHasErrors('items');
        $this->assertSame(0, SalesBill::count());
    }

    public function test_future_bill_date_is_rejected(): void
    {
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['bill_date' => now()->addDays(3)->format('Y-m-d H:i:s')]))
            ->assertSessionHasErrors('bill_date');
    }

    public function test_repeat_bill_for_named_customer_is_allowed_without_content_heuristic(): void
    {
        // The old 10 s same-customer/same-item content guard is gone (see DuplicateSubmitGuardRegressionTest);
        // double-submit protection is posting_key idempotency.
        $named = Customer::create(['name' => 'Rajesh Patel', 'mobile' => '9111111111', 'state' => 'Gujarat', 'status' => true]);
        $this->createBill(['customer_id' => $named->id], 1);
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['customer_id' => $named->id], null, 1))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, SalesBill::where('customer_id', $named->id)->count());

        // walk-in: repeated identical bills are legitimate
        $this->createBill([], 1);
        $this->createBill([], 1);
        $this->assertSame(2, SalesBill::where('customer_id', $this->walkIn->id)->count());
    }

    public function test_customer_without_mobile_is_treated_as_walkin(): void
    {
        $anon = Customer::create(['name' => 'Someone', 'mobile' => null, 'state' => 'Gujarat', 'status' => true]);
        $this->createBill(['customer_id' => $anon->id], 1);
        $this->createBill(['customer_id' => $anon->id], 1);
        $this->assertSame(2, SalesBill::where('customer_id', $anon->id)->count());
    }

    public function test_credit_limit_blocks_and_rolls_back(): void
    {
        $c = Customer::create(['name' => 'Limited Co', 'mobile' => '9222222222', 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 100]);
        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['customer_id' => $c->id]))
            ->assertSessionHasErrors('credit_limit');

        $this->assertSame(0, SalesBill::count());
        $this->assertSame(10.0, $this->stock());
    }

    public function test_credit_limit_allows_within_limit(): void
    {
        $c = Customer::create(['name' => 'Ok Co', 'mobile' => '9333333333', 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 1000]);
        $bill = $this->createBill(['customer_id' => $c->id]);
        $this->assertSame($c->id, $bill->customer_id);
    }

    public function test_split_payments_persist_and_mismatch_is_rejected(): void
    {
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $card = TenderType::create(['name' => 'Card', 'type' => 'Card', 'status' => true]);

        $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['payments' => [
            ['tender_type_id' => $cash->id, 'amount' => 50],
        ]]))->assertSessionHasErrors('payments');
        $this->assertSame(0, SalesBill::count());
        $this->assertSame(10.0, $this->stock());

        $bill = $this->createBill(['payments' => [
            ['tender_type_id' => $cash->id, 'amount' => 120],
            ['tender_type_id' => $card->id, 'amount' => 80],
        ]]);
        $this->assertSame(2, $bill->payments()->count());
        $this->assertEquals(200.0, (float) $bill->payments()->sum('amount'));
    }

    public function test_small_rounding_difference_is_absorbed_into_first_payment(): void
    {
        $cash = TenderType::create(['name' => 'Cash', 'type' => 'Cash', 'status' => true]);
        $bill = $this->createBill(['payments' => [['tender_type_id' => $cash->id, 'amount' => 199.97]]]);
        $this->assertEquals(200.0, (float) $bill->payments()->first()->amount);
    }

    public function test_update_reverses_old_stock_and_reposts_new_quantities(): void
    {
        $bill = $this->createBill([], 4); // stock 6
        $this->assertSame(6.0, $this->stock());

        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload([], null, 3))
            ->assertRedirect(route('sales.sales-bills.index'));

        $this->assertSame(7.0, $this->stock());
        $bill->refresh();
        $this->assertEquals(300.0, (float) $bill->total);
        $this->assertSame(1, $bill->items()->count());
        $this->assertEquals(3.0, (float) $bill->items()->first()->qty);
        $this->assertTrue(StockLedger::where('reference_type', SalesBill::class)->where('reference_id', $bill->id)->whereNotNull('reversal_of')->exists());
    }

    public function test_update_cannot_exceed_stock_and_leaves_bill_untouched(): void
    {
        $bill = $this->createBill([], 4);
        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload([], null, 99))
            ->assertSessionHasErrors('items');

        $this->assertSame(6.0, $this->stock());
        $this->assertEquals(400.0, (float) $bill->fresh()->total);
    }

    public function test_update_rejects_duplicate_bill_number_but_allows_own(): void
    {
        $a = $this->createBill([], 1);
        $b = $this->createBill([], 1);

        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $b), $this->payload(['bill_number' => $a->bill_number], null, 1))
            ->assertSessionHasErrors('bill_number');

        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $b), $this->payload(['bill_number' => $b->bill_number], null, 1))
            ->assertSessionHasNoErrors();
    }

    public function test_update_of_cancelled_bill_is_rejected(): void
    {
        $bill = $this->createBill();
        $bill->update(['status' => 'Cancelled']);
        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload())->assertSessionHasErrors('status');
    }

    public function test_update_credit_limit_uses_delta_not_full_total(): void
    {
        $c = Customer::create(['name' => 'Delta Co', 'mobile' => '9444444444', 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 250]);
        $bill = $this->createBill(['customer_id' => $c->id], 2); // outstanding 200

        // 2 -> 2 units again: delta 0, must pass even though 200+200 > 250
        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload(['customer_id' => $c->id], null, 2))
            ->assertSessionHasNoErrors();
        // 2 -> 4 units: delta 200 -> 400 > 250
        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload(['customer_id' => $c->id], null, 4))
            ->assertSessionHasErrors('credit_limit');
    }

    public function test_update_switching_customer_checks_new_customer_full_total(): void
    {
        $bill = $this->createBill([], 2);
        $small = Customer::create(['name' => 'Small Co', 'mobile' => '9555555555', 'state' => 'Gujarat', 'status' => true, 'credit_limit' => 50]);
        $this->actingAs($this->owner)->put(route('sales.sales-bills.update', $bill), $this->payload(['customer_id' => $small->id], null, 2))
            ->assertSessionHasErrors('credit_limit');
        $this->assertSame($this->walkIn->id, $bill->fresh()->customer_id);
    }

    public function test_destroy_reverses_stock_and_journal_and_soft_deletes(): void
    {
        $bill = $this->createBill([], 3);
        $this->assertSame(7.0, $this->stock());

        $this->actingAs($this->owner)->delete(route('sales.sales-bills.destroy', $bill))
            ->assertRedirect(route('sales.sales-bills.index'));

        $this->assertSame(10.0, $this->stock());
        $this->assertNull(SalesBill::find($bill->id));
        $orig = JournalEntry::where('reference_type', SalesBill::class)->where('reference_id', $bill->id)->whereNull('reversal_of')->first();
        $this->assertNotNull($orig);
        $rev = JournalEntry::where('reversal_of', $orig->id)->first();
        $this->assertNotNull($rev);
        $this->assertEquals((float) $orig->total_debit, (float) $rev->total_credit);
    }

    public function test_destroy_without_cancel_permission_is_forbidden(): void
    {
        $bill = $this->createBill();
        $role = Role::firstOrCreate(['name' => 'NoCancel', 'guard_name' => 'web']);
        $u = User::factory()->create(['branch_id' => $this->branch->id]);
        $u->assignRole($role);

        $this->actingAs($u)->delete(route('sales.sales-bills.destroy', $bill))->assertForbidden();
        $this->assertNotNull(SalesBill::find($bill->id));
        $this->assertSame(8.0, $this->stock());
    }

    public function test_store_from_delivery_note_does_not_double_deduct_and_destroy_restores_dn(): void
    {
        $dn = \App\Models\SalesDeliveryNote::create([
            'delivery_number' => 'DN-COV-1', 'delivery_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Dispatched', 'total' => 200,
        ]);
        $dn->items()->create(['item_id' => $this->item->id, 'ordered_qty' => 2, 'dispatched_qty' => 2, 'unit_price' => 100, 'cost_at_dispatch' => 55]);

        $bill = $this->createBill(['sales_delivery_note_id' => $dn->id], 2);

        $this->assertSame(10.0, $this->stock(), 'stock was taken at dispatch, invoice must not deduct again');
        $this->assertSame('Invoiced', $dn->fresh()->status);
        $this->assertSame($bill->id, (int) $dn->fresh()->sales_bill_id);
        $this->assertEquals(55.0, (float) $bill->items()->first()->cost_at_sale);

        $this->actingAs($this->owner)->delete(route('sales.sales-bills.destroy', $bill))->assertRedirect();
        $this->assertSame('Dispatched', $dn->fresh()->status);
        $this->assertNull($dn->fresh()->sales_bill_id);
        $this->assertSame(10.0, $this->stock());
    }

    public function test_store_marks_quotation_and_order_as_converted(): void
    {
        $q = \App\Models\SalesQuotation::create([
            'quotation_number' => 'Q-COV-1', 'quotation_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Sent', 'total' => 100,
        ]);
        $o = \App\Models\SalesOrder::create([
            'order_number' => 'O-COV-1', 'order_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Open', 'total' => 100,
        ]);

        $bill = $this->createBill(['from_quotation_id' => $q->id, 'from_order_id' => $o->id], 1);

        $this->assertSame('Converted', $q->fresh()->status);
        $this->assertSame($bill->id, (int) $q->fresh()->converted_sales_bill_id);
        $this->assertSame('Converted', $o->fresh()->status);
        $this->assertSame($bill->id, (int) $o->fresh()->converted_sales_bill_id);
    }

    public function test_json_store_returns_payload_and_duplicate_posting_key_is_idempotent(): void
    {
        $p = $this->payload(['posting_key' => 'cov-key-1'], null, 1);
        $r1 = $this->actingAs($this->owner)->postJson(route('sales.sales-bills.store'), $p)->assertOk()->assertJson(['success' => true]);
        $r2 = $this->actingAs($this->owner)->postJson(route('sales.sales-bills.store'), $p)->assertOk()->assertJson(['success' => true, 'duplicate_prevented' => true]);

        $this->assertSame($r1->json('id'), $r2->json('id'));
        $this->assertSame(1, SalesBill::where('posting_key', 'cov-key-1')->count());
        $this->assertSame(9.0, $this->stock());
    }

    public function test_store_save_actions_print_and_whatsapp_set_flash_urls(): void
    {
        $r = $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['save_action' => 'print'], null, 1));
        $bill = SalesBill::latest('id')->first();
        $r->assertSessionHas('auto_print_url', route('sales.sales-bills.receipt', $bill));

        $r = $this->actingAs($this->owner)->post(route('sales.sales-bills.store'), $this->payload(['save_action' => 'whatsapp'], null, 1));
        $r->assertSessionHas('auto_whatsapp_url');
        $this->assertStringContainsString('phone=919000000001', session('auto_whatsapp_url'));
    }

    public function test_verify_stock_endpoint(): void
    {
        $this->actingAs($this->owner)->postJson(route('sales.sales-bills.verify-stock'), [
            'branch_id' => $this->branch->id, 'items' => [['item_id' => $this->item->id, 'qty' => 4], ['id' => $this->item->id, 'qty' => 3]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->actingAs($this->owner)->postJson(route('sales.sales-bills.verify-stock'), [
            'branch_id' => $this->branch->id, 'items' => [['item_id' => $this->item->id, 'qty' => 6], ['item_id' => $this->item->id, 'qty' => 6]],
        ])->assertStatus(422)->assertJson(['success' => false, 'item_id' => $this->item->id, 'available' => 10]);

        // ignores zero qty / unknown items / negative-stock items
        $this->item->update(['allow_negative_stock' => true]);
        $this->actingAs($this->owner)->postJson(route('sales.sales-bills.verify-stock'), [
            'items' => [['item_id' => $this->item->id, 'qty' => 500], ['item_id' => 99999, 'qty' => 5], ['item_id' => 0, 'qty' => 1]],
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_lookup_item_by_code_ean_id_name_and_exact_only(): void
    {
        $this->item->update(['ean_upc_code' => '8901234567890']);

        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['query' => 'COV-1', 'branch_id' => $this->branch->id]))
            ->assertJson(['found' => true, 'item' => ['id' => $this->item->id, 'stock' => 10]]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['q' => '8901234567890']))
            ->assertJson(['found' => true, 'item' => ['id' => $this->item->id]]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['query' => (string) $this->item->id]))
            ->assertJson(['found' => true, 'item' => ['id' => $this->item->id]]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['query' => 'Widget']))
            ->assertJson(['found' => true]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['query' => 'Widget', 'exact_match_only' => 1]))
            ->assertJson(['found' => false]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['query' => 'nothing-here']))
            ->assertJson(['found' => false]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['item_id' => $this->item->id]))
            ->assertJson(['found' => true]);
    }

    public function test_lookup_item_adds_back_bill_qty_in_edit_mode_and_ignores_inactive(): void
    {
        $bill = $this->createBill([], 4); // stock 6
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'sales_bill_id' => $bill->id]))
            ->assertJson(['item' => ['stock' => 10]]);

        $this->item->update(['status' => false]);
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['item_id' => $this->item->id]))->assertJson(['found' => false]);
    }

    public function test_lookup_item_batches_from_ledger(): void
    {
        $exp1 = now()->addDays(30)->toDateString();
        $exp2 = now()->addDays(90)->toDateString();
        $svc = app(\App\Services\Inventory\StockLedgerService::class);
        $svc->post(itemId: $this->item->id, branchId: $this->branch->id, movementType: 'PURCHASE_RECEIPT', qtyDelta: 5, unitCost: 50,
            referenceType: 'X', referenceId: 1, documentDate: now()->toDateString(), expDate: $exp1);
        $svc->post(itemId: $this->item->id, branchId: $this->branch->id, movementType: 'PURCHASE_RECEIPT', qtyDelta: 3, unitCost: 50,
            referenceType: 'X', referenceId: 2, documentDate: now()->toDateString(), expDate: $exp2);

        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.lookup-item', ['item_id' => $this->item->id, 'branch_id' => $this->branch->id]))->assertOk();
        $batches = collect($res->json('batches'))->keyBy('exp_date');
        $this->assertEquals(5, $batches[$exp1]['qty']);
        $this->assertEquals(3, $batches[$exp2]['qty']);
        $this->assertSame('ledger', $batches[$exp1]['source']);
        $this->assertSame($exp1, $res->json('item.exp_date'));
    }

    public function test_item_list_customer_search_and_loyalty_endpoints_respond(): void
    {
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.item-list', ['branch_id' => $this->branch->id, 'q' => 'Cov']))->assertOk();
        $res = $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-search', ['q' => 'Walk']))->assertOk();
        $this->assertStringContainsString('Walk-in', json_encode($res->json()));
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-search', ['q' => '9000000001']))->assertOk();
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-invoices', $this->walkIn->id))->assertOk();
        $this->actingAs($this->owner)->getJson(route('sales.sales-bills.customer-loyalty', $this->walkIn->id))->assertOk();
    }

    public function test_index_search_show_edit_receipt_render_and_public_receipt_needs_valid_hash(): void
    {
        $bill = $this->createBill();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.index', ['search' => $bill->bill_number]))->assertOk()->assertSee($bill->bill_number);
        $this->actingAs($this->owner)->get(route('sales.sales-bills.show', $bill))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.edit', $bill))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.receipt', $bill))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.create'))->assertOk();
        $this->get(route('sales-bills.public-receipt', [$bill, 'badhash']))->assertForbidden();
        $good = app(\App\Services\WhatsApp\ChatOnClickWhatsAppService::class)->generateReceiptHash($bill);
        $this->get(route('sales-bills.public-receipt', [$bill, $good]))->assertOk();
    }

    public function test_create_prefilled_from_delivery_note(): void
    {
        $dn = \App\Models\SalesDeliveryNote::create([
            'delivery_number' => 'DN-COV-2', 'delivery_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Dispatched', 'total' => 100,
        ]);
        $dn->items()->create(['item_id' => $this->item->id, 'ordered_qty' => 1, 'dispatched_qty' => 1, 'unit_price' => 100]);
        $this->actingAs($this->owner)->get(route('sales.sales-bills.create', ['from_delivery_note' => $dn->id]))->assertOk();
    }

    public function test_create_prefilled_from_quotation_and_order(): void
    {
        $q = \App\Models\SalesQuotation::create([
            'quotation_number' => 'Q-COV-9', 'quotation_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Sent', 'total' => 100,
        ]);
        $o = \App\Models\SalesOrder::create([
            'order_number' => 'O-COV-9', 'order_date' => now()->toDateString(), 'customer_id' => $this->walkIn->id,
            'branch_id' => $this->branch->id, 'status' => 'Open', 'total' => 100,
        ]);
        $this->actingAs($this->owner)->get(route('sales.sales-bills.create', ['from_quotation' => $q->id]))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.create', ['from_order' => $o->id]))->assertOk();
        $this->actingAs($this->owner)->get(route('sales.sales-bills.create', ['from_order' => 99999]))->assertNotFound();
    }
}
