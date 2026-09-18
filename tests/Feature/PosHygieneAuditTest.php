<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DynamicValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosHygieneAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_dynamic_validation_protects_core_keys_from_accidental_nullability(): void
    {
        $service = app(DynamicValidationService::class);
        $rules = [
            'name' => ['required', 'string'],
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'cost_price' => ['required', 'numeric'],
            'remarks' => ['nullable', 'string'],
        ];
        $messages = [];

        // When cost_price is optional, it can become nullable
        // BUT name, branch_id, customer_id must NEVER lose 'required'
        $service->applyTo('items', $rules, $messages);

        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['branch_id']);
        $this->assertContains('required', $rules['customer_id']);
    }

    public function test_all_primary_masters_edit_screens_load_without_exceptions(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['name' => 'Main Outlet', 'code' => 'MO-1', 'status' => 1]);
        $customer = Customer::create(['name' => 'Jane Doe', 'mobile' => '9123456780', 'status' => 1]);
        $supplier = Supplier::create(['name' => 'Acme Supplies', 'status' => 1]);
        $item = Item::create([
            'name' => 'Audit Test Item',
            'product_type' => 'Standard',
            'cost_price' => 50,
            'landing_cost' => 50,
            'sell_price' => 75,
            'mrp' => 75,
            'status' => 1,
            'store_pickup' => 0,
            'tax_inclusive' => 1,
            'batch_expiry_details' => 'Not Required',
            'allow_negative_stock' => 0,
        ]);

        $this->actingAs($user);

        $res1 = $this->get(route('master.items.edit', $item));
        $res1->assertStatus(200);

        $res2 = $this->get(route('master.customers.edit', $customer));
        $res2->assertStatus(200);

        $res3 = $this->get(route('master.suppliers.edit', $supplier));
        $res3->assertStatus(200);

        $res4 = $this->get(route('master.branches.edit', $branch));
        $res4->assertStatus(200);
    }
}
