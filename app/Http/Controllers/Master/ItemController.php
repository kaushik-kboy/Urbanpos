<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\Supplier;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $items = Item::with(['brand', 'supplier'])->orderBy('name')->paginate($this->perPage());

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

    protected function importModel(): string
    {
        return Item::class;
    }

    protected function importColumns(): array
    {
        return [
            'EAN/UPC Code' => ['column' => 'ean_upc_code'],
            'Name' => ['column' => 'name', 'required' => true],
            'Alias' => ['column' => 'alias'],
            'Product Type' => ['column' => 'product_type'],
            'Cost Price' => ['column' => 'cost_price', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Landing Cost' => ['column' => 'landing_cost', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Sell Price' => ['column' => 'sell_price', 'cast' => fn ($v) => $this->importDecimal($v)],
            'MRP' => ['column' => 'mrp', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
            'Store Pickup' => ['column' => 'store_pickup', 'cast' => fn ($v) => $this->importBool($v)],
            'Tax Inclusive' => ['column' => 'tax_inclusive', 'cast' => fn ($v) => $this->importBool($v)],
            'Batch Expiry Details' => ['column' => 'batch_expiry_details'],
            'Shelf Life Days' => ['column' => 'shelf_life_days', 'cast' => fn ($v) => (int) $v],
            'Minimum Shelf Life Days' => ['column' => 'minimum_shelf_life_days', 'cast' => fn ($v) => (int) $v],
            'Allow Negative Stock' => ['column' => 'allow_negative_stock', 'cast' => fn ($v) => $this->importBool($v)],
            'HSN Code' => ['column' => 'hsn_code'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }

    protected function importRelations(): array
    {
        return [
            'Brand' => fn (string $v) => $v === '' ? ['brand_id' => null]
                : ['brand_id' => Brand::firstOrCreate(['name' => $v], ['status' => true])->id],

            'Supplier' => fn (string $v) => $v === '' ? ['supplier_id' => null]
                : ['supplier_id' => Supplier::firstOrCreate(['name' => $v], [
                    'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit',
                    'credit_limit' => 0, 'credit_balance' => 0, 'credit_days' => 0, 'status' => true,
                    'gst_type' => 'Un Register', 'mail_type' => 'None',
                ])->id],

            'GST Tax' => fn (string $v) => $v === '' ? ['gst_tax_id' => null]
                : ['gst_tax_id' => GstTax::firstOrCreate(['description' => $v], ['percentage' => 0, 'status' => true])->id],

            'Department' => fn (string $v) => $v === '' ? ['department_value_id' => null]
                : ['department_value_id' => $this->resolveCategoryValue($v)],

            'Category' => fn (string $v) => $v === '' ? ['category_value_id' => null]
                : ['category_value_id' => $this->resolveCategoryValue($v)],

            'Brand Value' => fn (string $v) => $v === '' ? ['brand_value_id' => null]
                : ['brand_value_id' => $this->resolveCategoryValue($v)],
        ];
    }

    private function resolveCategoryValue(string $name): int
    {
        return ItemCategoryValue::firstOrCreate(
            ['name' => $name],
            ['item_category_id' => ItemCategory::firstOrCreate(['name' => 'Uncategorized'], ['is_mandatory' => false, 'status' => true])->id, 'status' => true]
        )->id;
    }
}
