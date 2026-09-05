<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use Illuminate\Http\Request;

class ItemCategoryValueController extends Controller
{
    public function index()
    {
        $itemCategoryValues = ItemCategoryValue::with('itemCategory')->orderBy('name')->paginate(20);

        return view('master.item-category-values.index', compact('itemCategoryValues'));
    }

    public function create()
    {
        $itemCategories = ItemCategory::orderBy('name')->pluck('name', 'id');

        return view('master.item-category-values.create', compact('itemCategories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        ItemCategoryValue::create($data);

        return redirect()->route('master.item-category-values.index')->with('status', 'Item category value created successfully.');
    }

    public function edit(ItemCategoryValue $itemCategoryValue)
    {
        $itemCategories = ItemCategory::orderBy('name')->pluck('name', 'id');

        return view('master.item-category-values.edit', compact('itemCategoryValue', 'itemCategories'));
    }

    public function update(Request $request, ItemCategoryValue $itemCategoryValue)
    {
        $data = $this->validateData($request);
        $itemCategoryValue->update($data);

        return redirect()->route('master.item-category-values.index')->with('status', 'Item category value updated successfully.');
    }

    public function destroy(ItemCategoryValue $itemCategoryValue)
    {
        $itemCategoryValue->delete();

        return redirect()->route('master.item-category-values.index')->with('status', 'Item category value deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'show_in_webstore' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
            'sellquick_applicable' => ['required', 'boolean'],
            'allowed_qty_ml' => ['nullable', 'integer'],
        ]);
    }
}
