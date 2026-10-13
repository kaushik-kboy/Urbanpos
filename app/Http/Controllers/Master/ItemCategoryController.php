<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    use HasPerPage, Importable;


    public function index(Request $request)
    {
        $query = ItemCategory::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        $statusInput = $request->input('status');
        if ($statusInput !== null && $statusInput !== '' && strtolower((string) $statusInput) !== 'all') {
            if ($statusInput === '1' || $statusInput === 'active' || (int) $statusInput === 1) {
                $query->where('status', 1);
            } elseif ($statusInput === '0' || $statusInput === 'inactive' || (int) $statusInput === 0) {
                $query->where('status', 0);
            }
        }

        $mandatoryInput = $request->input('is_mandatory');
        if ($mandatoryInput !== null && $mandatoryInput !== '' && strtolower((string) $mandatoryInput) !== 'all') {
            if ($mandatoryInput === '1' || $mandatoryInput === 'yes' || (int) $mandatoryInput === 1) {
                $query->where('is_mandatory', 1);
            } elseif ($mandatoryInput === '0' || $mandatoryInput === 'no' || (int) $mandatoryInput === 0) {
                $query->where('is_mandatory', 0);
            }
        }

        $itemCategories = $query->orderBy('name')->paginate($this->perPage())->withQueryString();

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
        $data = $this->validateData($request, $itemCategory);
        $itemCategory->update($data);

        return redirect()->route('master.item-categories.index')->with('status', 'Item category updated successfully.');
    }

    public function destroy(ItemCategory $itemCategory)
    {
        return redirect()->route('master.item-categories.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?ItemCategory $itemCategory = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('item_categories', 'name')->ignore($itemCategory?->id)],
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
