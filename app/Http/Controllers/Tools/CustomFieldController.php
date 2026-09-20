<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->input('tab', 'Customer');
        if (! in_array($activeTab, ['Customer', 'Item'])) {
            $activeTab = 'Customer';
        }

        $customerFields = CustomFieldDefinition::forModule('Customer')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $itemFields = CustomFieldDefinition::forModule('Item')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('tools.custom-fields.index', compact('customerFields', 'itemFields', 'activeTab'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|in:Customer,Item',
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
