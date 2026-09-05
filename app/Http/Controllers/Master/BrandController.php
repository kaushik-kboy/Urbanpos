<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::orderBy('name')->paginate(20);

        return view('master.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('master.brands.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Brand::create($data);

        return redirect()->route('master.brands.index')->with('status', 'Brand created successfully.');
    }

    public function edit(Brand $brand)
    {
        return view('master.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $this->validateData($request);
        $brand->update($data);

        return redirect()->route('master.brands.index')->with('status', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()->route('master.brands.index')->with('status', 'Brand deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'alias_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
