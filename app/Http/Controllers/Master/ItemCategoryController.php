<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $itemCategories = ItemCategory::orderBy('name')->paginate($this->perPage());

        return view('master.item-categories.index', compact('itemCategories'));
    }

    public function create()
    {
        return view('master.item-categories.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        ItemCategory::create($data);

        return redirect()->route('master.item-categories.index')->with('status', 'Item category created successfully.');
    }

    public function edit(ItemCategory $itemCategory)
    {
        return view('master.item-categories.edit', compact('itemCategory'));
    }

    public function update(Request $request, ItemCategory $itemCategory)
    {
        $data = $this->validateData($request);
        $itemCategory->update($data);

        return redirect()->route('master.item-categories.index')->with('status', 'Item category updated successfully.');
    }

    public function destroy(ItemCategory $itemCategory)
    {
        $itemCategory->delete();

        return redirect()->route('master.item-categories.index')->with('status', 'Item category deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_mandatory' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return ItemCategory::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Is Mandatory' => ['column' => 'is_mandatory', 'cast' => fn ($v) => $this->importBool($v)],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
