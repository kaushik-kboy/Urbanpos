<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\LoyaltyProgram;
use App\Models\PetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterALoyaltyUserItemCustomerTest extends TestCase
{
    use RefreshDatabase, MasterAHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnFirstUser();
    }

    // ------------------------------------------------------------ Loyalty programs

    private function lp(array $o = []): array
    {
        return array_merge([
            'name' => 'Gold Club', 'start_date' => '2026-01-01', 'based_on' => 'Bill Amount',
            'min_points_redeem' => 50, 'amount_per_point' => 1, 'points_per_hundred' => 2,
        ], $o);
    }

    public function test_loyalty_store_with_rules_defaults_and_zero_point_rules_skipped(): void
    {
        $this->asRole('Manager');
        $this->post(route('master.loyalty-programs.store'), $this->lp([
            'end_date' => '2026-12-31',
            'rules' => [
                ['min_bill_amount' => 0, 'max_bill_amount' => 500, 'points_earned' => 5],
                ['min_bill_amount' => 500, 'max_bill_amount' => '', 'points_earned' => 10],
                ['min_bill_amount' => 10, 'max_bill_amount' => 20, 'points_earned' => 0],
            ],
        ]))->assertRedirect(route('master.loyalty-programs.index'))->assertSessionHas('status', 'Loyalty Program created successfully.');

        $p = LoyaltyProgram::where('name', 'Gold Club')->firstOrFail();
        $this->assertTrue($p->roundoff);  // default true when omitted
        $this->assertTrue($p->status);
        $this->assertSame('2026-12-31', $p->end_date->toDateString());
        $rules = $p->rules;
        $this->assertCount(2, $rules); // zero-point slab dropped
        $this->assertEquals(5, $rules[0]->points_earned);
        $this->assertNull($rules[1]->max_bill_amount); // blank max => open-ended
    }

    public function test_loyalty_store_validation_errors(): void
    {
        $this->asRole('Manager');
        $bad = fn (array $o, $f) => $this->post(route('master.loyalty-programs.store'), $this->lp($o))->assertSessionHasErrors($f);
        $bad(['name' => ''], 'name');
        $bad(['start_date' => 'garbage'], 'start_date');
        $bad(['end_date' => '2025-12-31'], 'end_date'); // before start
        $bad(['min_points_redeem' => 0], 'min_points_redeem');
        $bad(['min_points_redeem' => 1.5], 'min_points_redeem');
        $bad(['amount_per_point' => 0], 'amount_per_point');
        $bad(['points_per_hundred' => -1], 'points_per_hundred');
        $bad(['based_on' => ''], 'based_on');
        $bad(['rules' => [['min_bill_amount' => 0]]], 'rules.0.points_earned');
        $bad(['rules' => [['points_earned' => 3]]], 'rules.0.min_bill_amount');
        $this->assertSame(0, LoyaltyProgram::where('name', 'Gold Club')->count());
    }

    public function test_loyalty_update_replaces_rules_and_honours_flags(): void
    {
        $this->asRole('Manager');
        $p = LoyaltyProgram::create($this->lp() + ['roundoff' => true, 'status' => true]);
        $p->rules()->create(['min_bill_amount' => 0, 'max_bill_amount' => 100, 'points_earned' => 1]);
        $p->rules()->create(['min_bill_amount' => 100, 'points_earned' => 2]);

        $this->put(route('master.loyalty-programs.update', $p), $this->lp([
            'name' => 'Gold Renamed', 'roundoff' => 0, 'status' => 0,
            'rules' => [['min_bill_amount' => 0, 'max_bill_amount' => 999, 'points_earned' => 7]],
        ]))->assertRedirect(route('master.loyalty-programs.index'))->assertSessionHas('status', 'Loyalty Program updated successfully.');

        $p->refresh();
        $this->assertSame('Gold Renamed', $p->name);
        $this->assertFalse($p->roundoff);
        $this->assertFalse($p->status);
        $this->assertCount(1, $p->rules);
        $this->assertEquals(7, $p->rules->first()->points_earned);

        // no rules => all removed
        $this->put(route('master.loyalty-programs.update', $p), $this->lp(['name' => 'Gold Renamed']))->assertRedirect();
        $this->assertCount(0, $p->fresh()->rules);
        $this->assertTrue($p->fresh()->status); // omitted => default true

        $this->put(route('master.loyalty-programs.update', $p), $this->lp(['name' => '']))->assertSessionHasErrors('name');
        $this->assertSame('Gold Renamed', $p->fresh()->name);
    }

    public function test_loyalty_destroy_deactivates_instead_of_deleting_and_forms_render(): void
    {
        $this->asRole('Manager');
        $p = LoyaltyProgram::create($this->lp() + ['roundoff' => true, 'status' => true]);
        $this->delete(route('master.loyalty-programs.destroy', $p))->assertRedirect(route('master.loyalty-programs.index'))->assertSessionHas('status', 'Loyalty Program deactivated.');
        $this->assertDatabaseHas('loyalty_programs', ['id' => $p->id, 'status' => 0]);

        $r = $this->get(route('master.loyalty-programs.create'))->assertOk();
        $prog = $r->viewData('program');
        $this->assertSame(50, $prog->min_points_redeem);
        $this->assertSame('Bill Amount', $prog->based_on);
        $this->get(route('master.loyalty-programs.edit', $p))->assertOk()->assertSee('Gold Club');
    }

    public function test_loyalty_index_filters_and_cashier_forbidden(): void
    {
        $this->asRole('Manager');
        LoyaltyProgram::create($this->lp(['name' => 'Active One']) + ['roundoff' => true, 'status' => true]);
        LoyaltyProgram::create($this->lp(['name' => 'Dormant One']) + ['roundoff' => true, 'status' => false]);
        $names = fn (array $q) => $this->get(route('master.loyalty-programs.index', $q))->assertOk()->viewData('programs')->pluck('name')->all();
        $this->assertSame(['Dormant One'], $names(['search' => 'Dormant']));
        $this->assertSame(['Active One'], $names(['status' => '1']));
        $this->assertSame(['Dormant One'], $names(['status' => '0']));
        $this->assertEqualsCanonicalizing(['Active One', 'Dormant One'], $names(['search' => 'One']));

        $this->asRole('Cashier');
        $this->post(route('master.loyalty-programs.store'), $this->lp(['name' => 'Cash']))->assertForbidden();
        $this->assertDatabaseMissing('loyalty_programs', ['name' => 'Cash']);
    }

    // ------------------------------------------------------------ Users

    private function userPayload(array $o = []): array
    {
        return array_merge([
            'name' => 'New Staff '.uniqid(), 'email' => uniqid().'@example.test', 'password' => 'secret123',
            'password_confirmation' => 'secret123', 'role' => 'Cashier',
        ], $o);
    }

    public function test_users_owner_creates_user_with_hashed_password_role_and_branch(): void
    {
        $this->asRole('Owner');
        $b = $this->makeBranch();
        $this->post(route('master.users.store'), $this->userPayload(['name' => 'Rita', 'email' => 'rita@example.test', 'branch_id' => $b->id, 'role' => 'Manager']))
            ->assertRedirect(route('master.users.index'))->assertSessionHas('status', 'User created successfully.');
        $u = User::where('email', 'rita@example.test')->firstOrFail();
        $this->assertTrue($u->hasRole('Manager'));
        $this->assertSame($b->id, $u->branch_id);
        $this->assertNotSame('secret123', $u->password);
        $this->assertTrue(\Hash::check('secret123', $u->password));
    }

    public function test_users_validation(): void
    {
        $this->asRole('Owner');
        $existing = User::factory()->create(['name' => 'Taken Name', 'email' => 'taken@example.test']);
        $bad = fn (array $o, $f) => $this->post(route('master.users.store'), $this->userPayload($o))->assertSessionHasErrors($f);
        $bad(['name' => 'Taken Name'], 'name');
        $bad(['email' => 'taken@example.test'], 'email');
        $bad(['email' => 'bad'], 'email');
        $bad(['password' => 'short', 'password_confirmation' => 'short'], 'password');
        $bad(['password_confirmation' => 'different1'], 'password');
        $bad(['password' => ''], 'password');
        $bad(['role' => 'Wizard'], 'role');
        $bad(['role' => ''], 'role');
        $bad(['branch_id' => 999999999], 'branch_id');
        $this->assertSame(0, User::where('email', 'like', '%@example.test')->where('id', '!=', $existing->id)->count());
    }

    public function test_users_update_keeps_password_when_blank_and_syncs_role_and_ignores_self_uniqueness(): void
    {
        $this->asRole('Owner');
        $u = User::factory()->create(['name' => 'Editable', 'email' => 'edit@example.test', 'password' => 'oldpassword1']);
        $u->assignRole('Cashier');
        $hash = $u->fresh()->password;

        $this->put(route('master.users.update', $u), ['name' => 'Editable', 'email' => 'edit@example.test', 'role' => 'Manager', 'password' => ''])
            ->assertRedirect(route('master.users.index'))->assertSessionHas('status', 'User updated successfully.');
        $u->refresh();
        $this->assertSame($hash, $u->password);
        $this->assertTrue($u->hasRole('Manager'));
        $this->assertFalse($u->hasRole('Cashier'));

        $this->put(route('master.users.update', $u), ['name' => 'Editable2', 'email' => 'edit@example.test', 'role' => 'Manager', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123'])->assertRedirect();
        $this->assertTrue(\Hash::check('brandnew123', $u->fresh()->password));
        $this->assertSame('Editable2', $u->fresh()->name);

        $other = User::factory()->create(['email' => 'other@example.test']);
        $this->put(route('master.users.update', $u), ['name' => 'Editable2', 'email' => 'other@example.test', 'role' => 'Manager'])->assertSessionHasErrors('email');
    }

    public function test_users_destroy_rules(): void
    {
        $owner = $this->asRole('Owner');
        $this->delete(route('master.users.destroy', $owner))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);

        // an Admin-role actor (Gate bypass) cannot delete the only Owner
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
        $this->delete(route('master.users.destroy', $owner))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);

        // with a second Owner, one can be deleted (and its roles detached)
        $owner2 = User::factory()->create();
        $owner2->assignRole('Owner');
        $this->delete(route('master.users.destroy', $owner2))->assertRedirect(route('master.users.index'))->assertSessionHas('status', 'User deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $owner2->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $owner2->id, 'model_type' => User::class]);

        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->delete(route('master.users.destroy', $cashier))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $cashier->id]);
    }

    public function test_users_index_filters_show_redirect_and_forms(): void
    {
        $owner = $this->asRole('Owner');
        $b = $this->makeBranch();
        $m = User::factory()->create(['name' => 'Zed Manager', 'email' => 'zed@example.test', 'branch_id' => $b->id]);
        $m->assignRole('Manager');
        $c = User::factory()->create(['name' => 'Amy Cashier', 'email' => 'amy@example.test']);
        $c->assignRole('Cashier');

        $names = fn (array $q) => $this->get(route('master.users.index', $q))->assertOk()->viewData('users')->pluck('name')->all();
        $this->assertSame(['Zed Manager'], $names(['search' => 'zed@']));
        $this->assertSame(['Amy Cashier'], $names(['search' => 'Amy']));
        $this->assertSame(['Zed Manager'], $names(['role' => 'Manager']));
        $this->assertSame(['Amy Cashier'], $names(['role' => 'Cashier']));
        $this->assertSame(['Zed Manager'], $names(['branch_id' => $b->id]));

        $this->get(route('master.users.show', $m))->assertRedirect(route('master.users.edit', $m));
        $this->get(route('master.users.create'))->assertOk();
        $r = $this->get(route('master.users.edit', $m))->assertOk();
        $this->assertContains('Cashier', $r->viewData('roles')->all());
    }

    public function test_users_manager_and_cashier_cannot_write_but_can_read(): void
    {
        $target = User::factory()->create(['name' => 'Target']);
        foreach (['Manager', 'Cashier'] as $role) {
            $this->asRole($role);
            $this->get(route('master.users.index'))->assertOk();
            $this->post(route('master.users.store'), $this->userPayload(['name' => 'Esc '.$role, 'role' => 'Owner']))->assertForbidden();
            $this->put(route('master.users.update', $target), ['name' => 'Target', 'email' => $target->email, 'role' => 'Owner'])->assertForbidden();
            $this->delete(route('master.users.destroy', $target))->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['name' => 'Esc Manager']);
        $this->assertFalse($target->fresh()->hasRole('Owner'));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    // ------------------------------------------------------------ Items

    private function itemPayload(array $o = []): array
    {
        return array_merge([
            'name' => 'Ctl Item '.uniqid(), 'product_type' => 'Standard',
            'cost_price' => 10, 'landing_cost' => 10, 'sell_price' => 15, 'mrp' => 20, 'status' => 1,
            'store_pickup' => 0, 'tax_inclusive' => 0, 'batch_expiry_details' => 'Not Required', 'allow_negative_stock' => 0,
        ], $o);
    }

    public function test_items_price_ordering_and_field_validation(): void
    {
        $this->asRole('Manager');
        $bad = fn (array $o, $f) => $this->post(route('master.items.store'), $this->itemPayload($o))->assertSessionHasErrors($f);
        $bad(['landing_cost' => 5], 'landing_cost');                 // landing < cost
        $bad(['sell_price' => 9], 'sell_price');                     // sell < landing
        $bad(['sell_price' => 25], 'sell_price');                    // sell > mrp
        $bad(['mrp' => 12], 'mrp');                                  // mrp < sell
        $bad(['hsn_code' => '12'], 'hsn_code');
        $bad(['hsn_code' => 'ABCD'], 'hsn_code');
        $bad(['batch_expiry_details' => 'Sometimes'], 'batch_expiry_details');
        $bad(['shelf_life_days' => -1], 'shelf_life_days');
        $bad(['brand_id' => 999999999], 'brand_id');
        $bad(['gst_tax_id' => 999999999], 'gst_tax_id');
        $bad(['name' => ''], 'name');
        $bad(['product_type' => ''], 'product_type');
        $this->assertSame(0, Item::where('name', 'like', 'Ctl Item%')->count());

        $ok = $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Ctl Good', 'hsn_code' => '230910', 'ean_upc_code' => 'EAN-1']));
        $ok->assertRedirect(route('master.items.index'))->assertSessionHas('status', 'Item created successfully.');
        $this->assertDatabaseHas('items', ['name' => 'Ctl Good', 'hsn_code' => '230910', 'ean_upc_code' => 'EAN-1']);
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Ctl Good']))->assertSessionHasErrors('name');
        $this->post(route('master.items.store'), $this->itemPayload(['ean_upc_code' => 'EAN-1']))->assertSessionHasErrors('ean_upc_code');
    }

    public function test_items_store_blank_optionals_become_null_and_update_is_owner_only(): void
    {
        $this->asRole('Manager');
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Ctl Blank', 'alias' => '', 'ean_upc_code' => '', 'brand_id' => '', 'hsn_code' => '', 'shelf_life_days' => '']))->assertSessionHasNoErrors();
        $i = Item::where('name', 'Ctl Blank')->firstOrFail();
        $this->assertNull($i->alias);
        $this->assertNull($i->brand_id);
        $this->assertNull($i->hsn_code);
        $this->assertNull($i->shelf_life_days);

        // Manager holds items.create only; repricing an existing item is Owner-only
        $this->put(route('master.items.update', $i), $this->itemPayload(['name' => 'Ctl Blank', 'sell_price' => 19]))->assertForbidden();
        $this->assertEquals(15, $i->fresh()->sell_price);
        $this->delete(route('master.items.destroy', $i))->assertForbidden();

        $this->asRole('Owner');
        $this->put(route('master.items.update', $i), $this->itemPayload(['name' => 'Ctl Blank', 'sell_price' => 19]))->assertRedirect(route('master.items.index'))->assertSessionHas('status', 'Item updated successfully.');
        $this->assertEquals(19, $i->fresh()->sell_price);
        $this->delete(route('master.items.destroy', $i))->assertRedirect(route('master.items.index'))->assertSessionHas('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
        $this->assertDatabaseHas('items', ['id' => $i->id]);
    }

    public function test_items_update_ean_unique_ignores_self_and_cashier_cannot_create(): void
    {
        $this->asRole('Owner');
        $a = Item::create($this->itemPayload(['name' => 'Ctl A', 'ean_upc_code' => 'E-A']));
        Item::create($this->itemPayload(['name' => 'Ctl B', 'ean_upc_code' => 'E-B']));
        $this->put(route('master.items.update', $a), $this->itemPayload(['name' => 'Ctl A', 'ean_upc_code' => 'E-A']))->assertSessionHasNoErrors();
        $this->put(route('master.items.update', $a), $this->itemPayload(['name' => 'Ctl A', 'ean_upc_code' => 'E-B']))->assertSessionHasErrors('ean_upc_code');

        $this->asRole('Cashier');
        $this->post(route('master.items.store'), $this->itemPayload(['name' => 'Ctl Cash']))->assertForbidden();
        $this->assertDatabaseMissing('items', ['name' => 'Ctl Cash']);
    }

    public function test_items_index_filters(): void
    {
        $this->asRole('Manager');
        $brand = Brand::create(['name' => 'IdxBrand', 'status' => true]);
        $cat = ItemCategory::create(['name' => 'IdxCat', 'is_mandatory' => false, 'status' => true]);
        $val = ItemCategoryValue::create(['item_category_id' => $cat->id, 'name' => 'IdxVal', 'status' => true, 'show_in_webstore' => false, 'sellquick_applicable' => false]);
        Item::create($this->itemPayload(['name' => 'Idx Alpha', 'brand_id' => $brand->id, 'category_value_id' => $val->id, 'item_code' => 'IDXCODE1', 'status' => true]));
        Item::create($this->itemPayload(['name' => 'Idx Beta', 'alias' => 'betaalias', 'ean_upc_code' => 'IDXEAN9', 'status' => false]));

        $names = fn (array $q) => $this->get(route('master.items.index', $q))->assertOk()->viewData('items')->pluck('name')->all();
        $this->assertSame(['Idx Alpha'], $names(['name' => 'IDXCODE1']));
        $this->assertSame(['Idx Beta'], $names(['name' => 'betaalias']));
        $this->assertSame(['Idx Beta'], $names(['name' => 'IDXEAN9']));
        $this->assertSame(['Idx Alpha'], $names(['brand_id' => $brand->id]));
        $this->assertSame(['Idx Alpha'], $names(['category_value_id' => $val->id]));
        $this->assertSame(['Idx Beta'], $names(['status' => '0']));
        $sup = \App\Models\Supplier::create(['name' => 'IdxSup', 'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit', 'credit_limit' => 0, 'credit_balance' => 0, 'credit_days' => 0, 'status' => true, 'gst_type' => 'Un Register', 'mail_type' => 'None']);
        Item::where('name', 'Idx Beta')->update(['supplier_id' => $sup->id]);
        $this->assertSame(['Idx Beta'], $names(['supplier_id' => $sup->id]));
    }

    public function test_items_generate_barcode_and_create_copy_from(): void
    {
        $this->asRole('Manager');
        $j = $this->getJson(route('master.items.generate-barcode'))->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($j->json('barcode'));
        $this->assertDatabaseMissing('items', ['ean_upc_code' => $j->json('barcode')]);

        $src = Item::create($this->itemPayload(['name' => 'Copy Src', 'ean_upc_code' => 'SRC-EAN', 'item_code' => 'SRCCODE', 'hsn_code' => '12', 'product_type' => 'Weird Old Type', 'batch_expiry_details' => 'Optional']));
        Item::create($this->itemPayload(['name' => 'Copy Src (Copy)']));
        $r = $this->get(route('master.items.create', ['copy_from' => $src->id]))->assertOk();
        $copy = $r->viewData('item');
        $this->assertSame('Copy Src (Copy 2)', $copy->name);       // (Copy) already taken
        $this->assertNotSame('SRC-EAN', $copy->ean_upc_code);       // fresh barcode
        $this->assertNull($copy->item_code);
        $this->assertNull($copy->hsn_code);                         // invalid HSN cleaned
        $this->assertSame('Standard', $copy->product_type);         // unknown type reset

        $this->assertNull($this->get(route('master.items.create', ['copy_from' => 987654321]))->assertOk()->viewData('item'));
        $this->assertNull($this->get(route('master.items.create'))->assertOk()->viewData('item'));
        $this->get(route('master.items.edit', $src))->assertOk()->assertSee('Copy Src');
    }

    // ------------------------------------------------------------ Customers

    private function custPayload(array $o = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'name' => 'Cust '.$n, 'sales_type' => 'Local', 'payment_mode' => 'Cash Only', 'credit_limit' => 100,
            'credit_balance' => 50, 'monthly_credit_balance' => 25, 'credit_days' => 10, 'status' => 1,
            'gst_type' => 'Un Register', 'sms_consent' => 0, 'mobile' => '90000'.str_pad((string) (10000 + $n), 5, '0', STR_PAD_LEFT),
            'customer_type' => 'RETAIL INVOICE',
        ], $o);
    }

    public function test_customers_index_category_filter_regression(): void
    {
        // Regression: index filtered on a non-existent `category_id` column => SQL error / HTTP 500.
        $this->asRole('Manager');
        $cat = CustomerCategory::create(['name' => 'VIP', 'status' => true, 'app_access' => false, 'enable_loyalty' => false, 'discount_percent' => 0]);
        $in = Customer::create($this->custPayload(['name' => 'In Cat', 'customer_category_id' => $cat->id]));
        Customer::create($this->custPayload(['name' => 'No Cat']));

        $r = $this->get(route('master.customers.index', ['category_id' => $cat->id]))->assertOk();
        $this->assertSame([$in->id], $r->viewData('customers')->pluck('id')->all());
        $this->assertSame('VIP', $r->viewData('categories')[$cat->id]);
    }

    public function test_customers_index_search_and_status_filters(): void
    {
        $this->asRole('Manager');
        Customer::create($this->custPayload(['name' => 'Searchy One', 'customer_code' => 'CC-77', 'phone' => '0261999', 'status' => true]));
        Customer::create($this->custPayload(['name' => 'Other Two', 'status' => false]));
        $names = fn (array $q) => $this->get(route('master.customers.index', $q))->assertOk()->viewData('customers')->pluck('name')->all();
        $this->assertSame(['Searchy One'], $names(['search' => 'CC-77']));
        $this->assertSame(['Searchy One'], $names(['search' => '0261999']));
        $this->assertSame(['Other Two'], $names(['search' => 'Other']));
        $this->assertSame(['Other Two'], $names(['status' => '0']));
        $this->assertSame(['Searchy One'], $names(['status' => '1']));
    }

    public function test_customers_store_json_html_validation_and_pets(): void
    {
        $this->asRole('Manager');
        $pt = PetType::create(['name' => 'Dog', 'status' => true]);

        $j = $this->postJson(route('master.customers.store'), $this->custPayload(['name' => 'Json Cust', 'mobile' => '9111111111']))->assertOk()->assertJson(['success' => true, 'message' => 'Customer created successfully.']);
        $this->assertSame('9111111111', $j->json('customer.mobile'));
        $this->assertSame('RETAIL INVOICE', $j->json('customer.customer_type'));

        $this->post(route('master.customers.store'), $this->custPayload(['name' => 'Pet Owner', 'mobile' => '9222222222', 'pets' => [
            ['pet_type_id' => $pt->id, 'name' => 'Rex', 'gender' => 'Male'],
            ['name' => '', 'pet_type_id' => '', 'breed_id' => ''], // blank row skipped
        ]]))->assertRedirect(route('master.customers.index'))->assertSessionHas('status', 'Customer created successfully.');
        $c = Customer::where('name', 'Pet Owner')->firstOrFail();
        $this->assertSame(1, $c->pets()->count());
        $this->assertSame('Rex', $c->pets()->first()->name);

        $bad = fn (array $o, $f) => $this->post(route('master.customers.store'), $this->custPayload($o))->assertSessionHasErrors($f);
        $bad(['mobile' => '9111111111'], 'mobile');   // duplicate
        $bad(['mobile' => '123'], 'mobile');
        $bad(['mobile' => ''], 'mobile');
        $bad(['sales_type' => 'Moon'], 'sales_type');
        $bad(['customer_type' => 'Alien'], 'customer_type');
        $bad(['payment_mode' => 'Barter'], 'payment_mode');
        $bad(['credit_limit' => -1], 'credit_limit');
        $bad(['credit_days' => 1.5], 'credit_days');
        $bad(['title' => 'Sir'], 'title');
        $bad(['gender' => 'Other'], 'gender');
        $bad(['gst_no' => 'BADGST'], 'gst_no');
        $bad(['email' => 'nope'], 'email');
        $bad(['name' => ''], 'name');
    }

    public function test_customers_show_and_edit_json_vs_html(): void
    {
        $this->asRole('Manager');
        $c = Customer::create($this->custPayload(['name' => 'Showy']));
        $this->getJson(route('master.customers.show', $c))->assertOk()->assertJson(['success' => true, 'customer' => ['name' => 'Showy']])->assertJsonStructure(['pets']);
        $this->get(route('master.customers.show', $c))->assertRedirect(route('master.customers.edit', $c));
        $this->getJson(route('master.customers.edit', $c))->assertOk()->assertJsonPath('customer.id', $c->id);
        $this->get(route('master.customers.edit', $c))->assertOk()->assertSee('Showy');
        $this->get(route('master.customers.create'))->assertOk();
    }

    public function test_customers_update_type_immutable_credit_owner_only_and_pet_sync(): void
    {
        $owner = $this->asRole('Owner');
        $pt = PetType::create(['name' => 'Cat', 'status' => true]);
        $c = Customer::create($this->custPayload(['name' => 'Upd', 'mobile' => '9333333333']));
        $c->pets()->create(['pet_type_id' => $pt->id, 'name' => 'Tom']);
        $c->pets()->create(['pet_type_id' => $pt->id, 'name' => 'Old']);
        [$tom, $old] = [$c->pets()->where('name', 'Tom')->first(), $c->pets()->where('name', 'Old')->first()];

        // Owner may change credit terms; sales/customer type are frozen after creation
        $res = $this->putJson(route('master.customers.update', $c), $this->custPayload([
            'name' => 'Upd', 'mobile' => '9333333333', 'credit_limit' => 999, 'sales_type' => 'Interstate', 'customer_type' => 'TAX INVOICE',
            'pets' => [['id' => $tom->id, 'name' => 'Tommy', 'pet_type_id' => $pt->id], ['id' => $old->id, '_delete' => 1], ['name' => 'Fresh', 'pet_type_id' => $pt->id]],
        ]))->assertOk()->assertJson(['success' => true, 'message' => 'Customer updated successfully.']);
        $this->assertSame('Upd (9333333333)', $res->json('customer.text'));
        $c->refresh();
        $this->assertEquals(999, $c->credit_limit);
        $this->assertSame('Local', $c->sales_type);
        $this->assertSame('RETAIL INVOICE', $c->customer_type);
        $this->assertEqualsCanonicalizing(['Tommy', 'Fresh'], $c->pets()->pluck('name')->all());

        // Manager: unchanged credit fields pass, changed ones are rejected
        $this->asRole('Manager');
        $same = ['name' => 'Renamed', 'mobile' => '9333333333', 'credit_limit' => 999, 'credit_balance' => 50, 'monthly_credit_balance' => 25, 'credit_days' => 10];
        $this->put(route('master.customers.update', $c), $this->custPayload($same))->assertRedirect(route('master.customers.index'))->assertSessionHas('status', 'Customer updated successfully.');
        $this->assertSame('Renamed', $c->fresh()->name);
        foreach (['credit_limit' => 1, 'credit_balance' => 1, 'monthly_credit_balance' => 1, 'credit_days' => 99] as $field => $val) {
            $this->put(route('master.customers.update', $c), $this->custPayload(array_merge($same, [$field => $val])))->assertSessionHasErrors($field);
        }
        $this->assertEquals(999, $c->fresh()->credit_limit);
        $this->assertEquals(50, $c->fresh()->credit_balance);
        $this->assertSame(10, (int) $c->fresh()->credit_days);
    }

    public function test_customers_destroy_blocked_and_cashier_forbidden(): void
    {
        $c = Customer::create($this->custPayload(['name' => 'Keepy']));
        $this->asRole('Manager');
        $this->delete(route('master.customers.destroy', $c))->assertRedirect(route('master.customers.index'))->assertSessionHas('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
        $this->assertDatabaseHas('customers', ['id' => $c->id]);

        $this->asRole('Cashier');
        $this->post(route('master.customers.store'), $this->custPayload(['name' => 'Nope']))->assertForbidden();
        $this->put(route('master.customers.update', $c), $this->custPayload(['name' => 'Nope']))->assertForbidden();
        $this->assertSame('Keepy', $c->fresh()->name);
    }
}
