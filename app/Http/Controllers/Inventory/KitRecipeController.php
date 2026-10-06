<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\KitRecipe;
use App\Models\KitRecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KitRecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = KitRecipe::with(['kitItem.brand', 'items.componentItem'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim($request->search);
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('kitItem', fn ($iq) => $iq->where('name', 'like', "%{$term}%")->orWhere('item_code', 'like', "%{$term}%"));
            })
            ->latest();

        $recipes = $query->paginate(20)->withQueryString();

        return view('inventory.kits.index', compact('recipes'));
    }

    public function create()
    {
        return view('inventory.kits.form', [
            'recipe' => new KitRecipe(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kit_item_id' => ['required', 'exists:items,id', 'unique:kit_recipes,kit_item_id'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_item_id' => ['required', 'exists:items,id', 'different:kit_item_id'],
            'components.*.qty_per_kit' => ['required', 'numeric', 'min:0.0001'],
            'components.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $kitItem = Item::findOrFail($data['kit_item_id']);

        DB::transaction(function () use ($data, $kitItem) {
            $recipe = KitRecipe::create([
                'kit_item_id' => $kitItem->id,
                'name' => $data['name'] ?: ($kitItem->name . ' Recipe'),
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['components'] as $line) {
                $recipe->items()->create([
                    'component_item_id' => $line['component_item_id'],
                    'qty_per_kit' => $line['qty_per_kit'],
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('inventory.kit-recipes.index')
            ->with('status', "Kit Recipe for '{$kitItem->name}' created successfully.");
    }

    public function edit(KitRecipe $kitRecipe)
    {
        $kitRecipe->load(['kitItem', 'items.componentItem']);

        return view('inventory.kits.form', [
            'recipe' => $kitRecipe,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, KitRecipe $kitRecipe)
    {
        $data = $request->validate([
            'kit_item_id' => ['required', 'exists:items,id', Rule::unique('kit_recipes', 'kit_item_id')->ignore($kitRecipe->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_item_id' => ['required', 'exists:items,id', 'different:kit_item_id'],
            'components.*.qty_per_kit' => ['required', 'numeric', 'min:0.0001'],
            'components.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $kitItem = Item::findOrFail($data['kit_item_id']);

        DB::transaction(function () use ($data, $kitRecipe, $kitItem) {
            $kitRecipe->update([
                'kit_item_id' => $kitItem->id,
                'name' => $data['name'] ?: ($kitItem->name . ' Recipe'),
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $kitRecipe->items()->delete();

            foreach ($data['components'] as $line) {
                $kitRecipe->items()->create([
                    'component_item_id' => $line['component_item_id'],
                    'qty_per_kit' => $line['qty_per_kit'],
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('inventory.kit-recipes.index')
            ->with('status', "Kit Recipe for '{$kitItem->name}' updated successfully.");
    }

    public function destroy(KitRecipe $kitRecipe)
    {
        $kitName = $kitRecipe->kitItem?->name ?? 'Kit';
        $kitRecipe->delete();

        return redirect()->route('inventory.kit-recipes.index')
            ->with('status', "Kit Recipe for '{$kitName}' deleted successfully.");
    }

    /**
     * AJAX endpoint used by Kit Preparation & Kit Unpack to auto-load recipe ingredients.
     */
    public function byKitItem(int $itemId)
    {
        $recipe = KitRecipe::with(['items.componentItem'])
            ->where('kit_item_id', $itemId)
            ->where('is_active', true)
            ->first();

        if (!$recipe) {
            return response()->json([
                'found' => false,
                'message' => 'No active recipe defined for this product.',
                'components' => [],
            ]);
        }

        $components = $recipe->items->map(function ($comp) {
            $cItem = $comp->componentItem;
            return [
                'item_id' => $comp->component_item_id,
                'name' => $cItem?->name ?? 'Component #' . $comp->component_item_id,
                'item_code' => $cItem?->item_code ?? '',
                'qty_per_kit' => (float) $comp->qty_per_kit,
                'cost_price' => (float) ($cItem?->cost_price ?? 0),
                'remarks' => $comp->remarks,
            ];
        });

        return response()->json([
            'found' => true,
            'recipe_id' => $recipe->id,
            'recipe_name' => $recipe->name,
            'notes' => $recipe->notes,
            'components' => $components,
        ]);
    }
}
