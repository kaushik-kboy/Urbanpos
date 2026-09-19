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


    public function index(Request $request)
    {
        $query = Item::with(['brand', 'supplier']);

        if ($request->filled('name')) {
            $term = $request->input('name');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('alias', 'like', "%{$term}%")
                  ->orWhere('item_code', 'like', "%{$term}%")
                  ->orWhere('ean_upc_code', 'like', "%{$term}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        if ($request->filled('category_value_id')) {
            $query->where('category_value_id', $request->input('category_value_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (bool)$request->input('status'));
        }

        $items = $query->orderBy('name')->paginate($this->perPage())->withQueryString();
        $suppliers = Supplier::orderBy('name')->pluck('name', 'id');
        $brands = Brand::orderBy('name')->pluck('name', 'id');
        $categories = $this->categoryValues(['CATEGORY', 'Category', 'category', 'Categories', 'CAT'], 'CATEGORY')['values'];

        return view('master.items.index', compact('items', 'suppliers', 'brands', 'categories'));
    }

    public function create(Request $request)
    {
        $item = null;
        if ($request->filled('copy_from')) {
            $source = Item::find($request->input('copy_from'));
            if ($source) {
                $item = $source->replicate();

                // Smart unique copy name generation to avoid duplicate name validation errors
                $baseName = trim(preg_replace('/\s*\(Copy(\s+\d+)?\)$/i', '', $source->name));
                $copyName = $baseName . ' (Copy)';
                $counter = 2;
                while (Item::where('name', $copyName)->exists()) {
                    $copyName = $baseName . ' (Copy ' . $counter . ')';
                    $counter++;
                }
                $item->name = $copyName;
                $item->ean_upc_code = Item::generateUniqueEanUpc();
                $item->item_code = null;

                // Ensure referenced foreign keys are still valid
                if ($item->brand_id && !Brand::where('id', $item->brand_id)->exists()) {
                    $item->brand_id = null;
                }
                if ($item->supplier_id && !Supplier::where('id', $item->supplier_id)->exists()) {
                    $item->supplier_id = null;
                }
                if ($item->department_value_id && !ItemCategoryValue::where('id', $item->department_value_id)->exists()) {
                    $item->department_value_id = null;
                }
                if ($item->category_value_id && !ItemCategoryValue::where('id', $item->category_value_id)->exists()) {
                    $item->category_value_id = null;
                }
                if ($item->brand_value_id && !ItemCategoryValue::where('id', $item->brand_value_id)->exists()) {
                    $item->brand_value_id = null;
                }
                if ($item->gst_tax_id && !GstTax::where('id', $item->gst_tax_id)->exists()) {
                    $item->gst_tax_id = null;
                }

                // Clean HSN code if it doesn't match numeric 4-8 digits
                if ($item->hsn_code) {
                    $cleanedHsn = preg_replace('/\D/', '', (string)$item->hsn_code);
                    $item->hsn_code = (strlen($cleanedHsn) >= 4 && strlen($cleanedHsn) <= 8) ? $cleanedHsn : null;
                }

                // Ensure enum attributes are valid
                if (!in_array($item->product_type, ['Standard', 'Serialized', 'Service Component', 'Gift Voucher'])) {
                    $item->product_type = 'Standard';
                }
                if (!in_array($item->batch_expiry_details, ['Not Required', 'Optional', 'Mandatory', 'Days', 'Month'])) {
                    $item->batch_expiry_details = 'Not Required';
                }
            }
        }

        return view('master.items.create', array_merge(['item' => $item], $this->formOptions()));
    }

    public function generateBarcode()
    {
        return response()->json([
            'success' => true,
            'barcode' => Item::generateUniqueEanUpc(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->sanitizeItemData($this->validateData($request));
        Item::create($data);

        return redirect()->route('master.items.index')->with('status', 'Item created successfully.');
    }

    public function edit(Item $item)
    {
        return view('master.items.edit', array_merge(['item' => $item], $this->formOptions()));
    }

    public function update(Request $request, Item $item)
    {
        $data = $this->sanitizeItemData($this->validateData($request, $item));
        $item->update($data);

        return redirect()->route('master.items.index')->with('status', 'Item updated successfully.');
    }

    private function sanitizeItemData(array $data): array
    {
        $cost = $data['cost_price'] ?? null;
        $landing = $data['landing_cost'] ?? null;
        $sell = $data['sell_price'] ?? null;
        $mrp = $data['mrp'] ?? null;

        $data['cost_price'] = ($cost !== null && $cost !== '') ? $cost : (($landing !== null && $landing !== '') ? $landing : 0);
        $data['landing_cost'] = ($landing !== null && $landing !== '') ? $landing : (($cost !== null && $cost !== '') ? $cost : 0);
        $data['sell_price'] = ($sell !== null && $sell !== '') ? $sell : (($mrp !== null && $mrp !== '') ? $mrp : 0);
        $data['mrp'] = ($mrp !== null && $mrp !== '') ? $mrp : (($sell !== null && $sell !== '') ? $sell : 0);

        if (!isset($data['product_type']) || empty($data['product_type'])) {
            $data['product_type'] = 'Standard';
        }
        if (!isset($data['status'])) {
            $data['status'] = true;
        }
        if (!isset($data['store_pickup'])) {
            $data['store_pickup'] = false;
        }
        if (!isset($data['tax_inclusive'])) {
            $data['tax_inclusive'] = false;
        }
        if (!isset($data['batch_expiry_details']) || empty($data['batch_expiry_details'])) {
            $data['batch_expiry_details'] = 'Not Required';
        }
        if (!isset($data['allow_negative_stock'])) {
            $data['allow_negative_stock'] = false;
        }

        // Nullable foreign key, integer, and string fields: convert empty strings to null
        $nullableFields = [
            'brand_id', 'supplier_id', 'department_value_id', 'category_value_id',
            'brand_value_id', 'gst_tax_id', 'shelf_life_days', 'minimum_shelf_life_days',
            'hsn_code', 'alias', 'ean_upc_code', 'item_code'
        ];
        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || (is_string($data[$field]) && trim($data[$field]) === ''))) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    public function destroy(Item $item)
    {
        return redirect()->route('master.items.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function formOptions(): array
    {
        $dept = $this->categoryValues(['DEPARTMENT', 'Department', 'department', 'Departments', 'Dept', 'DEPT'], 'DEPARTMENT');
        $cat = $this->categoryValues(['CATEGORY', 'Category', 'category', 'Categories', 'CAT'], 'CATEGORY');
        $brandVal = $this->categoryValues(['Brands', 'Brand', 'brands', 'brand', 'BRANDS'], 'Brands');

        return [
            'brands' => Brand::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'gstTaxes' => GstTax::where('status', true)->orderBy('description')->pluck('description', 'id'),
            'departmentValues' => $dept['values'],
            'categoryValues' => $cat['values'],
            'brandValues' => $brandVal['values'],
            'deptHeadId' => $dept['head_id'],
            'catHeadId' => $cat['head_id'],
            'brandHeadId' => $brandVal['head_id'],
        ];
    }

    private function categoryValues(array $aliases, string $defaultName): array
    {
        $heads = ItemCategory::where(function ($query) use ($aliases) {
            foreach ($aliases as $alias) {
                $query->orWhere('name', 'like', $alias);
            }
        })->get();

        if ($heads->isEmpty()) {
            $head = ItemCategory::firstOrCreate(
                ['name' => $defaultName],
                ['is_mandatory' => false, 'status' => true]
            );
            $heads = collect([$head]);
        }

        $headIds = $heads->pluck('id');
        $values = ItemCategoryValue::whereIn('item_category_id', $headIds)
            ->where(function ($q) {
                $q->where('status', true)->orWhere('status', 1);
            })
            ->orderBy('name')
            ->pluck('name', 'id');

        return [
            'values' => $values,
            'head_id' => $heads->first()->id,
        ];
    }

    private function validateData(Request $request, ?Item $item = null): array
    {
        $eanRule = $item
            ? ['nullable', 'string', 'max:100', 'unique:items,ean_upc_code,'.$item->id]
            : ['nullable', 'string', 'max:100', 'unique:items,ean_upc_code'];

        $rules = [
            // General
            'ean_upc_code' => $eanRule,
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('items', 'name')->ignore($item?->id)],
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
            'hsn_code' => ['nullable', 'regex:/^\d{4,8}$/'],
        ];

        $messages = [
            'hsn_code.regex' => 'HSN Code must be between 4 and 8 digits (numeric).',
        ];

        app(\App\Services\DynamicValidationService::class)->applyTo('items', $rules, $messages);

        return $request->validate($rules, $messages);
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
