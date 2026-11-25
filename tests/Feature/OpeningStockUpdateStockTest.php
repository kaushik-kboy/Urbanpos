<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OpeningStock;
use App\Models\OpeningStockItem;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpeningStockUpdateStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_opening_stock_succeeds_even_when_branch_stock_is_zero(): void
    {
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $branch = Branch::create(['name' => 'Motera', 'code' => 'MOT']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Owner');

        $item = Item::create([
            'name' => 'Royal Canin Mother & Babycat',
            'item_code' => 'RC001',
            'sell_price' => 500,
            'mrp' => 500,
            'cost_price' => 350,
            'allow_negative_stock' => false,
        ]);

        $openingStock = OpeningStock::create([
            'entry_number' => 'OS/2627/000001',
            'entry_date' => '2026-11-01',
            'branch_id' => $branch->id,
            'total_qty' => 5,
            'total' => 1750,
            'status' => 'Posted',
        ]);

        OpeningStockItem::create([
            'opening_stock_id' => $openingStock->id,
            'item_id' => $item->id,
            'qty' => 5,
            'cost_price' => 350,
            'net_amount' => 1750,
        ]);

        app(StockLedgerService::class)->post(
            itemId: $item->id,
            branchId: $branch->id,
            movementType: 'OPENING',
            qtyDelta: 5,
            unitCost: 350,
            referenceType: OpeningStock::class,
            referenceId: $openingStock->id,
            documentDate: '2026-11-01',
        );

        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(5.0, (float) $stock->quantity);

        // All 5 units subsequently sold -> current stock is 0
        $stock->update(['quantity' => 0.0]);

        // User edits opening stock (e.g. changing remarks)
        $updatePayload = [
            'branch_id' => $branch->id,
            'entry_date' => '2026-11-01',
            'remarks' => 'Updated opening stock remarks',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 5,
                    'cost_price' => 350,
                    'net_amount' => 1750,
                ],
            ],
        ];

        $response = $this->actingAs($user)->put(
            route('inventory.opening-stocks.update', $openingStock),
            $updatePayload
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('inventory.opening-stocks.index'));

        $openingStock->refresh();
        $this->assertEquals('Updated opening stock remarks', $openingStock->remarks);

        $stock->refresh();
        $this->assertEquals(0.0, (float) $stock->quantity);
    }

    public function test_reducing_opening_stock_qty_when_items_sold_rejects_with_negative_stock_error(): void
    {
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $branch = Branch::create(['name' => 'Satellite', 'code' => 'SAT']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Owner');

        $item = Item::create([
            'name' => 'Whiskas Ocean Fish',
            'item_code' => 'WH001',
            'sell_price' => 200,
            'mrp' => 200,
            'cost_price' => 150,
            'allow_negative_stock' => false,
        ]);

        $openingStock = OpeningStock::create([
            'entry_number' => 'OS/2627/000002',
            'entry_date' => '2026-11-01',
            'branch_id' => $branch->id,
            'total_qty' => 4,
            'total' => 600,
            'status' => 'Posted',
        ]);

        OpeningStockItem::create([
            'opening_stock_id' => $openingStock->id,
            'item_id' => $item->id,
            'qty' => 4,
            'cost_price' => 150,
            'net_amount' => 600,
        ]);

        app(StockLedgerService::class)->post(
            itemId: $item->id,
            branchId: $branch->id,
            movementType: 'OPENING',
            qtyDelta: 4,
            unitCost: 150,
            referenceType: OpeningStock::class,
            referenceId: $openingStock->id,
            documentDate: '2026-11-01',
        );

        // All 4 units sold -> current stock is 0
        $stock = ItemStock::where('item_id', $item->id)->where('branch_id', $branch->id)->first();
        $stock->update(['quantity' => 0.0]);

        // Attempting to reduce quantity from 4 to 2 (resulting stock would be -2)
        $updatePayload = [
            'branch_id' => $branch->id,
            'entry_date' => '2026-11-01',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'cost_price' => 150,
                    'net_amount' => 300,
                ],
            ],
        ];

        $response = $this->actingAs($user)->put(
            route('inventory.opening-stocks.update', $openingStock),
            $updatePayload
        );

        $response->assertSessionHasErrors('stock');
    }
}
