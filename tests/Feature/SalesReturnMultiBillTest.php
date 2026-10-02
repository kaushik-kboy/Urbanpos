<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnMultiBillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Item $itemA;
    private Item $itemB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->user = User::factory()->create();
        $this->branch = Branch::create(['name' => 'Main Test Branch', 'state' => 'Gujarat']);
        $gst = GstTax::firstOrCreate(['percentage' => 18], ['name' => 'GST 18%', 'description' => 'GST 18%', 'status' => true]);

        $this->customer = Customer::create(['name' => 'Multi Bill Customer', 'mobile' => '9888877777', 'status' => true]);

        $mkItem = fn ($name, $code, $price) => Item::create([
            'name' => $name,
            'item_code' => $code,
            'sell_price' => $price,
            'mrp' => $price * 1.2,
            'cost_price' => $price * 0.7,
            'landing_cost' => $price * 0.7,
            'gst_tax_id' => $gst->id,
            'status' => true,
            'allow_negative_stock' => true,
            'batch_expiry_details' => 'Not Required'
        ]);

        $this->itemA = $mkItem('Item Alpha', 'ALPHA-01', 100);
        $this->itemB = $mkItem('Item Beta', 'BETA-02', 200);

        ItemStock::create(['item_id' => $this->itemA->id, 'branch_id' => $this->branch->id, 'quantity' => 100, 'cost_price' => 70]);
        ItemStock::create(['item_id' => $this->itemB->id, 'branch_id' => $this->branch->id, 'quantity' => 100, 'cost_price' => 140]);
    }

    public function test_customer_purchased_items_endpoint_returns_grouped_bills_with_remaining_qty()
    {
        // Bill 1: 01-Oct
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-101',
            'bill_date' => now()->subDays(5)->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 500,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill1->id,
            'item_id' => $this->itemA->id,
            'qty' => 5,
            'sell_price' => 100,
            'net_amount' => 500,
        ]);

        // Bill 2: 20-Sep
        $bill2 = SalesBill::create([
            'bill_number' => 'SB-102',
            'bill_date' => now()->subDays(15)->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 600,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill2->id,
            'item_id' => $this->itemB->id,
            'qty' => 3,
            'sell_price' => 200,
            'net_amount' => 600,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('sales.sales-returns.customer-purchased-items', $this->customer->id));
        $response->assertOk();
        $response->assertJsonStructure([
            'customer' => ['id', 'name'],
            'days',
            'bills' => [
                '*' => [
                    'id', 'bill_number', 'bill_date', 'total', 'items' => [
                        '*' => ['item_id', 'item_name', 'original_qty', 'remaining_qty', 'sales_bill_id']
                    ]
                ]
            ],
            'summary' => ['total_bills', 'total_items', 'total_returnable_items']
        ]);

        $data = $response->json();
        $this->assertEquals(2, $data['summary']['total_bills']);
        $this->assertEquals(2, $data['summary']['total_returnable_items']);
    }

    public function test_can_return_items_originating_from_different_bills_in_single_return()
    {
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-101',
            'bill_date' => now()->subDays(5)->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 500,
        ]);
        $billItemA = SalesBillItem::create([
            'sales_bill_id' => $bill1->id,
            'item_id' => $this->itemA->id,
            'qty' => 5,
            'sell_price' => 100,
            'net_amount' => 500,
        ]);

        $bill2 = SalesBill::create([
            'bill_number' => 'SB-102',
            'bill_date' => now()->subDays(15)->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 600,
        ]);
        $billItemB = SalesBillItem::create([
            'sales_bill_id' => $bill2->id,
            'item_id' => $this->itemB->id,
            'qty' => 3,
            'sell_price' => 200,
            'net_amount' => 600,
        ]);

        // Submit multi-bill return
        $postData = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->format('Y-m-d'),
            'sales_type' => 'Local',
            'return_mode' => 'Credit Note',
            'sales_bill_id' => null, // Multi-bill mode
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'sales_bill_id' => $bill1->id,
                    'sales_bill_item_id' => $billItemA->id,
                    'qty' => 2,
                    'sell_price' => 100,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ],
                [
                    'item_id' => $this->itemB->id,
                    'sales_bill_id' => $bill2->id,
                    'sales_bill_item_id' => $billItemB->id,
                    'qty' => 1,
                    'sell_price' => 200,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 18,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->post(route('sales.sales-returns.store'), $postData);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.sales-returns.index'));

        $salesReturn = SalesReturn::latest('id')->first();
        $this->assertNotNull($salesReturn);
        $this->assertCount(2, $salesReturn->items);

        $lineA = $salesReturn->items->where('item_id', $this->itemA->id)->first();
        $this->assertEquals($bill1->id, $lineA->sales_bill_id);
        $this->assertEquals(2, (float) $lineA->qty);

        $lineB = $salesReturn->items->where('item_id', $this->itemB->id)->first();
        $this->assertEquals($bill2->id, $lineB->sales_bill_id);
        $this->assertEquals(1, (float) $lineB->qty);

        // Verify remaining returnable quantities in subsequent history query
        $histResp = $this->actingAs($this->user)->getJson(route('sales.sales-returns.customer-purchased-items', $this->customer->id));
        $bills = collect($histResp->json('bills'))->keyBy('id');

        $bill1Items = collect($bills[$bill1->id]['items'])->keyBy('item_id');
        $this->assertEquals(3, (float) $bill1Items[$this->itemA->id]['remaining_qty']); // 5 - 2 = 3

        $bill2Items = collect($bills[$bill2->id]['items'])->keyBy('item_id');
        $this->assertEquals(2, (float) $bill2Items[$this->itemB->id]['remaining_qty']); // 3 - 1 = 2
    }

    public function test_cannot_exceed_remaining_quantity_of_specific_bill()
    {
        $bill1 = SalesBill::create([
            'bill_number' => 'SB-101',
            'bill_date' => now()->subDays(5)->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'sales_type' => 'Local',
            'total' => 200,
        ]);
        SalesBillItem::create([
            'sales_bill_id' => $bill1->id,
            'item_id' => $this->itemA->id,
            'qty' => 2,
            'sell_price' => 100,
            'net_amount' => 200,
        ]);

        // Attempt to return 3 when only 2 was sold on Bill 1
        $postData = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'return_date' => now()->format('Y-m-d'),
            'sales_type' => 'Local',
            'return_mode' => 'Credit Note',
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'sales_bill_id' => $bill1->id,
                    'qty' => 3,
                    'sell_price' => 100,
                ],
            ]
        ];

        $response = $this->actingAs($this->user)->post(route('sales.sales-returns.store'), $postData);
        $response->assertSessionHasErrors('items');
    }
}
