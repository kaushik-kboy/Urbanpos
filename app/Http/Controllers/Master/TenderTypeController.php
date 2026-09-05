<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TenderType;
use Illuminate\Http\Request;

class TenderTypeController extends Controller
{
    public function index()
    {
        $tenderTypes = TenderType::with('branch')->orderBy('name')->paginate(20);

        return view('master.tender-types.index', compact('tenderTypes'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.tender-types.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        TenderType::create($data);

        return redirect()->route('master.tender-types.index')->with('status', 'Tender type created successfully.');
    }

    public function edit(TenderType $tenderType)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.tender-types.edit', compact('tenderType', 'branches'));
    }

    public function update(Request $request, TenderType $tenderType)
    {
        $data = $this->validateData($request);
        $tenderType->update($data);

        return redirect()->route('master.tender-types.index')->with('status', 'Tender type updated successfully.');
    }

    public function destroy(TenderType $tenderType)
    {
        $tenderType->delete();

        return redirect()->route('master.tender-types.index')->with('status', 'Tender type deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'type' => ['required', 'in:Cash,Card,Coupon,Wallet,Credit,Finance'],
            'mode' => ['required', 'string', 'max:255'],
            'service_applicable' => ['required', 'boolean'],
            'mandate_refno' => ['required', 'boolean'],
            'service_charge_perc' => ['required', 'numeric', 'min:0', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }
}
