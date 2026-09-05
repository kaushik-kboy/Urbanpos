<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Breed;
use App\Models\PetType;
use Illuminate\Http\Request;

class BreedController extends Controller
{
    public function index()
    {
        $breeds = Breed::with('petType')->orderBy('name')->paginate(20);

        return view('master.breeds.index', compact('breeds'));
    }

    public function create()
    {
        $petTypes = PetType::orderBy('name')->pluck('name', 'id');

        return view('master.breeds.create', compact('petTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Breed::create($data);

        return redirect()->route('master.breeds.index')->with('status', 'Breed created successfully.');
    }

    public function edit(Breed $breed)
    {
        $petTypes = PetType::orderBy('name')->pluck('name', 'id');

        return view('master.breeds.edit', compact('breed', 'petTypes'));
    }

    public function update(Request $request, Breed $breed)
    {
        $data = $this->validateData($request);
        $breed->update($data);

        return redirect()->route('master.breeds.index')->with('status', 'Breed updated successfully.');
    }

    public function destroy(Breed $breed)
    {
        $breed->delete();

        return redirect()->route('master.breeds.index')->with('status', 'Breed deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'pet_type_id' => ['required', 'exists:pet_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
