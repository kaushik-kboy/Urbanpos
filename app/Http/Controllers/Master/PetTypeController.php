<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\PetType;
use Illuminate\Http\Request;

class PetTypeController extends Controller
{
    public function index()
    {
        $petTypes = PetType::orderBy('name')->paginate(20);

        return view('master.pet-types.index', compact('petTypes'));
    }

    public function create()
    {
        return view('master.pet-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        PetType::create($data);

        return redirect()->route('master.pet-types.index')->with('status', 'Pet type created successfully.');
    }

    public function edit(PetType $petType)
    {
        return view('master.pet-types.edit', compact('petType'));
    }

    public function update(Request $request, PetType $petType)
    {
        $data = $this->validateData($request);
        $petType->update($data);

        return redirect()->route('master.pet-types.index')->with('status', 'Pet type updated successfully.');
    }

    public function destroy(PetType $petType)
    {
        $petType->delete();

        return redirect()->route('master.pet-types.index')->with('status', 'Pet type deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
