<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Models\UserFormPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormLayoutCustomizerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Outlet', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_sales_bill_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('sales.sales-bills.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('sb-header-fields-grid');
        $response->assertSee('data-field="customer_id"', false);
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="bill_date"', false);
    }

    public function test_user_can_save_and_retrieve_form_layout_preferences(): void
    {
        $payload = [
            'form_key' => 'sales_bills.header',
            'preferences' => [
                ['field' => 'bill_date', 'order' => 1, 'grid_col' => 'col-md-6', 'visible' => true],
                ['field' => 'customer_id', 'order' => 2, 'grid_col' => 'col-md-6', 'visible' => true],
                ['field' => 'branch_id', 'order' => 3, 'grid_col' => 'col-md-4', 'visible' => true],
                ['field' => 'delivery_time', 'order' => 4, 'grid_col' => 'col-md-4', 'visible' => false],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('tools.form-preferences.store'), $payload);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        // Verify in database
        $saved = UserFormPreference::getForUser($this->user->id, 'sales_bills.header');
        $this->assertNotNull($saved);
        $this->assertCount(4, $saved);
        $this->assertSame('bill_date', $saved[0]['field']);
        $this->assertSame('col-md-6', $saved[0]['grid_col']);
        $this->assertFalse($saved[3]['visible']);

        // Verify GET endpoint
        $getResponse = $this->actingAs($this->user)
            ->get(route('tools.form-preferences.get', ['form_key' => 'sales_bills.header']));

        $getResponse->assertOk();
        $getResponse->assertJsonPath('preferences.0.field', 'bill_date');
    }

    public function test_user_can_reset_form_layout_preferences_to_default(): void
    {
        UserFormPreference::setForUser($this->user->id, 'sales_bills.header', [
            ['field' => 'bill_date', 'order' => 1, 'grid_col' => 'col-md-12', 'visible' => true],
        ]);

        $this->assertNotNull(UserFormPreference::getForUser($this->user->id, 'sales_bills.header'));

        $response = $this->actingAs($this->user)
            ->post(route('tools.form-preferences.reset'), [
                'form_key' => 'sales_bills.header',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $this->assertNull(UserFormPreference::getForUser($this->user->id, 'sales_bills.header'));
    }

    public function test_sales_bill_can_be_created_seamlessly_with_customized_layout(): void
    {
        // Set custom layout for user: reorder fields and hide optional field
        UserFormPreference::setForUser($this->user->id, 'sales_bills.header', [
            ['field' => 'bill_date', 'order' => 1, 'grid_col' => 'col-md-6', 'visible' => true],
            ['field' => 'customer_id', 'order' => 2, 'grid_col' => 'col-md-6', 'visible' => true],
            ['field' => 'branch_id', 'order' => 3, 'grid_col' => 'col-md-4', 'visible' => true],
            ['field' => 'delivery_time', 'order' => 4, 'grid_col' => 'col-md-4', 'visible' => false],
        ]);

        $customer = Customer::create([
            'name' => 'Walk-in Customer',
            'mobile' => '9876543210',
            'city' => 'Mumbai',
        ]);

        $item = Item::create([
            'name' => 'Brake Fluid DOT4',
            'item_code' => 'BFDOT4',
            'sell_price' => 250.00,
            'cost_price' => 180.00,
            'mrp' => 280.00,
            'status' => true,
        ]);

        \App\Models\ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'quantity' => 50,
            'cost_price' => 180.00,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'bill_date' => now()->format('Y-m-d\TH:i'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'sell_price' => 250.00,
                    'mrp' => 280.00,
                ],
            ],
            'payment_mode' => 'Cash',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('sales.sales-bills.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.sales-bills.index'));

        $this->assertDatabaseHas('sales_bills', [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
        ]);
    }

    public function test_purchase_invoice_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchase.purchase-invoices.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('pinv-header-fields-grid');
        $response->assertSee('data-field="invoice_date"', false);
        $response->assertSee('data-field="supplier_id"', false);
        $response->assertSee('data-field="supplier_inv_amount"', false);
    }

    public function test_purchase_order_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchase.purchase-orders.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('po-header-fields-grid');
        $response->assertSee('data-field="supplier_id"', false);
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="po_date"', false);
    }

    public function test_receipt_note_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('purchase.purchase-receipt-notes.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('grn-header-fields-grid');
        $response->assertSee('data-field="receipt_date"', false);
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="supplier_id"', false);
    }

    public function test_stock_transfer_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.stock-transfers.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('st-header-fields-grid');
        $response->assertSee('data-field="from_branch_id"', false);
        $response->assertSee('data-field="to_branch_id"', false);
        $response->assertSee('data-field="transfer_date"', false);
    }

    public function test_opening_stock_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.opening-stocks.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('os-header-fields-grid');
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="entry_date"', false);
    }

    public function test_damage_stock_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.damage-stocks.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('ds-header-fields-grid');
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="entry_date"', false);
        $response->assertSee('data-field="wastage_type"', false);
    }

    public function test_stock_update_create_page_renders_form_layout_customizer(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.stock-updates.create'));

        $response->assertOk();
        $response->assertSee('Customize Layout');
        $response->assertSee('su-header-fields-grid');
        $response->assertSee('data-field="branch_id"', false);
        $response->assertSee('data-field="entry_date"', false);
    }
}
