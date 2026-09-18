<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReportActionsAndValidationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Main Branch', 'code' => 'BR001', 'status' => true]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@urbanpos.test'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'branch_id' => $this->branch->id,
            ]
        );
        $this->admin->assignRole('Owner');
    }

    public function test_reports_render_print_report_button()
    {
        $response = $this->actingAs($this->admin)->get(route('reports.billwise-sales'));
        $response->assertStatus(200);
        $response->assertSee('Print Report');
        $response->assertSee('window.print()', false);
        $response->assertSee('Action');

        $responsePurchase = $this->actingAs($this->admin)->get(route('reports.purchase-detail'));
        $responsePurchase->assertStatus(200);
        $responsePurchase->assertSee('Print Report');
        $responsePurchase->assertSee('Action');

        $responseReturn = $this->actingAs($this->admin)->get(route('reports.sales-return-summary'));
        $responseReturn->assertStatus(200);
        $responseReturn->assertSee('Print Report');
        $responseReturn->assertSee('Action');
    }

    public function test_item_hsn_code_validation_requires_8_digits()
    {
        $supplier = Supplier::firstOrCreate(['id' => 1], ['name' => 'Supplier 1', 'status' => true]);

        // Attempt invalid 5-digit HSN code
        $invalidPayload = [
            'name' => 'Test HSN Item Invalid',
            'product_type' => 'Standard',
            'cost_price' => 100,
            'landing_cost' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'status' => 1,
            'store_pickup' => 1,
            'tax_inclusive' => 0,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
            'hsn_code' => '12345', // only 5 digits
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('master.items.store'), $invalidPayload);

        $response->assertSessionHasErrors(['hsn_code']);

        // Attempt valid 8-digit HSN code
        $validPayload = $invalidPayload;
        $validPayload['name'] = 'Test HSN Item Valid 8 Digits';
        $validPayload['hsn_code'] = '85044090'; // exactly 8 digits

        $validResponse = $this->actingAs($this->admin)
            ->post(route('master.items.store'), $validPayload);

        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('items', [
            'name' => 'Test HSN Item Valid 8 Digits',
            'hsn_code' => '85044090',
        ]);
    }

    public function test_purchase_invoice_uppercases_supplier_inv_no()
    {
        $supplier = Supplier::firstOrCreate(['id' => 1], ['name' => 'Test Supplier', 'status' => true]);
        $item = Item::firstOrCreate(
            ['item_code' => 'TESTITEM001'],
            [
                'name' => 'Test Item for Purchase',
                'product_type' => 'Standard',
                'cost_price' => 50,
                'landing_cost' => 50,
                'sell_price' => 100,
                'mrp' => 100,
                'status' => true,
                'store_pickup' => true,
                'tax_inclusive' => false,
                'batch_expiry_details' => 'Not Required',
                'allow_negative_stock' => false,
            ]
        );

        $payload = [
            'invoice_date' => date('Y-m-d'),
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_type' => 'Local',
            'c_form' => 'No Forms',
            'supplier_inv_no' => 'inv-test-lower-123',
            'supplier_inv_date' => date('Y-m-d'),
            'supplier_inv_amount' => 100.00,
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'cost_price' => 50,
                    'sell_price' => 100,
                    'mrp' => 100,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('purchase.purchase-invoices.store'), $payload);

        $response->assertSessionHasNoErrors();

        $saved = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($saved);
        $this->assertEquals('INV-TEST-LOWER-123', $saved->supplier_inv_no);
    }
}
