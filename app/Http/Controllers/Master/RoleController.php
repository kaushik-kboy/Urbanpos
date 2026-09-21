<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use HasPerPage;

    /**
     * Core system roles that cannot be deleted.
     */
    public const PROTECTED_ROLES = ['Owner', 'Manager', 'Cashier'];

    public function index(Request $request)
    {
        $query = Role::withCount(['users', 'permissions']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->orderBy('name')->paginate($this->perPage())->withQueryString();
        $protectedRoles = self::PROTECTED_ROLES;

        return view('master.roles.index', compact('roles', 'protectedRoles'));
    }

    public function create()
    {
        $permissionGroups = self::getPermissionGroups();
        $assignedPermissions = [];

        return view('master.roles.create', compact('permissionGroups', 'assignedPermissions'));
    }

    public function show(Role $role)
    {
        return redirect()->route('master.roles.edit', $role);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::create([
            'name' => trim($request->input('name')),
            'guard_name' => 'web',
        ]);

        $permissions = $request->input('permissions', []);
        $this->syncRolePermissions($role, $permissions);

        return redirect()->route('master.roles.index')->with('status', "Role '{$role->name}' created successfully with " . count($permissions) . " permissions.");
    }

    public function edit(Role $role)
    {
        $permissionGroups = self::getPermissionGroups();
        $assignedPermissions = $role->permissions->pluck('name')->toArray();
        $isProtected = in_array($role->name, self::PROTECTED_ROLES, true);

        return view('master.roles.edit', compact('role', 'permissionGroups', 'assignedPermissions', 'isProtected'));
    }

    public function update(Request $request, Role $role)
    {
        $isProtected = in_array($role->name, self::PROTECTED_ROLES, true);

        $rules = [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];

        if (! $isProtected) {
            $rules['name'] = ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)];
        }

        $request->validate($rules);

        if (! $isProtected && $request->filled('name')) {
            $role->update(['name' => trim($request->input('name'))]);
        }

        $permissions = $request->input('permissions', []);
        $this->syncRolePermissions($role, $permissions);

        return redirect()->route('master.roles.index')->with('status', "Role '{$role->name}' permissions updated successfully.");
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => "The core system role '{$role->name}' cannot be deleted.",
            ]);
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            throw ValidationException::withMessages([
                'role' => "Cannot delete role '{$role->name}' because it is currently assigned to {$userCount} user(s). Reassign them first.",
            ]);
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('master.roles.index')->with('status', "Role '{$roleName}' deleted successfully.");
    }

    /**
     * Safely sync permissions ensuring each permission exists in Spatie's table.
     */
    private function syncRolePermissions(Role $role, array $permissionNames): void
    {
        $permissionModels = [];
        foreach ($permissionNames as $name) {
            $permissionModels[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissionModels);
    }

    /**
     * Complete grouped definitions of all system modules and sensitive actions.
     */
    public static function getPermissionGroups(): array
    {
        return [
            'sales' => [
                'label' => 'Sales & Billing',
                'icon' => 'fas fa-shopping-cart text-primary',
                'modules' => [
                    'sales-bills' => ['label' => 'Sales Bills / Invoices', 'actions' => ['create' => 'Create Bill', 'edit' => 'Edit Bill', 'cancel' => 'Cancel/Delete']],
                    'sales-returns' => ['label' => 'Sales Returns', 'actions' => ['create' => 'Create Return', 'edit' => 'Edit Return', 'cancel' => 'Cancel Return']],
                    'sales-quotations' => ['label' => 'Sales Quotations', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'sales-orders' => ['label' => 'Sales Orders', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'sales-delivery-notes' => ['label' => 'Sales Delivery Notes', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                ]
            ],
            'purchase' => [
                'label' => 'Purchase & Procurement',
                'icon' => 'fas fa-truck text-success',
                'modules' => [
                    'purchase-invoices' => ['label' => 'Purchase Invoices', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'purchase-returns' => ['label' => 'Purchase Returns', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'purchase-receipt-notes' => ['label' => 'Goods Receipt Notes (GRN)', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'purchase-orders' => ['label' => 'Purchase Orders (PO)', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'purchase-indents' => ['label' => 'Purchase Indents', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel', 'approve' => 'Approve', 'reject' => 'Reject']],
                ]
            ],
            'inventory' => [
                'label' => 'Inventory & Stock Operations',
                'icon' => 'fas fa-boxes text-warning',
                'modules' => [
                    'stock-updates' => ['label' => 'Stock Updates / Adjustments', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'stock-update-approval' => ['label' => 'Stock Adjustment Approvals', 'actions' => ['approve' => 'Approve Adjustment', 'reject' => 'Reject Adjustment']],
                    'stock-transfers' => ['label' => 'Stock Transfers', 'actions' => ['create' => 'Dispatch', 'receive' => 'Receive', 'cancel' => 'Cancel']],
                    'opening-stocks' => ['label' => 'Opening Stock Setup', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'damage-stocks' => ['label' => 'Damage & Wastage Stocks', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'repack' => ['label' => 'Repack Operations', 'actions' => ['create' => 'Create Repack']],
                    'kit-preparation' => ['label' => 'Kit Preparation', 'actions' => ['create' => 'Prepare Kit']],
                    'kit-unpack' => ['label' => 'Kit Unpack', 'actions' => ['create' => 'Unpack Kit']],
                ]
            ],
            'pricing' => [
                'label' => 'Pricing, Margins & Catalog Items',
                'icon' => 'fas fa-tags text-danger',
                'modules' => [
                    'items' => ['label' => 'Product Items Catalog', 'actions' => ['create' => 'Create New Item', 'edit' => 'Edit Non-Price Data', 'cancel' => 'Delete Item']],
                    'item-price-change' => ['label' => 'Item Price & MRP Modification', 'actions' => ['edit' => 'Change Selling Price / MRP']],
                    'price-fixing' => ['label' => 'Price Fixing Rules', 'actions' => ['apply' => 'Apply Rule']],
                    'change-selling' => ['label' => 'Batch Selling Price Modification', 'actions' => ['edit' => 'Execute Changes']],
                ]
            ],
            'finance' => [
                'label' => 'Finance, Ledgers & Year Lock',
                'icon' => 'fas fa-file-invoice-dollar text-info',
                'modules' => [
                    'vouchers' => ['label' => 'Financial Vouchers (Payment/Receipt/Journal)', 'actions' => ['create' => 'Create Voucher', 'edit' => 'Edit Voucher', 'cancel' => 'Cancel/Delete']],
                    'bill-settlements' => ['label' => 'Bill Settlements', 'actions' => ['create' => 'Record Settlement', 'cancel' => 'Cancel Settlement']],
                    'ledgers' => ['label' => 'Ledger Accounts Master', 'actions' => ['create' => 'Create Account', 'edit' => 'Edit Account', 'cancel' => 'Delete Account']],
                    'financial-years' => ['label' => 'Financial Period Locking', 'actions' => ['create' => 'Create Period', 'edit' => 'Edit Period', 'lock' => 'Lock Period', 'reopen' => 'Reopen Locked Period']],
                ]
            ],
            'counter' => [
                'label' => 'POS Terminal & Cash Shift',
                'icon' => 'fas fa-cash-register text-success',
                'modules' => [
                    'till' => ['label' => 'Cash Drawer Shift Management', 'actions' => ['open' => 'Open Shift Float', 'close' => 'Close Shift Cash Count']],
                ]
            ],
            'masters' => [
                'label' => 'Master Reference Data',
                'icon' => 'fas fa-th-large text-secondary',
                'modules' => [
                    'customers' => ['label' => 'Customer Master', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'customer-categories' => ['label' => 'Customer Categories', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'suppliers' => ['label' => 'Supplier / Vendor Master', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'item-categories' => ['label' => 'Item Categories', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'item-category-values' => ['label' => 'Category Sub-Values', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'product-types' => ['label' => 'Product Types', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'brands' => ['label' => 'Brands', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'uoms' => ['label' => 'Units of Measure (UOM)', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'areas' => ['label' => 'Areas / Geographies', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'pet-types' => ['label' => 'Pet Types', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'breeds' => ['label' => 'Breeds Master', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'colors' => ['label' => 'Colors Master', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'registers' => ['label' => 'Registers', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'branches' => ['label' => 'Branches / Outlets', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'tender-types' => ['label' => 'Tender Types', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'tender-type-values' => ['label' => 'Tender Wallets / Cards', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'gst-taxes' => ['label' => 'GST Tax Rates', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'gst-types' => ['label' => 'GST Classifications', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                    'loyalty-programs' => ['label' => 'Loyalty Reward Programs', 'actions' => ['create' => 'Create', 'edit' => 'Edit', 'cancel' => 'Cancel']],
                ]
            ],
            'security' => [
                'label' => 'System Administration & Security',
                'icon' => 'fas fa-shield-alt text-dark',
                'modules' => [
                    'users' => ['label' => 'Staff Accounts & Roles Assignment', 'actions' => ['create' => 'Create User', 'edit' => 'Edit User', 'cancel' => 'Delete User']],
                ]
            ]
        ];
    }
}
