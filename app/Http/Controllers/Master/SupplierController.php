<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('master.suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Supplier::create($data);

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier)
    {
        return view('master.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateData($request);
        $supplier->update($data);

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'purchase_type' => ['required', 'in:Local,Interstate,Import'],
            'purchase_mode' => ['required', 'in:Credit,Cash,Consignment'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'credit_balance' => ['required', 'numeric', 'min:0'],
            'credit_days' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
            'gst_type' => ['required', 'in:Regular,Composite,Un Register'],
            'mail_type' => ['required', 'in:None,Inline HTML,CSV,SAP,EDI'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'gst_no' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
