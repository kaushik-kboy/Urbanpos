<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::with('branch')->orderBy('name')->paginate(20);

        return view('master.areas.index', compact('areas'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.areas.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Area::create($data);

        return redirect()->route('master.areas.index')->with('status', 'Area created successfully.');
    }

    public function edit(Area $area)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.areas.edit', compact('area', 'branches'));
    }

    public function update(Request $request, Area $area)
    {
        $data = $this->validateData($request);
        $area->update($data);

        return redirect()->route('master.areas.index')->with('status', 'Area updated successfully.');
    }

    public function destroy(Area $area)
    {
        $area->delete();

        return redirect()->route('master.areas.index')->with('status', 'Area deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }
}
