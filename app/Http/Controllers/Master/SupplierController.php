<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use HasPerPage, Importable;


    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate($this->perPage());

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('master.suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $supplier = Supplier::create($data);
        $this->syncContacts($request, $supplier);

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('contacts');

        return view('master.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateData($request);
        $supplier->update($data);
        $this->syncContacts($request, $supplier);

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier updated successfully.');
    }

    private function syncContacts(Request $request, Supplier $supplier): void
    {
        foreach ($request->input('contacts', []) as $contact) {
            if (! empty($contact['_delete'])) {
                if (! empty($contact['id'])) {
                    $supplier->contacts()->where('id', $contact['id'])->delete();
                }

                continue;
            }

            if (empty($contact['contact_person']) && empty($contact['mobile']) && empty($contact['phone']) && empty($contact['email'])) {
                continue;
            }

            $attributes = [
                'contact_person' => $contact['contact_person'] ?? null,
                'designation' => $contact['designation'] ?? null,
                'mobile' => $contact['mobile'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
            ];

            if (! empty($contact['id'])) {
                $supplier->contacts()->where('id', $contact['id'])->update($attributes);
            } else {
                $supplier->contacts()->create($attributes);
            }
        }
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

    protected function importModel(): string
    {
        return Supplier::class;
    }

    protected function importColumns(): array
    {
        return [
            'Name' => ['column' => 'name', 'required' => true],
            'Currency' => ['column' => 'currency'],
            'Purchase Type' => ['column' => 'purchase_type'],
            'Purchase Mode' => ['column' => 'purchase_mode'],
            'Credit Limit' => ['column' => 'credit_limit', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Credit Balance' => ['column' => 'credit_balance', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Credit Days' => ['column' => 'credit_days', 'cast' => fn ($v) => (int) $v],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
            'GST Type' => ['column' => 'gst_type'],
            'Mail Type' => ['column' => 'mail_type'],
            'Address' => ['column' => 'address'],
            'City' => ['column' => 'city'],
            'Postal Code' => ['column' => 'postal_code'],
            'State' => ['column' => 'state'],
            'Country' => ['column' => 'country'],
            'Phone' => ['column' => 'phone'],
            'Email' => ['column' => 'email'],
            'Mobile' => ['column' => 'mobile'],
            'Aadhar No' => ['column' => 'aadhar_no'],
            'PAN No' => ['column' => 'pan_no'],
            'GST No' => ['column' => 'gst_no'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['name'];
    }
}
