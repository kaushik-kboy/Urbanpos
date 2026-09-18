<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\TenderType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesBillIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private User $cashier;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'Motera Branch', 'code' => 'MOTERA', 'state' => 'Gujarat']
        );

        $this->customer = Customer::firstOrCreate(
            ['mobile' => '9876543299'],
            ['name' => 'Idempotency Test Customer', 'status' => true, 'credit_limit' => 10000]
        );

        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%']);

        $this->item = Item::firstOrCreate(
            ['item_code' => 'IDEMP-TEST-001'],
            [
                'name' => 'Cat Food Premium 1kg',
                'sell_price' => 300.00,
                'mrp' => 300.00,
                'cost_price' => 200.00,
                'gst_tax_id' => $gst->id,
                'tax_inclusive' => true,
                'status' => true,
                'allow_negative_stock' => true,
            ]
        );

        ItemStock::updateOrCreate(
            ['branch_id' => $this->branch->id, 'item_id' => $this->item->id],
            ['quantity' => 50.0]
        );

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('Owner');
    }

    public function test_create_sales_bill_form_renders_posting_key_and_guard_script(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('sales.sales-bills.create'));

        $response->assertOk();
        $response->assertSee('name="posting_key"', false);
        $response->assertSee('pos-scan-guard.js', false);
    }

    public function test_submitting_duplicate_posting_key_prevents_duplicate_bill_creation(): void
    {
        $postingKey = (string) Str::uuid();
        $cashTender = TenderType::where('name', 'Cash')->first();

        $payload = [
            'posting_key' => $postingKey,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 300.00,
                    'mrp' => 300.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ]
            ],
            'payments' => [
                [
                    'tender_type_id' => $cashTender->id,
                    'amount' => 300.00,
                ]
            ],
        ];

        // First Submission
        $firstResponse = $this->actingAs($this->cashier)->post(route('sales.sales-bills.store'), $payload);
        $firstResponse->assertSessionHasNoErrors();
        $firstResponse->assertRedirect();

        $matchingBills = SalesBill::where('posting_key', $postingKey)->get();
        $this->assertCount(1, $matchingBills, 'First submission must create exactly one bill.');
        $createdBillId = $matchingBills->first()->id;

        // Second Submission with the EXACT same posting_key (simulating double click / retry)
        $secondResponse = $this->actingAs($this->cashier)->post(route('sales.sales-bills.store'), $payload);
        $secondResponse->assertRedirect();

        $afterSecondBills = SalesBill::where('posting_key', $postingKey)->get();
        $this->assertCount(1, $afterSecondBills, 'Second duplicate submission MUST NOT create a second bill in database.');
        $this->assertEquals($createdBillId, $afterSecondBills->first()->id, 'Must reference the already created bill.');
    }

    public function test_create_sales_bill_view_renders_save_and_reset_buttons(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('sales.sales-bills.create'));

        $response->assertOk();
        $response->assertSee('Create Sales Bill');
        $response->assertSee('<button type="submit" class="btn btn-primary">Save</button>', false);
        $response->assertSee('btn-reset-form');
        $response->assertSee('Cancel');
    }
}
