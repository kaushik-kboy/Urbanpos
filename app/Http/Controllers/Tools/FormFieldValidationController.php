<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\FormFieldValidation;
use App\Services\DynamicValidationService;
use Database\Seeders\FormFieldValidationSeeder;
use Illuminate\Http\Request;

class FormFieldValidationController extends Controller
{
    private array $modules = [
        'purchase_invoices' => [
            'name' => 'Purchase Invoices',
            'icon' => 'fas fa-file-invoice',
            'description' => 'Purchase Invoices create and edit forms (/purchase/purchase-invoices/create)',
        ],
        'sales_bills' => [
            'name' => 'Sales Bills',
            'icon' => 'fas fa-cash-register',
            'description' => 'Sales Bills create and edit forms (/sales/sales-bills/create)',
        ],
        'stock_transfers' => [
            'name' => 'Stock Transfers',
            'icon' => 'fas fa-exchange-alt',
            'description' => 'Stock Transfers / Dispatches (/inventory/stock-transfers/create)',
        ],
        'customers' => [
            'name' => 'Customer Master',
            'icon' => 'fas fa-user',
            'description' => 'Customer Master forms & Quick Add Customer popup',
        ],
        'suppliers' => [
            'name' => 'Supplier Master',
            'icon' => 'fas fa-truck',
            'description' => 'Supplier Master forms & registration (/master/suppliers)',
        ],
    ];

    public function __construct(
        private DynamicValidationService $dynamicValidationService
    ) {
    }

    public function index(Request $request)
    {
        $activeModule = $request->input('module', 'purchase_invoices');
        if (!array_key_exists($activeModule, $this->modules)) {
            $activeModule = 'purchase_invoices';
        }

        $fields = FormFieldValidation::where('module_key', $activeModule)
            ->orderBy('sort_order')
            ->get();

        return view('tools.form-validations.index', [
            'modules' => $this->modules,
            'activeModule' => $activeModule,
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

        $moduleName = $this->modules[$moduleKey]['name'] ?? $moduleKey;
        return redirect()->route('tools.form-validations.index', ['module' => $moduleKey])
            ->with('status', "Validation rules for {$moduleName} updated successfully.");
    }

    public function reset(Request $request)
    {
        $moduleKey = $request->input('module_key', 'purchase_invoices');

        $seeder = new FormFieldValidationSeeder();
        $seeder->run();

        $this->dynamicValidationService->clearCache($moduleKey);

        $moduleName = $this->modules[$moduleKey]['name'] ?? $moduleKey;
        return redirect()->route('tools.form-validations.index', ['module' => $moduleKey])
            ->with('status', "Validation rules for {$moduleName} have been reset to system defaults.");
    }
}
