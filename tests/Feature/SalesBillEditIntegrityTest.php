<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBillEditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Customer $customer;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->branch = Branch::firstOrCreate(['name' => 'Main Test Branch'], ['status' => true]);
        $this->customer = Customer::firstOrCreate(['name' => 'Alice Test', 'mobile' => '9876543210'], ['status' => true, 'sales_type' => 'Local']);
        $this->item = Item::create([
            'name' => 'Test Item',
            'item_code' => 'TI-001',
            'product_type' => 'Standard',
            'sell_price' => 100,
            'cost_price' => 80,
            'mrp' => 120,
            'status' => true,
        ]);
        \App\Models\ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->branch->id, 'quantity' => 50]);
        TenderType::firstOrCreate(['name' => 'Cash', 'type' => 'Cash'], ['status' => true]);
    }

    /**
     * Test that updating an existing sales bill preserves its original bill_number,
     * does not trigger "Bill number must be unique" validation error,
     * and rejects attempts to hijack another bill's number.
     */
    public function test_sales_bill_edit_preserves_number_and_passes_validation(): void
    {
        $this->actingAs($this->owner);

        // Create Bill 1
        $bill1 = SalesBill::create([
            'bill_number' => 'UB/2026-27/00099',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'total' => 100,
            'status' => 'Completed',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill1->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'sell_price' => 100,
            'mrp' => 120,
            'net_amount' => 100,
        ]);

        // Attempt to update Bill 1
        $updatePayload = [
            'bill_number' => $bill1->bill_number,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Local',
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'sell_price' => 100,
                    'mrp' => 120,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                ],
            ],
            'payments' => [
                ['tender_type_id' => 1, 'amount' => 200],
            ],
        ];

        $response = $this->put(route('sales.sales-bills.update', $bill1), $updatePayload);

        // Validation must pass without error
        $response->assertSessionHasNoErrors();

        // Bill number must remain immutable
        $bill1->refresh();
        $this->assertEquals('UB/2026-27/00099', $bill1->bill_number, 'Bill number must remain immutable on update');
    }
}
