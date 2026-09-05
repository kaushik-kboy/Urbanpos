<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TenderType;
use App\Models\TenderTypeValue;
use Illuminate\Http\Request;

class TenderTypeValueController extends Controller
{
    public function index()
    {
        $tenderTypeValues = TenderTypeValue::with(['tenderType', 'branch'])->orderBy('name')->paginate(20);

        return view('master.tender-type-values.index', compact('tenderTypeValues'));
    }

    public function create()
    {
        $tenderTypes = TenderType::orderBy('name')->pluck('name', 'id');
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.tender-type-values.create', compact('tenderTypes', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        TenderTypeValue::create($data);

        return redirect()->route('master.tender-type-values.index')->with('status', 'Tender type value created successfully.');
    }

    public function edit(TenderTypeValue $tenderTypeValue)
    {
        $tenderTypes = TenderType::orderBy('name')->pluck('name', 'id');
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.tender-type-values.edit', compact('tenderTypeValue', 'tenderTypes', 'branches'));
    }

    public function update(Request $request, TenderTypeValue $tenderTypeValue)
    {
        $data = $this->validateData($request);
        $tenderTypeValue->update($data);

        return redirect()->route('master.tender-type-values.index')->with('status', 'Tender type value updated successfully.');
    }

    public function destroy(TenderTypeValue $tenderTypeValue)
    {
        $tenderTypeValue->delete();

        return redirect()->route('master.tender-type-values.index')->with('status', 'Tender type value deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'tender_type_id' => ['required', 'exists:tender_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'group_ledger' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }
}
