<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierUatAndBackupConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(
            ['id' => 1],
            ['name' => 'Main Outlet Branch', 'code' => 'MAIN', 'state' => 'Maharashtra']
        );
        $this->branch2 = Branch::firstOrCreate(
            ['id' => 2],
            ['name' => 'Secondary Branch', 'code' => 'SEC', 'state' => 'Maharashtra']
        );

        $this->owner = User::factory()->create([
            'email' => 'owner@urbanpos.com',
            'branch_id' => $this->branch1->id,
        ]);
        $this->owner->assignRole('Owner');

        $this->manager = User::factory()->create([
            'email' => 'manager@urbanpos.com',
            'branch_id' => $this->branch1->id,
        ]);
        $this->manager->assignRole('Manager');

        $this->cashier = User::factory()->create([
            'email' => 'cashier1@urbanpos.com',
            'branch_id' => $this->branch1->id,
        ]);
        $this->cashier->assignRole('Cashier');

        $gst = GstTax::firstOrCreate(['percentage' => 0], ['name' => 'Zero GST', 'description' => '0% GST']);

        $this->item = Item::create([
            'item_code' => 'PET-FOOD-001',
            'barcode' => '8901234567890',
            'name' => 'Premium Dog Food 1kg',
            'sell_price' => 450,
            'cost_price' => 300,
            'mrp' => 500,
            'gst_tax_id' => $gst->id,
            'tax_inclusive' => true,
            'allow_negative_stock' => true,
            'status' => true,
        ]);

        ItemStock::create([
            'item_id' => $this->item->id,
            'branch_id' => $this->branch1->id,
            'quantity' => 100,
        ]);
    }

    public function test_backup_console_accessible_by_owner_and_manager(): void
    {
        // 1. Owner can access backups index
        $resOwner = $this->actingAs($this->owner)->get(route('tools.backups.index'));
        $resOwner->assertOk();
        $resOwner->assertSee('Database Backups');
        $resOwner->assertSee('Generate Backup Now');

        // 2. Manager can access backups index
        $resManager = $this->actingAs($this->manager)->get(route('tools.backups.index'));
        $resManager->assertOk();
        $resManager->assertSee('Database Backups');

        // Seed a sample backup file in storage/app/backups to verify table list, download, and delete
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }
        $sampleFile = 'backup-uat-sample.sql.gz';
        File::put($backupDir . '/' . $sampleFile, gzencode('SAMPLE SQL BACKUP CONTENT', 9));

        // 3. Verify it shows in list
        $resList = $this->actingAs($this->owner)->get(route('tools.backups.index'));
        $resList->assertOk();
        $resList->assertSee($sampleFile);

        // 4. Download backup
        $resDownload = $this->actingAs($this->owner)->get(route('tools.backups.download', $sampleFile));
        $resDownload->assertOk();

        // 5. Delete backup
        $resDelete = $this->actingAs($this->owner)->delete(route('tools.backups.destroy', $sampleFile));
        $resDelete->assertRedirect(route('tools.backups.index'));
        $resDelete->assertSessionHas('success');
        $this->assertFalse(File::exists($backupDir . '/' . $sampleFile));
    }

    public function test_backup_console_strictly_forbidden_for_cashier_role(): void
    {
        // Cashier attempts to view backups list -> 403
        $resIndex = $this->actingAs($this->cashier)->get(route('tools.backups.index'));
        $resIndex->assertForbidden();

        // Cashier attempts to trigger backup -> 403
        $resCreate = $this->actingAs($this->cashier)->post(route('tools.backups.create'));
        $resCreate->assertForbidden();

        // Cashier attempts to download a backup -> 403
        $resDownload = $this->actingAs($this->cashier)->get(route('tools.backups.download', 'test.sql.gz'));
        $resDownload->assertForbidden();

        // Cashier attempts to delete a backup -> 403
        $resDelete = $this->actingAs($this->cashier)->delete(route('tools.backups.destroy', 'test.sql.gz'));
        $resDelete->assertForbidden();
    }

    public function test_cashier_can_access_pos_terminal_and_create_sales_bill(): void
    {
        // Cashier accesses POS terminal
        $resPos = $this->actingAs($this->cashier)->get(route('pos.terminal'));
        $resPos->assertOk();
        $resPos->assertSee('POS Terminal', false);

        // Cashier searches item
        $resLookup = $this->actingAs($this->cashier)->getJson(route('sales.sales-bills.item-list', [
            'q' => 'Premium Dog Food',
            'branch_id' => $this->branch1->id,
        ]));
        $resLookup->assertOk();

        // Cashier creates a customer
        $walkInCust = Customer::create([
            'name' => 'Counter Walk-in Customer',
            'mobile' => '9876543210',
            'status' => true,
        ]);

        $cashTender = \App\Models\TenderType::firstOrCreate(['name' => 'Cash'], ['type' => 'Cash', 'status' => true]);

        // Cashier completes billing at POS for assigned branch
        $billPayload = [
            'branch_id' => $this->branch1->id,
            'customer_id' => $walkInCust->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash',
            'paid_amount' => 450,
            'total_amount' => 450,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 450,
                    'mrp' => 500,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ]
            ],
            'payments' => [
                [
                    'tender_type_id' => $cashTender->id,
                    'tender_type' => 'Cash',
                    'amount' => 450,
                ]
            ]
        ];

        $resStore = $this->actingAs($this->cashier)->post(route('sales.sales-bills.store'), $billPayload);
        $resStore->assertRedirect();

        // Verify the bill was saved under Cashier's branch
        $savedBill = SalesBill::latest()->first();
        $this->assertNotNull($savedBill);
        $this->assertEquals($this->branch1->id, $savedBill->branch_id);
        $this->assertEquals(450, $savedBill->total);
    }

    public function test_cashier_is_strictly_blocked_from_billing_foreign_branch(): void
    {
        $walkInCust = Customer::create([
            'name' => 'Foreign Branch Customer',
            'mobile' => '9123456789',
            'status' => true,
        ]);

        // Cashier is assigned to branch1, but tries to bill under branch2
        $foreignPayload = [
            'branch_id' => $this->branch2->id,
            'customer_id' => $walkInCust->id,
            'bill_date' => now()->format('Y-m-d H:i:s'),
            'invoice_type' => 'Retail Invoice',
            'delivery_type' => 'Delivered',
            'sales_type' => 'Local',
            'payment_mode' => 'Cash',
            'paid_amount' => 450,
            'total_amount' => 450,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'sell_price' => 450,
                    'mrp' => 500,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => 0,
                ]
            ]
        ];

        // Should abort with 403 due to EnsureBranchAccess middleware
        $res = $this->actingAs($this->cashier)->post(route('sales.sales-bills.store'), $foreignPayload);
        $res->assertForbidden();
    }

    public function test_cashier_dashboard_widgets_restricted(): void
    {
        $res = $this->actingAs($this->cashier)->get(route('home'));
        $res->assertOk();

        // Dashboard widgets configuration for cashier restricts sensitive charts
        $dashboardService = app(\App\Services\Dashboard\DashboardRegistryService::class);
        $visibleWidgets = $dashboardService->getActiveWidgetsForUser($this->cashier);

        $this->assertTrue($visibleWidgets['kpi_sales']);
        $this->assertFalse($visibleWidgets['kpi_stock'], 'Cashier must not see full stock inventory valuation');
        $this->assertFalse($visibleWidgets['revenue_trend'], 'Cashier must not see company revenue trends');
        $this->assertFalse($visibleWidgets['category_share'], 'Cashier must not see company margin/category distribution');
    }
}
