<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomerCategory;
use Illuminate\Http\Request;

class CustomerCategoryController extends Controller
{
    public function index()
    {
        $customerCategories = CustomerCategory::orderBy('name')->paginate(20);

        return view('master.customer-categories.index', compact('customerCategories'));
    }

    public function create()
    {
        return view('master.customer-categories.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        CustomerCategory::create($data);

        return redirect()->route('master.customer-categories.index')->with('status', 'Customer category created successfully.');
    }

    public function edit(CustomerCategory $customerCategory)
    {
        return view('master.customer-categories.edit', compact('customerCategory'));
    }

    public function update(Request $request, CustomerCategory $customerCategory)
    {
        $data = $this->validateData($request);
        $customerCategory->update($data);

        return redirect()->route('master.customer-categories.index')->with('status', 'Customer category updated successfully.');
    }

    public function destroy(CustomerCategory $customerCategory)
    {
        $customerCategory->delete();

        return redirect()->route('master.customer-categories.index')->with('status', 'Customer category deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'app_access' => ['required', 'boolean'],
            'enable_loyalty' => ['required', 'boolean'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'business_type' => ['required', 'in:ALL,COCO,FRANCHISE,BRANCH,DISTRIBUTION CENTER,SERVICE UNIT,FOFO,ASP'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
