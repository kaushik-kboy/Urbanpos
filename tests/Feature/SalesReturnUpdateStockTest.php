<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReturnUpdateStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_return_mode_succeeds_even_when_branch_stock_is_zero(): void
    {
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $branch = Branch::create(['name' => 'Motera', 'code' => 'MOT']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Owner');

        $customer = Customer::create([
            'name' => 'Palak Sarvaiya',
            'mobile' => '8490844016',
            'branch_id' => $branch->id,
        ]);

        $item = Item::create([
            'name' => 'Royal Canin Mini Puppy 800 GM',
            'item_code' => '1693',
            'sell_price' => 980,
            'mrp' => 980,
            'cost_price' => 700,
            'allow_negative_stock' => false,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'SI02/2627/001836',
            'bill_date' => '2026-11-15',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_type' => 'Local',
            'total' => 980,
            'status' => 'Completed',
        ]);

        $billItem = SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 1,
            'sell_price' => 980,
            'mrp' => 980,
            'cost_at_sale' => 700,
            'net_amount' => 980,
        ]);

        // Create initial sales return
        $salesReturn = SalesReturn::create([
            'return_number' => 'CN02/2627/000037',
            'return_date' => '2026-11-22',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_bill_id' => $bill->id,
            'sales_type' => 'Local',
            'return_mode' => 'RRN',
            'total' => 980,
            'status' => 'Posted',
        ]);

        $returnItem = SalesReturnItem::create([
            'sales_return_id' => $salesReturn->id,
            'sales_bill_id' => $bill->id,
            'sales_bill_item_id' => $billItem->id,
            'item_id' => $item->id,
            'qty' => 1,
            'sell_price' => 980,
            'mrp' => 980,
            'net_amount' => 980,
        ]);

        // Post initial stock in via StockLedger
        app(StockLedgerService::class)->post(
            itemId: $item->id,
            branchId: $branch->id,
            movementType: 'SALE_RETURN',
            qtyDelta: 1,
            unitCost: 700,
            referenceType: SalesReturn::class,
            referenceId: $salesReturn->id,
            documentDate: '2026-11-22',
        );

        // Simulate that this returned unit was subsequently sold (reducing stock to 0)
        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(1.0, (float) $stock->quantity);

        $stock->update(['quantity' => 0.0]); // Now available is 0

        // User edits the return mode from 'RRN' to 'Card'
        $updatePayload = [
            'return_date' => '2026-11-22',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_bill_id' => $bill->id,
            'sales_type' => 'Local',
            'return_mode' => 'Card',
            'items' => [
                [
                    'item_id' => $item->id,
                    'sales_bill_id' => $bill->id,
                    'sales_bill_item_id' => $billItem->id,
                    'qty' => 1,
                    'sell_price' => 980,
                    'mrp' => 980,
                ],
            ],
        ];

        $response = $this->actingAs($user)->put(
            route('sales.sales-returns.update', $salesReturn),
            $updatePayload
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.sales-returns.index'));

        $salesReturn->refresh();
        $this->assertEquals('Card', $salesReturn->return_mode);

        $stock->refresh();
        $this->assertEquals(0.0, (float) $stock->quantity);
    }

    public function test_reducing_return_qty_when_items_sold_rejects_with_negative_stock_error(): void
    {
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $branch = Branch::create(['name' => 'Satellite', 'code' => 'SAT']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Owner');

        $customer = Customer::create([
            'name' => 'Test Customer',
            'mobile' => '9999999999',
            'branch_id' => $branch->id,
        ]);

        $item = Item::create([
            'name' => 'Test Product',
            'item_code' => 'TP01',
            'sell_price' => 500,
            'mrp' => 500,
            'cost_price' => 300,
            'allow_negative_stock' => false,
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'SI01/2627/000100',
            'bill_date' => '2026-11-15',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_type' => 'Local',
            'total' => 1000,
            'status' => 'Completed',
        ]);

        $billItem = SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $item->id,
            'qty' => 2,
            'sell_price' => 500,
            'mrp' => 500,
            'cost_at_sale' => 300,
            'net_amount' => 1000,
        ]);

        $salesReturn = SalesReturn::create([
            'return_number' => 'CN01/2627/000010',
            'return_date' => '2026-11-20',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_bill_id' => $bill->id,
            'sales_type' => 'Local',
            'return_mode' => 'Cash',
            'total' => 1000,
            'status' => 'Posted',
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $salesReturn->id,
            'sales_bill_id' => $bill->id,
            'sales_bill_item_id' => $billItem->id,
            'item_id' => $item->id,
            'qty' => 2,
            'sell_price' => 500,
            'mrp' => 500,
            'net_amount' => 1000,
        ]);

        app(StockLedgerService::class)->post(
            itemId: $item->id,
            branchId: $branch->id,
            movementType: 'SALE_RETURN',
            qtyDelta: 2,
            unitCost: 300,
            referenceType: SalesReturn::class,
            referenceId: $salesReturn->id,
            documentDate: '2026-11-20',
        );

        // All 2 returned units are sold (available becomes 0)
        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
        $stock->update(['quantity' => 0.0]);

        // Attempting to reduce return qty from 2 to 1 (resulting stock would be -1)
        $updatePayload = [
            'return_date' => '2026-11-20',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sales_bill_id' => $bill->id,
            'sales_type' => 'Local',
            'return_mode' => 'Cash',
            'items' => [
                [
                    'item_id' => $item->id,
                    'sales_bill_id' => $bill->id,
                    'sales_bill_item_id' => $billItem->id,
                    'qty' => 1,
                    'sell_price' => 500,
                    'mrp' => 500,
                ],
            ],
        ];

        $response = $this->actingAs($user)->put(
            route('sales.sales-returns.update', $salesReturn),
            $updatePayload
        );

        $response->assertSessionHasErrors('stock');
    }
}
