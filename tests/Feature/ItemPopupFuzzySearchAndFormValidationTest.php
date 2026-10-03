<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FormFieldValidation;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use App\Services\DynamicValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ItemPopupFuzzySearchAndFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'state' => 'Rajasthan',
            'status' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->user);
    }

    private function createItemWithStock(string $name, string $code, float $stock = 10): Item
    {
        $gst = GstTax::firstOrCreate(['name' => '18%'], ['percentage' => 18, 'status' => 1, 'description' => 'GST 18%']);
        $item = Item::create([
            'name' => $name,
            'item_code' => $code,
            'status' => 1,
            'sell_price' => 100,
            'mrp' => 120,
            'gst_tax_id' => $gst->id,
            'allow_negative_stock' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'quantity' => $stock,
        ]);

        return $item;
    }

    /**
     * Test exact user scenario:
     * item a 20 kg
     * item b 30 kg
     * item c 40kg
     *
     * agar item 30 kg likhu to item b 30kg aana chiye
     */
    public function test_item_search_multi_token_and_unit_expansion(): void
    {
        $itemA = $this->createItemWithStock('item a 20 kg', 'IT-20');
        $itemB = $this->createItemWithStock('item b 30 kg', 'IT-30');
        $itemC = $this->createItemWithStock('item c 40kg', 'IT-40');

        // 1. Search "item 30 kg" -> ONLY item b 30 kg should be returned
        $res30 = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'item 30 kg',
        ]));
        $res30->assertOk();
        $names30 = collect($res30->json('items'))->pluck('name')->all();
        $this->assertContains('item b 30 kg', $names30);
        $this->assertNotContains('item a 20 kg', $names30);
        $this->assertNotContains('item c 40kg', $names30);

        // 2. Search "item 30kg" (no space in search, space in DB) -> item b 30 kg matches
        $res30noSpace = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'item 30kg',
        ]));
        $res30noSpace->assertOk();
        $names30noSpace = collect($res30noSpace->json('items'))->pluck('name')->all();
        $this->assertContains('item b 30 kg', $names30noSpace);

        // 3. Search "item 40 kg" (space in search, no space in DB) -> item c 40kg matches
        $res40 = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'item 40 kg',
        ]));
        $res40->assertOk();
        $names40 = collect($res40->json('items'))->pluck('name')->all();
        $this->assertContains('item c 40kg', $names40);
        $this->assertNotContains('item a 20 kg', $names40);
        $this->assertNotContains('item b 30 kg', $names40);

        // 4. Search "item 40kg" -> item c 40kg matches
        $res40noSpace = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'item 40kg',
        ]));
        $res40noSpace->assertOk();
        $names40noSpace = collect($res40noSpace->json('items'))->pluck('name')->all();
        $this->assertContains('item c 40kg', $names40noSpace);

        // 5. Search "30 kg" alone -> item b 30 kg matches
        $resOnly30 = $this->getJson(route('sales.sales-bills.item-list', [
            'branch_id' => $this->branch->id,
            'search' => '30 kg',
        ]));
        $resOnly30->assertOk();
        $namesOnly30 = collect($resOnly30->json('items'))->pluck('name')->all();
        $this->assertContains('item b 30 kg', $namesOnly30);
        $this->assertNotContains('item a 20 kg', $namesOnly30);
    }

    /**
     * Test Purchase Invoices item list also supports multi-token search.
     */
    public function test_purchase_invoice_item_list_multi_token_search(): void
    {
        $this->createItemWithStock('Royal Canin Maxi Adult 15 kg', 'RC-15');
        $this->createItemWithStock('Royal Canin Mini Puppy 4 kg', 'RC-4');

        $res = $this->getJson(route('purchase.purchase-invoices.item-list', [
            'branch_id' => $this->branch->id,
            'search' => 'Canin Adult 15 kg',
        ]));
        $res->assertOk();
        $names = collect($res->json('items'))->pluck('name')->all();
        $this->assertContains('Royal Canin Maxi Adult 15 kg', $names);
        $this->assertNotContains('Royal Canin Mini Puppy 4 kg', $names);
    }

    /**
     * Test DynamicValidationService module resolution.
     */
    public function test_dynamic_validation_service_resolves_module_from_request(): void
    {
        $svc = app(DynamicValidationService::class);

        $reqPos = Request::create('/pos', 'GET');
        $this->assertSame('sales_bills', $svc->resolveModuleFromRequest($reqPos));

        $reqSalesBills = Request::create('/sales/sales-bills/create', 'GET');
        $this->assertSame('sales_bills', $svc->resolveModuleFromRequest($reqSalesBills));

        $reqCust = Request::create('/master/customers/create', 'GET');
        $this->assertSame('customers', $svc->resolveModuleFromRequest($reqCust));

        $reqPI = Request::create('/purchase/purchase-invoices/create', 'GET');
        $this->assertSame('purchase_invoices', $svc->resolveModuleFromRequest($reqPI));
    }

    /**
     * Test Form Validations index page renders.
     */
    public function test_form_validations_page_loads(): void
    {
        $response = $this->get(route('tools.form-validations.index', [
            'group' => 'sales',
            'module' => 'sales_bills',
        ]));
        $response->assertOk();
        $response->assertSee('Form Field Validations');
    }
}
