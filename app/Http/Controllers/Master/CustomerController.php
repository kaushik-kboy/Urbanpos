<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\PetType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use HasPerPage, Importable;


    public function index(Request $request)
    {
        $query = Customer::with('category');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool)$request->status);
        }

        $customers = $query->orderBy('name')->paginate($this->perPage())->withQueryString();
        $categories = CustomerCategory::orderBy('name')->pluck('name', 'id');

        return view('master.customers.index', compact('customers', 'categories'));
    }

    public function create()
    {
        return view('master.customers.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $customer = Customer::create($data);
        $this->syncPets($request, $customer);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                    'text' => $customer->displayName,
                ],
                'message' => 'Customer created successfully.',
            ]);
        }

        return redirect()->route('master.customers.index')->with('status', 'Customer created successfully.');
    }

    public function edit(Customer $customer)
    {
        $customer->load('pets');

        return view('master.customers.edit', array_merge(['customer' => $customer], $this->formOptions()));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validateData($request, $customer);
        $this->assertCreditFieldsUnchangedUnlessOwner($request, $customer, $data);
        $customer->update($data);
        $this->syncPets($request, $customer);

        return redirect()->route('master.customers.index')->with('status', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        return redirect()->route('master.customers.index')->with('error', 'Master records cannot be deleted. You can set status to Inactive instead.');
    }

    /**
     * Credit terms are routine to SET when onboarding a new customer (store() is
     * untouched), but CHANGING them on an existing one is credit-exposure-sensitive —
     * same tier as item-price-change. Rather than a new permission/form split, this
     * blocks the change inline: a Manager submitting the form unchanged (the normal
     * case when editing unrelated fields) passes silently; actually altering a credit
     * field requires Owner.
     */
    private function assertCreditFieldsUnchangedUnlessOwner(Request $request, Customer $customer, array $data): void
    {
        if ($request->user()->hasRole('Owner')) {
            return;
        }

        $numericFields = ['credit_limit', 'credit_balance', 'monthly_credit_balance'];
        foreach ($numericFields as $field) {
            if (abs((float) $data[$field] - (float) $customer->$field) > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Only an Owner can change credit terms.',
                ]);
            }
        }

        if ((int) $data['credit_days'] !== (int) $customer->credit_days) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credit_days' => 'Only an Owner can change credit terms.',
            ]);
        }
    }

    private function syncPets(Request $request, Customer $customer): void
    {
        foreach ($request->input('pets', []) as $pet) {
            if (! empty($pet['_delete'])) {
                if (! empty($pet['id'])) {
                    $customer->pets()->where('id', $pet['id'])->delete();
                }

                continue;
            }

            if (empty($pet['pet_type_id']) && empty($pet['breed_id']) && empty($pet['name'])) {
                continue;
            }

            $attributes = [
                'pet_type_id' => $pet['pet_type_id'] ?: null,
                'breed_id' => $pet['breed_id'] ?: null,
                'color_id' => $pet['color_id'] ?: null,
                'name' => $pet['name'] ?? null,
                'gender' => $pet['gender'] ?: null,
                'age' => $pet['age'] ?? null,
                'remarks' => $pet['remarks'] ?? null,
                'birth_date' => $pet['birth_date'] ?: null,
            ];

            if (! empty($pet['id'])) {
                $customer->pets()->where('id', $pet['id'])->update($attributes);
            } else {
                $customer->pets()->create($attributes);
            }
        }
    }

    private function formOptions(): array
    {
        return [
            'customerCategories' => CustomerCategory::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'areas' => Area::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'petTypes' => PetType::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'breeds' => Breed::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'colors' => Color::where('status', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            // General
            'title' => ['nullable', 'in:Mr,Ms,Mrs,M/s,Dr'],
            'name' => ['required', 'string', 'max:255'],
            'customer_category_id' => ['nullable', 'exists:customer_categories,id'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'payment_mode' => ['required', 'in:Cash Only,No Credit,Credit Only,Both Cash and Credit,Cash on Delivery'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'credit_balance' => ['required', 'numeric', 'min:0'],
            'monthly_credit_balance' => ['required', 'numeric', 'min:0'],
            'credit_days' => ['required', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['required', 'boolean'],
            'sales_formula' => ['nullable', 'string', 'max:255'],
            'gst_type' => ['required', 'in:Regular,Composite,Un Register'],
            'sms_consent' => ['required', 'boolean'],

            // Contact Details
            'address1' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'std_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'gst_no' => ['nullable', 'string', 'size:15', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[A-Z\d]{1}Z[A-Z\d]{1}$/'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'mobile' => ['required', 'string', 'digits:10', \Illuminate\Validation\Rule::unique('customers', 'mobile')->ignore($customer?->id)],

            // Others
            'gender' => ['nullable', 'in:Male,Female'],
            'exempted_reason' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', 'in:RETAIL INVOICE,TAX INVOICE,EXEMPTED,E-COMMERCE'],
        ], [
            'mobile.required' => 'Customer mobile number is required.',
            'mobile.digits' => 'Customer mobile number must be exactly 10 digits.',
            'mobile.unique' => 'A customer with this mobile number already exists.',
        ]);
    }

    protected function importModel(): string
    {
        return Customer::class;
    }

    protected function importColumns(): array
    {
        return [
            'Title' => ['column' => 'title'],
            'Name' => ['column' => 'name', 'required' => true],
            'Customer Code' => ['column' => 'customer_code', 'required' => true],
            'Sales Type' => ['column' => 'sales_type'],
            'Payment Mode' => ['column' => 'payment_mode'],
            'Credit Limit' => ['column' => 'credit_limit', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Credit Balance' => ['column' => 'credit_balance', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Monthly Credit Balance' => ['column' => 'monthly_credit_balance', 'cast' => fn ($v) => $this->importDecimal($v)],
            'Credit Days' => ['column' => 'credit_days', 'cast' => fn ($v) => (int) $v],
            'Status' => ['column' => 'status', 'cast' => fn ($v) => $this->importBool($v)],
            'Sales Formula' => ['column' => 'sales_formula'],
            'GST Type' => ['column' => 'gst_type'],
            'SMS Consent' => ['column' => 'sms_consent', 'cast' => fn ($v) => $this->importBool($v)],
            'Address1' => ['column' => 'address1'],
            'City' => ['column' => 'city'],
            'State' => ['column' => 'state'],
            'Country' => ['column' => 'country'],
            'Postal Code' => ['column' => 'postal_code'],
            'Std Code' => ['column' => 'std_code'],
            'Phone' => ['column' => 'phone'],
            'Email' => ['column' => 'email'],
            'Remarks' => ['column' => 'remarks'],
            'GST No' => ['column' => 'gst_no'],
            'Aadhar No' => ['column' => 'aadhar_no'],
            'PAN No' => ['column' => 'pan_no'],
            'Mobile' => ['column' => 'mobile'],
            'Gender' => ['column' => 'gender'],
            'Exempted Reason' => ['column' => 'exempted_reason'],
            'Customer Type' => ['column' => 'customer_type'],
        ];
    }

    protected function importUniqueBy(): array
    {
        return ['customer_code'];
    }

    protected function importRelations(): array
    {
        return [
            'Customer Category' => fn (string $v) => $v === '' ? ['customer_category_id' => null]
                : ['customer_category_id' => CustomerCategory::firstOrCreate(['name' => $v], [
                    'app_access' => false, 'enable_loyalty' => false, 'discount_percent' => 0,
                    'business_type' => 'ALL', 'status' => true,
                ])->id],

            'Branch' => fn (string $v) => $v === '' ? ['branch_id' => null]
                : ['branch_id' => Branch::firstOrCreate(['name' => $v], ['language' => 'English', 'business_type' => 'BRANCH', 'webstore' => false, 'country_code' => 'IN', 'enable_thirdparty_loyalty' => false, 'gst_type' => 'Un Register', 'gst_filing' => 'Monthly', 'status' => true])->id],

            'Area' => fn (string $v) => $v === '' ? ['area_id' => null]
                : ['area_id' => Area::firstOrCreate(['name' => $v])->id],
        ];
    }
}
