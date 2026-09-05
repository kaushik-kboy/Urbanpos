<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index()
    {
        $colors = Color::orderBy('name')->paginate(20);

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
}
