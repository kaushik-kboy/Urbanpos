<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $brands = Brand::orderBy('name')->paginate($this->perPage());

        return view('master.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('master.brands.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $brand = Brand::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Brand created successfully.',
                'data' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ],
            ]);
        }

        return redirect()->route('master.brands.index')->with('status', 'Brand created successfully.');
    }

    public function edit(Brand $brand)
    {
        return view('master.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $this->validateData($request, $brand);
        $brand->update($data);

        return redirect()->route('master.brands.index')->with('status', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand)
    {
        return redirect()->route('master.brands.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?Brand $brand = null): array
    {
        if (! $request->has('status') || $request->input('status') === null) {
            $request->merge(['status' => 1]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand?->id)],
            'prefix' => ['nullable', 'string', 'max:50'],
            'alias_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return Brand::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Prefix' => ['column' => 'prefix'],
            'Alias Code' => ['column' => 'alias_code'],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
