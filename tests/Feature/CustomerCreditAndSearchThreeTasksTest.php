<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCreditAndSearchThreeTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['id' => 2], [
            'name' => 'Satellite Branch',
            'code' => 'SAT',
            'status' => true,
        ]);

        $this->admin = User::firstOrCreate(['email' => 'admin@urbanpos.com'], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->admin->assignRole('Owner');
        session(['active_branch_id' => $this->branch->id]);
        $this->actingAs($this->admin);

        CustomerType::firstOrCreate(['name' => 'Regular'], ['status' => true]);
        TenderType::firstOrCreate(['name' => 'Credit'], ['type' => 'Credit', 'status' => true]);
    }

    /**
     * Task 1: Change Selling search must only search by name and item_code, NOT barcode.
     */
    public function test_change_selling_search_filters_only_by_name_and_code()
    {
        $item = Item::create([
            'name' => 'Test Item Distinct Name',
            'item_code' => 'CODE-9999',
            'ean_upc_code' => 'BARCODE-7777',
            'cost_price' => 100,
            'sell_price' => 150,
            'status' => true,
        ]);

        // Search by name -> should match
        $resName = $this->get(route('inventory.change-selling.index', ['search' => 'Distinct Name']));
        $resName->assertStatus(200);
        $resName->assertSee('Test Item Distinct Name');

        // Search by item_code -> should match
        $resCode = $this->get(route('inventory.change-selling.index', ['search' => 'CODE-9999']));
        $resCode->assertStatus(200);
        $resCode->assertSee('Test Item Distinct Name');

        // Search by barcode -> MUST NOT match
        $resBarcode = $this->get(route('inventory.change-selling.index', ['search' => 'BARCODE-7777']));
        $resBarcode->assertStatus(200);
        $resBarcode->assertDontSee('Test Item Distinct Name');
    }

    /**
     * Task 2: Customer with 'No Credit' cannot have Credit sales bill saved.
     */
    public function test_no_credit_customer_cannot_save_credit_sales_bill()
    {
        $customerNoCredit = Customer::create([
            'name' => 'Cash Only Customer',
            'mobile' => '9898000001',
            'payment_mode' => 'No Credit',
            'sales_type' => 'Local',
            'customer_type' => 'Regular',
            'gst_type' => 'Unregistered',
            'status' => true,
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'sms_consent' => false,
        ]);

        $this->assertFalse($customerNoCredit->isCreditAllowed());

        $item = Item::create([
            'name' => 'Sample Item',
            'cost_price' => 50,
            'sell_price' => 100,
            'allow_negative_stock' => true,
            'status' => true,
        ]);

        $payload = [
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => $customerNoCredit->id,
            'branch_id' => $this->branch->id,
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Direct',
            'sales_type' => 'Local',
            'payment_type' => 'Credit',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'sell_price' => 100,
                    'mrp' => 100,
                ],
            ],
            'payments' => [
                [
                    'tender_type_id' => TenderType::where('name', 'Credit')->first()->id,
                    'amount' => 100,
                ],
            ],
        ];

        $response = $this->postJson(route('sales.sales-bills.store'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_type']);
    }

    /**
     * Task 2B: Customer update merges existing fields and updates cleanly.
     */
    public function test_customer_update_succeeds_even_with_partial_fields()
    {
        $cust = Customer::create([
            'name' => 'Original Customer',
            'mobile' => '9898000002',
            'payment_mode' => 'Both Cash and Credit',
            'sales_type' => 'Local',
            'customer_type' => 'Regular',
            'gst_type' => 'Unregistered',
            'status' => true,
            'credit_limit' => 5000,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 30,
            'sms_consent' => false,
        ]);

        // Submit update without passing credit_days, monthly_credit_balance, etc.
        $res = $this->put(route('master.customers.update', $cust), [
            'name' => 'Updated Customer Name',
            'mobile' => '9898000002',
            'payment_mode' => 'No Credit',
            'sales_type' => 'Local',
            'customer_type' => 'Regular',
        ]);

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'id' => $cust->id,
            'name' => 'Updated Customer Name',
            'payment_mode' => 'No Credit',
        ]);
    }

    /**
     * Task 3: Customer invoices must be returned in ascending order by bill_date.
     */
    public function test_customer_invoices_returned_in_descending_order()
    {
        $cust = Customer::create([
            'name' => 'Invoice History Customer',
            'mobile' => '9898000003',
            'payment_mode' => 'Cash Only',
            'sales_type' => 'Local',
            'customer_type' => 'Regular',
            'gst_type' => 'Unregistered',
            'status' => true,
            'credit_limit' => 0,
            'credit_balance' => 0,
            'monthly_credit_balance' => 0,
            'credit_days' => 0,
            'sms_consent' => false,
        ]);

        // Create bills with different dates
        $b1 = SalesBill::create([
            'bill_number' => 'TEST-001',
            'bill_date' => '2026-05-10 10:00:00',
            'customer_id' => $cust->id,
            'branch_id' => $this->branch->id,
            'total' => 100,
            'tax_amount' => 0,
            'status' => 'Paid',
            'payment_type' => 'Cash',
        ]);

        $b2 = SalesBill::create([
            'bill_number' => 'TEST-002',
            'bill_date' => '2026-05-01 10:00:00',
            'customer_id' => $cust->id,
            'branch_id' => $this->branch->id,
            'total' => 200,
            'tax_amount' => 0,
            'status' => 'Paid',
            'payment_type' => 'Cash',
        ]);

        $b3 = SalesBill::create([
            'bill_number' => 'TEST-003',
            'bill_date' => '2026-05-20 10:00:00',
            'customer_id' => $cust->id,
            'branch_id' => $this->branch->id,
            'total' => 300,
            'tax_amount' => 0,
            'status' => 'Paid',
            'payment_type' => 'Cash',
        ]);

        $res = $this->getJson(url("sales/sales-bills/customer-invoices/{$cust->id}"));
        $res->assertStatus(200);

        $invoices = $res->json('invoices');
        $this->assertCount(3, $invoices);

        // Must be in descending order: b3 (05-20), b1 (05-10), b2 (05-01)
        $this->assertEquals('TEST-003', $invoices[0]['bill_number']);
        $this->assertEquals('TEST-001', $invoices[1]['bill_number']);
        $this->assertEquals('TEST-002', $invoices[2]['bill_number']);
    }
}
