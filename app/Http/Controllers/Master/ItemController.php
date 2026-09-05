<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::with(['brand', 'supplier'])->orderBy('name')->paginate(20);

        return view('master.items.index', compact('items'));
    }

    public function create()
    {
        return view('master.items.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Item::create($data);

        return redirect()->route('master.items.index')->with('status', 'Item created successfully.');
    }

    public function edit(Item $item)
    {
        return view('master.items.edit', array_merge(['item' => $item], $this->formOptions()));
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->validateData($request, $item);
        $item->update($data);

        return redirect()->route('master.items.index')->with('status', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('master.items.index')->with('status', 'Item deleted.');
    }

    private function formOptions(): array
    {
        return [
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'gstTaxes' => GstTax::orderBy('description')->pluck('description', 'id'),
            'departmentValues' => $this->categoryValues('DEPARTMENT'),
            'categoryValues' => $this->categoryValues('CATEGORY'),
            'brandValues' => $this->categoryValues('Brands'),
        ];
    }

    private function categoryValues(string $headName)
    {
        $head = ItemCategory::where('name', $headName)->first();

        return $head ? $head->values()->orderBy('name')->pluck('name', 'id') : collect();
    }

    private function validateData(Request $request, ?Item $item = null): array
    {
        $eanRule = $item
            ? ['nullable', 'string', 'max:100', 'unique:items,ean_upc_code,'.$item->id]
            : ['nullable', 'string', 'max:100', 'unique:items,ean_upc_code'];

        return $request->validate([
            // General
            'ean_upc_code' => $eanRule,
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'product_type' => ['required', 'in:Standard,Serialized,Service Component,Gift Voucher'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'landing_cost' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'mrp' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'boolean'],
            'store_pickup' => ['required', 'boolean'],

            // Taxes
            'tax_inclusive' => ['required', 'boolean'],

            // Sales
            'batch_expiry_details' => ['required', 'in:Not Required,Optional,Mandatory,Days,Month'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'minimum_shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'allow_negative_stock' => ['required', 'boolean'],

            // Category
            'department_value_id' => ['nullable', 'exists:item_category_values,id'],
            'category_value_id' => ['nullable', 'exists:item_category_values,id'],
            'brand_value_id' => ['nullable', 'exists:item_category_values,id'],

            // GST
            'gst_tax_id' => ['nullable', 'exists:gst_taxes,id'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
