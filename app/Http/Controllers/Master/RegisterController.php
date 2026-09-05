<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Register;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function index()
    {
        $registers = Register::with('branch')->orderBy('name')->paginate(20);

        return view('master.registers.index', compact('registers'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.registers.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Register::create($data);

        return redirect()->route('master.registers.index')->with('status', 'Register created successfully.');
    }

    public function edit(Register $register)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('master.registers.edit', compact('register', 'branches'));
    }

    public function update(Request $request, Register $register)
    {
        $data = $this->validateData($request);
        $register->update($data);

        return redirect()->route('master.registers.index')->with('status', 'Register updated successfully.');
    }

    public function destroy(Register $register)
    {
        $register->delete();

        return redirect()->route('master.registers.index')->with('status', 'Register deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive,Yet to Active'],
            'product_type' => ['required', 'string', 'max:255'],
            'inv_seq_no' => ['required', 'integer', 'min:1'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'online_sales_allowed' => ['required', 'boolean'],
            'register_prefix' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
