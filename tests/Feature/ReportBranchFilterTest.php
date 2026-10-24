<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchMotera;
    private Branch $branchSatellite;
    private User $managerMotera;
    private User $managerSatellite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchSatellite = Branch::create(['name' => 'Urban Pets | Satellite', 'status' => true]);
        $this->branchMotera = Branch::create(['name' => 'Urban Pets | Motera', 'status' => true]);

        $this->managerMotera = User::factory()->create([
            'email' => 'manager.motera@test.com',
            'branch_id' => $this->branchMotera->id,
        ]);
        $this->managerMotera->assignRole('Manager');

        $this->managerSatellite = User::factory()->create([
            'email' => 'manager.satellite@test.com',
            'branch_id' => $this->branchSatellite->id,
        ]);
        $this->managerSatellite->assignRole('Manager');

        // Create sample bills
        $customer = Customer::create(['name' => 'Test Customer', 'status' => true, 'sales_type' => 'Local']);
        SalesBill::create([
            'bill_number' => 'SB-MOT-001',
            'bill_date' => now()->format('Y-m-d'),
            'branch_id' => $this->branchMotera->id,
            'customer_id' => $customer->id,
            'status' => 'Posted',
            'total' => 1000,
            'total_qty' => 1,
            'total_gst' => 180,
        ]);
        SalesBill::create([
            'bill_number' => 'SB-SAT-001',
            'bill_date' => now()->format('Y-m-d'),
            'branch_id' => $this->branchSatellite->id,
            'customer_id' => $customer->id,
            'status' => 'Posted',
            'total' => 2000,
            'total_qty' => 2,
            'total_gst' => 360,
        ]);
    }

    public function test_manager_can_access_all_branches_in_report_filter(): void
    {
        $this->actingAs($this->managerMotera);

        $response = $this->get(route('reports.view', ['module' => 'gst-sales-taxwise', 'branch_id' => 'all']));
        $response->assertOk();
        $response->assertSee('Urban Pets | Satellite');
        $response->assertSee('Urban Pets | Motera');
        $response->assertSee('All Branches / Locations');
        // Both bills should be visible when branch_id=all
        $response->assertSee('SB-MOT-001');
        $response->assertSee('SB-SAT-001');
    }

    public function test_manager_can_filter_specific_branch(): void
    {
        $this->actingAs($this->managerMotera);

        // Filter only Satellite
        $response = $this->get(route('reports.view', [
            'module' => 'gst-sales-taxwise',
            'branch_id' => $this->branchSatellite->id,
        ]));
        $response->assertOk();
        $response->assertSee('SB-SAT-001');
        $response->assertDontSee('SB-MOT-001');

        // Filter only Motera
        $response = $this->get(route('reports.view', [
            'module' => 'gst-sales-taxwise',
            'branch_id' => $this->branchMotera->id,
        ]));
        $response->assertOk();
        $response->assertSee('SB-MOT-001');
        $response->assertDontSee('SB-SAT-001');
    }

    public function test_ensure_active_branch_middleware_does_not_stomp_on_reports_all_branch(): void
    {
        $this->actingAs($this->managerMotera);

        // Request with branch_id=all should NOT be rewritten to session active_branch_id
        $response = $this->get('/reports/view/gst-sales-taxwise?branch_id=all');
        $response->assertOk();
        $response->assertSee('SB-MOT-001');
        $response->assertSee('SB-SAT-001');
    }
}
