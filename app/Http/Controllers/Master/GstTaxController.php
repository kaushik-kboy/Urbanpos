<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\GstTax;
use Illuminate\Http\Request;

class GstTaxController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $gstTaxes = GstTax::orderBy('description')->paginate($this->perPage());

        return view('master.gst-taxes.index', compact('gstTaxes'));
    }

    public function create()
    {
        return view('master.gst-taxes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        GstTax::create($data);

        return redirect()->route('master.gst-taxes.index')->with('status', 'GST tax created successfully.');
    }

    public function edit(GstTax $gstTax)
    {
        return view('master.gst-taxes.edit', compact('gstTax'));
    }

    public function update(Request $request, GstTax $gstTax)
    {
        $data = $this->validateData($request);
        $gstTax->update($data);

        return redirect()->route('master.gst-taxes.index')->with('status', 'GST tax updated successfully.');
    }

    public function destroy(GstTax $gstTax)
    {
        $gstTax->delete();

        return redirect()->route('master.gst-taxes.index')->with('status', 'GST tax deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);
    }

    protected function importModel(): string
    {
        return GstTax::class;
    }

    protected function importColumns(): array
    {
        return [
            'Description' => ['column' => 'description', 'required' => true],
            'Percentage' => ['column' => 'percentage', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['description'];
    }
}
