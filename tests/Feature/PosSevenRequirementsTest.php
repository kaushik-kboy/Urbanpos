<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PosSetting;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesType;
use App\Models\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSevenRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $moteraBranch;
    private Branch $satelliteBranch;
    private Customer $customerA;
    private Customer $customerB;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@urbanpos.com']);
        $this->admin->assignRole('Owner');

        $this->moteraBranch = Branch::where('name', 'like', '%motera%')->first() 
            ?: Branch::firstOrCreate(['name' => 'Urban Pets - Motera'], ['erp_code' => 'MOTERA', 'status' => 1]);
        $this->satelliteBranch = Branch::where('name', 'like', '%satellite%')->first() 
            ?: Branch::firstOrCreate(['name' => 'Urban Pets - Satellite'], ['erp_code' => 'SAT', 'status' => 1]);

        SalesType::firstOrCreate(['name' => 'Retail', 'branch_id' => $this->moteraBranch->id], ['code' => 'RETAIL', 'status' => 1]);
        SalesType::firstOrCreate(['name' => 'Home', 'branch_id' => $this->moteraBranch->id], ['code' => 'HOME', 'status' => 1]);
        SalesType::firstOrCreate(['name' => 'Spa', 'branch_id' => $this->moteraBranch->id], ['code' => 'SPA', 'status' => 1]);

        SalesType::firstOrCreate(['name' => 'B2B', 'branch_id' => $this->satelliteBranch->id], ['code' => 'B2B', 'status' => 1]);
        SalesType::firstOrCreate(['name' => 'IPC', 'branch_id' => $this->satelliteBranch->id], ['code' => 'IPC', 'status' => 1]);
        SalesType::firstOrCreate(['name' => 'Home', 'branch_id' => $this->satelliteBranch->id], ['code' => 'HOME', 'status' => 1]);
        SalesType::firstOrCreate(['name' => 'Retail', 'branch_id' => $this->satelliteBranch->id], ['code' => 'RETAIL', 'status' => 1]);

        $this->customerA = Customer::firstOrCreate(['name' => 'Customer Alpha', 'mobile' => '9999900001'], ['status' => 1, 'sales_type' => 'Retail']);
        $this->customerB = Customer::firstOrCreate(['name' => 'Customer Beta', 'mobile' => '9999900002'], ['status' => 1, 'sales_type' => 'Retail']);

        $this->item = Item::create([
            'name' => 'Test Pet Food',
            'item_code' => 'TPF-01',
            'product_type' => 'Standard',
            'sell_price' => 100,
            'cost_price' => 70,
            'mrp' => 110,
            'status' => true,
        ]);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->moteraBranch->id, 'quantity' => 100]);
        ItemStock::create(['item_id' => $this->item->id, 'branch_id' => $this->satelliteBranch->id, 'quantity' => 100]);

        TenderType::firstOrCreate(['name' => 'Cash', 'type' => 'Cash'], ['status' => true]);
    }

    /**
     * Requirement 1: POS Lock Option controlled by Admin
     */
    public function test_pos_lock_option_admin_control(): void
    {
        $this->actingAs($this->admin);

        // Initially enabled
        $this->assertTrue(PosSetting::isLockEnabled());

        // Admin can toggle it off via AJAX toggle endpoint
        $response = $this->postJson(route('pos.toggle-lock-setting'));
        $response->assertOk()
            ->assertJson(['success' => true, 'enabled' => false]);
        $this->assertFalse(PosSetting::isLockEnabled());

        // Admin settings view can be loaded
        $viewResponse = $this->get(route('tools.pos-settings.index'));
        $viewResponse->assertOk()
            ->assertSee('POS Screen Lock Settings');

        // Admin can toggle it back on via settings form
        $updateResp = $this->post(route('tools.pos-settings.update'), [
            'pos_lock_enabled' => '1',
        ]);
        $updateResp->assertRedirect();
        $this->assertTrue(PosSetting::isLockEnabled());
    }

    /**
     * Requirement 2 & 3: Bill Discount and POS Remarks
     */
    public function test_bill_discount_and_remarks_saved_and_calculated(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'bill_number' => 'UB/2026-27/00101',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerA->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Retail',
            'payment_type' => 'Cash',
            'bill_discount' => 25.00,
            'remarks' => 'Urgent home delivery after 6 PM',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'sell_price' => 100.00,
                    'mrp' => 110.00,
                    'disc_amount' => 0.00,
                    'disc_percent' => 0.00,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => TenderType::where('name', 'Cash')->value('id'),
                    'amount' => 175.00, // 200 - 25 = 175
                ],
            ],
        ];

        $response = $this->postJson(route('sales.sales-bills.store'), $payload);
        $response->assertOk()
            ->assertJson(['success' => true]);

        $bill = SalesBill::where('bill_number', 'UB/2026-27/00101')->first();
        $this->assertNotNull($bill);
        $this->assertEquals(25.00, (float) $bill->bill_discount);
        $this->assertEquals('Urgent home delivery after 6 PM', $bill->remarks);
        $this->assertEquals(175.00, (float) $bill->total);
    }

    /**
     * Requirement 4: Sales Bill Type is compulsory (form-field validation)
     */
    public function test_sales_bill_type_is_compulsory(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'bill_number' => 'UB/2026-27/00102',
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerA->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => '', // Empty sales bill type
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 100.00,
                ],
            ],
        ];

        $response = $this->postJson(route('sales.sales-bills.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sales_type']);
    }

    /**
     * Requirement 5: Branch-wise Sales Bill Type Master
     * Motera: Retail, Home, Spa
     * Satellite: B2B, IPC, Home, Retail
     */
    public function test_branch_wise_sales_bill_types(): void
    {
        $this->actingAs($this->admin);

        $moteraResp = $this->getJson(route('sales-types.by-branch', ['branch_id' => $this->moteraBranch->id]));
        $moteraResp->assertOk();
        $moteraNames = collect($moteraResp->json('data'))->pluck('name')->all();
        $this->assertContains('Retail', $moteraNames);
        $this->assertContains('Home', $moteraNames);
        $this->assertContains('Spa', $moteraNames);
        $this->assertNotContains('IPC', $moteraNames);
        $this->assertNotContains('B2B', $moteraNames);

        $satResp = $this->getJson(route('sales-types.by-branch', ['branch_id' => $this->satelliteBranch->id]));
        $satResp->assertOk();
        $satNames = collect($satResp->json('data'))->pluck('name')->all();
        $this->assertContains('B2B', $satNames);
        $this->assertContains('IPC', $satNames);
        $this->assertContains('Home', $satNames);
        $this->assertContains('Retail', $satNames);
        $this->assertNotContains('Spa', $satNames);
    }

    /**
     * Requirement 6: POS View in Terminal (Read-only terminal view)
     */
    public function test_pos_view_in_terminal_renders_view_mode(): void
    {
        $this->actingAs($this->admin);

        $bill = SalesBill::create([
            'bill_number' => 'UB/2026-27/00103',
            'bill_date' => now(),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerA->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Retail',
            'bill_discount' => 10.00,
            'remarks' => 'Test POS View',
            'total' => 90.00,
            'posting_key' => 'pos_test_view_1',
            'status' => 'Posted',
        ]);

        $response = $this->get(route('pos.terminal', ['view_id' => $bill->id]));
        $response->assertOk()
            ->assertSee('Viewing POS Bill: #UB/2026-27/00103', false)
            ->assertSee('window.IS_VIEW_ONLY = true;', false);
    }

    /**
     * Requirement 7: Customer can NEVER be changed on edit in both POS and Sales Bill
     */
    public function test_customer_cannot_be_changed_on_edit(): void
    {
        $this->actingAs($this->admin);

        $bill = SalesBill::create([
            'bill_number' => 'UB/2026-27/00104',
            'bill_date' => now(),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerA->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Retail',
            'total' => 100.00,
            'posting_key' => 'pos_cust_lock_test',
            'status' => 'Draft',
        ]);

        SalesBillItem::create([
            'sales_bill_id' => $bill->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'sell_price' => 100.00,
            'net_amount' => 100.00,
        ]);

        // Attempt to update the bill with customerB instead of customerA
        $updatePayload = [
            'bill_number' => $bill->bill_number,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerB->id, // Attempting to switch customer!
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Retail',
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 100.00,
                    'mrp' => 110.00,
                    'disc_amount' => 0.00,
                    'disc_percent' => 0.00,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => TenderType::where('name', 'Cash')->value('id'),
                    'amount' => 100.00,
                ],
            ],
        ];

        $response = $this->putJson(route('sales.sales-bills.update', $bill->id), $updatePayload);
        $response->assertOk()
            ->assertJson(['success' => true]);

        // Customer ID MUST remain customerA->id
        $bill->refresh();
        $this->assertEquals($this->customerA->id, $bill->customer_id);
        $this->assertNotEquals($this->customerB->id, $bill->customer_id);
    }

    public function test_einvoiced_bill_with_irn_cannot_be_edited(): void
    {
        $this->actingAs($this->admin);

        $einvoiceBill = SalesBill::create([
            'bill_number' => 'UB/2026-27/00105',
            'bill_date' => now(),
            'branch_id' => $this->moteraBranch->id,
            'customer_id' => $this->customerA->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'B2B',
            'total' => 500.00,
            'irn' => str_repeat('a', 64), // Simulated active IRN
            'einvoice_status' => 'Completed',
            'posting_key' => 'pos_einvoice_lock_test',
            'status' => 'Posted',
        ]);

        // 1. GET edit route must redirect with error
        $getResp = $this->get(route('sales.sales-bills.edit', $einvoiceBill->id));
        $getResp->assertRedirect(route('sales.sales-bills.show', $einvoiceBill->id))
            ->assertSessionHas('error');

        // 2. PUT update route must abort 403 Forbidden
        $putResp = $this->put(route('sales.sales-bills.update', $einvoiceBill->id), []);
        $putResp->assertForbidden();
    }
}
