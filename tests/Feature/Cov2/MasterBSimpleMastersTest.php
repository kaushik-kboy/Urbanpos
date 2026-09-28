<?php

namespace Tests\Feature\Cov2;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\CustomerCategory;
use App\Models\CustomerType;
use App\Models\ItemCategory;
use App\Models\PetType;
use App\Models\SalesType;
use App\Models\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterBSimpleMastersTest extends TestCase
{
    use MasterBHelper, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnSuperUser();
    }

    public static function moduleProvider(): array
    {
        // module, model, payload, manager-allowed
        return [
            'pet-types' => ['pet-types', PetType::class, ['name' => 'Perm Pet', 'status' => 1], true],
            'uoms' => ['uoms', Uom::class, ['name' => 'Perm Uom', 'status' => 1], true],
            'item-categories' => ['item-categories', ItemCategory::class, ['name' => 'Perm Cat', 'is_mandatory' => 0, 'status' => 1], true],
            'customer-categories' => ['customer-categories', CustomerCategory::class, ['name' => 'Perm CC', 'app_access' => 0, 'enable_loyalty' => 0, 'discount_percent' => 5, 'business_type' => 'ALL', 'status' => 1], true],
            'areas' => ['areas', Area::class, ['name' => 'Perm Area', 'status' => 1], true],
            // not in the Manager module list => Owner-only
            'sales-types' => ['sales-types', SalesType::class, ['name' => 'Perm ST', 'status' => 1], false],
            'customer-types' => ['customer-types', CustomerType::class, ['name' => 'Perm CT', 'status' => 1], false],
        ];
    }

    #[DataProvider('moduleProvider')]
    public function test_store_permission_matrix(string $module, string $model, array $payload, bool $managerAllowed): void
    {
        $route = "master.$module.store";

        $baseline = $model::count(); // some tables are seeded by migrations
        $this->actAs('Cashier');
        $this->post(route($route), $payload)->assertForbidden();
        $this->assertSame($baseline, $model::count());

        $this->actAs('Manager');
        if ($managerAllowed) {
            $this->post(route($route), $payload)->assertRedirect(route("master.$module.index"))->assertSessionHas('status');
            $this->assertSame(1, $model::where('name', $payload['name'])->count());
        } else {
            $this->post(route($route), $payload)->assertForbidden();
            $this->assertSame($baseline, $model::count());
        }

        $this->actAs('Owner');
        $second = array_merge($payload, ['name' => $payload['name'].' 2']);
        $this->post(route($route), $second)->assertRedirect(route("master.$module.index"));
        $this->assertSame(1, $model::where('name', $second['name'])->count());
    }

    #[DataProvider('moduleProvider')]
    public function test_index_and_forms_open_to_cashier_and_destroy_is_gated_and_never_deletes(string $module, string $model, array $payload): void
    {
        $this->actAs('Owner');
        $row = $model::create($payload);

        $this->actAs('Cashier');
        $this->get(route("master.$module.index"))->assertOk()->assertSee($payload['name']);
        $this->get(route("master.$module.create"))->assertOk();
        $this->get(route("master.$module.edit", $row->id))->assertOk()->assertSee($payload['name']);
        $this->delete(route("master.$module.destroy", $row->id))->assertForbidden();
        $this->put(route("master.$module.update", $row->id), $payload)->assertForbidden();

        $this->actAs('Owner');
        $this->delete(route("master.$module.destroy", $row->id))
            ->assertRedirect(route("master.$module.index"))
            ->assertSessionHas('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
        $this->assertNotNull($model::find($row->id));
    }

    #[DataProvider('moduleProvider')]
    public function test_name_required_unique_and_update_may_keep_own_name(string $module, string $model, array $payload): void
    {
        $this->actAs('Owner');
        $a = $model::create($payload);
        $b = $model::create(array_merge($payload, ['name' => 'Other One']));

        $this->post(route("master.$module.store"), array_merge($payload, ['name' => '']))->assertSessionHasErrors('name');
        $this->post(route("master.$module.store"), $payload)->assertSessionHasErrors('name');
        $this->post(route("master.$module.store"), array_merge($payload, ['name' => str_repeat('x', 256)]))->assertSessionHasErrors('name');
        $noStatus = $payload;
        unset($noStatus['status']);
        $this->post(route("master.$module.store"), array_merge($noStatus, ['name' => 'No Status']))->assertSessionHasErrors('status');
        $this->post(route("master.$module.store"), array_merge($payload, ['name' => 'Bad Status', 'status' => 'maybe']))->assertSessionHasErrors('status');
        $this->assertNull($model::where('name', 'No Status')->first());
        $this->assertNull($model::where('name', 'Bad Status')->first());

        $this->put(route("master.$module.update", $a->id), array_merge($payload, ['name' => 'Other One']))->assertSessionHasErrors('name');
        $this->put(route("master.$module.update", $a->id), array_merge($payload, ['status' => 0]))
            ->assertRedirect(route("master.$module.index"))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $a->fresh()->status);
        $this->assertSame($payload['name'], $a->fresh()->name);
        $this->assertSame('Other One', $b->fresh()->name);
    }

    public function test_sales_type_and_customer_type_search_json_store_and_length_rules(): void
    {
        $this->actAs('Owner');
        SalesType::create(['name' => 'Walk-in', 'code' => 'WLK', 'description' => 'Counter sale', 'status' => 1]);
        SalesType::create(['name' => 'Online', 'code' => 'WEB', 'description' => 'Courier', 'status' => 1]);
        CustomerType::create(['name' => 'Retail', 'code' => 'RET', 'description' => 'Ordinary', 'status' => 1]);
        CustomerType::create(['name' => 'Wholesale', 'code' => 'WHS', 'description' => 'Bulk', 'status' => 1]);

        $this->get(route('master.sales-types.index', ['search' => 'WEB']))->assertOk()->assertSee('Online')->assertDontSee('Walk-in');
        $this->get(route('master.sales-types.index', ['search' => 'counter']))->assertSee('Walk-in')->assertDontSee('Online');
        $this->get(route('master.customer-types.index', ['search' => 'bulk']))->assertSee('Wholesale')->assertDontSee('Retail');
        $this->get(route('master.customer-types.index', ['search' => 'Retail']))->assertSee('Retail')->assertDontSee('Wholesale');

        $this->postJson(route('master.sales-types.store'), ['name' => 'Json ST', 'status' => 1])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.name', 'Json ST');
        $this->postJson(route('master.customer-types.store'), ['name' => 'Json CT', 'status' => 1])
            ->assertOk()->assertJsonPath('data.name', 'Json CT');
        $this->assertSame(1, SalesType::where('name', 'Json ST')->count());

        $this->post(route('master.sales-types.store'), ['name' => 'Long', 'status' => 1, 'code' => str_repeat('c', 51)])->assertSessionHasErrors('code');
        $this->post(route('master.customer-types.store'), ['name' => 'Long', 'status' => 1, 'description' => str_repeat('d', 501)])->assertSessionHasErrors('description');
        $this->postJson(route('master.sales-types.store'), ['name' => 'Walk-in', 'status' => 1])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_uom_alias_length_and_customer_category_numeric_rules(): void
    {
        $this->actAs('Owner');
        $this->post(route('master.uoms.store'), ['name' => 'Kilogram', 'alias' => str_repeat('a', 51), 'status' => 1])->assertSessionHasErrors('alias');
        $this->post(route('master.uoms.store'), ['name' => 'Kilogram', 'alias' => 'KG', 'status' => 1])->assertSessionHasNoErrors();
        $this->assertSame('KG', Uom::where('name', 'Kilogram')->value('alias'));

        $base = ['name' => 'VIP', 'app_access' => 1, 'enable_loyalty' => 1, 'discount_percent' => 10, 'business_type' => 'ALL', 'status' => 1];
        foreach ([['discount_percent' => -1], ['discount_percent' => 101], ['discount_percent' => 'abc'], ['business_type' => 'NOPE'], ['app_access' => 'x'], ['enable_loyalty' => null]] as $bad) {
            $key = array_key_first($bad);
            $this->post(route('master.customer-categories.store'), array_merge($base, $bad))->assertSessionHasErrors($key);
        }
        $this->assertSame(0, CustomerCategory::count());

        $this->post(route('master.customer-categories.store'), array_merge($base, ['discount_percent' => 100, 'business_type' => 'FOFO']))->assertSessionHasNoErrors();
        $cc = CustomerCategory::first();
        $this->assertSame('FOFO', $cc->business_type);
        $this->assertEquals(100, $cc->discount_percent);
        $this->assertTrue((bool) $cc->app_access);
    }

    public function test_item_category_is_mandatory_required_and_stored(): void
    {
        $this->actAs('Owner');
        $this->post(route('master.item-categories.store'), ['name' => 'Dept', 'status' => 1])->assertSessionHasErrors('is_mandatory');
        $this->post(route('master.item-categories.store'), ['name' => 'Dept', 'status' => 1, 'is_mandatory' => 1]);
        $this->assertTrue((bool) ItemCategory::where('name', 'Dept')->value('is_mandatory'));
    }

    public function test_breed_requires_existing_pet_type_and_name_unique_per_pet_type(): void
    {
        $this->actAs('Manager');
        $dog = PetType::create(['name' => 'Dog', 'status' => 1]);
        $cat = PetType::create(['name' => 'Cat', 'status' => 1]);
        PetType::create(['name' => 'Retired', 'status' => 0]);

        $this->post(route('master.breeds.store'), ['name' => 'Persian', 'status' => 1])->assertSessionHasErrors('pet_type_id');
        $this->post(route('master.breeds.store'), ['name' => 'Persian', 'status' => 1, 'pet_type_id' => 999999])->assertSessionHasErrors('pet_type_id');
        $this->assertSame(0, Breed::count());

        $this->post(route('master.breeds.store'), ['name' => 'Persian', 'status' => 1, 'pet_type_id' => $cat->id])
            ->assertRedirect(route('master.breeds.index'))->assertSessionHas('status', 'Breed created successfully.');
        $persian = Breed::where('name', 'Persian')->first();
        $this->assertSame($cat->id, $persian->pet_type_id);

        $this->post(route('master.breeds.store'), ['name' => 'Persian', 'status' => 1, 'pet_type_id' => $cat->id])->assertSessionHasErrors('name');
        $this->post(route('master.breeds.store'), ['name' => 'Persian', 'status' => 1, 'pet_type_id' => $dog->id])->assertSessionHasNoErrors();
        $this->assertSame(2, Breed::where('name', 'Persian')->count());

        $this->put(route('master.breeds.update', $persian), ['name' => 'Persian', 'status' => 0, 'pet_type_id' => $cat->id])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $persian->fresh()->status);
        $this->put(route('master.breeds.update', $persian), ['name' => 'Persian', 'status' => 0, 'pet_type_id' => $dog->id])->assertSessionHasErrors('name');

        $this->get(route('master.breeds.create'))->assertOk()->assertSee('Dog')->assertDontSee('Retired');
        $this->get(route('master.breeds.edit', $persian))->assertOk()->assertDontSee('Retired');
        $this->get(route('master.breeds.index'))->assertOk()->assertSee('Persian');

        $this->delete(route('master.breeds.destroy', $persian))->assertSessionHas('error');
        $this->assertNotNull(Breed::find($persian->id));
    }

    public function test_breed_permissions_cashier_forbidden(): void
    {
        $pt = PetType::create(['name' => 'Bird', 'status' => 1]);
        $this->actAs('Cashier');
        $this->post(route('master.breeds.store'), ['name' => 'Parrot', 'status' => 1, 'pet_type_id' => $pt->id])->assertForbidden();
        $this->assertSame(0, Breed::count());
        $this->get(route('master.breeds.index'))->assertOk();
    }

    public function test_area_branch_is_optional_must_exist_and_name_unique_per_branch(): void
    {
        $this->actAs('Owner');
        $b1 = Branch::create(['name' => 'B One']);
        $b2 = Branch::create(['name' => 'B Two']);

        $this->post(route('master.areas.store'), ['name' => 'North', 'status' => 1, 'branch_id' => 999999])->assertSessionHasErrors('branch_id');
        $this->post(route('master.areas.store'), ['name' => 'North', 'status' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Area::where('name', 'North')->first()->branch_id);

        $this->post(route('master.areas.store'), ['name' => 'North', 'status' => 1, 'branch_id' => $b1->id])->assertSessionHasNoErrors();
        $this->post(route('master.areas.store'), ['name' => 'North', 'status' => 1, 'branch_id' => $b1->id])->assertSessionHasErrors('name');
        $this->post(route('master.areas.store'), ['name' => 'North', 'status' => 1, 'branch_id' => $b2->id])->assertSessionHasNoErrors();
        $this->assertSame(3, Area::where('name', 'North')->count());

        $area = Area::where('branch_id', $b1->id)->first();
        $this->put(route('master.areas.update', $area), ['name' => 'North', 'status' => 1, 'branch_id' => $b2->id])->assertSessionHasErrors('name');
        $this->put(route('master.areas.update', $area), ['name' => 'North', 'status' => 0, 'branch_id' => $b1->id])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $area->fresh()->status);

        $this->get(route('master.areas.create'))->assertOk()->assertSee('B One');
        $this->get(route('master.areas.edit', $area))->assertOk()->assertSee('B Two');
        $this->get(route('master.areas.index'))->assertOk();
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('in.csv', $content);
    }

    private function importRes($response): array
    {
        return $response->baseResponse->getSession()->get('import_result');
    }

    public function test_breed_import_creates_pet_type_and_reports_missing_pet_type(): void
    {
        $this->actAs('Owner');
        PetType::create(['name' => 'Dog', 'status' => 1]);
        $csv = "Name,Pet Type,Status\nLabrador,Dog,active\nSiamese,Cat,yes\nGhost,,1\n";

        $r = $this->importRes($this->post(route('master.breeds.import'), ['file' => $this->csv($csv)]));

        $this->assertSame(2, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertStringContainsString('Pet Type is required.', $r['errors'][0]);
        $this->assertStringContainsString('Row 4', $r['errors'][0]);
        $cat = PetType::where('name', 'Cat')->first();
        $this->assertNotNull($cat);
        $this->assertTrue((bool) $cat->status);
        $this->assertSame($cat->id, Breed::where('name', 'Siamese')->value('pet_type_id'));
        $this->assertSame(1, PetType::where('name', 'Dog')->count());

        $r = $this->importRes($this->post(route('master.breeds.import'), ['file' => $this->csv("Name,Pet Type,Status\nLabrador,Dog,no\n")]));
        $this->assertSame(1, $r['updated']);
        $this->assertFalse((bool) Breed::where('name', 'Labrador')->first()->status);
        $this->assertSame(2, Breed::count());
    }

    public function test_pet_type_and_item_category_import_cast_status_and_flags_and_upsert(): void
    {
        $this->actAs('Owner');
        $r = $this->importRes($this->post(route('master.pet-types.import'), ['file' => $this->csv("Name,Status\nFerret,yes\nSnake,no\n")]));
        $this->assertSame(2, $r['created']);
        $this->assertTrue((bool) PetType::where('name', 'Ferret')->value('status'));
        $this->assertFalse((bool) PetType::where('name', 'Snake')->value('status'));
        $r = $this->importRes($this->post(route('master.pet-types.import'), ['file' => $this->csv("Name,Status\nSnake,1\n")]));
        $this->assertSame(1, $r['updated']);
        $this->assertTrue((bool) PetType::where('name', 'Snake')->value('status'));

        $r = $this->importRes($this->post(route('master.item-categories.import'), ['file' => $this->csv("Name,Is Mandatory,Status\nBrand Dept,yes,active\nOptional Dept,no,1\n")]));
        $this->assertSame(2, $r['created']);
        $this->assertTrue((bool) ItemCategory::where('name', 'Brand Dept')->value('is_mandatory'));
        $this->assertFalse((bool) ItemCategory::where('name', 'Optional Dept')->value('is_mandatory'));
        $this->assertTrue((bool) ItemCategory::where('name', 'Optional Dept')->value('status'));
    }

    public function test_area_import_branch_resolver_blank_and_new_branch(): void
    {
        $this->actAs('Owner');
        $r = $this->importRes($this->post(route('master.areas.import'), ['file' => $this->csv("Name,Branch\nEast,Fresh Branch\nWest,\n")]));
        $this->assertSame(2, $r['created']);
        $br = Branch::where('name', 'Fresh Branch')->first();
        $this->assertNotNull($br);
        $this->assertSame($br->id, Area::where('name', 'East')->value('branch_id'));
        $this->assertNull(Area::where('name', 'West')->value('branch_id'));
    }

    public function test_customer_category_import_casts_uom_import_sample_download_and_import_permission(): void
    {
        $this->actAs('Owner');
        $csv = "Name,App Access,Enable Loyalty,Discount Percent,Business Type,Status\nGold,yes,no,12.5,FOFO,active\nBad,yes,no,\"1,250.5\",FOFO,active\n";
        $r = $this->importRes($this->post(route('master.customer-categories.import'), ['file' => $this->csv($csv)]));
        // thousands separator is stripped (1,250.5 => 1250.5) and the out-of-range value is a row error, not a crash
        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertStringContainsString('Row 3', $r['errors'][0]);
        $this->assertNull(CustomerCategory::where('name', 'Bad')->first());
        $g = CustomerCategory::where('name', 'Gold')->first();
        $this->assertTrue((bool) $g->app_access);
        $this->assertFalse((bool) $g->enable_loyalty);
        $this->assertSame('FOFO', $g->business_type);

        $this->post(route('master.uoms.import'), ['file' => $this->csv("Name,Alias\nLitre,L\n")]);
        $this->assertSame('L', Uom::where('name', 'Litre')->value('alias'));

        $this->post(route('master.uoms.import'), [])->assertSessionHasErrors('file');
        $this->post(route('master.uoms.import'), ['file' => UploadedFile::fake()->create('x.pdf', 1)])->assertSessionHasErrors('file');

        $resp = $this->get(route('master.breeds.import-sample'))->assertOk();
        $this->assertStringContainsString('breed-import-sample.csv', $resp->headers->get('content-disposition'));
        $this->assertSame("Name,Status\n", $resp->streamedContent());

        $this->actAs('Cashier');
        $this->post(route('master.uoms.import'), ['file' => $this->csv("Name\nX\n")])->assertForbidden();
        $this->assertNull(Uom::where('name', 'X')->first());
    }
}
