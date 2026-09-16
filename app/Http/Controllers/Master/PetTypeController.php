<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\PetType;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

class PetTypeController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $petTypes = PetType::orderBy('name')->paginate($this->perPage());

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
        $data = $this->validateData($request, $petType);
        $petType->update($data);

        return redirect()->route('master.pet-types.index')->with('status', 'Pet type updated successfully.');
    }

    public function destroy(PetType $petType)
    {
        return redirect()->route('master.pet-types.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?PetType $petType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('pet_types', 'name')->ignore($petType?->id)],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return PetType::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
