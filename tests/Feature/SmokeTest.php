<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * UrbanPOS Smoke Test Suite
 *
 * Run: php artisan test tests/Feature/SmokeTest.php -c phpunit.smoke.xml --no-coverage
 *
 * Covers:
 * - All critical route accessibility (HTTP status checks)
 * - Roles & Permissions DB consistency (actual naming: module.create/edit/cancel)
 * - Spatie cache health
 * - Receipt Settings global fallback
 * - Stock Update batch API
 * - Branch integrity
 *
 * Permission convention in this project: {module}.create | {module}.edit | {module}.cancel
 * No "Super Admin" role — roles are: Owner, Manager, Cashier
 */
class SmokeTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Prefer an Owner, then Manager, then any user
        $this->admin = User::whereHas('roles', fn($q) => $q->whereIn('name', ['Owner', 'Manager']))
            ->first() ?? User::first();

        $this->assertNotNull($this->admin, 'No admin user found. Run seeders first.');
    }

    // =====================================================
    // 1. ROLES & PERMISSIONS CONSISTENCY
    // =====================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function all_required_permissions_exist_in_database()
    {
        // Convention: module.create | module.edit | module.cancel
        // Run this test after any new module/permission is added to catch gaps early.
        $required = [
            // Sales
            'sales-bills.create', 'sales-bills.edit', 'sales-bills.cancel',
            'sales-returns.create', 'sales-returns.edit', 'sales-returns.cancel',
            'sales-orders.create', 'sales-orders.edit', 'sales-orders.cancel',
            'sales-types.create', 'sales-types.edit', 'sales-types.cancel',
            // Purchase
            'purchase-invoices.create', 'purchase-invoices.edit', 'purchase-invoices.cancel',
            'purchase-orders.create', 'purchase-orders.edit', 'purchase-orders.cancel',
            'purchase-returns.create', 'purchase-returns.edit', 'purchase-returns.cancel',
            // Inventory
            'stock-updates.create', 'stock-updates.edit', 'stock-updates.cancel',
            'damage-stocks.create', 'damage-stocks.edit', 'damage-stocks.cancel',
            'opening-stocks.create', 'opening-stocks.edit', 'opening-stocks.cancel',
            'stock-transfers.create', 'stock-transfers.cancel',
            // Master
            'customers.create', 'customers.edit', 'customers.cancel',
            'customer-types.create', 'customer-types.edit', 'customer-types.cancel',
            'items.create', 'items.edit', 'items.cancel',
            'suppliers.create', 'suppliers.edit', 'suppliers.cancel',
            // Financial
            'bill-settlements.create', 'bill-settlements.edit', 'bill-settlements.cancel',
            'ledgers.create', 'ledgers.edit', 'ledgers.cancel',
            // Users & Branches
            'users.create', 'users.edit', 'users.cancel',
            'branches.create', 'branches.edit', 'branches.cancel',
        ];

        $missing = [];
        foreach ($required as $perm) {
            if (!Permission::where('name', $perm)->exists()) {
                $missing[] = $perm;
            }
        }

        $this->assertEmpty(
            $missing,
            "MISSING PERMISSIONS — add these to your permissions seeder:\n" . implode("\n", $missing)
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function all_required_roles_exist()
    {
        // This project uses Owner / Manager / Cashier (no "Super Admin")
        $required = ['Owner', 'Manager', 'Cashier'];
        $missing = [];
        foreach ($required as $role) {
            if (!Role::where('name', $role)->exists()) {
                $missing[] = $role;
            }
        }

        $this->assertEmpty(
            $missing,
            "MISSING ROLES:\n" . implode("\n", $missing)
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spatie_permission_cache_is_healthy()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $count = Permission::count();
        $this->assertGreaterThan(0, $count, 'No permissions found after cache clear.');
    }

    // =====================================================
    // 2. CRITICAL ROUTES — HTTP STATUS CHECKS
    // =====================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function sales_bill_index_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('sales.sales-bills.index'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sales_bill_create_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('sales.sales-bills.create'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stock_update_create_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('inventory.stock-updates.create'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stock_update_search_api_works()
    {
        $branch = Branch::first();
        $this->actingAs($this->admin)
            ->getJson(route('inventory.stock-updates.search-items', [
                'branch_id' => $branch->id,
                'search'    => 'a',
            ]))
            ->assertStatus(200)
            ->assertJsonStructure(['items']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stock_update_item_by_code_api_works()
    {
        $branch = Branch::first();
        $this->actingAs($this->admin)
            ->getJson(route('inventory.stock-updates.item-by-code', [
                'branch_id' => $branch->id,
                'query'     => 'a',
            ]))
            ->assertStatus(200)
            ->assertJsonStructure(['found']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function damage_stock_create_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('inventory.damage-stocks.create'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function opening_stock_create_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('inventory.opening-stocks.create'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function purchase_invoice_create_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('purchase.purchase-invoices.create'))
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_designer_is_accessible()
    {
        $this->actingAs($this->admin)
            ->get(route('tools.receipt-designer.index'))
            ->assertStatus(200);
    }

    // =====================================================
    // 3. RECEIPT SETTINGS FALLBACK
    // =====================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_setting_global_fallback_exists()
    {
        if (!class_exists(\App\Models\ReceiptSetting::class)) {
            $this->markTestSkipped('ReceiptSetting model not found.');
        }

        $global = \App\Models\ReceiptSetting::whereNull('branch_id')->first();

        $this->assertNotNull(
            $global,
            'CRITICAL: No global ReceiptSetting (branch_id = NULL). Print will fail for new branches.'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_setting_for_document_returns_something()
    {
        if (!class_exists(\App\Models\ReceiptSetting::class)) {
            $this->markTestSkipped('ReceiptSetting model not found.');
        }

        $branch = Branch::first();
        if (!$branch) {
            $this->markTestSkipped('No branch found.');
        }

        $setting = \App\Models\ReceiptSetting::forDocument('sales_bill', $branch->id);

        $this->assertNotNull(
            $setting,
            "ReceiptSetting::forDocument() returned null for branch {$branch->id}."
        );
    }

    // =====================================================
    // 4. BRANCH INTEGRITY
    // =====================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function at_least_one_branch_exists()
    {
        $this->assertGreaterThan(0, Branch::count(), 'No branches exist!');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function all_branches_have_names()
    {
        $nameless = Branch::whereNull('name')->orWhere('name', '')->count();
        $this->assertEquals(0, $nameless, "{$nameless} branch(es) have no name.");
    }
}
