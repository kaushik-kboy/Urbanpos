<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeroenNoten\LaravelAdminLte\AdminLte;
use Tests\TestCase;

class CashierMenuAndPermissionGatingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Burn ID 1 because AppServiceProvider has Gate::before bypass for user id 1
        User::factory()->create();

        $this->branch = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Branch', 'code' => 'MB01', 'status' => 1]
        );

        $this->cashier = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole('Cashier');
    }

    public function test_cashier_sidebar_menu_hides_unauthorized_modules(): void
    {
        $this->actingAs($this->cashier);

        $adminLte = app(AdminLte::class);
        $menu = $adminLte->menu();
        $menuTitles = collect($menu)->pluck('text')->filter()->values()->toArray();

        // Cashier MUST see Sales, Till, POS Terminal, Dashboard, Master
        $this->assertContains('POS Terminal', $menuTitles);
        $this->assertContains('Dashboard', $menuTitles);
        $this->assertContains('Sales', $menuTitles);
        $this->assertContains('Till', $menuTitles);
        $this->assertContains('Master', $menuTitles);

        // Cashier MUST NOT see Purchase, Inventory, Reports, Tools, Finance & Accounts
        $this->assertNotContains('Purchase', $menuTitles, 'Purchase menu must be hidden from Cashier');
        $this->assertNotContains('Inventory', $menuTitles, 'Inventory menu must be hidden from Cashier');
        $this->assertNotContains('Reports', $menuTitles, 'Reports menu must be hidden from Cashier');
        $this->assertNotContains('Tools', $menuTitles, 'Tools menu must be hidden from Cashier');
        $this->assertNotContains('Finance & Accounts', $menuTitles, 'Finance menu must be hidden from Cashier');

        // Master should ONLY contain Customer
        $master = collect($menu)->firstWhere('text', 'Master');
        $this->assertNotNull($master);
        $masterSubmenus = collect($master['submenu'])->pluck('text')->toArray();
        $this->assertEquals(['Customer'], $masterSubmenus);

        // Sales should ONLY contain Sales Bill and Sales Return
        $sales = collect($menu)->firstWhere('text', 'Sales');
        $this->assertNotNull($sales);
        $salesSubmenus = collect($sales['submenu'])->pluck('text')->toArray();
        $this->assertEquals(['Sales Bill', 'Sales Return'], $salesSubmenus);
    }

    public function test_cashier_is_forbidden_from_purchase_routes(): void
    {
        $this->actingAs($this->cashier);

        // GET create routes for Purchase modules must return 403 Forbidden
        $this->get(route('purchase.purchase-orders.create'))->assertForbidden();
        $this->get(route('purchase.purchase-invoices.create'))->assertForbidden();
        $this->get(route('purchase.purchase-receipt-notes.create'))->assertForbidden();
        $this->get(route('purchase.purchase-returns.create'))->assertForbidden();
    }

    public function test_cashier_can_access_permitted_sales_routes(): void
    {
        $this->actingAs($this->cashier);

        // Sales Bill create and return should be accessible (200 OK)
        $this->get(route('sales.sales-bills.create'))->assertOk();
        $this->get(route('sales.sales-returns.create'))->assertOk();
    }
}
