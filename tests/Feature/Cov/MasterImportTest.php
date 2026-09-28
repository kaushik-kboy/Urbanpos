<?php

namespace Tests\Feature\Cov;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Exercises the shared Importable trait through real master controllers
 * (brands, items, customers, suppliers) with uploaded CSV fixtures.
 */
class MasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(); // burn id 1 (hard-coded super-user)
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner);
    }

    private function csv(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function importResult($response): array
    {
        return $response->baseResponse->getSession()->get('import_result');
    }

    public function test_brand_import_creates_updates_and_skips_rows_with_missing_required_name(): void
    {
        Brand::create(['name' => 'Royal Canin', 'prefix' => 'OLD', 'status' => 1]);

        $csv = "Name,Prefix,Alias Code,Status\n"
            ."Royal Canin,RC,,inactive\n"     // update existing
            ."Pedigree,PD,PED1,yes\n"          // create
            .",XX,,yes\n"                      // missing name => skipped
            .",,,\n";                          // blank row => ignored silently

        $res = $this->post(route('master.brands.import'), ['file' => $this->csv('brands.csv', $csv)]);
        $res->assertRedirect();

        $r = $this->importResult($res);
        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['updated']);
        $this->assertSame(1, $r['skipped']);
        $this->assertSame(1, $r['error_count']);
        $this->assertStringContainsString('Row 4', $r['errors'][0]);
        $this->assertStringContainsString('Missing required value for "Name"', $r['errors'][0]);

        $rc = Brand::where('name', 'Royal Canin')->first();
        $this->assertSame('RC', $rc->prefix);
        $this->assertFalse((bool) $rc->status);   // 'inactive' is not a truthy token
        $ped = Brand::where('name', 'Pedigree')->first();
        $this->assertSame('PED1', $ped->alias_code);
        $this->assertTrue((bool) $ped->status);
        $this->assertSame(2, Brand::count());
    }

    public function test_import_finds_header_row_below_company_title_rows(): void
    {
        $csv = "Urban Pets Report,,\n"
            ."Generated 2026,,\n"
            ."Name,Prefix,Status\n"
            ."Whiskas,WH,1\n";

        $res = $this->post(route('master.brands.import'), ['file' => $this->csv('brands.csv', $csv)]);
        $r = $this->importResult($res);

        $this->assertSame(1, $r['created']);
        $this->assertSame(0, $r['skipped']);
        $this->assertSame('WH', Brand::where('name', 'Whiskas')->value('prefix'));
    }

    public function test_error_row_numbers_match_the_spreadsheet_line_even_below_title_rows(): void
    {
        // Regression: line number used to double-count the header offset.
        $csv = "Title row,
Name,Prefix
Ok,1
,2
";
        $r = $this->importResult($this->post(route('master.brands.import'), ['file' => $this->csv('b.csv', $csv)]));
        $this->assertStringStartsWith('Row 4:', $r['errors'][0]);
    }

    public function test_import_recognises_pos_export_aliases_for_items(): void
    {
        $csv = "Item name,Barcode,Selling,MRP,Landing cost,BRANDS,DEPARTMENT\n"
            ."Dog Leash,8901234567890,120,150,80,Trixie,Accessories\n";

        $res = $this->post(route('master.items.import'), ['file' => $this->csv('items.csv', $csv)]);
        $r = $this->importResult($res);
        $this->assertSame(1, $r['created'], json_encode($r));

        $item = Item::where('name', 'Dog Leash')->first();
        $this->assertNotNull($item);
        $this->assertSame('8901234567890', $item->ean_upc_code);
        $this->assertEquals(120, $item->sell_price);
        $this->assertEquals(150, $item->mrp);
        $this->assertEquals(80, $item->landing_cost);
        $this->assertSame('Trixie', Brand::find($item->brand_id)->name);
        $this->assertNotNull($item->department_value_id);
    }

    public function test_item_import_updates_existing_item_by_name_and_strips_thousand_separators(): void
    {
        Item::create(['name' => 'Cat Tree', 'sell_price' => 10, 'mrp' => 10]);

        $csv = "Name,Sell Price,MRP\n\"Cat Tree\",\"1,250.50\",\"1,500\"\n";
        $r = $this->importResult($this->post(route('master.items.import'), ['file' => $this->csv('items.csv', $csv)]));

        $this->assertSame(0, $r['created']);
        $this->assertSame(1, $r['updated']);
        $this->assertSame(1, Item::where('name', 'Cat Tree')->count());
        $this->assertEquals(1250.5, Item::where('name', 'Cat Tree')->value('sell_price'));
        $this->assertEquals(1500, Item::where('name', 'Cat Tree')->value('mrp'));
    }

    public function test_row_level_database_failure_is_reported_and_does_not_abort_other_rows(): void
    {
        // Duplicate EAN on the second row violates the unique index => error row, first & third survive.
        $csv = "Name,EAN/UPC Code\n"
            ."Item A,111\n"
            ."Item B,111\n"
            ."Item C,333\n";
        $r = $this->importResult($this->post(route('master.items.import'), ['file' => $this->csv('items.csv', $csv)]));

        $this->assertSame(2, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertStringContainsString('Row 3', $r['errors'][0]);
        $this->assertNull(Item::where('name', 'Item B')->first());
        $this->assertNotNull(Item::where('name', 'Item C')->first());
    }

    public function test_customer_import_requires_code_and_upserts_on_customer_code(): void
    {
        $csv = "Name,Customer Code,Mobile,Credit Days\n"
            ."Anita,C001,9876543210,15\n"
            .",C002,9876543211,0\n"
            ."Bharat,,9876543212,0\n";
        $r = $this->importResult($this->post(route('master.customers.import'), ['file' => $this->csv('c.csv', $csv)]));

        $this->assertSame(1, $r['created']);
        $this->assertSame(2, $r['skipped']);
        $this->assertSame(2, $r['error_count']);
        $this->assertSame(15, (int) Customer::where('customer_code', 'C001')->value('credit_days'));

        $csv2 = "Name,Customer Code,Mobile,Credit Days\nAnita Sharma,C001,9876543210,30\n";
        $r2 = $this->importResult($this->post(route('master.customers.import'), ['file' => $this->csv('c.csv', $csv2)]));
        $this->assertSame(1, $r2['updated']);
        $c = Customer::where('customer_code', 'C001')->first();
        $this->assertSame('Anita Sharma', $c->name);
        $this->assertSame(30, (int) $c->credit_days);
        $this->assertSame(1, Customer::where('customer_code', 'C001')->count());
    }

    public function test_supplier_import_with_unrecognised_columns_skips_the_row(): void
    {
        $csv = "Foo,Bar\n1,2\n";
        $r = $this->importResult($this->post(route('master.suppliers.import'), ['file' => $this->csv('s.csv', $csv)]));
        $this->assertSame(0, $r['created']);
        $this->assertSame(1, $r['skipped']);
        $this->assertSame(0, Supplier::count());
    }

    public function test_import_of_header_only_file_creates_nothing(): void
    {
        $r = $this->importResult($this->post(route('master.brands.import'), ['file' => $this->csv('b.csv', "Name,Prefix\n")]));
        $this->assertSame(0, $r['created'] + $r['updated'] + $r['skipped']);
        $this->assertSame(0, Brand::count());
    }

    public function test_import_validates_file_presence_and_type(): void
    {
        $this->post(route('master.brands.import'), [])->assertSessionHasErrors('file');
        $this->post(route('master.brands.import'), [
            'file' => UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, Brand::count());
    }

    public function test_import_requires_create_permission(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);

        $this->post(route('master.brands.import'), ['file' => $this->csv('b.csv', "Name\nX\n")])->assertForbidden();
        $this->assertSame(0, Brand::count());
    }

    public function test_import_sample_downloads_header_only_csv_named_after_model(): void
    {
        $res = $this->get(route('master.brands.import-sample'));
        $res->assertOk();
        $this->assertStringContainsString('brand-import-sample.csv', $res->headers->get('content-disposition'));
        $this->assertSame('Name,Prefix,"Alias Code",Status', trim($res->streamedContent()));
    }
}
