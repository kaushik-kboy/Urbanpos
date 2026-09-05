<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\PetType;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with('category')->orderBy('name')->paginate(20);

        return view('master.customers.index', compact('customers'));
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

        return redirect()->route('master.customers.index')->with('status', 'Customer created successfully.');
    }

    public function edit(Customer $customer)
    {
        $customer->load('pets');

        return view('master.customers.edit', array_merge(['customer' => $customer], $this->formOptions()));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validateData($request);
        $customer->update($data);
        $this->syncPets($request, $customer);

        return redirect()->route('master.customers.index')->with('status', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('master.customers.index')->with('status', 'Customer deleted.');
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
            'customerCategories' => CustomerCategory::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'areas' => Area::orderBy('name')->pluck('name', 'id'),
            'petTypes' => PetType::orderBy('name')->pluck('name', 'id'),
            'breeds' => Breed::orderBy('name')->pluck('name', 'id'),
            'colors' => Color::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request): array
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
            'gst_no' => ['nullable', 'string', 'max:20'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:50'],

            // Others
            'gender' => ['nullable', 'in:Male,Female'],
            'exempted_reason' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', 'in:RETAIL INVOICE,TAX INVOICE,EXEMPTED,E-COMMERCE'],
        ]);
    }
}
