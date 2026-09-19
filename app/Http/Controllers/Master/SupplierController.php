<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    use HasPerPage, Importable;


    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('gst_no', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('purchase_type')) {
            $query->where('purchase_type', $request->purchase_type);
        }

        if ($request->filled('purchase_mode')) {
            $query->where('purchase_mode', $request->purchase_mode);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool)$request->status);
        }

        $suppliers = $query->orderBy('name')->paginate($this->perPage())->withQueryString();
        $purchaseTypes = Supplier::select('purchase_type')->distinct()->whereNotNull('purchase_type')->pluck('purchase_type');
        $purchaseModes = Supplier::select('purchase_mode')->distinct()->whereNotNull('purchase_mode')->pluck('purchase_mode');

        return view('master.suppliers.index', compact('suppliers', 'purchaseTypes', 'purchaseModes'));
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully.',
                'data' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
            ]);
        }

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('contacts');

        return view('master.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateData($request, $supplier);
        $this->assertCreditFieldsUnchangedUnlessOwner($request, $supplier, $data);
        $supplier->update($data);
        $this->syncContacts($request, $supplier);

        return redirect()->route('master.suppliers.index')->with('status', 'Supplier updated successfully.');
    }

    /**
     * Same guard as CustomerController::assertCreditFieldsUnchangedUnlessOwner() — credit
     * terms are routine to SET at onboarding (store() untouched) but CHANGING them later
     * is Owner-only, without needing a new permission or a form split.
     */
    private function assertCreditFieldsUnchangedUnlessOwner(Request $request, Supplier $supplier, array $data): void
    {
        if ($request->user()->hasRole('Owner')) {
            return;
        }

        foreach (['credit_limit', 'credit_balance'] as $field) {
            if (abs((float) $data[$field] - (float) $supplier->$field) > 0.01) {
                throw ValidationException::withMessages([
                    $field => 'Only an Owner can change credit terms.',
                ]);
            }
        }

        if ((int) $data['credit_days'] !== (int) $supplier->credit_days) {
            throw ValidationException::withMessages([
                'credit_days' => 'Only an Owner can change credit terms.',
            ]);
        }
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
        return redirect()->route('master.suppliers.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    private function validateData(Request $request, ?Supplier $supplier = null): array
    {
        $request->merge([
            'currency' => $request->input('currency') ?: 'INR',
            'purchase_type' => $request->input('purchase_type') ?: 'Local',
            'purchase_mode' => $request->input('purchase_mode') ?: 'Credit',
            'credit_limit' => ($request->input('credit_limit') !== null && $request->input('credit_limit') !== '') ? $request->input('credit_limit') : 0,
            'credit_balance' => ($request->input('credit_balance') !== null && $request->input('credit_balance') !== '') ? $request->input('credit_balance') : 0,
            'credit_days' => ($request->input('credit_days') !== null && $request->input('credit_days') !== '') ? $request->input('credit_days') : 0,
            'status' => ($request->input('status') !== null && $request->input('status') !== '') ? $request->input('status') : 1,
            'gst_type' => $request->input('gst_type') ?: 'Regular',
            'mail_type' => $request->input('mail_type') ?: 'None',
        ]);

        foreach (['mobile', 'phone', 'email', 'gst_no', 'address', 'city', 'state', 'postal_code', 'country', 'aadhar_no', 'pan_no'] as $optField) {
            if ($request->input($optField) === '') {
                $request->merge([$optField => null]);
            }
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
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
            'mobile' => ['nullable', 'string', 'digits:10'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'gst_no' => ['nullable', 'string', 'size:15', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[A-Z\d]{1}Z[A-Z\d]{1}$/'],
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
