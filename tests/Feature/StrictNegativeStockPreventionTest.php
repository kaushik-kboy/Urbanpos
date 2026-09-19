<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormFieldValidation;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StrictNegativeStockPreventionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch1;
    private Branch $branch2;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch1 = Branch::firstOrCreate(
            ['id' => 901],
            ['name' => 'Source Branch 901', 'code' => 'SB901', 'status' => true]
        );
        $this->branch2 = Branch::firstOrCreate(
            ['id' => 902],
            ['name' => 'Dest Branch 902', 'code' => 'DB902', 'status' => true]
        );

        $this->item = Item::firstOrCreate(
            ['item_code' => 'STKTEST01'],
            [
                'name' => 'Strict Stock Test Item',
                'cost_price' => 50.00,
                'sell_price' => 100.00,
                'mrp' => 100.00,
                'status' => true,
                'allow_negative_stock' => false,
            ]
        );

        $this->user = User::first() ?? User::factory()->create();
        $this->user->assignRole('Owner');
        $this->user->branch_id = $this->branch1->id;
        $this->user->save();
        session(['active_branch_id' => $this->branch1->id]);
    }

    public function test_stock_ledger_service_strictly_blocks_negative_stock(): void
    {
        $ledger = app(StockLedgerService::class);

        // Ensure current stock is set to exactly 5
        ItemStock::updateOrCreate(
            ['item_id' => $this->item->id, 'branch_id' => $this->branch1->id],
            ['quantity' => 5.0, 'cost_price' => 50.0]
        );

        // Deducting 3 should succeed (balance remains 2)
        $row = $ledger->post(
            itemId: $this->item->id,
            branchId: $this->branch1->id,
            movementType: 'TRANSFER_OUT',
            qtyDelta: -3.0,
            unitCost: 50.0,
            referenceType: null,
            referenceId: null,
            documentDate: now()->toDateString(),
        );
        $this->assertEquals(2.0, (float) $row->running_balance_qty);

        // Deducting 5 when only 2 is available MUST throw ValidationException
        $this->expectException(ValidationException::class);
        $ledger->post(
            itemId: $this->item->id,
            branchId: $this->branch1->id,
            movementType: 'TRANSFER_OUT',
            qtyDelta: -5.0,
            unitCost: 50.0,
            referenceType: null,
            referenceId: null,
            documentDate: now()->toDateString(),
        );
    }

    public function test_pending_receipt_page_supports_status_tabs_and_history(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.stock-transfers.pending-receipt', [
            'status' => 'All',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Awaiting Receipt');
        $response->assertSee('Received History');
        $response->assertSee('All Transfers Inward');
    }

    public function test_inventory_line_item_fields_exist_in_form_validations(): void
    {
        $this->seed(\Database\Seeders\FormFieldValidationSeeder::class);

        $requiredKeys = ['item_code', 'item_name', 'exp_date', 'available', 'qty'];
        $existingKeys = FormFieldValidation::where('module_key', 'stock_transfers')
            ->whereIn('field_name', $requiredKeys)
            ->pluck('field_name')
            ->all();

        foreach ($requiredKeys as $key) {
            $this->assertContains($key, $existingKeys, "Expected line-item field '{$key}' in stock_transfers validation.");
        }
    }
}
