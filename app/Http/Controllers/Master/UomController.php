<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Uom;
use Illuminate\Http\Request;

class UomController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $uoms = Uom::orderBy('name')->paginate($this->perPage());

        return view('master.uoms.index', compact('uoms'));
    }

    public function create()
    {
        return view('master.uoms.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Uom::create($data);

        return redirect()->route('master.uoms.index')->with('status', 'UOM created successfully.');
    }

    public function edit(Uom $uom)
    {
        return view('master.uoms.edit', compact('uom'));
    }

    public function update(Request $request, Uom $uom)
    {
        $data = $this->validateData($request);
        $uom->update($data);

        return redirect()->route('master.uoms.index')->with('status', 'UOM updated successfully.');
    }

    public function destroy(Uom $uom)
    {
        $uom->delete();

        return redirect()->route('master.uoms.index')->with('status', 'UOM deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:50'],
        ]);
    }

    protected function importModel(): string
    {
        return Uom::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Alias' => ['column' => 'alias'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
