<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\FormFieldValidation;
use App\Services\DynamicValidationService;
use Database\Seeders\FormFieldValidationSeeder;
use Illuminate\Http\Request;

class FormFieldValidationController extends Controller
{
    private array $moduleGroups = [
        'purchase' => [
            'name' => 'Purchase',
            'icon' => 'fas fa-shopping-bag',
            'badge' => 'badge-primary',
            'modules' => [
                'purchase_invoices' => [
                    'name' => 'Purchase Invoices',
                    'icon' => 'fas fa-file-invoice',
                    'description' => 'Purchase Invoices create & edit forms (/purchase/purchase-invoices/create)',
                ],
                'purchase_orders' => [
                    'name' => 'Purchase Orders',
                    'icon' => 'fas fa-clipboard-list',
                    'description' => 'Purchase Orders create & edit forms (/purchase/purchase-orders/create)',
                ],
                'purchase_receipt_notes' => [
                    'name' => 'Receipt Notes (GIN)',
                    'icon' => 'fas fa-receipt',
                    'description' => 'Goods Inward Notes (/purchase/receipt-notes/create)',
                ],
                'purchase_indents' => [
                    'name' => 'Purchase Indents',
                    'icon' => 'fas fa-indent',
                    'description' => 'Internal purchase requisition forms (/purchase/purchase-indents/create)',
                ],
                'purchase_returns' => [
                    'name' => 'Purchase Returns',
                    'icon' => 'fas fa-undo-alt',
                    'description' => 'Purchase Returns / Debit Notes (/purchase/purchase-returns/create)',
                ],
            ],
        ],
        'sales' => [
            'name' => 'Sales',
            'icon' => 'fas fa-cash-register',
            'badge' => 'badge-success',
            'modules' => [
                'sales_bills' => [
                    'name' => 'Sales Bills',
                    'icon' => 'fas fa-cash-register',
                    'description' => 'Sales Bills & POS billing screens (/sales/sales-bills/create)',
                ],
                'sales_returns' => [
                    'name' => 'Sales Returns',
                    'icon' => 'fas fa-undo',
                    'description' => 'Sales Returns / Credit Notes (/sales/sales-returns/create)',
                ],
                'sales_quotations' => [
                    'name' => 'Sales Quotations',
                    'icon' => 'fas fa-file-signature',
                    'description' => 'Sales Quotations & Estimates (/sales/sales-quotations/create)',
                ],
                'sales_orders' => [
                    'name' => 'Sales Orders',
                    'icon' => 'fas fa-shopping-cart',
                    'description' => 'Sales Orders / Customer Bookings (/sales/sales-orders/create)',
                ],
                'sales_delivery_notes' => [
                    'name' => 'Delivery Notes',
                    'icon' => 'fas fa-truck',
                    'description' => 'Sales Delivery & Dispatch Notes (/sales/delivery-notes/create)',
                ],
            ],
        ],
        'inventory' => [
            'name' => 'Inventory',
            'icon' => 'fas fa-warehouse',
            'badge' => 'badge-warning',
            'modules' => [
                'stock_transfers' => [
                    'name' => 'Stock Transfers',
                    'icon' => 'fas fa-exchange-alt',
                    'description' => 'Branch Stock Transfers & Dispatches (/inventory/stock-transfers/create)',
                ],
                'opening_stocks' => [
                    'name' => 'Opening Stock',
                    'icon' => 'fas fa-dolly',
                    'description' => 'Initial inventory opening balances (/inventory/opening-stocks/create)',
                ],
                'damage_stocks' => [
                    'name' => 'Damage / Wastage Stock',
                    'icon' => 'fas fa-dumpster-fire',
                    'description' => 'Damaged or written-off stock (/inventory/damage-stocks/create)',
                ],
                'stock_updates' => [
                    'name' => 'Stock Updates',
                    'icon' => 'fas fa-sync-alt',
                    'description' => 'Stock adjustments and physical counts (/inventory/stock-updates/create)',
                ],
            ],
        ],
        'master' => [
            'name' => 'Masters',
            'icon' => 'fas fa-database',
            'badge' => 'badge-info',
            'modules' => [
                'customers' => [
                    'name' => 'Customer Master',
                    'icon' => 'fas fa-user',
                    'description' => 'Customer registration and Quick Add modal',
                ],
                'suppliers' => [
                    'name' => 'Supplier Master',
                    'icon' => 'fas fa-truck-loading',
                    'description' => 'Supplier master data and details (/master/suppliers/create)',
                ],
                'items' => [
                    'name' => 'Item Master',
                    'icon' => 'fas fa-box',
                    'description' => 'Product / Item master catalog (/master/items/create)',
                ],
                'branches' => [
                    'name' => 'Branch Master',
                    'icon' => 'fas fa-store',
                    'description' => 'Branch and outlet settings (/master/branches/create)',
                ],
            ],
        ],
    ];

    public function __construct(
        private DynamicValidationService $dynamicValidationService
    ) {
    }

    public function index(Request $request)
    {
        $requestedModule = $request->input('module');
        $requestedGroup = $request->input('group');

        // Locate module's group if module is requested
        if ($requestedModule) {
            foreach ($this->moduleGroups as $gKey => $group) {
                if (array_key_exists($requestedModule, $group['modules'])) {
                    $requestedGroup = $gKey;
                    break;
                }
            }
        }

        if (!$requestedGroup || !array_key_exists($requestedGroup, $this->moduleGroups)) {
            $requestedGroup = 'purchase';
        }

        $groupModules = $this->moduleGroups[$requestedGroup]['modules'];

        if (!$requestedModule || !array_key_exists($requestedModule, $groupModules)) {
            $requestedModule = array_key_first($groupModules);
        }

        $fields = FormFieldValidation::where('module_key', $requestedModule)
            ->orderBy('sort_order')
            ->get();

        return view('tools.form-validations.index', [
            'moduleGroups' => $this->moduleGroups,
            'activeGroup' => $requestedGroup,
            'activeModule' => $requestedModule,
            'fields' => $fields,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string'],
            'fields' => ['nullable', 'array'],
            'fields.*.custom_error_message' => ['nullable', 'string', 'max:500'],
        ]);

        $moduleKey = $validated['module_key'];
        $rawFields = $request->input('fields', []);

        foreach ($rawFields as $fieldId => $data) {
            $field = FormFieldValidation::where('id', $fieldId)
                ->where('module_key', $moduleKey)
                ->first();

            if ($field) {
                $field->update([
                    'is_required' => !empty($data['is_required']),
                    'is_readonly' => !empty($data['is_readonly']),
                    'block_future_date' => !empty($data['block_future_date']),
                    'is_unique' => !empty($data['is_unique']),
                    'custom_error_message' => !empty($data['custom_error_message']) ? trim($data['custom_error_message']) : null,
                ]);
            }
        }

        $this->dynamicValidationService->clearCache($moduleKey);

        return redirect()->route('tools.form-validations.index', ['module' => $moduleKey])
            ->with('status', 'Validation rules updated successfully.');
    }

    public function reset(Request $request)
    {
        $moduleKey = $request->input('module_key', 'purchase_invoices');

        $seeder = new FormFieldValidationSeeder();
        $seeder->run($moduleKey);

        $this->dynamicValidationService->clearCache($moduleKey);

        return redirect()->route('tools.form-validations.index', ['module' => $moduleKey])
            ->with('status', 'Validation rules have been reset to system defaults.');
    }
}
