<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\City;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\State;
use App\Models\User;
use App\Services\Reports\DynamicReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class StateCityAreaMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Urban Pets Motera',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => true,
        ]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@urbanpos.com',
            'branch_id' => $this->branch->id,
        ]);
        $this->admin->assignRole('Owner');

        // Seed States & Cities
        $this->seed(\Database\Seeders\StateAndCitySeeder::class);
    }

    public function test_state_and_city_masters_seeded_properly(): void
    {
        $this->assertGreaterThanOrEqual(30, State::count());
        $this->assertGreaterThanOrEqual(20, City::count());

        $gujarat = State::where('name', 'Gujarat')->first();
        $this->assertNotNull($gujarat);
        $this->assertEquals('GJ', $gujarat->code);

        $ahmedabad = City::where('name', 'Ahmedabad')->first();
        $this->assertNotNull($ahmedabad);
        $this->assertEquals($gujarat->id, $ahmedabad->state_id);
    }

    public function test_state_crud_and_api(): void
    {
        $this->actingAs($this->admin);

        // Index
        $response = $this->get(route('master.states.index'));
        $response->assertOk();
        $response->assertSee('Gujarat');

        // Create
        $response = $this->post(route('master.states.store'), [
            'name' => 'New Test State',
            'code' => 'NTS',
            'country' => 'India',
            'status' => 1,
        ]);
        $response->assertRedirect(route('master.states.index'));
        $this->assertDatabaseHas('states', ['name' => 'New Test State']);

        // API
        $apiResp = $this->getJson(route('master.states.api-list'));
        $apiResp->assertOk();
        $apiResp->assertJsonFragment(['name' => 'Gujarat']);
    }

    public function test_city_crud_and_api(): void
    {
        $this->actingAs($this->admin);

        $gujarat = State::where('name', 'Gujarat')->first();

        // Index
        $response = $this->get(route('master.cities.index'));
        $response->assertOk();
        $response->assertSee('Ahmedabad');

        // Create
        $response = $this->post(route('master.cities.store'), [
            'name' => 'Bopal City',
            'state_id' => $gujarat->id,
            'status' => 1,
        ]);
        $response->assertRedirect(route('master.cities.index'));
        $this->assertDatabaseHas('cities', ['name' => 'Bopal City', 'state_id' => $gujarat->id]);

        // API
        $apiResp = $this->getJson(route('master.cities.api-list', ['state_id' => $gujarat->id]));
        $apiResp->assertOk();
        $apiResp->assertJsonFragment(['name' => 'Ahmedabad']);
    }

    public function test_area_master_links_to_city_and_does_not_require_branch(): void
    {
        $this->actingAs($this->admin);

        $ahmedabad = City::where('name', 'Ahmedabad')->first();

        // Create Area with City (No branch required!)
        $response = $this->post(route('master.areas.store'), [
            'name' => 'Satellite Road Area',
            'city_id' => $ahmedabad->id,
            'status' => 1,
        ]);
        $response->assertRedirect(route('master.areas.index'));

        $area = Area::where('name', 'Satellite Road Area')->first();
        $this->assertNotNull($area);
        $this->assertEquals($ahmedabad->id, $area->city_id);

        // Index displays City name
        $indexResp = $this->get(route('master.areas.index'));
        $indexResp->assertOk();
        $indexResp->assertSee('Satellite Road Area');
        $indexResp->assertSee('Ahmedabad');

        // API
        $apiResp = $this->getJson(route('master.areas.api-list', ['city_id' => $ahmedabad->id]));
        $apiResp->assertOk();
        $apiResp->assertJsonFragment(['name' => 'Satellite Road Area']);
    }

    public function test_itemwise_stock_statement_displays_breakdown_when_inward_and_outward_are_zero(): void
    {
        $this->actingAs($this->admin);

        $item = Item::create([
            'name' => 'Zero Movement Product',
            'item_code' => 'ZERO-001',
            'cost_price' => 200,
            'sell_price' => 300,
            'mrp' => 300,
            'status' => true,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'quantity' => 10,
            'cost_price' => 200,
            'sell_price' => 300,
            'mrp' => 300,
        ]);

        $reportService = app(DynamicReportService::class);
        $request = new Request([
            'branch_id' => $this->branch->id,
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $data = $reportService->generate($request, 'itemwise-stock-statement');
        $this->assertNotEmpty($data['rows']);

        $row = $data['rows']->first();
        $this->assertNotNull($row, 'Item must be in the report');

        $cells = $row['cells'];
        // Cells: 0: Code, 1: Description, 2: Brand, 3: Supplier, 4: Branch, 5: Opening, 6: Inward, 7: Outward
        $this->assertStringContainsString('+0', $cells[6]);
        $this->assertStringContainsString('Pur: 0', $cells[6]);
        $this->assertStringContainsString('SR: 0', $cells[6]);
        $this->assertStringContainsString('Trf-In: 0', $cells[6]);
        $this->assertStringContainsString('Adj+: 0', $cells[6]);

        $this->assertStringContainsString('-0', $cells[7]);
        $this->assertStringContainsString('Sale: 0', $cells[7]);
        $this->assertStringContainsString('PR: 0', $cells[7]);
        $this->assertStringContainsString('Trf-Out: 0', $cells[7]);
        $this->assertStringContainsString('Adj-: 0', $cells[7]);
        $this->assertStringContainsString('Dmg: 0', $cells[7]);

        // Drilldown endpoint for zero-movement returns success with 0 records
        $drilldownResp = $this->getJson(route('reports.stock-statement.drilldown', [
            'item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'flow' => 'inward',
        ]));

        $drilldownResp->assertOk();
        $this->assertTrue($drilldownResp->json('success'));
        $this->assertEquals(0, $drilldownResp->json('count'));
    }
}
