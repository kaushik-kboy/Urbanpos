<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SalesType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = SalesType::with('branch')->orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%")
                  ->orWhereHas('branch', fn ($bq) => $bq->where('name', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('branch_id')) {
            $bId = $request->input('branch_id');
            if ($bId === 'global' || $bId === 'null') {
                $query->whereNull('branch_id');
            } elseif ($bId !== 'all') {
                $query->where('branch_id', $bId);
            }
        }

        $statusInput = $request->input('status');
        if ($statusInput !== null && $statusInput !== '' && strtolower((string) $statusInput) !== 'all') {
            if ($statusInput === '1' || $statusInput === 'active' || (int) $statusInput === 1) {
                $query->where('status', 1);
            } elseif ($statusInput === '0' || $statusInput === 'inactive' || (int) $statusInput === 0) {
                $query->where('status', 0);
            }
        }

        $salesTypes = $query->paginate($this->perPage())->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('master.sales-types.index', compact('salesTypes', 'branches'));
    }

    public function create()
    {
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        return view('master.sales-types.create', compact('branches'));
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
                    'branch_id' => $salesType->branch_id,
                ],
            ]);
        }

        return redirect()->route('master.sales-types.index')->with('status', 'Sales Type created successfully.');
    }

    public function edit(SalesType $salesType)
    {
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        return view('master.sales-types.edit', compact('salesType', 'branches'));
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

    /**
     * API to get branch-specific sales types (for POS and Sales Bill forms)
     */
    public function byBranch(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');

        $query = SalesType::where('status', true);
        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhereNull('branch_id');
            });
        }

        $salesTypes = $query->orderBy('name')->get(['id', 'name', 'code', 'branch_id']);

        return response()->json([
            'success' => true,
            'data' => $salesTypes,
        ]);
    }

    private function validateData(Request $request, ?SalesType $salesType = null): array
    {
        $branchId = $request->input('branch_id') ?: null;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sales_types', 'name')
                    ->where(fn ($q) => $q->where('branch_id', $branchId))
                    ->ignore($salesType?->id),
            ],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
