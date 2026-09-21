<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * One permission per (sensitive module, action) pair — the same 11 modules the
     * foundation spec names as auditable/sensitive actions. index/create-form/show/edit-
     * form GET routes stay ungated for any authenticated user; only these state-changing
     * actions are permission-checked.
     */
    private const MODULE_ACTIONS = [
        'purchase-invoices' => ['create', 'edit', 'cancel'],
        'purchase-returns' => ['create', 'edit', 'cancel'],
        'purchase-receipt-notes' => ['create', 'edit', 'cancel'],
        'sales-bills' => ['create', 'edit', 'cancel'],
        'sales-returns' => ['create', 'edit', 'cancel'],
        'sales-quotations' => ['create', 'edit', 'cancel'],
        'sales-orders' => ['create', 'edit', 'cancel'],
        'sales-delivery-notes' => ['create', 'edit', 'cancel'],
        'damage-stocks' => ['create', 'edit', 'cancel'],
        'opening-stocks' => ['create', 'edit', 'cancel'],
        'stock-updates' => ['create', 'edit', 'cancel'],
        'stock-update-approval' => ['approve', 'reject'],
        'stock-transfers' => ['create', 'receive', 'cancel'],
        'vouchers' => ['create', 'edit', 'cancel'],
        'bill-settlements' => ['create', 'cancel'],
        'loyalty-programs' => ['create', 'edit', 'cancel'],
        // Widened from edit-only to full CRUD — same Owner-only tier as before, just
        // consistently gated instead of leaving create/delete open to anyone.
        'gst-taxes' => ['create', 'edit', 'cancel'],
        'gst-types' => ['create', 'edit', 'cancel'],
        'item-price-change' => ['edit'],
        // Owner-only (not in MANAGER_MODULES below) — a Manager granting roles could
        // otherwise assign themselves Owner, i.e. self-escalate privileges.
        'users' => ['create', 'edit', 'cancel'],

        // Master data — day-to-day reference-data upkeep, Manager-level (see
        // MANAGER_MODULES below).
        'item-categories' => ['create', 'edit', 'cancel'],
        'item-category-values' => ['create', 'edit', 'cancel'],
        'product-types' => ['create', 'edit', 'cancel'],
        'brands' => ['create', 'edit', 'cancel'],
        'uoms' => ['create', 'edit', 'cancel'],
        'customer-categories' => ['create', 'edit', 'cancel'],
        'customers' => ['create', 'edit', 'cancel'],
        'areas' => ['create', 'edit', 'cancel'],
        'pet-types' => ['create', 'edit', 'cancel'],
        'breeds' => ['create', 'edit', 'cancel'],
        'colors' => ['create', 'edit', 'cancel'],
        'suppliers' => ['create', 'edit', 'cancel'],
        'registers' => ['create', 'edit', 'cancel'],
        'tender-types' => ['create', 'edit', 'cancel'],
        'tender-type-values' => ['create', 'edit', 'cancel'],
        'purchase-orders' => ['create', 'edit', 'cancel'],
        'purchase-indents' => ['create', 'edit', 'cancel', 'approve', 'reject'],
        'repack' => ['create'],
        'kit-preparation' => ['create'],
        'kit-unpack' => ['create'],

        // Structural / financial-master — Owner-only, same tier as vouchers/gst-taxes.
        'branches' => ['create', 'edit', 'cancel'],
        'ledgers' => ['create', 'edit', 'cancel'],
        'price-fixing' => ['apply'],
        'change-selling' => ['edit'],

        // Items: split by action, not a single tier — see MANAGER_EXTRA_PERMISSIONS.
        // Creating a brand-new product (incl. its starting price) is routine Manager
        // setup work; re-pricing an EXISTING item later is the sensitive action
        // item-price-change already exists to control, so edit/cancel stay Owner-only.
        'items' => ['create', 'edit', 'cancel'],

        // Financial Year lock — Owner-only entirely. Locking/reopening a period is a
        // high-authorization action per spec §15.4, same tier as vouchers/branches/ledgers.
        'financial-years' => ['create', 'edit', 'lock', 'reopen'],

        // Till — the one operational module Cashier gets beyond sales-bills/sales-returns
        // create; a cashier opens/closes their own shift in real retail (see Cashier's
        // explicit permission list below, since MANAGER_MODULES only reaches Owner+Manager).
        'till' => ['open', 'close'],
    ];

    /**
     * Operational modules a Manager can run day to day. Approval authority
     * (stock-update-approval) and financial-master actions (vouchers, gst-taxes,
     * item-price-change, branches, ledgers, price-fixing, change-selling) are reserved
     * for Owner — this is the real distinction between the two roles, not a cosmetic one.
     */
    private const MANAGER_MODULES = [
        'purchase-invoices', 'purchase-returns', 'purchase-receipt-notes', 'sales-bills', 'sales-returns', 'sales-quotations', 'sales-orders', 'sales-delivery-notes', 'damage-stocks',
        'opening-stocks', 'stock-updates', 'stock-transfers', 'bill-settlements', 'loyalty-programs',
        'item-categories', 'item-category-values', 'product-types', 'brands', 'uoms',
        'customer-categories', 'customers', 'areas', 'pet-types', 'breeds', 'colors',
        'suppliers', 'registers', 'tender-types', 'tender-type-values', 'gst-types',
        'purchase-orders', 'purchase-indents', 'repack', 'kit-preparation', 'kit-unpack', 'till',
    ];

    /**
     * One-off permissions granted to Manager that don't fit the whole-module bucket
     * above — e.g. Manager may create new items (initial setup) but not edit/cancel
     * existing ones (re-pricing), which stays Owner-only via item-price-change parity.
     */
    private const MANAGER_EXTRA_PERMISSIONS = [
        'items.create',
    ];

    public function run(): void
    {
        $permissions = [];
        foreach (self::MODULE_ACTIONS as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        $owner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $owner->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $managerPermissions = collect($permissions)->filter(
            fn (Permission $permission) => in_array(explode('.', $permission->name)[0], self::MANAGER_MODULES, true)
                || in_array($permission->name, self::MANAGER_EXTRA_PERMISSIONS, true)
        );
        $manager->syncPermissions($managerPermissions);

        $cashier = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'sales-bills.create',
            'sales-returns.create',
            'till.open',
            'till.close',
        ]);
    }
}
