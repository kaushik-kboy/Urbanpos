<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Color;
use App\Models\GstTax;
use App\Models\GstType;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ProductType;
use App\Models\Register;
use App\Models\TenderType;
use App\Models\TenderTypeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterASimpleMastersTest extends TestCase
{
    use RefreshDatabase, MasterAHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnFirstUser();
    }

    private const DELETE_MSG = 'Master records cannot be deleted. You can set status to Inactive instead.';

    // ------------------------------------------------------------ Colors

    public function test_colors_crud_unique_name_and_no_delete(): void
    {
        $this->asRole('Manager');
        $this->post(route('master.colors.store'), ['name' => 'Crimson', 'status' => 1])->assertRedirect(route('master.colors.index'))->assertSessionHas('status', 'Color created successfully.');
        $c = Color::where('name', 'Crimson')->firstOrFail();
        $this->assertTrue($c->status);

        $this->post(route('master.colors.store'), ['name' => 'Crimson', 'status' => 1])->assertSessionHasErrors('name');
        $this->post(route('master.colors.store'), ['status' => 1])->assertSessionHasErrors('name');
        $this->post(route('master.colors.store'), ['name' => 'NoStatus'])->assertSessionHasErrors('status');
        $this->assertSame(1, Color::where('name', 'Crimson')->count());

        // update keeps own name (ignore self) but cannot collide with another
        Color::create(['name' => 'Teal', 'status' => true]);
        $this->put(route('master.colors.update', $c), ['name' => 'Crimson', 'status' => 0])->assertRedirect(route('master.colors.index'));
        $this->assertFalse($c->fresh()->status);
        $this->put(route('master.colors.update', $c), ['name' => 'Teal', 'status' => 0])->assertSessionHasErrors('name');
        $this->assertSame('Crimson', $c->fresh()->name);

        $this->get(route('master.colors.index'))->assertOk()->assertSee('Crimson')->assertSee('Teal');
        $this->get(route('master.colors.create'))->assertOk();
        $this->get(route('master.colors.edit', $c))->assertOk()->assertSee('Crimson');

        $this->delete(route('master.colors.destroy', $c))->assertRedirect(route('master.colors.index'))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('colors', ['id' => $c->id]);
    }

    public function test_colors_cashier_forbidden_writes_but_reads_allowed(): void
    {
        $c = Color::create(['name' => 'Ivory', 'status' => true]);
        $this->asRole('Cashier');
        $this->get(route('master.colors.index'))->assertOk();
        $this->post(route('master.colors.store'), ['name' => 'X', 'status' => 1])->assertForbidden();
        $this->put(route('master.colors.update', $c), ['name' => 'Y', 'status' => 1])->assertForbidden();
        $this->delete(route('master.colors.destroy', $c))->assertForbidden();
        $this->assertSame('Ivory', $c->fresh()->name);
    }

    // ------------------------------------------------------------ Product types & GST types (search + ajax)

    public function test_product_types_search_ajax_store_validation_and_update(): void
    {
        $this->asRole('Manager');
        ProductType::create(['name' => 'Service Pack', 'code' => 'SPK', 'description' => 'bundle of services', 'status' => true]);
        ProductType::create(['name' => 'Widget', 'code' => 'WDG', 'description' => 'plain', 'status' => true]);

        $r = $this->get(route('master.product-types.index', ['search' => 'SPK']))->assertOk();
        $this->assertSame(['Service Pack'], $r->viewData('productTypes')->pluck('name')->all());
        $r = $this->get(route('master.product-types.index', ['search' => 'bundle']))->assertOk();
        $this->assertSame(['Service Pack'], $r->viewData('productTypes')->pluck('name')->all());
        $r = $this->get(route('master.product-types.index', ['search' => 'wid']))->assertOk();
        $this->assertSame(['Widget'], $r->viewData('productTypes')->pluck('name')->all());

        $j = $this->postJson(route('master.product-types.store'), ['name' => 'Ajax Type', 'status' => 1])->assertOk()->assertJson(['success' => true, 'message' => 'Product Type created successfully.']);
        $this->assertSame('Ajax Type', $j->json('data.name'));
        $this->assertDatabaseHas('product_types', ['id' => $j->json('data.id'), 'name' => 'Ajax Type']);

        $this->postJson(route('master.product-types.store'), ['name' => 'Ajax Type', 'status' => 1])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->post(route('master.product-types.store'), ['name' => str_repeat('a', 256), 'status' => 1])->assertSessionHasErrors('name');
        $this->post(route('master.product-types.store'), ['name' => 'Coded', 'code' => str_repeat('c', 51), 'status' => 1])->assertSessionHasErrors('code');

        $this->post(route('master.product-types.store'), ['name' => 'Html Type', 'status' => 1])->assertRedirect(route('master.product-types.index'))->assertSessionHas('status', 'Product Type created successfully.');

        $w = ProductType::where('name', 'Widget')->first();
        $this->put(route('master.product-types.update', $w), ['name' => 'Widget2', 'code' => 'W2', 'status' => 0])->assertRedirect(route('master.product-types.index'))->assertSessionHas('status', 'Product Type updated successfully.');
        $w->refresh();
        $this->assertSame('Widget2', $w->name);
        $this->assertFalse((bool) $w->status);
        $this->put(route('master.product-types.update', $w), ['name' => 'Service Pack', 'status' => 1])->assertSessionHasErrors('name');

        $this->get(route('master.product-types.create'))->assertOk();
        $this->get(route('master.product-types.edit', $w))->assertOk()->assertSee('Widget2');
        $this->delete(route('master.product-types.destroy', $w))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('product_types', ['id' => $w->id]);
    }

    public function test_product_types_cashier_forbidden(): void
    {
        $this->asRole('Cashier');
        $this->post(route('master.product-types.store'), ['name' => 'Nope', 'status' => 1])->assertForbidden();
        $this->assertDatabaseMissing('product_types', ['name' => 'Nope']);
    }

    public function test_gst_types_search_ajax_store_update_and_permissions(): void
    {
        $this->asRole('Owner');
        GstType::create(['name' => 'Regular Test', 'code' => 'ZQREG', 'description' => 'zzregistered dealer', 'status' => true]);
        GstType::create(['name' => 'Composition Test', 'code' => 'ZQCMP', 'description' => 'small', 'status' => true]);

        $r = $this->get(route('master.gst-types.index', ['search' => 'ZQCMP']))->assertOk();
        $this->assertSame(['Composition Test'], $r->viewData('gstTypes')->pluck('name')->all());
        $r = $this->get(route('master.gst-types.index', ['search' => 'zzregistered']))->assertOk();
        $this->assertSame(['Regular Test'], $r->viewData('gstTypes')->pluck('name')->all());
        $r = $this->get(route('master.gst-types.index', ['search' => 'Composition Te']))->assertOk();
        $this->assertSame(['Composition Test'], $r->viewData('gstTypes')->pluck('name')->all());

        $j = $this->postJson(route('master.gst-types.store'), ['name' => 'Ajax GT', 'status' => 1])->assertOk()->assertJson(['success' => true, 'message' => 'GST Type created successfully.']);
        $this->assertDatabaseHas('gst_types', ['id' => $j->json('data.id'), 'name' => 'Ajax GT']);
        $this->postJson(route('master.gst-types.store'), ['name' => 'Ajax GT', 'status' => 1])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->post(route('master.gst-types.store'), ['name' => 'Desc', 'description' => str_repeat('d', 501), 'status' => 1])->assertSessionHasErrors('description');
        $this->post(route('master.gst-types.store'), ['name' => 'Html GT', 'status' => 1])->assertRedirect(route('master.gst-types.index'))->assertSessionHas('status', 'GST Type created successfully.');

        $g = GstType::where('name', 'Regular Test')->first();
        $this->put(route('master.gst-types.update', $g), ['name' => 'Regular Test', 'code' => 'RG2', 'status' => 0])->assertRedirect(route('master.gst-types.index'));
        $g->refresh();
        $this->assertSame('RG2', $g->code);
        $this->assertFalse((bool) $g->status);
        $this->put(route('master.gst-types.update', $g), ['name' => 'Composition Test', 'status' => 1])->assertSessionHasErrors('name');

        $this->get(route('master.gst-types.create'))->assertOk();
        $this->get(route('master.gst-types.edit', $g))->assertOk();
        $this->delete(route('master.gst-types.destroy', $g))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('gst_types', ['id' => $g->id]);
    }

    public function test_gst_types_manager_allowed_cashier_forbidden(): void
    {
        $this->asRole('Manager');
        $this->post(route('master.gst-types.store'), ['name' => 'MgrGT', 'status' => 1])->assertRedirect();
        $this->assertDatabaseHas('gst_types', ['name' => 'MgrGT']);
        $this->asRole('Cashier');
        $this->post(route('master.gst-types.store'), ['name' => 'CashGT', 'status' => 1])->assertForbidden();
        $this->assertDatabaseMissing('gst_types', ['name' => 'CashGT']);
    }

    // ------------------------------------------------------------ GST taxes (Owner only + audit)

    public function test_gst_taxes_owner_crud_validation_and_audit_on_update(): void
    {
        $this->asRole('Owner');
        $this->post(route('master.gst-taxes.store'), ['description' => 'GST 18%', 'percentage' => 18, 'status' => 1])->assertRedirect(route('master.gst-taxes.index'))->assertSessionHas('status', 'GST tax created successfully.');
        $t = GstTax::where('description', 'GST 18%')->firstOrFail();
        $this->assertEquals(18, $t->percentage);

        $this->post(route('master.gst-taxes.store'), ['description' => 'GST 18%', 'percentage' => 5, 'status' => 1])->assertSessionHasErrors('description');
        $this->post(route('master.gst-taxes.store'), ['description' => 'Too High', 'percentage' => 101, 'status' => 1])->assertSessionHasErrors('percentage');
        $this->post(route('master.gst-taxes.store'), ['description' => 'Negative', 'percentage' => -1, 'status' => 1])->assertSessionHasErrors('percentage');
        $this->post(route('master.gst-taxes.store'), ['percentage' => 5, 'status' => 1])->assertSessionHasErrors('description');
        $this->post(route('master.gst-taxes.store'), ['description' => 'Null Pct', 'status' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gst_taxes', ['description' => 'Null Pct']);

        $this->put(route('master.gst-taxes.update', $t), ['description' => 'GST 18%', 'percentage' => 12, 'status' => 1])->assertRedirect(route('master.gst-taxes.index'))->assertSessionHas('status', 'GST tax updated successfully.');
        $this->assertEquals(12, $t->fresh()->percentage);
        $log = \App\Models\AuditLog::where('auditable_type', GstTax::class)->where('auditable_id', $t->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(18, (float) $log->old_values['percentage']);
        $this->assertEquals(12, (float) $log->new_values['percentage']);

        $this->get(route('master.gst-taxes.index'))->assertOk()->assertSee('GST 18%');
        $this->get(route('master.gst-taxes.create'))->assertOk();
        $this->get(route('master.gst-taxes.edit', $t))->assertOk();
        $this->delete(route('master.gst-taxes.destroy', $t))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('gst_taxes', ['id' => $t->id]);
    }

    public function test_gst_taxes_manager_and_cashier_forbidden(): void
    {
        $t = GstTax::create(['description' => 'GST 5%', 'percentage' => 5, 'status' => true]);
        foreach (['Manager', 'Cashier'] as $role) {
            $this->asRole($role);
            $this->post(route('master.gst-taxes.store'), ['description' => 'Sneak '.$role, 'percentage' => 1, 'status' => 1])->assertForbidden();
            $this->put(route('master.gst-taxes.update', $t), ['description' => 'GST 5%', 'percentage' => 0, 'status' => 1])->assertForbidden();
            $this->delete(route('master.gst-taxes.destroy', $t))->assertForbidden();
        }
        $this->assertEquals(5, $t->fresh()->percentage);
        $this->assertSame(0, \App\Models\AuditLog::where('auditable_type', GstTax::class)->count());
    }

    // ------------------------------------------------------------ Item category values

    private function cat(string $name = 'Dept'): ItemCategory
    {
        return ItemCategory::firstOrCreate(['name' => $name], ['is_mandatory' => false, 'status' => true]);
    }

    public function test_item_category_values_index_filters(): void
    {
        $this->asRole('Manager');
        $c1 = $this->cat('CatOne');
        $c2 = $this->cat('CatTwo');
        ItemCategoryValue::create(['item_category_id' => $c1->id, 'name' => 'Alpha Food', 'status' => true, 'show_in_webstore' => false, 'sellquick_applicable' => false]);
        ItemCategoryValue::create(['item_category_id' => $c1->id, 'name' => 'Beta Toys', 'status' => false, 'show_in_webstore' => false, 'sellquick_applicable' => false]);
        ItemCategoryValue::create(['item_category_id' => $c2->id, 'name' => 'Alpha Other', 'status' => true, 'show_in_webstore' => false, 'sellquick_applicable' => false]);

        $names = fn (array $q) => $this->get(route('master.item-category-values.index', $q))->assertOk()->viewData('itemCategoryValues')->pluck('name')->all();
        $this->assertSame(['Alpha Food', 'Alpha Other', 'Beta Toys'], $names([]));
        $this->assertSame(['Alpha Food', 'Alpha Other'], $names(['name' => 'Alpha']));
        $this->assertSame(['Alpha Food', 'Beta Toys'], $names(['category_id' => $c1->id]));
        $this->assertSame(['Beta Toys'], $names(['status' => '0']));
        $this->assertSame(['Alpha Food', 'Alpha Other'], $names(['status' => '1']));
        $this->assertSame(['Alpha Food', 'Alpha Other', 'Beta Toys'], $names(['status' => 'bogus'])); // ignored
        $this->assertSame(['Alpha Food'], $names(['name' => 'Alpha', 'category_id' => $c1->id, 'status' => '1']));
        $this->assertArrayHasKey($c1->id, $this->get(route('master.item-category-values.index'))->viewData('itemCategories')->all());
    }

    public function test_item_category_values_store_defaults_json_and_unique_per_category(): void
    {
        $this->asRole('Manager');
        $c1 = $this->cat('CatOne');
        $c2 = $this->cat('CatTwo');

        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c1->id, 'name' => 'Dry Food'])->assertRedirect(route('master.item-category-values.index'))->assertSessionHas('status', 'Item category value created successfully.');
        $v = ItemCategoryValue::where('name', 'Dry Food')->firstOrFail();
        $this->assertTrue($v->status);                 // defaults status=1
        $this->assertFalse($v->show_in_webstore);      // defaults 0
        $this->assertFalse($v->sellquick_applicable);

        // same name in same category rejected, in another category accepted
        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c1->id, 'name' => 'Dry Food'])->assertSessionHasErrors('name');
        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c2->id, 'name' => 'Dry Food'])->assertSessionHasNoErrors();
        $this->assertSame(2, ItemCategoryValue::where('name', 'Dry Food')->count());

        $j = $this->postJson(route('master.item-category-values.store'), ['item_category_id' => $c1->id, 'name' => 'Json Val', 'show_in_webstore' => 1, 'allowed_qty_ml' => 250])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Category value created successfully.']);
        $this->assertSame($c1->id, $j->json('data.item_category_id'));
        $this->assertDatabaseHas('item_category_values', ['id' => $j->json('data.id'), 'show_in_webstore' => 1, 'allowed_qty_ml' => 250]);

        // validation
        $this->post(route('master.item-category-values.store'), ['name' => 'X'])->assertSessionHasErrors('item_category_id');
        $this->post(route('master.item-category-values.store'), ['item_category_id' => 999999999, 'name' => 'X'])->assertSessionHasErrors('item_category_id');
        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c1->id])->assertSessionHasErrors('name');
        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c1->id, 'name' => 'Q', 'allowed_qty_ml' => 'abc'])->assertSessionHasErrors('allowed_qty_ml');
    }

    public function test_item_category_values_update_edit_create_and_destroy(): void
    {
        $this->asRole('Manager');
        $c1 = $this->cat('CatOne');
        $a = ItemCategoryValue::create(['item_category_id' => $c1->id, 'name' => 'Aaa', 'status' => true, 'show_in_webstore' => false, 'sellquick_applicable' => false]);
        ItemCategoryValue::create(['item_category_id' => $c1->id, 'name' => 'Bbb', 'status' => true, 'show_in_webstore' => false, 'sellquick_applicable' => false]);

        $this->put(route('master.item-category-values.update', $a), ['item_category_id' => $c1->id, 'name' => 'Aaa', 'status' => 0, 'sellquick_applicable' => 1])
            ->assertRedirect(route('master.item-category-values.index'))->assertSessionHas('status', 'Item category value updated successfully.');
        $a->refresh();
        $this->assertFalse($a->status);
        $this->assertTrue($a->sellquick_applicable);
        $this->put(route('master.item-category-values.update', $a), ['item_category_id' => $c1->id, 'name' => 'Bbb'])->assertSessionHasErrors('name');

        $this->get(route('master.item-category-values.create'))->assertOk();
        $this->get(route('master.item-category-values.edit', $a))->assertOk()->assertSee('Aaa');
        $this->delete(route('master.item-category-values.destroy', $a))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('item_category_values', ['id' => $a->id]);
    }

    public function test_item_category_values_cashier_forbidden(): void
    {
        $c = $this->cat('CatOne');
        $this->asRole('Cashier');
        $this->post(route('master.item-category-values.store'), ['item_category_id' => $c->id, 'name' => 'Nope'])->assertForbidden();
        $this->assertDatabaseMissing('item_category_values', ['name' => 'Nope']);
    }

    // ------------------------------------------------------------ Registers

    private function regPayload(Branch $b, array $o = []): array
    {
        return array_merge(['branch_id' => $b->id, 'name' => 'Counter 1', 'status' => 'Active', 'product_type' => 'Standard', 'inv_seq_no' => 1, 'online_sales_allowed' => 0], $o);
    }

    public function test_registers_store_update_validation_and_unique_per_branch(): void
    {
        $this->asRole('Manager');
        $b1 = $this->makeBranch();
        $b2 = $this->makeBranch();

        $this->post(route('master.registers.store'), $this->regPayload($b1, ['device_id' => 'DEV1', 'register_prefix' => 'R1']))->assertRedirect(route('master.registers.index'))->assertSessionHas('status', 'Register created successfully.');
        $reg = Register::where('name', 'Counter 1')->firstOrFail();
        $this->assertSame($b1->id, $reg->branch_id);
        $this->assertSame('DEV1', $reg->device_id);

        $this->post(route('master.registers.store'), $this->regPayload($b1))->assertSessionHasErrors('name');
        $this->post(route('master.registers.store'), $this->regPayload($b2))->assertSessionHasNoErrors(); // same name other branch ok
        $this->assertSame(2, Register::where('name', 'Counter 1')->count());

        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'C2', 'status' => 'Broken']))->assertSessionHasErrors('status');
        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'C3', 'inv_seq_no' => 0]))->assertSessionHasErrors('inv_seq_no');
        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'C4', 'branch_id' => 999999999]))->assertSessionHasErrors('branch_id');
        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'C5', 'product_type' => '']))->assertSessionHasErrors('product_type');
        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'C6', 'online_sales_allowed' => 'maybe']))->assertSessionHasErrors('online_sales_allowed');
        $this->post(route('master.registers.store'), $this->regPayload($b1, ['name' => 'Yet', 'status' => 'Yet to Active']))->assertSessionHasNoErrors();

        $this->put(route('master.registers.update', $reg), $this->regPayload($b1, ['status' => 'Inactive', 'inv_seq_no' => 55, 'online_sales_allowed' => 1]))->assertRedirect(route('master.registers.index'))->assertSessionHas('status', 'Register updated successfully.');
        $reg->refresh();
        $this->assertSame('Inactive', $reg->status);
        $this->assertSame(55, (int) $reg->inv_seq_no);
        $this->assertTrue($reg->online_sales_allowed);

        $this->get(route('master.registers.index'))->assertOk()->assertSee('Counter 1');
        $this->get(route('master.registers.create'))->assertOk();
        $this->get(route('master.registers.edit', $reg))->assertOk();
        $this->delete(route('master.registers.destroy', $reg))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('registers', ['id' => $reg->id]);
    }

    public function test_registers_branch_scoped_manager_cannot_write_other_branch_and_cashier_forbidden(): void
    {
        $mine = $this->makeBranch();
        $other = $this->makeBranch();
        $this->asRole('Manager', $mine->id);
        $this->post(route('master.registers.store'), $this->regPayload($other, ['name' => 'Cross']))->assertForbidden();
        $this->assertDatabaseMissing('registers', ['name' => 'Cross']);
        $this->post(route('master.registers.store'), $this->regPayload($mine, ['name' => 'Own']))->assertRedirect();
        $this->assertDatabaseHas('registers', ['name' => 'Own', 'branch_id' => $mine->id]);

        $foreign = Register::create($this->regPayload($other, ['name' => 'Foreign']));
        $this->put(route('master.registers.update', $foreign), ['name' => 'NoBody'])->assertForbidden(); // route model's branch is not the user's
        $this->assertSame('Foreign', $foreign->fresh()->name);
        $this->assertSame($other->id, $foreign->fresh()->branch_id);

        $this->asRole('Cashier');
        $this->post(route('master.registers.store'), $this->regPayload($mine, ['name' => 'CashReg']))->assertForbidden();
    }

    // ------------------------------------------------------------ Tender types

    private function ttPayload(array $o = []): array
    {
        return array_merge(['name' => 'Cash Main', 'status' => 1, 'type' => 'Cash', 'mode' => 'Manual', 'service_applicable' => 0, 'mandate_refno' => 0, 'service_charge_perc' => 0], $o);
    }

    public function test_tender_types_store_update_validation(): void
    {
        $this->asRole('Manager');
        $b = $this->makeBranch();
        $this->post(route('master.tender-types.store'), $this->ttPayload(['branch_id' => $b->id]))->assertRedirect(route('master.tender-types.index'))->assertSessionHas('status', 'Tender type created successfully.');
        $t = TenderType::where('name', 'Cash Main')->firstOrFail();
        $this->assertSame($b->id, $t->branch_id);

        $this->post(route('master.tender-types.store'), $this->ttPayload())->assertSessionHasErrors('name');
        $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => 'Bad Type', 'type' => 'Crypto']))->assertSessionHasErrors('type');
        foreach (['Card', 'Coupon', 'Wallet', 'Credit', 'Finance'] as $ok) {
            $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => "T $ok", 'type' => $ok]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('tender_types', ['name' => "T $ok", 'type' => $ok]);
        }
        $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => 'Over', 'service_charge_perc' => 100.5]))->assertSessionHasErrors('service_charge_perc');
        $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => 'Neg', 'service_charge_perc' => -1]))->assertSessionHasErrors('service_charge_perc');
        $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => 'Br', 'branch_id' => 999999999]))->assertSessionHasErrors('branch_id');
        $this->post(route('master.tender-types.store'), ['name' => 'Missing'])->assertSessionHasErrors(['status', 'type', 'mode', 'service_applicable', 'mandate_refno', 'service_charge_perc']);

        $this->put(route('master.tender-types.update', $t), $this->ttPayload(['type' => 'Card', 'service_applicable' => 1, 'service_charge_perc' => 2.5, 'status' => 0]))->assertRedirect(route('master.tender-types.index'))->assertSessionHas('status', 'Tender type updated successfully.');
        $t->refresh();
        $this->assertSame('Card', $t->type);
        $this->assertTrue($t->service_applicable);
        $this->assertEquals(2.5, $t->service_charge_perc);
        $this->assertFalse($t->status);
        $this->put(route('master.tender-types.update', $t), $this->ttPayload(['name' => 'T Card']))->assertSessionHasErrors('name');

        $this->get(route('master.tender-types.index'))->assertOk()->assertSee('Cash Main');
        $this->get(route('master.tender-types.create'))->assertOk();
        $this->get(route('master.tender-types.edit', $t))->assertOk();
        $this->delete(route('master.tender-types.destroy', $t))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('tender_types', ['id' => $t->id]);
    }

    public function test_tender_types_cashier_forbidden(): void
    {
        $this->asRole('Cashier');
        $this->post(route('master.tender-types.store'), $this->ttPayload(['name' => 'Cash Nope']))->assertForbidden();
        $this->assertDatabaseMissing('tender_types', ['name' => 'Cash Nope']);
    }

    // ------------------------------------------------------------ Tender type values

    public function test_tender_type_values_store_update_unique_per_type(): void
    {
        $this->asRole('Manager');
        $t1 = TenderType::create($this->ttPayload(['name' => 'TT1']));
        $t2 = TenderType::create($this->ttPayload(['name' => 'TT2']));
        $b = $this->makeBranch();

        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t1->id, 'name' => 'Visa', 'status' => 1, 'group_ledger' => 'Bank', 'branch_id' => $b->id])
            ->assertRedirect(route('master.tender-type-values.index'))->assertSessionHas('status', 'Tender type value created successfully.');
        $v = TenderTypeValue::where('name', 'Visa')->firstOrFail();
        $this->assertSame($t1->id, $v->tender_type_id);
        $this->assertSame('Bank', $v->group_ledger);
        $this->assertSame($b->id, $v->branch_id);

        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t1->id, 'name' => 'Visa', 'status' => 1])->assertSessionHasErrors('name');
        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t2->id, 'name' => 'Visa', 'status' => 1])->assertSessionHasNoErrors();
        $this->post(route('master.tender-type-values.store'), ['name' => 'X', 'status' => 1])->assertSessionHasErrors('tender_type_id');
        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => 999999999, 'name' => 'X', 'status' => 1])->assertSessionHasErrors('tender_type_id');
        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t1->id, 'name' => 'X'])->assertSessionHasErrors('status');
        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t1->id, 'name' => 'X', 'status' => 1, 'branch_id' => 999999999])->assertSessionHasErrors('branch_id');

        $this->put(route('master.tender-type-values.update', $v), ['tender_type_id' => $t1->id, 'name' => 'Visa Gold', 'status' => 0])->assertRedirect(route('master.tender-type-values.index'))->assertSessionHas('status', 'Tender type value updated successfully.');
        $v->refresh();
        $this->assertSame('Visa Gold', $v->name);
        $this->assertFalse($v->status);
        $this->assertSame('Bank', $v->group_ledger); // key omitted on update => untouched

        $this->get(route('master.tender-type-values.index'))->assertOk()->assertSee('Visa Gold');
        $this->get(route('master.tender-type-values.create'))->assertOk();
        $this->get(route('master.tender-type-values.edit', $v))->assertOk();
        $this->delete(route('master.tender-type-values.destroy', $v))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('tender_type_values', ['id' => $v->id]);
    }

    public function test_tender_type_values_create_form_lists_only_active_types_and_branches(): void
    {
        $this->asRole('Manager');
        TenderType::create($this->ttPayload(['name' => 'ActiveTT']));
        TenderType::create($this->ttPayload(['name' => 'DeadTT', 'status' => 0]));
        $this->makeBranch(['name' => 'Live Br']);
        $this->makeBranch(['name' => 'Dead Br', 'status' => false]);

        $r = $this->get(route('master.tender-type-values.create'))->assertOk();
        $this->assertContains('ActiveTT', $r->viewData('tenderTypes')->all());
        $this->assertNotContains('DeadTT', $r->viewData('tenderTypes')->all());
        $this->assertContains('Live Br', $r->viewData('branches')->all());
        $this->assertNotContains('Dead Br', $r->viewData('branches')->all());
    }

    public function test_tender_type_values_cashier_forbidden(): void
    {
        $t = TenderType::create($this->ttPayload(['name' => 'TTc']));
        $this->asRole('Cashier');
        $this->post(route('master.tender-type-values.store'), ['tender_type_id' => $t->id, 'name' => 'Nope', 'status' => 1])->assertForbidden();
        $this->assertDatabaseMissing('tender_type_values', ['name' => 'Nope']);
    }

    // ------------------------------------------------------------ Branches

    public function test_branches_owner_store_defaults_and_business_type_enum(): void
    {
        $this->asRole('Owner');
        $this->post(route('master.branches.store'), $this->branchPayload(['name' => 'Br Alpha', 'state' => 'Gujarat', 'mobile' => '9876543210', 'email' => 'a@b.co']))
            ->assertRedirect(route('master.branches.index'))->assertSessionHas('status', 'Branch created successfully.');
        $b = Branch::where('name', 'Br Alpha')->firstOrFail();
        $this->assertSame('Gujarat', $b->state);
        $this->assertSame('COCO', $b->business_type);

        foreach (['FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'] as $bt) {
            $this->post(route('master.branches.store'), $this->branchPayload(['name' => "BT $bt", 'business_type' => $bt]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('branches', ['name' => "BT $bt", 'business_type' => $bt]);
        }
        $this->post(route('master.branches.store'), $this->branchPayload(['name' => 'BT Bad', 'business_type' => 'MLM']))->assertSessionHasErrors('business_type');
        $this->assertDatabaseMissing('branches', ['name' => 'BT Bad']);
    }

    public function test_branches_validation_rules(): void
    {
        $this->asRole('Owner');
        $this->makeBranch(['name' => 'Taken']);
        $bad = function (array $o, string $field) {
            $this->post(route('master.branches.store'), $this->branchPayload($o))->assertSessionHasErrors($field);
        };
        $bad(['name' => 'Taken'], 'name');
        $bad(['name' => ''], 'name');
        $this->makeBranch(['erp_code' => 'DUPERP']);
        $bad(['erp_code' => 'DUPERP'], 'erp_code'); // dynamic-validation: Branch Code (erp_code alias) unique
        $bad(['erp_code' => ''], 'erp_code');       // ... and required
        $bad(['mobile' => '12345'], 'mobile');
        $bad(['mobile' => '98765432101'], 'mobile');
        $bad(['email' => 'not-an-email'], 'email');
        $bad(['gst_filing' => 'Yearly'], 'gst_filing');
        $bad(['gst_no' => '27AAAAA0000A1Z'], 'gst_no');        // 14 chars
        $bad(['gst_no' => 'XXAAAAA0000A1Z5'], 'gst_no');       // bad pattern
        $bad(['gst_no' => '27aaaaa0000a1z5'], 'gst_no');       // lower case
        $bad(['webstore' => 'maybe'], 'webstore');
        $bad(['language' => ''], 'language');
        $bad(['country_code' => ''], 'country_code');
        $bad(['gst_type' => ''], 'gst_type');
        $bad(['status' => ''], 'status');
        $bad(['postal_code' => str_repeat('1', 21)], 'postal_code');

        $this->post(route('master.branches.store'), $this->branchPayload(['name' => 'Valid Gst', 'gst_no' => '27AAAAA0000A1Z5', 'gst_filing' => 'Quarterly']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['name' => 'Valid Gst', 'gst_no' => '27AAAAA0000A1Z5', 'gst_filing' => 'Quarterly']);
    }

    public function test_branches_update_keeps_own_name_but_blocks_duplicates_and_index_filters(): void
    {
        $this->asRole('Owner');
        $a = $this->makeBranch(['name' => 'Zulu Store', 'city' => 'Surat', 'mobile' => '9000011111', 'business_type' => 'FOFO']);
        $b = $this->makeBranch(['name' => 'Yankee Store', 'city' => 'Pune', 'phone' => '0201234567', 'status' => false]);

        $this->put(route('master.branches.update', $a), $this->branchPayload(['name' => 'Zulu Store', 'state' => 'Gujarat', 'business_type' => 'FOFO']))->assertRedirect(route('master.branches.index'))->assertSessionHas('status', 'Branch updated successfully.');
        $this->assertSame('Gujarat', $a->fresh()->state);
        $this->put(route('master.branches.update', $a), $this->branchPayload(['name' => 'Yankee Store']))->assertSessionHasErrors('name');
        $this->assertSame('Zulu Store', $a->fresh()->name);

        $names = fn (array $q) => $this->get(route('master.branches.index', $q))->assertOk()->viewData('branches')->pluck('name')->all();
        $this->assertSame(['Zulu Store'], $names(['search' => 'Surat']));
        $this->assertSame(['Yankee Store'], $names(['search' => '020123']));
        $this->assertSame(['Zulu Store'], $names(['search' => '90000111']));
        $this->assertSame(['Yankee Store'], $names(['search' => 'Yankee']));
        $this->assertSame(['Zulu Store'], $names(['business_type' => 'FOFO']));
        $bt = $this->get(route('master.branches.index'))->viewData('businessTypes');
        $this->assertTrue($bt->contains('FOFO'));
        $this->assertSame(['Yankee Store'], $names(['status' => '0']));
        $this->assertContains('Zulu Store', $names(['status' => '1']));
        $this->assertNotContains('Yankee Store', $names(['status' => '1']));

        $this->get(route('master.branches.create'))->assertOk();
        $this->get(route('master.branches.edit', $a))->assertOk()->assertSee('Zulu Store');
        $this->delete(route('master.branches.destroy', $a))->assertSessionHas('error', self::DELETE_MSG);
        $this->assertDatabaseHas('branches', ['id' => $a->id]);
    }

    public function test_branches_manager_and_cashier_forbidden(): void
    {
        $b = $this->makeBranch(['name' => 'Locked']);
        foreach (['Manager', 'Cashier'] as $role) {
            $this->asRole($role);
            $this->post(route('master.branches.store'), $this->branchPayload(['name' => 'Sneak '.$role]))->assertForbidden();
            $this->put(route('master.branches.update', $b), $this->branchPayload(['name' => 'Renamed']))->assertForbidden();
            $this->delete(route('master.branches.destroy', $b))->assertForbidden();
        }
        $this->assertSame('Locked', $b->fresh()->name);
        $this->assertDatabaseMissing('branches', ['name' => 'Sneak Manager']);
    }
}
