<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\Register;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\TenderTypeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Import column/relation mappings of the master controllers (through the real import routes). */
class MasterAImportsTest extends TestCase
{
    use RefreshDatabase, MasterAHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->burnFirstUser();
        $this->asRole('Owner');
    }

    private function import(string $route, string $csv): array
    {
        $res = $this->post(route($route), ['file' => UploadedFile::fake()->createWithContent('f.csv', $csv)]);
        $res->assertRedirect();

        return $res->baseResponse->getSession()->get('import_result');
    }

    public function test_colors_and_gst_taxes_import_create_and_update(): void
    {
        Color::create(['name' => 'ImpRed', 'status' => true]);
        $r = $this->import('master.colors.import', "Name,Status\nImpRed,no\nImpBlue,yes\n,yes\n");
        $this->assertSame([1, 1, 1], [$r['created'], $r['updated'], $r['skipped']]);
        $this->assertFalse((bool) Color::where('name', 'ImpRed')->value('status'));
        $this->assertTrue((bool) Color::where('name', 'ImpBlue')->value('status'));

        $r = $this->import('master.gst-taxes.import', "Description,Percentage,Status\nImp GST 12,12.5,yes\nImp GST 0,0,no\n");
        $this->assertSame(2, $r['created']);
        $this->assertEquals(12.5, GstTax::where('description', 'Imp GST 12')->value('percentage'));
        $r = $this->import('master.gst-taxes.import', "Description,Percentage,Status\nImp GST 12,18,yes\n");
        $this->assertSame(1, $r['updated']);
        $this->assertEquals(18, GstTax::where('description', 'Imp GST 12')->value('percentage'));
        $this->assertSame(1, GstTax::where('description', 'Imp GST 12')->count());
    }

    public function test_item_category_values_import_auto_creates_category_and_requires_it(): void
    {
        $r = $this->import('master.item-category-values.import', "Name,Item Category,Show In Webstore,Status,Sellquick Applicable,Allowed Qty Ml\nImp Val,Imp Cat,yes,yes,no,500\nNo Cat,,yes,yes,no,0\n");
        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['error_count']);
        $this->assertStringContainsString('Item Category is required', $r['errors'][0]);
        $cat = ItemCategory::where('name', 'Imp Cat')->firstOrFail();
        $v = ItemCategoryValue::where('name', 'Imp Val')->firstOrFail();
        $this->assertSame($cat->id, $v->item_category_id);
        $this->assertTrue($v->show_in_webstore);
        $this->assertSame(500, (int) $v->allowed_qty_ml);
        $this->assertDatabaseMissing('item_category_values', ['name' => 'No Cat']);

        // upsert key is (category, name): re-import updates instead of duplicating
        $r = $this->import('master.item-category-values.import', "Name,Item Category,Status\nImp Val,Imp Cat,no\n");
        $this->assertSame(1, $r['updated']);
        $this->assertSame(1, ItemCategoryValue::where('name', 'Imp Val')->count());
        $this->assertFalse((bool) $v->fresh()->status);
    }

    public function test_branches_registers_import_with_auto_created_branch(): void
    {
        $r = $this->import('master.branches.import', "Name,City,Business Type,Webstore,Language,Country Code,GST Type,GST Filing,Status\nImp Branch,Surat,COCO,yes,English,IN,Regular,Monthly,yes\n");
        $this->assertSame(1, $r['created']);
        $b = Branch::where('name', 'Imp Branch')->firstOrFail();
        $this->assertSame('Surat', $b->city);
        $this->assertTrue((bool) $b->webstore);

        $r = $this->import('master.registers.import', "Name,Branch,Status,Product Type,Inv Seq No,Online Sales Allowed\nImp Reg,Imp Branch,Active,Standard,7,yes\nBad Reg,,Active,Standard,1,no\nAuto Reg,Fresh Branch,Active,Standard,1,no\n");
        $this->assertSame(2, $r['created']);
        $this->assertSame(1, $r['error_count']);
        $this->assertStringContainsString('Branch is required', $r['errors'][0]);
        $reg = Register::where('name', 'Imp Reg')->firstOrFail();
        $this->assertSame($b->id, $reg->branch_id);
        $this->assertSame(7, (int) $reg->inv_seq_no);
        $this->assertTrue($reg->online_sales_allowed);
        $this->assertDatabaseHas('branches', ['name' => 'Fresh Branch', 'business_type' => 'BRANCH']);
    }

    public function test_tender_types_and_values_import_relations(): void
    {
        $r = $this->import('master.tender-types.import', "Name,Status,Type,Mode,Service Applicable,Mandate Refno,Service Charge Perc,Branch\nImp Card,yes,Card,POS,yes,no,\"1.5\",Imp TBranch\nImp Cash,yes,Cash,Manual,no,no,0,\n");
        $this->assertSame(2, $r['created']);
        $card = TenderType::where('name', 'Imp Card')->firstOrFail();
        $this->assertEquals(1.5, $card->service_charge_perc);
        $this->assertTrue($card->service_applicable);
        $this->assertNotNull($card->branch_id);
        $this->assertNull(TenderType::where('name', 'Imp Cash')->value('branch_id'));

        $r = $this->import('master.tender-type-values.import', "Name,Status,Group Ledger,Tender Type,Branch\nImp Visa,yes,Bank,Imp Card,\nOrphan,yes,X,,\nNew Type Val,yes,,Brand New TT,Imp TBranch\n");
        $this->assertSame(2, $r['created']);
        $this->assertSame(1, $r['error_count']);
        $this->assertStringContainsString('Tender Type is required', $r['errors'][0]);
        $visa = TenderTypeValue::where('name', 'Imp Visa')->firstOrFail();
        $this->assertSame($card->id, $visa->tender_type_id);
        $this->assertNull($visa->branch_id);
        $this->assertSame('Bank', $visa->group_ledger);
        $auto = TenderType::where('name', 'Brand New TT')->firstOrFail();
        $this->assertSame('Cash', $auto->type);
        $this->assertSame($auto->id, TenderTypeValue::where('name', 'New Type Val')->value('tender_type_id'));
    }

    public function test_items_import_resolves_relations_and_auto_creates_masters(): void
    {
        $csv = "Name,Product Type,Cost Price,Landing Cost,Sell Price,MRP,Status,Brand,Supplier,GST Tax,Department,Category,Brand Value,HSN Code\n"
            ."Imp Item,Standard,10,10,15,20,yes,Imp Brand,Imp Supplier,Imp 5%,Dept X,Cat X,BV X,230910\n"
            ."Plain Item,Standard,1,1,2,3,yes,,,,,,,\n";
        $r = $this->import('master.items.import', $csv);
        $this->assertSame(2, $r['created']);

        $i = Item::where('name', 'Imp Item')->firstOrFail();
        $this->assertSame(Brand::where('name', 'Imp Brand')->value('id'), $i->brand_id);
        $this->assertSame(Supplier::where('name', 'Imp Supplier')->value('id'), $i->supplier_id);
        $this->assertSame(GstTax::where('description', 'Imp 5%')->value('id'), $i->gst_tax_id);
        $this->assertNotNull($i->department_value_id);
        $this->assertNotNull($i->category_value_id);
        $this->assertNotNull($i->brand_value_id);
        $this->assertSame('Uncategorized', ItemCategoryValue::find($i->department_value_id)->itemCategory->name);
        $this->assertSame('230910', $i->hsn_code);

        $p = Item::where('name', 'Plain Item')->firstOrFail();
        $this->assertNull($p->brand_id);
        $this->assertNull($p->supplier_id);
        $this->assertNull($p->gst_tax_id);
        $this->assertNull($p->department_value_id);
    }

    public function test_customers_import_creates_category_and_relations_by_code(): void
    {
        $csv = "Name,Customer Code,Mobile,Sales Type,Customer Type,Payment Mode,Credit Limit,Status,Customer Category\n"
            ."Imp Cust,IC-1,9444444444,Local,RETAIL INVOICE,Cash Only,500,yes,Imp Cat\n";
        $r = $this->import('master.customers.import', $csv);
        $this->assertSame(1, $r['created']);
        $c = Customer::where('customer_code', 'IC-1')->firstOrFail();
        $this->assertSame(CustomerCategory::where('name', 'Imp Cat')->value('id'), $c->customer_category_id);
        $this->assertEquals(500, $c->credit_limit);

        $r = $this->import('master.customers.import', "Name,Customer Code,Mobile\nImp Cust Renamed,IC-1,9444444444\n");
        $this->assertSame(1, $r['updated']);
        $this->assertSame('Imp Cust Renamed', $c->fresh()->name);
        $this->assertSame(1, Customer::where('customer_code', 'IC-1')->count());
    }
}
