<?php

namespace Tests\Feature\Cov;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared gatedResource path (Brand as the representative), plus Item / Customer / Supplier
 * specific validation and role branches.
 */
class MasterCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1
    }

    private function as(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    private function itemPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Crud Item',
            'product_type' => 'Standard',
            'cost_price' => 10,
            'landing_cost' => 10,
            'sell_price' => 15,
            'mrp' => 20,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ], $over);
    }

    private function customerPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Crud Customer',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash Only',
            'credit_limit' => 100,
            'credit_balance' => 50,
            'monthly_credit_balance' => 25,
            'credit_days' => 10,
            'status' => 1,
            'gst_type' => 'Un Register',
            'sms_consent' => 0,
            'mobile' => '9000000001',
            'customer_type' => 'RETAIL INVOICE',
        ], $over);
    }

    // ---------------------------------------------------------------- shared gated path (Brand)

    public function test_brand_crud_role_matrix_owner_manager_cashier(): void
    {
        $this->as('Cashier');
        $this->post(route('master.brands.store'), ['name' => 'Nope'])->assertForbidden();
        $this->assertSame(0, Brand::count());

        $this->as('Manager');
        $this->post(route('master.brands.store'), ['name' => 'Mgr Brand'])
            ->assertRedirect(route('master.brands.index'));
        $brand = Brand::where('name', 'Mgr Brand')->first();
        $this->assertNotNull($brand);
        $this->assertTrue((bool) $brand->status, 'status defaults to active when omitted');

        // Manager has brands.edit but NOT gst-taxes/branches (owner-only tier)
        $this->put(route('master.brands.update', $brand), ['name' => 'Mgr Brand 2', 'prefix' => 'MB'])
            ->assertRedirect(route('master.brands.index'));
        $this->assertSame('MB', $brand->fresh()->prefix);
        $this->post(route('master.branches.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_brand_validation_duplicate_name_and_update_may_keep_its_own_name(): void
    {
        $this->as('Owner');
        $a = Brand::create(['name' => 'Alpha', 'status' => 1]);
        Brand::create(['name' => 'Beta', 'status' => 1]);

        $this->post(route('master.brands.store'), ['name' => 'Alpha'])->assertSessionHasErrors('name');
        $this->post(route('master.brands.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('master.brands.store'), ['name' => 'Gamma', 'status' => 'maybe'])->assertSessionHasErrors('status');

        $this->put(route('master.brands.update', $a), ['name' => 'Beta'])->assertSessionHasErrors('name');
        $this->put(route('master.brands.update', $a), ['name' => 'Alpha', 'prefix' => 'AL', 'alias_code' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('AL', $a->fresh()->prefix);
        $this->assertNull($a->fresh()->alias_code);
    }

    public function test_brand_json_store_returns_created_record_and_destroy_never_deletes(): void
    {
        $this->as('Owner');
        $this->postJson(route('master.brands.store'), ['name' => 'Json Brand'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Json Brand');

        $b = Brand::where('name', 'Json Brand')->first();
        $this->delete(route('master.brands.destroy', $b))
            ->assertRedirect(route('master.brands.index'))
            ->assertSessionHas('error');
        $this->assertNotNull(Brand::find($b->id));
    }

    public function test_master_index_and_form_pages_render_for_any_authenticated_user(): void
    {
        $this->as('Cashier');
        Brand::create(['name' => 'Visible Brand', 'status' => 1]);
        $this->get(route('master.brands.index'))->assertOk()->assertSee('Visible Brand');
        $this->get(route('master.brands.create'))->assertOk();
    }

    // ---------------------------------------------------------------- items

    public function test_item_hsn_code_must_be_4_to_8_digits(): void
    {
        $this->as('Owner');
        foreach (['123', '123456789', '12AB56', '12 34'] as $bad) {
            $this->post(route('master.items.store'), $this->itemPayload(['hsn_code' => $bad]))
                ->assertSessionHasErrors('hsn_code');
        }
        $this->assertSame(0, Item::count());

        foreach (['1234', '12345678'] as $i => $ok) {
            $this->post(route('master.items.store'), $this->itemPayload(['name' => "Hsn $i", 'hsn_code' => $ok]))
                ->assertSessionHasNoErrors();
            $this->assertSame($ok, Item::where('name', "Hsn $i")->value('hsn_code'));
        }
    }

    public function test_item_price_ordering_rules_are_enforced(): void
    {
        $this->as('Owner');
        // landing < cost
        $this->post(route('master.items.store'), $this->itemPayload(['cost_price' => 10, 'landing_cost' => 5]))
            ->assertSessionHasErrors('landing_cost');
        // sell < landing
        $this->post(route('master.items.store'), $this->itemPayload(['sell_price' => 8]))
            ->assertSessionHasErrors('sell_price');
        // sell > mrp
        $this->post(route('master.items.store'), $this->itemPayload(['sell_price' => 25, 'mrp' => 20]))
            ->assertSessionHasErrors(['sell_price', 'mrp']);
        $this->assertSame(0, Item::count());
    }

    public function test_item_store_blank_optionals_become_null_and_prices_default_from_each_other(): void
    {
        $this->as('Owner');
        $this->post(route('master.items.store'), $this->itemPayload([
            'name' => 'Blank Optionals', 'alias' => '  ', 'ean_upc_code' => '', 'hsn_code' => '',
            'brand_id' => '', 'shelf_life_days' => '',
        ]))->assertRedirect(route('master.items.index'));

        $item = Item::where('name', 'Blank Optionals')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->alias);
        $this->assertNull($item->hsn_code);
        $this->assertNull($item->brand_id);
        $this->assertNull($item->shelf_life_days);
        $this->assertEquals(15, $item->sell_price);
    }

    public function test_item_duplicate_name_and_ean_rejected_but_update_can_keep_own(): void
    {
        $this->as('Owner');
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'One', 'ean_upc_code' => 'E1']))->assertSessionHasNoErrors();
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'One']))->assertSessionHasErrors('name');
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Two', 'ean_upc_code' => 'E1']))->assertSessionHasErrors('ean_upc_code');

        $one = Item::where('name', 'One')->first();
        $this->put(route('master.items.update', $one), $this->itemPayload(['name' => 'One', 'ean_upc_code' => 'E1', 'sell_price' => 18]))
            ->assertRedirect(route('master.items.index'));
        $this->assertEquals(18, $one->fresh()->sell_price);
    }

    public function test_manager_can_create_but_not_update_or_delete_items_and_price_stays_unchanged(): void
    {
        $item = Item::create(['name' => 'Priced', 'sell_price' => 15, 'mrp' => 20]);

        $this->as('Manager');
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Manager New']))->assertRedirect();
        $this->assertNotNull(Item::where('name', 'Manager New')->first());

        $this->put(route('master.items.update', $item), $this->itemPayload(['name' => 'Priced', 'sell_price' => 19]))->assertForbidden();
        $this->delete(route('master.items.destroy', $item))->assertForbidden();
        $this->assertEquals(15, $item->fresh()->sell_price);
    }

    public function test_owner_item_destroy_is_soft_refusal_and_generate_barcode_returns_unused_code(): void
    {
        $this->as('Owner');
        $item = Item::create(['name' => 'Keep Me']);
        $this->delete(route('master.items.destroy', $item))->assertSessionHas('error');
        $this->assertNotNull(Item::find($item->id));

        $res = $this->getJson(route('master.items.generate-barcode'))->assertOk()->assertJsonPath('success', true);
        $code = $res->json('barcode');
        $this->assertNotEmpty($code);
        $this->assertFalse(Item::where('ean_upc_code', $code)->exists());
    }

    public function test_item_index_filters_by_name_status_and_brand(): void
    {
        $this->as('Owner');
        $brand = Brand::create(['name' => 'FB', 'status' => 1]);
        Item::create(['name' => 'Filter Alpha', 'brand_id' => $brand->id, 'status' => 1]);
        Item::create(['name' => 'Filter Beta', 'status' => 0]);

        $this->get(route('master.items.index', ['name' => 'Alpha']))->assertOk()->assertSee('Filter Alpha')->assertDontSee('Filter Beta');
        $this->get(route('master.items.index', ['brand_id' => $brand->id]))->assertSee('Filter Alpha')->assertDontSee('Filter Beta');
        $this->get(route('master.items.index', ['status' => 1]))->assertSee('Filter Alpha')->assertDontSee('Filter Beta');
    }

    public function test_item_create_form_copy_from_generates_unique_copy_name_and_clean_hsn(): void
    {
        $this->as('Owner');
        $src = Item::create(['name' => 'Origin', 'ean_upc_code' => 'SRC1', 'hsn_code' => '12', 'product_type' => 'Weird']);
        Item::create(['name' => 'Origin (Copy)']);

        $res = $this->get(route('master.items.create', ['copy_from' => $src->id]))->assertOk();
        $copy = $res->viewData('item');
        $this->assertSame('Origin (Copy 2)', $copy->name);
        $this->assertNull($copy->hsn_code, 'invalid 2-digit HSN is dropped');
        $this->assertSame('Standard', $copy->product_type);
        $this->assertNotSame('SRC1', $copy->ean_upc_code);
        $this->assertNull($copy->item_code);
    }

    // ---------------------------------------------------------------- customers

    public function test_customer_store_validation_mobile_and_gst_rules(): void
    {
        $this->as('Manager');
        $this->post(route('master.customers.store'), $this->customerPayload(['mobile' => '123']))->assertSessionHasErrors('mobile');
        $this->post(route('master.customers.store'), $this->customerPayload(['gst_no' => 'BADGST']))->assertSessionHasErrors('gst_no');
        $this->post(route('master.customers.store'), $this->customerPayload(['customer_type' => 'NOPE']))->assertSessionHasErrors('customer_type');
        $this->assertSame(0, Customer::count());

        $this->post(route('master.customers.store'), $this->customerPayload())->assertRedirect(route('master.customers.index'));
        $this->post(route('master.customers.store'), $this->customerPayload(['name' => 'Dup']))
            ->assertSessionHasErrors(['mobile' => 'A customer with this mobile number already exists.']);
    }

    public function test_customer_json_store_and_pets_sync(): void
    {
        $this->as('Manager');
        $this->postJson(route('master.customers.store'), $this->customerPayload([
            'pets' => [['name' => 'Rex'], ['name' => '']],
        ]))->assertOk()->assertJsonPath('success', true)->assertJsonPath('customer.name', 'Crud Customer');

        $c = Customer::where('mobile', '9000000001')->first();
        $this->assertSame(1, $c->pets()->count());
        $this->assertSame('Rex', $c->pets()->first()->name);

        // update: edit pet and delete via _delete flag
        $pet = $c->pets()->first();
        $this->putJson(route('master.customers.update', $c), $this->customerPayload([
            'pets' => [['id' => $pet->id, '_delete' => 1]],
        ]))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, $c->pets()->count());
    }

    public function test_customer_type_and_sales_type_are_immutable_on_update(): void
    {
        $this->as('Owner');
        $c = Customer::create($this->customerPayload(['customer_code' => 'CX']));
        $this->put(route('master.customers.update', $c), $this->customerPayload(['customer_type' => 'TAX INVOICE', 'sales_type' => 'Interstate', 'name' => 'Renamed']))
            ->assertRedirect(route('master.customers.index'));
        $c->refresh();
        $this->assertSame('Renamed', $c->name);
        $this->assertSame('RETAIL INVOICE', $c->customer_type);
        $this->assertSame('Local', $c->sales_type);
    }

    public function test_manager_cannot_change_customer_credit_terms_but_can_resubmit_them_unchanged(): void
    {
        $c = Customer::create($this->customerPayload(['customer_code' => 'CY']));

        $this->as('Manager');
        foreach (['credit_limit' => 999, 'credit_balance' => 1, 'monthly_credit_balance' => 5, 'credit_days' => 99] as $field => $val) {
            $this->put(route('master.customers.update', $c), $this->customerPayload([$field => $val]))
                ->assertSessionHasErrors([$field => 'Only an Owner can change credit terms.']);
        }
        $this->assertEquals(100, $c->fresh()->credit_limit);
        $this->assertSame(10, (int) $c->fresh()->credit_days);

        $this->put(route('master.customers.update', $c), $this->customerPayload(['name' => 'Unrelated Edit']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Unrelated Edit', $c->fresh()->name);

        $this->as('Owner');
        $this->put(route('master.customers.update', $c), $this->customerPayload(['credit_limit' => 999]))->assertSessionHasNoErrors();
        $this->assertEquals(999, $c->fresh()->credit_limit);
    }

    public function test_customer_show_and_edit_json_and_index_search(): void
    {
        $this->as('Cashier');
        $c = Customer::create($this->customerPayload(['name' => 'Searchable Sam', 'customer_code' => 'CZ']));
        Customer::create($this->customerPayload(['name' => 'Other', 'mobile' => '9000000002', 'customer_code' => 'CW']));

        $this->getJson(route('master.customers.show', $c))->assertOk()->assertJsonPath('customer.name', 'Searchable Sam');
        $this->getJson(route('master.customers.edit', $c))->assertOk()->assertJsonPath('customer.id', $c->id);
        $this->get(route('master.customers.index', ['search' => 'Searchable']))->assertSee('Searchable Sam')->assertDontSee('Other');
        // Regression: HTML show used to 500 (missing view); now lands on the edit form.
        $this->get(route('master.customers.show', $c))->assertRedirect(route('master.customers.edit', $c));
        $this->get(route('master.customers.edit', $c))->assertOk();
        $this->delete(route('master.customers.destroy', $c))->assertForbidden();
    }

    // ---------------------------------------------------------------- suppliers

    private function supplierPayload(array $over = []): array
    {
        return array_merge(['name' => 'Crud   Supplier', 'credit_limit' => 500, 'credit_balance' => 100, 'credit_days' => 30], $over);
    }

    public function test_supplier_store_normalises_name_gst_and_applies_defaults(): void
    {
        $this->as('Manager');
        $this->post(route('master.suppliers.store'), $this->supplierPayload(['gst_no' => ' 24aaaaa0000a1z5 ', 'mobile' => '']))
            ->assertRedirect();

        $s = Supplier::first();
        $this->assertSame('Crud Supplier', $s->name);
        $this->assertSame('24AAAAA0000A1Z5', $s->gst_no);
        $this->assertSame('INR', $s->currency);
        $this->assertSame('Local', $s->purchase_type);
        $this->assertNull($s->mobile);
    }

    public function test_supplier_rejects_bad_gst_duplicate_gst_and_duplicate_name(): void
    {
        $this->as('Manager');
        Supplier::create(['name' => 'Existing', 'gst_no' => '24AAAAA0000A1Z5', 'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit', 'credit_limit' => 0, 'credit_balance' => 0, 'credit_days' => 0, 'status' => 1, 'gst_type' => 'Regular', 'mail_type' => 'None']);

        $this->post(route('master.suppliers.store'), $this->supplierPayload(['name' => 'New', 'gst_no' => 'NOTAGST']))->assertSessionHasErrors('gst_no');
        $this->post(route('master.suppliers.store'), $this->supplierPayload(['name' => 'New', 'gst_no' => '24AAAAA0000A1Z5']))
            ->assertSessionHasErrors(['gst_no' => 'This GST number is already registered with another supplier.']);
        $this->post(route('master.suppliers.store'), $this->supplierPayload(['name' => 'existing']))->assertSessionHasErrors('name');
        $this->assertSame(1, Supplier::count());
    }

    public function test_supplier_contacts_are_created_updated_deleted_and_blank_rows_ignored(): void
    {
        $this->as('Manager');
        $this->post(route('master.suppliers.store'), $this->supplierPayload(['name' => 'Contact Sup', 'contacts' => [
            ['contact_person' => 'Ravi', 'mobile' => '9000000009', 'designation' => 'Sales'],
            ['contact_person' => '', 'mobile' => '', 'phone' => '', 'email' => ''],
        ]]))->assertRedirect();

        $s = Supplier::where('name', 'Contact Sup')->first();
        $this->assertSame(1, $s->contacts()->count());
        $c = $s->contacts()->first();
        $this->assertSame('Sales', $c->designation);

        $this->put(route('master.suppliers.update', $s), $this->supplierPayload(['name' => 'Contact Sup', 'contacts' => [
            ['id' => $c->id, 'contact_person' => 'Ravi K', 'email' => 'ravi@example.com'],
            ['contact_person' => 'Second'],
        ]]))->assertRedirect();
        $this->assertSame(2, $s->contacts()->count());
        $this->assertSame('Ravi K', $c->fresh()->contact_person);

        $this->put(route('master.suppliers.update', $s), $this->supplierPayload(['name' => 'Contact Sup', 'contacts' => [
            ['id' => $c->id, '_delete' => 1],
        ]]))->assertRedirect();
        $this->assertSame(1, $s->contacts()->count());
        $this->assertSame('Second', $s->contacts()->first()->contact_person);
    }

    public function test_supplier_json_store_index_filters_and_screens(): void
    {
        $this->as('Manager');
        $this->postJson(route('master.suppliers.store'), $this->supplierPayload(['name' => 'Json Sup']))
            ->assertOk()->assertJsonPath('data.name', 'Json Sup');
        $this->post(route('master.suppliers.store'), $this->supplierPayload(['name' => 'Cash Sup', 'purchase_mode' => 'Cash', 'status' => 0]));

        $names = fn (array $q) => $this->get(route('master.suppliers.index', $q))->assertOk()->viewData('suppliers')->pluck('name')->all();
        $this->assertSame(['Cash Sup'], $names(['purchase_mode' => 'Cash']));
        $this->assertSame(['Json Sup'], $names(['status' => 1]));
        $this->assertSame(['Json Sup'], $names(['search' => 'Json']));
        $this->assertSame(['Cash Sup', 'Json Sup'], $names(['purchase_type' => 'Local']));

        $s = Supplier::where('name', 'Json Sup')->first();
        $this->get(route('master.suppliers.create'))->assertOk();
        $this->get(route('master.suppliers.edit', $s))->assertOk();
        $this->delete(route('master.suppliers.destroy', $s))->assertSessionHas('error');
        $this->assertNotNull(Supplier::find($s->id));
    }

    public function test_supplier_credit_terms_owner_only_on_update(): void
    {
        $s = Supplier::create(['name' => 'Cred Sup', 'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit', 'credit_limit' => 500, 'credit_balance' => 100, 'credit_days' => 30, 'status' => 1, 'gst_type' => 'Regular', 'mail_type' => 'None']);

        $this->as('Manager');
        foreach (['credit_limit' => 900, 'credit_balance' => 5, 'credit_days' => 60] as $field => $val) {
            $this->put(route('master.suppliers.update', $s), $this->supplierPayload(['name' => 'Cred Sup', $field => $val]))
                ->assertSessionHasErrors([$field => 'Only an Owner can change credit terms.']);
        }
        $this->assertEquals(500, $s->fresh()->credit_limit);

        $this->put(route('master.suppliers.update', $s), $this->supplierPayload(['name' => 'Cred Sup Renamed']))->assertSessionHasNoErrors();
        $this->assertSame('Cred Sup Renamed', $s->fresh()->name);

        $this->as('Owner');
        $this->put(route('master.suppliers.update', $s), $this->supplierPayload(['name' => 'Cred Sup Renamed', 'credit_limit' => 900]))->assertSessionHasNoErrors();
        $this->assertEquals(900, $s->fresh()->credit_limit);
    }
}
