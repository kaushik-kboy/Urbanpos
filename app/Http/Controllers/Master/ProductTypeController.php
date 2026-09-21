<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = ProductType::orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $productTypes = $query->paginate($this->perPage())->withQueryString();

        return view('master.product-types.index', compact('productTypes'));
    }

    public function create()
    {
        return view('master.product-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $productType = ProductType::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Product Type created successfully.',
                'data' => [
                    'id' => $productType->id,
                    'name' => $productType->name,
                ],
            ]);
        }

        return redirect()->route('master.product-types.index')->with('status', 'Product Type created successfully.');
    }

    public function edit(ProductType $productType)
    {
        return view('master.product-types.edit', compact('productType'));
    }

    public function update(Request $request, ProductType $productType)
    {
        $data = $this->validateData($request, $productType);
        $productType->update($data);

        return redirect()->route('master.product-types.index')->with('status', 'Product Type updated successfully.');
    }

    public function destroy(ProductType $productType)
    {
        return redirect()->route('master.product-types.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?ProductType $productType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_types', 'name')->ignore($productType?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
