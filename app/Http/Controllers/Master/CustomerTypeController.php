<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = CustomerType::orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $customerTypes = $query->paginate($this->perPage())->withQueryString();

        return view('master.customer-types.index', compact('customerTypes'));
    }

    public function create()
    {
        return view('master.customer-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $customerType = CustomerType::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer Type created successfully.',
                'data' => [
                    'id' => $customerType->id,
                    'name' => $customerType->name,
                ],
            ]);
        }

        return redirect()->route('master.customer-types.index')->with('status', 'Customer Type created successfully.');
    }

    public function edit(CustomerType $customerType)
    {
        return view('master.customer-types.edit', compact('customerType'));
    }

    public function update(Request $request, CustomerType $customerType)
    {
        $data = $this->validateData($request, $customerType);
        $customerType->update($data);

        return redirect()->route('master.customer-types.index')->with('status', 'Customer Type updated successfully.');
    }

    public function destroy(CustomerType $customerType)
    {
        return redirect()->route('master.customer-types.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?CustomerType $customerType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_types', 'name')->ignore($customerType?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
