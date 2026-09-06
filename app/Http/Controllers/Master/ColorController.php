<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $colors = Color::orderBy('name')->paginate($this->perPage());

        return view('master.colors.index', compact('colors'));
    }

    public function create()
    {
        return view('master.colors.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Color::create($data);

        return redirect()->route('master.colors.index')->with('status', 'Color created successfully.');
    }

    public function edit(Color $color)
    {
        return view('master.colors.edit', compact('color'));
    }

    public function update(Request $request, Color $color)
    {
        $data = $this->validateData($request);
        $color->update($data);

        return redirect()->route('master.colors.index')->with('status', 'Color updated successfully.');
    }

    public function destroy(Color $color)
    {
        $color->delete();

        return redirect()->route('master.colors.index')->with('status', 'Color deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return Color::class;
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
