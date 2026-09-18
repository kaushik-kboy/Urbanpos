<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBillEditStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_sales_bill_edit_view_has_proper_stock_for_bill_items(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB', 'status' => 1]);
        $customer = Customer::create(['name' => 'John Doe', 'mobile' => '9876543210', 'status' => 1]);

        $item = Item::create([
            'name' => 'Test Widget',
            'product_type' => 'Standard',
            'cost_price' => 100,
            'landing_cost' => 100,
            'sell_price' => 150,
            'mrp' => 150,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 1,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ]);

        // Current stock on hand in warehouse is 30
        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 30,
        ]);

        // Bill has 20 units
        $bill = SalesBill::create([
            'bill_number' => 'SB-2026-TEST',
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'bill_date' => now()->toDateString(),
            'status' => 'Posted',
            'total' => 3000,
        ]);

        $bill->items()->create([
            'item_id' => $item->id,
            'qty' => 20,
            'sell_price' => 150,
            'mrp' => 150,
            'net_amount' => 3000,
        ]);

        $response = $this->actingAs($user)->get(route('sales.sales-bills.edit', $bill));
        $response->assertStatus(200);

        // In edit mode, available stock for this bill = 30 (on hand) + 20 (already in bill) = 50
        $response->assertSee('data-stock="50"', false);
        $response->assertSee('value="50"', false);
    }
}
