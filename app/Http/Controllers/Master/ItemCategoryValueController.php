<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use Illuminate\Http\Request;

class ItemCategoryValueController extends Controller
{
    use HasPerPage, Importable;


    public function index(Request $request)
    {
        $query = ItemCategoryValue::with('itemCategory');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('item_category_id', $request->input('category_id'));
        }

        if ($request->filled('status') && in_array($request->input('status'), ['0', '1'], true)) {
            $query->where('status', (int) $request->input('status'));
        }

        $itemCategoryValues = $query->orderBy('name')->paginate($this->perPage());
        $itemCategories = ItemCategory::orderBy('name')->pluck('name', 'id');

        return view('master.item-category-values.index', compact('itemCategoryValues', 'itemCategories'));
    }

    public function create()
    {
        $itemCategories = ItemCategory::orderBy('name')->pluck('name', 'id');

        return view('master.item-category-values.create', compact('itemCategories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $val = ItemCategoryValue::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Category value created successfully.',
                'data' => [
                    'id' => $val->id,
                    'name' => $val->name,
                    'item_category_id' => $val->item_category_id,
                ],
            ]);
        }

        return redirect()->route('master.item-category-values.index')->with('status', 'Item category value created successfully.');
    }

    public function edit(ItemCategoryValue $itemCategoryValue)
    {
        $itemCategories = ItemCategory::orderBy('name')->pluck('name', 'id');

        return view('master.item-category-values.edit', compact('itemCategoryValue', 'itemCategories'));
    }

    public function update(Request $request, ItemCategoryValue $itemCategoryValue)
    {
        $data = $this->validateData($request, $itemCategoryValue);
        $itemCategoryValue->update($data);

        return redirect()->route('master.item-category-values.index')->with('status', 'Item category value updated successfully.');
    }

    public function destroy(ItemCategoryValue $itemCategoryValue)
    {
        return redirect()->route('master.item-category-values.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?ItemCategoryValue $itemCategoryValue = null): array
    {
        $request->merge([
            'show_in_webstore' => $request->input('show_in_webstore', 0),
            'status' => $request->input('status', 1),
            'sellquick_applicable' => $request->input('sellquick_applicable', 0),
        ]);

        return $request->validate([
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('item_category_values', 'name')
                    ->where(fn ($q) => $q->where('item_category_id', $request->input('item_category_id')))
                    ->ignore($itemCategoryValue?->id),
            ],
            'show_in_webstore' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
            'sellquick_applicable' => ['required', 'boolean'],
            'allowed_qty_ml' => ['nullable', 'integer'],
        ]);
    }

    protected function importModel(): string
    {
        return ItemCategoryValue::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Show In Webstore' => ['column' => 'show_in_webstore', 'cast' => fn ($v) => $this->importBool($v)],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
            'Sellquick Applicable' => ['column' => 'sellquick_applicable', 'cast' => fn ($v) => $this->importBool($v)],
            'Allowed Qty Ml' => ['column' => 'allowed_qty_ml', 'cast' => fn ($v) => (int) $v],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['item_category_id', 'name'];
    }

    protected function importRelations(): array
    {
        return [
            'Item Category' => function (string $value) {
                if ($value === '') {
                    return ['__error' => 'Item Category is required.'];
                }

                $category = ItemCategory::firstOrCreate(['name' => $value], ['is_mandatory' => false, 'status' => true]);

                return ['item_category_id' => $category->id];
            },
        ];
    }
}
