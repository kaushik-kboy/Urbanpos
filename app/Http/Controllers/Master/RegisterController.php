<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Register;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $registers = Register::with('branch')->orderBy('name')->paginate($this->perPage());

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

    protected function importModel(): string
    {
        return Register::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Status' => ['column' => 'status'],
            'Product Type' => ['column' => 'product_type'],
            'Inv Seq No' => ['column' => 'inv_seq_no', 'cast' => fn ($v) => (int) $v],
            'Device Id' => ['column' => 'device_id'],
            'Online Sales Allowed' => ['column' => 'online_sales_allowed', 'cast' => fn ($v) => $this->importBool($v)],
            'Register Prefix' => ['column' => 'register_prefix'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['branch_id', 'name'];
    }

    protected function importRelations(): array
    {
        return [
            'Branch' => function (string $v) {
                if ($v === '') {
                    return ['__error' => 'Branch is required.'];
                }

                return ['branch_id' => Branch::firstOrCreate(['name' => $v], ['language' => 'English', 'business_type' => 'BRANCH', 'webstore' => false, 'country_code' => 'IN', 'enable_thirdparty_loyalty' => false, 'gst_type' => 'Un Register', 'gst_filing' => 'Monthly', 'status' => true])->id];
            },
        ];
    }
}
