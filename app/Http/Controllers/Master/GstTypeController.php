<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\GstType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GstTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request)
    {
        $query = GstType::orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $gstTypes = $query->paginate($this->perPage())->withQueryString();

        return view('master.gst-types.index', compact('gstTypes'));
    }

    public function create()
    {
        return view('master.gst-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $gstType = GstType::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'GST Type created successfully.',
                'data' => [
                    'id' => $gstType->id,
                    'name' => $gstType->name,
                ],
            ]);
        }

        return redirect()->route('master.gst-types.index')->with('status', 'GST Type created successfully.');
    }

    public function edit(GstType $gstType)
    {
        return view('master.gst-types.edit', compact('gstType'));
    }

    public function update(Request $request, GstType $gstType)
    {
        $data = $this->validateData($request, $gstType);
        $gstType->update($data);

        return redirect()->route('master.gst-types.index')->with('status', 'GST Type updated successfully.');
    }

    public function destroy(GstType $gstType)
    {
        return redirect()->route('master.gst-types.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?GstType $gstType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('gst_types', 'name')->ignore($gstType?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
