<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBillNegativeStockAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Item $itemAllowNeg;
    private Item $itemStrict;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 3],
            ['name' => 'URBAN PETS / MOTERA', 'code' => 'UPMOTERA', 'status' => true]
        );

        $this->customer = Customer::firstOrCreate(
            ['id' => 101],
            ['name' => 'Test Customer', 'mobile' => '9876543210', 'status' => true]
        );

        $this->itemAllowNeg = Item::firstOrCreate(
            ['item_code' => 'NEG_STOCK_ITEM'],
            [
                'name' => 'Proplan Medium Puppy 3kg',
                'cost_price' => 450.00,
                'sell_price' => 850.00,
                'mrp' => 850.00,
                'status' => true,
                'allow_negative_stock' => true,
            ]
        );

        $this->itemStrict = Item::firstOrCreate(
            ['item_code' => 'STRICT_STOCK_ITEM'],
            [
                'name' => 'Snookie Butter Chicken 120gm',
                'cost_price' => 120.00,
                'sell_price' => 280.00,
                'mrp' => 280.00,
                'status' => true,
                'allow_negative_stock' => false,
            ]
        );

        $this->user = User::first() ?? User::factory()->create();
        $this->user->assignRole('Owner');
        $this->user->branch_id = $this->branch->id;
        $this->user->save();
        session(['active_branch_id' => $this->branch->id]);
    }

    public function test_item_with_allow_negative_stock_succeeds_even_when_available_stock_is_zero(): void
    {
        // Ensure 0 stock in ItemStock
        ItemStock::updateOrCreate(
            ['item_id' => $this->itemAllowNeg->id, 'branch_id' => $this->branch->id],
            ['quantity' => 0.0, 'cost_price' => 450.0]
        );

        $payload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'None',
            'items' => [
                [
                    'item_id' => $this->itemAllowNeg->id,
                    'qty' => 1,
                    'sell_price' => 850.00,
                    'mrp' => 850.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.sales-bills.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales_bills', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'total' => 850.00,
        ]);
    }

    public function test_item_without_allow_negative_stock_is_safely_blocked_when_insufficient(): void
    {
        // Ensure 0 stock in ItemStock
        ItemStock::updateOrCreate(
            ['item_id' => $this->itemStrict->id, 'branch_id' => $this->branch->id],
            ['quantity' => 0.0, 'cost_price' => 120.0]
        );

        $payload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'None',
            'items' => [
                [
                    'item_id' => $this->itemStrict->id,
                    'qty' => 1,
                    'sell_price' => 280.00,
                    'mrp' => 280.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sales.sales-bills.store'), $payload);

        $response->assertSessionHasErrors('items');
    }

    public function test_create_view_retains_customer_and_active_branch_on_validation_failure(): void
    {
        // Post invalid payload (e.g. missing bill_date)
        $payload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'bill_date' => '', // missing bill date triggers validation error
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_type' => 'None',
            'items' => [
                [
                    'item_id' => $this->itemAllowNeg->id,
                    'qty' => 2,
                    'sell_price' => 850.00,
                    'mrp' => 850.00,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->from(route('sales.sales-bills.create'))->post(route('sales.sales-bills.store'), $payload);

        $response->assertRedirect(route('sales.sales-bills.create'));
        $response->assertSessionHasErrors('bill_date');

        // Follow redirect to create view with old input
        $followResponse = $this->actingAs($this->user)->get(route('sales.sales-bills.create'));

        $followResponse->assertStatus(200);
        // Customer must be selected
        $followResponse->assertSee((string) $this->customer->id);
        // Proplan item must be present
        $followResponse->assertSee('Proplan Medium Puppy 3kg');
        // allow-negative-stock must be '1'
        $followResponse->assertSee('data-allow-negative-stock="1"', false);
    }
}
