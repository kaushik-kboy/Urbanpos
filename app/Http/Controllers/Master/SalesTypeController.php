<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\SalesType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = SalesType::orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $salesTypes = $query->paginate($this->perPage())->withQueryString();

        return view('master.sales-types.index', compact('salesTypes'));
    }

    public function create()
    {
        return view('master.sales-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $salesType = SalesType::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sales Type created successfully.',
                'data' => [
                    'id' => $salesType->id,
                    'name' => $salesType->name,
                ],
            ]);
        }

        return redirect()->route('master.sales-types.index')->with('status', 'Sales Type created successfully.');
    }

    public function edit(SalesType $salesType)
    {
        return view('master.sales-types.edit', compact('salesType'));
    }

    public function update(Request $request, SalesType $salesType)
    {
        $data = $this->validateData($request, $salesType);
        $salesType->update($data);

        return redirect()->route('master.sales-types.index')->with('status', 'Sales Type updated successfully.');
    }

    public function destroy(SalesType $salesType)
    {
        return redirect()->route('master.sales-types.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?SalesType $salesType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sales_types', 'name')->ignore($salesType?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
