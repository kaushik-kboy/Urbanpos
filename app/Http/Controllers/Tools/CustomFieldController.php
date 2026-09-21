<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public const CATEGORIES = [
        'Masters' => [
            'icon' => 'fas fa-database text-info',
            'modules' => [
                'Customer' => 'Customer Fields',
                'Item' => 'Item Master Fields',
                'Supplier' => 'Supplier / Vendor Fields',
                'Branch' => 'Branch / Store Fields',
            ],
        ],
        'Sales' => [
            'icon' => 'fas fa-shopping-cart text-success',
            'modules' => [
                'SalesBill' => 'Sales Bill / POS Invoice',
                'SalesReturn' => 'Sales Return / Credit Note',
                'SalesOrder' => 'Sales Order',
                'SalesQuotation' => 'Sales Quotation / Estimate',
                'SalesDeliveryNote' => 'Sales Delivery Note / Challan',
            ],
        ],
        'Purchase' => [
            'icon' => 'fas fa-shopping-bag text-primary',
            'modules' => [
                'PurchaseInvoice' => 'Purchase Invoice / Bill',
                'PurchaseOrder' => 'Purchase Order (PO)',
                'PurchaseReturn' => 'Purchase Return / Debit Note',
                'PurchaseReceiptNote' => 'Goods Receipt Note (GRN)',
                'PurchaseIndent' => 'Purchase Indent / Requisition',
            ],
        ],
        'Inventory' => [
            'icon' => 'fas fa-warehouse text-warning',
            'modules' => [
                'StockTransfer' => 'Stock Transfer (Inter-Branch)',
                'DamageStock' => 'Damage / Wastage Stock',
                'OpeningStock' => 'Opening Stock Entry',
                'StockUpdate' => 'Stock Adjustment / Update',
            ],
        ],
    ];

    public static function getAllModules(): array
    {
        $all = [];
        foreach (self::CATEGORIES as $cat => $data) {
            foreach ($data['modules'] as $key => $label) {
                $all[$key] = [
                    'label' => $label,
                    'category' => $cat,
                ];
            }
        }
        return $all;
    }

    public function index(Request $request)
    {
        $allModules = self::getAllModules();
        $activeModule = $request->input('tab', 'Customer');

        if (! isset($allModules[$activeModule])) {
            $activeModule = 'Customer';
        }

        $activeCategory = $allModules[$activeModule]['category'];

        $fieldsByModule = CustomFieldDefinition::orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('module');

        $categories = self::CATEGORIES;

        return view('tools.custom-fields.index', compact(
            'categories',
            'allModules',
            'activeCategory',
            'activeModule',
            'fieldsByModule'
        ));
    }

    public function store(Request $request)
    {
        $allKeys = array_keys(self::getAllModules());

        $validated = $request->validate([
            'module' => 'required|in:' . implode(',', $allKeys),
            'field_name' => 'required|string|max:100',
            'field_type' => 'required|in:text,number,date,select,textarea,checkbox',
            'options' => 'nullable|string', // Comma-separated or newline-separated
            'default_value' => 'nullable|string|max:255',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $optionsArray = null;
        if ($validated['field_type'] === 'select' && ! empty($validated['options'])) {
            $optionsArray = array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $validated['options']))));
        }

        $fieldKey = CustomFieldDefinition::generateKey($validated['module'], $validated['field_name']);

        $field = CustomFieldDefinition::create([
            'module' => $validated['module'],
            'field_name' => $validated['field_name'],
            'field_key' => $fieldKey,
            'field_type' => $validated['field_type'],
            'options' => $optionsArray,
            'default_value' => $validated['default_value'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'status' => $request->has('status') ? $request->boolean('status') : true,
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Custom field '{$field->field_name}' created successfully.",
                'field' => $field,
            ]);
        }

        return redirect()
            ->route('tools.custom-fields.index', ['tab' => $field->module])
            ->with('status', "Custom Field \"{$field->field_name}\" added successfully for {$field->module}.");
    }

    public function update(Request $request, CustomFieldDefinition $customField)
    {
        $validated = $request->validate([
            'field_name' => 'required|string|max:100',
            'field_type' => 'required|in:text,number,date,select,textarea,checkbox',
            'options' => 'nullable|string',
            'default_value' => 'nullable|string|max:255',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $optionsArray = null;
        if ($validated['field_type'] === 'select' && ! empty($validated['options'])) {
            $optionsArray = array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $validated['options']))));
        }

        $customField->update([
            'field_name' => $validated['field_name'],
            'field_type' => $validated['field_type'],
            'options' => $optionsArray,
            'default_value' => $validated['default_value'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'status' => $request->boolean('status'),
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Custom field '{$customField->field_name}' updated successfully.",
                'field' => $customField,
            ]);
        }

        return redirect()
            ->route('tools.custom-fields.index', ['tab' => $customField->module])
            ->with('status', "Custom Field \"{$customField->field_name}\" updated successfully.");
    }

    public function destroy(Request $request, CustomFieldDefinition $customField)
    {
        $module = $customField->module;
        $name = $customField->field_name;
        $customField->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Custom field '{$name}' deleted successfully.",
            ]);
        }

        return redirect()
            ->route('tools.custom-fields.index', ['tab' => $module])
            ->with('status', "Custom Field \"{$name}\" deleted successfully.");
    }

    public function toggleStatus(Request $request, CustomFieldDefinition $customField)
    {
        $customField->status = ! $customField->status;
        $customField->save();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => $customField->status,
                'message' => "Status updated for '{$customField->field_name}'.",
            ]);
        }

        return back()->with('status', "Field status toggled successfully.");
    }
}
