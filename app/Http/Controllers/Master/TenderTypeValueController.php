<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TenderType;
use App\Models\TenderTypeValue;
use Illuminate\Http\Request;

class TenderTypeValueController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $tenderTypeValues = TenderTypeValue::with(['tenderType', 'branch'])->orderBy('name')->paginate($this->perPage());

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

    protected function importModel(): string
    {
        return TenderTypeValue::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
            'Group Ledger' => ['column' => 'group_ledger'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['tender_type_id', 'name'];
    }

    protected function importRelations(): array
    {
        return [
            'Tender Type' => function (string $v) {
                if ($v === '') {
                    return ['__error' => 'Tender Type is required.'];
                }

                return ['tender_type_id' => TenderType::firstOrCreate(['name' => $v], ['status' => true, 'type' => 'Cash', 'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0])->id];
            },

            'Branch' => fn (string $v) => $v === '' ? ['branch_id' => null]
                : ['branch_id' => Branch::firstOrCreate(['name' => $v], ['language' => 'English', 'business_type' => 'BRANCH', 'webstore' => false, 'country_code' => 'IN', 'enable_thirdparty_loyalty' => false, 'gst_type' => 'Un Register', 'gst_filing' => 'Monthly', 'status' => true])->id],
        ];
    }
}
