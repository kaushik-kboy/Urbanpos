<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\CustomerPet;
use App\Models\CustomerType;
use App\Models\PetType;
use App\Models\SalesBill;
use App\Models\SalesType;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinSalesBillMoreTest extends TestCase
{
    use FinHelper;

    private function bill(string $no, Customer $c, string $date, float $total, array $over = []): SalesBill
    {
        return SalesBill::create(array_merge([
            'bill_number' => $no, 'bill_date' => $date, 'customer_id' => $c->id, 'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice', 'payment_type' => 'Cash', 'total' => $total, 'status' => 'Posted',
        ], $over));
    }

    private function idx(array $q = []): array
    {
        return $this->get(route('sales.sales-bills.index', $q))->assertOk()->viewData('salesBills')->pluck('bill_number')->sort()->values()->all();
    }

    public function test_index_filters_search_columns_dates_branch_customer_and_type(): void
    {
        $anil = Customer::create(['name' => 'Anil Kumar', 'mobile' => '9111111111', 'phone' => '0222333444', 'customer_code' => 'CUST-ANIL', 'state' => 'Gujarat', 'status' => true]);
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $this->bill('IDX-001', $anil, '2026-01-10 10:00:00', 555, ['invoice_type' => 'Tax Invoice']);
        $this->bill('IDX-002', $this->cust, '2026-02-10 10:00:00', 777);
        $this->bill('IDX-003', $this->cust, '2026-02-20 23:30:00', 888, ['branch_id' => $b2->id]);

        // default: the user's own branch only
        $this->assertSame(['IDX-001', 'IDX-002'], $this->idx());
        $this->assertSame(['IDX-001', 'IDX-002', 'IDX-003'], $this->idx(['branch_id' => 'all']));
        $this->assertSame(['IDX-003'], $this->idx(['branch_id' => $b2->id]));

        // exact bill number short-circuits to that one bill; partial matches on number
        $this->assertSame(['IDX-002'], $this->idx(['search' => 'IDX-002']));
        $this->assertSame(['IDX-001', 'IDX-002'], $this->idx(['search' => 'IDX-00', 'search_column' => 'bill_number']));
        // customer name / code / mobile / phone / amount columns
        $this->assertSame(['IDX-001'], $this->idx(['search' => 'anil', 'search_column' => 'customer_name']));
        $this->assertSame(['IDX-001'], $this->idx(['search' => 'CUST-ANIL', 'search_column' => 'customer_name']));
        $this->assertSame(['IDX-001'], $this->idx(['search' => '911111', 'search_column' => 'mobile']));
        $this->assertSame(['IDX-001'], $this->idx(['search' => '222333', 'search_column' => 'mobile']));
        $this->assertSame(['IDX-002'], $this->idx(['search' => '777', 'search_column' => 'amount']));
        $this->assertSame(['IDX-001'], $this->idx(['search' => 'Anil']), 'column=all searches customers too');
        $this->assertSame([], $this->idx(['search' => 'anil', 'search_column' => 'amount']));

        // date window is inclusive of the whole end day
        $this->assertSame(['IDX-002'], $this->idx(['date_from' => '2026-02-01', 'date_to' => '2026-02-10']));
        $this->assertSame(['IDX-003'], $this->idx(['date_from' => '2026-02-20', 'branch_id' => 'all']));
        $this->assertSame(['IDX-001'], $this->idx(['date_to' => '2026-01-31']));

        $this->assertSame(['IDX-001'], $this->idx(['customer_id' => $anil->id]));
        $this->assertSame(['IDX-001'], $this->idx(['invoice_type' => 'Tax Invoice']));

        // a filtered customer outside the first 30 still appears in the dropdown options
        $this->assertArrayHasKey($anil->id, $this->get(route('sales.sales-bills.index', ['customer_id' => $anil->id]))->viewData('customers')->all());
    }

    public function test_customer_search_exact_partial_pet_and_inactive_rules(): void
    {
        $dog = PetType::create(['name' => 'Dog', 'status' => true]);
        $cat = PetType::create(['name' => 'Cat', 'status' => true]);
        $lab = Breed::create(['pet_type_id' => $dog->id, 'name' => 'Labrador', 'status' => true]);
        $bob = Customer::create(['name' => 'Bobby Singh', 'mobile' => '9222222222', 'customer_code' => 'CB-77', 'state' => 'Gujarat', 'status' => true]);
        $gone = Customer::create(['name' => 'Bobby Gone', 'mobile' => '9333333333', 'state' => 'Gujarat', 'status' => false]);
        CustomerPet::create(['customer_id' => $bob->id, 'pet_type_id' => $dog->id, 'breed_id' => $lab->id, 'name' => 'Rex']);
        CustomerPet::create(['customer_id' => $bob->id, 'pet_type_id' => $cat->id, 'name' => 'Tom']);
        $owner2 = Customer::create(['name' => 'Petless Owner', 'mobile' => '9444444444', 'state' => 'Gujarat', 'status' => true]);
        CustomerPet::create(['customer_id' => $owner2->id, 'pet_type_id' => $dog->id, 'name' => 'Fido']);

        $search = fn (string $q) => $this->getJson(route('sales.sales-bills.customer-search', ['q' => $q]))->assertOk()->json('results');

        $byCode = $search('CB-77');
        $this->assertCount(1, $byCode);
        $this->assertSame($bob->id, $byCode[0]['id']);
        $this->assertSame('Bobby Singh (9222222222)', $byCode[0]['text']);
        $this->assertEqualsCanonicalizing(['Rex (Labrador)', 'Tom (Cat)'], array_column($byCode[0]['pets'], 'display'));
        $this->assertStringContainsString('Rex (Labrador)', $byCode[0]['pets_summary']);
        $this->assertSame(url("master/customers/{$bob->id}/edit"), $byCode[0]['edit_url']);

        $this->assertSame([$bob->id], array_column($search('9222222222'), 'id'), 'exact mobile');
        $this->assertSame([$bob->id], array_column($search('bobby'), 'id'), 'partial name, inactive excluded');
        $this->assertSame([$owner2->id], array_column($search('Fido'), 'id'), 'matches through pet name');
        $this->assertSame([], $search('zzzz-none'));
        $all = array_column($search(''), 'id');
        $this->assertContains($bob->id, $all);
        $this->assertNotContains($gone->id, $all);
    }

    public function test_customer_invoices_lists_pet_display_names(): void
    {
        $dog = PetType::create(['name' => 'Dog', 'status' => true]);
        $lab = Breed::create(['pet_type_id' => $dog->id, 'name' => 'Labrador', 'status' => true]);
        CustomerPet::create(['customer_id' => $this->cust->id, 'pet_type_id' => $dog->id, 'breed_id' => $lab->id, 'name' => 'Rex']);
        CustomerPet::create(['customer_id' => $this->cust->id, 'pet_type_id' => $dog->id, 'name' => 'Max']);
        $this->bill('CI-1', $this->cust, now()->toDateString(), 10);

        $r = $this->getJson(route('sales.sales-bills.customer-invoices', $this->cust->id))->assertOk();
        $this->assertEqualsCanonicalizing(['Rex (Labrador)', 'Max (Dog)'], array_column($r->json('pets'), 'display'));
        $this->assertCount(1, $r->json('invoices'));
        $this->getJson(route('sales.sales-bills.customer-invoices', 999999))->assertNotFound();
    }

    public function test_form_options_customer_and_sales_type_fallbacks_and_selected_customer_injection(): void
    {
        // no master rows -> built-in defaults
        SalesType::query()->delete();
        CustomerType::query()->delete();
        $r = $this->get(route('sales.sales-bills.create'))->assertOk();
        $this->assertEqualsCanonicalizing(['Retail Invoice', 'Tax Invoice', 'Exempted'], $r->viewData('customerTypes')->keys()->all());
        $this->assertEqualsCanonicalizing(['Local', 'Interstate'], $r->viewData('salesTypes')->keys()->all());

        // master rows are normalised to the three valid invoice types
        CustomerType::create(['name' => 'TAX INVOICE', 'status' => true]);
        CustomerType::create(['name' => 'Exempted', 'status' => true]);
        CustomerType::create(['name' => 'Wholesale', 'status' => true]);
        SalesType::create(['name' => 'Export', 'status' => true]);
        $r = $this->get(route('sales.sales-bills.create'))->assertOk();
        $this->assertEqualsCanonicalizing(['Tax Invoice', 'Exempted', 'Retail Invoice'], $r->viewData('customerTypes')->keys()->all());
        $this->assertSame(['Export'], $r->viewData('salesTypes')->keys()->all());

        // a customer outside the first 20 is injected when editing their bill
        for ($i = 0; $i < 22; $i++) {
            Customer::create(['name' => sprintf('AAA %02d', $i), 'mobile' => '95000000'.sprintf('%02d', $i), 'state' => 'Gujarat', 'status' => true]);
        }
        $late = Customer::create(['name' => 'ZZZ Late', 'mobile' => '9666666666', 'state' => 'Gujarat', 'status' => true]);
        $bill = $this->bill('FO-1', $late, now()->toDateString(), 10);
        $customers = $this->get(route('sales.sales-bills.edit', $bill))->assertOk()->viewData('customers');
        $this->assertSame('ZZZ Late (9666666666)', $customers[$late->id]);
        $this->assertNotContains('ZZZ Late (9666666666)', $this->get(route('sales.sales-bills.create'))->viewData('customers')->all());
    }

    // ---- store: duplicate protection & WhatsApp on save --------------------

    private function storePayload(array $over = []): array
    {
        $this->seedStock($this->item, 10);

        $walkIn = Customer::firstOrCreate(['name' => 'Walk-in Customer'], ['mobile' => '9777000009', 'state' => 'Gujarat', 'status' => true]);

        return array_merge([
            'bill_date' => now()->format('Y-m-d H:i:s'), 'customer_id' => $walkIn->id, 'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice', 'delivery_type' => 'Delivered', 'sales_type' => 'Local',
            'items' => [['item_id' => $this->item->id, 'qty' => 2, 'sell_price' => 100, 'mrp' => 100, 'disc_percent' => 0, 'disc_amount' => 0, 'gst_percent' => 18]],
        ], $over);
    }

    public function test_form_post_with_same_posting_key_creates_one_bill_and_deducts_stock_once(): void
    {
        $p = $this->storePayload(['posting_key' => 'KEY-DUP-1']);
        $this->post(route('sales.sales-bills.store'), $p)->assertSessionHasNoErrors();
        $first = SalesBill::firstOrFail();
        $this->assertSame(8.0, $this->stockOf($this->item));

        $this->post(route('sales.sales-bills.store'), $p)
            ->assertRedirect(route('sales.sales-bills.index'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, $first->bill_number));
        $this->assertSame(1, SalesBill::count());
        $this->assertSame(8.0, $this->stockOf($this->item), 'the replayed request must not deduct stock again');
    }

    public function test_save_and_whatsapp_dispatches_invoice_only_when_integration_is_active(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true, 'data' => ['status' => 'sent', 'mid' => 'm1']], 200)]);
        WhatsAppSetting::current()->update(['api_url' => 'https://wa.test', 'app_key' => 'AK', 'auth_key' => 'UK', 'template_name' => null, 'is_active' => true]);

        $this->post(route('sales.sales-bills.store'), $this->storePayload(['save_action' => 'whatsapp']))->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains((string) $req->body(), '919777000009'));
        $this->assertSame(1, SalesBill::count());

        // disabled integration: bill is still saved, nothing is sent
        WhatsAppSetting::current()->update(['is_active' => false]);
        $this->post(route('sales.sales-bills.store'), $this->storePayload(['send_whatsapp' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(2, SalesBill::count());
        Http::assertSentCount(1);
    }

    public function test_whatsapp_transport_failure_never_blocks_the_sale(): void
    {
        Http::fake(['wa.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        WhatsAppSetting::current()->update(['api_url' => 'https://wa.test', 'app_key' => 'AK', 'auth_key' => 'UK', 'template_name' => null, 'is_active' => true]);
        $this->post(route('sales.sales-bills.store'), $this->storePayload(['save_action' => 'whatsapp']))->assertSessionHasNoErrors();
        $this->assertSame(1, SalesBill::count());
        $this->assertSame(8.0, $this->stockOf($this->item));
    }
}
