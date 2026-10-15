<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\FormFieldValidationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCodeAutoGenerationAndPurchaseInvoiceViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FormFieldValidationSeeder::class);

        $this->branch = Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('Owner');

        $this->supplier = Supplier::create([
            'name' => 'Pet Supplier Test',
            'state' => 'Gujarat',
            'country' => 'India',
            'purchase_type' => 'Local',
            'status' => true,
        ]);
    }

    public function test_new_item_creation_auto_generates_unique_sequential_item_code(): void
    {
        // 1. Create first item with a starting item_code
        Item::create([
            'name' => 'Existing Product A',
            'item_code' => '500',
            'cost_price' => 100,
            'landing_cost' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'product_type' => 'Standard',
            'status' => true,
        ]);

        // 2. Create new item without providing item_code
        $payload = [
            'name' => 'New Product B',
            'cost_price' => 200,
            'landing_cost' => 200,
            'sell_price' => 250,
            'mrp' => 250,
            'product_type' => 'Standard',
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ];

        $response = $this->actingAs($this->admin)->post(route('master.items.store'), $payload);
        $response->assertRedirect(route('master.items.index'));

        $itemB = Item::where('name', 'New Product B')->first();
        $this->assertNotNull($itemB);
        $this->assertEquals('501', $itemB->item_code);

        // 3. Create another item, should get 502
        $payload['name'] = 'New Product C';
        $response2 = $this->actingAs($this->admin)->post(route('master.items.store'), $payload);
        $response2->assertRedirect(route('master.items.index'));

        $itemC = Item::where('name', 'New Product C')->first();
        $this->assertNotNull($itemC);
        $this->assertEquals('502', $itemC->item_code);
    }

    public function test_generate_item_code_api_endpoint_returns_unique_code(): void
    {
        Item::create([
            'name' => 'Existing Base Product',
            'item_code' => '1000',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 80,
            'status' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('master.items.generate-item-code'));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'item_code' => '1001',
        ]);
    }

    public function test_item_create_view_renders_item_code_field_with_suggested_code(): void
    {
        Item::create([
            'name' => 'Prior Item',
            'item_code' => '2000',
            'cost_price' => 50,
            'sell_price' => 80,
            'mrp' => 80,
            'status' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('master.items.create'));
        $response->assertOk();
        $response->assertSee('name="item_code"', false);
        $response->assertSee('value="2001"', false);
        $response->assertSee('id="btn-generate-item-code"', false);
    }

    public function test_purchase_invoice_view_displays_item_code_for_items(): void
    {
        $item = Item::create([
            'name' => 'Whiskas Kitten Food',
            'item_code' => '8637',
            'cost_price' => 550,
            'landing_cost' => 550,
            'sell_price' => 850,
            'mrp' => 850,
            'product_type' => 'Standard',
            'status' => true,
        ]);

        $invoice = PurchaseInvoice::create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PI02/2627/000003',
            'invoice_date' => now()->toDateString(),
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'subtotal' => 11000,
            'total_gst' => 550,
            'grand_total' => 11550,
            'supplier_inv_amount' => 11550,
            'created_by' => $this->admin->id,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'item_id' => $item->id,
            'qty' => 20,
            'free_qty' => 0,
            'cost_price' => 550,
            'effective_cost' => 550,
            'sell_price' => 850,
            'mrp' => 850,
            'gst_percent' => 5,
            'gst_tax_amount' => 550,
            'net_amount' => 11550,
        ]);

        $response = $this->actingAs($this->admin)->get(route('purchase.purchase-invoices.show', $invoice));
        $response->assertOk();
        // Item code 8637 must be visible in the table row
        $response->assertSee('8637');
        $response->assertSee('Whiskas Kitten Food');
    }

    public function test_barcode_api_endpoint_resolves_by_item_code_as_well_as_ean(): void
    {
        $item = Item::create([
            'name' => 'Royal Canin Mini Adult',
            'item_code' => '9988',
            'ean_upc_code' => '8901234567890',
            'cost_price' => 700,
            'sell_price' => 1100,
            'mrp' => 1100,
            'status' => true,
        ]);

        // Search by EAN
        $responseEan = $this->actingAs($this->admin)->getJson('/api/v1/items/barcode/8901234567890');
        $responseEan->assertOk();
        $responseEan->assertJsonPath('data.item_code', '9988');

        // Search by item_code
        $responseCode = $this->actingAs($this->admin)->getJson('/api/v1/items/barcode/9988');
        $responseCode->assertOk();
        $responseCode->assertJsonPath('data.name', 'Royal Canin Mini Adult');
    }

    public function test_item_model_code_attribute_accessor(): void
    {
        $item1 = Item::create([
            'name' => 'Item With Both',
            'item_code' => 'CODE-101',
            'ean_upc_code' => 'EAN-202',
            'status' => true,
        ]);
        $this->assertEquals('CODE-101', $item1->code);

        $item2 = new Item([
            'name' => 'Item With Only Ean',
            'ean_upc_code' => 'EAN-ONLY',
            'status' => true,
        ]);
        $this->assertEquals('EAN-ONLY', $item2->code);
    }

    public function test_batch_stock_service_resolves_truepos_closing_stock_batches(): void
    {
        $item = Item::create([
            'name' => 'Canimet 24mg Test Item',
            'item_code' => 'TEST-12164',
            'cost_price' => 300,
            'sell_price' => 450,
            'mrp' => 450,
            'batch_expiry_details' => 'Mandatory',
            'status' => true,
        ]);

        \Illuminate\Support\Facades\DB::table('closing_stocks')->insert([
            [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'branch_id' => $this->branch->id,
                'batch_no' => 'C12164-9840',
                'expiry_date' => '2027-09-30',
                'net_cost' => 288.12,
                'closing_stock' => 1.0,
                'mrp' => 450.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'branch_id' => $this->branch->id,
                'batch_no' => 'C12164-1373',
                'expiry_date' => '2028-01-31',
                'net_cost' => 48.01,
                'closing_stock' => 33.0,
                'mrp' => 75.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $batchService = app(\App\Services\Inventory\BatchStockService::class);
        $batches = $batchService->getItemBatches($item->id, $this->branch->id);

        $this->assertCount(2, $batches);

        $response = $this->actingAs($this->admin)->getJson(route('sales.sales-bills.lookup-item', [
            'query' => 'TEST-12164',
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonCount(2, 'batches');
        $this->assertEquals(450, $response->json('batches.0.mrp'));
        $this->assertEquals(75, $response->json('batches.1.mrp'));
    }
}


