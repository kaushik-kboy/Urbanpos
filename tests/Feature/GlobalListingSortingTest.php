<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TenderTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalListingSortingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TenderTypeSeeder::class);

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MAIN', 'state' => 'Gujarat']
        );

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole('Owner');
    }

    public function test_default_ordering_remains_unchanged(): void
    {
        Customer::create(['name' => 'Zara Clothing', 'customer_code' => 'CUST001', 'status' => 1]);
        Customer::create(['name' => 'Alpha Corp', 'customer_code' => 'CUST002', 'status' => 1]);
        Customer::create(['name' => 'Beta Industries', 'customer_code' => 'CUST003', 'status' => 1]);

        // Default ordering in CustomerController is name asc
        $response = $this->actingAs($this->user)->get(route('master.customers.index'));
        $response->assertStatus(200);

        $customers = $response->viewData('customers');
        $this->assertEquals('Alpha Corp', $customers->first()->name);
        $this->assertEquals('Zara Clothing', $customers->last()->name);
    }

    public function test_ascending_and_descending_sorting_work(): void
    {
        Customer::create(['name' => 'Zara Clothing', 'customer_code' => 'CUST001', 'status' => 1]);
        Customer::create(['name' => 'Alpha Corp', 'customer_code' => 'CUST002', 'status' => 1]);
        Customer::create(['name' => 'Beta Industries', 'customer_code' => 'CUST003', 'status' => 1]);

        // Descending sort by name
        $responseDesc = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'name',
            'direction' => 'desc',
        ]));
        $responseDesc->assertStatus(200);
        $customersDesc = $responseDesc->viewData('customers');
        $this->assertEquals('Zara Clothing', $customersDesc->first()->name);
        $this->assertEquals('Alpha Corp', $customersDesc->last()->name);

        // Ascending sort by name
        $responseAsc = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'name',
            'direction' => 'asc',
        ]));
        $responseAsc->assertStatus(200);
        $customersAsc = $responseAsc->viewData('customers');
        $this->assertEquals('Alpha Corp', $customersAsc->first()->name);
        $this->assertEquals('Zara Clothing', $customersAsc->last()->name);
    }

    public function test_numeric_sorting_is_numeric(): void
    {
        Item::create(['name' => 'Item 10', 'item_code' => 'ITM10', 'sell_price' => 10.00, 'cost_price' => 5.00, 'status' => 1]);
        Item::create(['name' => 'Item 2', 'item_code' => 'ITM2', 'sell_price' => 2.00, 'cost_price' => 1.00, 'status' => 1]);
        Item::create(['name' => 'Item 100', 'item_code' => 'ITM100', 'sell_price' => 100.00, 'cost_price' => 50.00, 'status' => 1]);

        // Ascending by sell_price: should be 2, 10, 100 (NOT string 10, 100, 2)
        $response = $this->actingAs($this->user)->get(route('master.items.index', [
            'sort' => 'sell_price',
            'direction' => 'asc',
        ]));
        $response->assertStatus(200);
        $items = $response->viewData('items');
        $this->assertEquals(2.00, (float) $items[0]->sell_price);
        $this->assertEquals(10.00, (float) $items[1]->sell_price);
        $this->assertEquals(100.00, (float) $items[2]->sell_price);

        // Descending by sell_price: should be 100, 10, 2
        $responseDesc = $this->actingAs($this->user)->get(route('master.items.index', [
            'sort' => 'sell_price',
            'direction' => 'desc',
        ]));
        $responseDesc->assertStatus(200);
        $itemsDesc = $responseDesc->viewData('items');
        $this->assertEquals(100.00, (float) $itemsDesc[0]->sell_price);
        $this->assertEquals(10.00, (float) $itemsDesc[1]->sell_price);
        $this->assertEquals(2.00, (float) $itemsDesc[2]->sell_price);
    }

    public function test_security_whitelist_blocks_sql_injection_and_invalid_columns(): void
    {
        Customer::create(['name' => 'Alpha Corp', 'customer_code' => 'CUST001', 'status' => 1]);
        Customer::create(['name' => 'Beta Corp', 'customer_code' => 'CUST002', 'status' => 1]);

        // Attempt SQL injection via sort parameter
        $maliciousSort = "name; DROP TABLE users;--";
        $response = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => $maliciousSort,
            'direction' => 'asc',
        ]));

        // Must NOT crash or execute SQL injection, must safely fallback to default order
        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $this->assertCount(2, $customers);

        // Attempt unwhitelisted column
        $responseUnwhitelisted = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'password',
            'direction' => 'desc',
        ]));
        $responseUnwhitelisted->assertStatus(200);
        $customersUnwhitelisted = $responseUnwhitelisted->viewData('customers');
        $this->assertCount(2, $customersUnwhitelisted);
    }

    public function test_invalid_direction_is_sanitized(): void
    {
        Customer::create(['name' => 'Zara Clothing', 'customer_code' => 'CUST001', 'status' => 1]);
        Customer::create(['name' => 'Alpha Corp', 'customer_code' => 'CUST002', 'status' => 1]);

        // Passing invalid direction like 'sideways' or SQL snippet
        $response = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'name',
            'direction' => 'sideways; SLEEP(5)',
        ]));
        // Should sanitize to asc and succeed
        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $this->assertEquals('Alpha Corp', $customers->first()->name);
    }

    public function test_search_and_filter_coexist_with_sorting(): void
    {
        Customer::create(['name' => 'Tech Alpha', 'customer_code' => 'CUST001', 'status' => 1]);
        Customer::create(['name' => 'Tech Gamma', 'customer_code' => 'CUST002', 'status' => 1]);
        Customer::create(['name' => 'Tech Beta', 'customer_code' => 'CUST003', 'status' => 1]);
        Customer::create(['name' => 'Other Retail', 'customer_code' => 'CUST004', 'status' => 1]);

        // Search 'Tech' + Sort desc by name
        $response = $this->actingAs($this->user)->get(route('master.customers.index', [
            'search' => 'Tech',
            'sort' => 'name',
            'direction' => 'desc',
        ]));

        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $this->assertCount(3, $customers);
        $this->assertEquals('Tech Gamma', $customers->first()->name);
        $this->assertEquals('Tech Alpha', $customers->last()->name);
    }

    public function test_pagination_preserves_sort_parameters(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            Customer::create([
                'name' => sprintf('Customer %02d', $i),
                'customer_code' => sprintf('C%03d', $i),
                'status' => 1,
            ]);
        }

        // Request page 2 with per_page=20 (supported option in HasPerPage) and sort=name&direction=desc
        $response = $this->actingAs($this->user)->get(route('master.customers.index', [
            'per_page' => 20,
            'page' => 2,
            'sort' => 'name',
            'direction' => 'desc',
        ]));

        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $this->assertEquals(2, $customers->currentPage());

        // In descending order with 20 per page, Customer 35 down to 16 are page 1, Customer 15 down to 01 are page 2
        $this->assertEquals('Customer 15', $customers->first()->name);
        $this->assertEquals('Customer 01', $customers->last()->name);
    }

    public function test_sortable_th_renders_icons_and_links(): void
    {
        // 1. Unsorted state: should render neutral sort icon & link towards asc
        $response = $this->actingAs($this->user)->get(route('master.customers.index'));
        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('fa-sort', $content);
        $this->assertStringContainsString('sort=name&amp;direction=asc', $content);

        // 2. Ascending state: should render sort-up icon & link towards desc
        $responseAsc = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'name',
            'direction' => 'asc',
        ]));
        $responseAsc->assertStatus(200);
        $contentAsc = $responseAsc->getContent();
        $this->assertStringContainsString('fa-sort-up', $contentAsc);
        $this->assertStringContainsString('sort=name&amp;direction=desc', $contentAsc);

        // 3. Descending state: should render sort-down icon & 3rd click link back to default (without sort params)
        $responseDesc = $this->actingAs($this->user)->get(route('master.customers.index', [
            'sort' => 'name',
            'direction' => 'desc',
        ]));
        $responseDesc->assertStatus(200);
        $contentDesc = $responseDesc->getContent();
        $this->assertStringContainsString('fa-sort-down', $contentDesc);
    }
}
