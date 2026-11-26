<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Concerns\HasSorting;
use App\Http\Controllers\Concerns\Importable;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerType;
use App\Models\SalesType;
use App\Models\PetType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use HasPerPage, HasSorting, Importable;


    public function index(Request $request)
    {
        $statsData = $this->getPetStatsAndFilterIds();
        $stats = $statsData['counts'];
        $filterIds = $statsData['customer_ids'];

        $query = Customer::with(['category', 'pets']);

        $filter = $request->input('filter');
        if ($filter === 'today_birthdays') {
            $query->whereIn('id', !empty($filterIds['today_birthdays']) ? $filterIds['today_birthdays'] : [0]);
        } elseif ($filter === 'month_birthdays') {
            $query->whereIn('id', !empty($filterIds['month_birthdays']) ? $filterIds['month_birthdays'] : [0]);
        } elseif ($filter === 'has_pets' || $filter === 'total_pets') {
            $query->has('pets');
        } elseif ($filter === 'baby') {
            $query->whereIn('id', !empty($filterIds['baby']) ? $filterIds['baby'] : [0]);
        } elseif ($filter === 'puppy') {
            $query->whereIn('id', !empty($filterIds['puppy']) ? $filterIds['puppy'] : [0]);
        } elseif ($filter === 'adult') {
            $query->whereIn('id', !empty($filterIds['adult']) ? $filterIds['adult'] : [0]);
        } elseif ($filter === 'senior') {
            $query->whereIn('id', !empty($filterIds['senior']) ? $filterIds['senior'] : [0]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $catId = $request->input('category_id');
        if ($catId !== null && $catId !== '' && strtolower((string) $catId) !== 'all') {
            $query->where('customer_category_id', $catId);
        }

        $statusInput = $request->input('status');
        if ($statusInput !== null && $statusInput !== '' && strtolower((string) $statusInput) !== 'all') {
            if ($statusInput === '1' || $statusInput === 'active' || (int) $statusInput === 1) {
                $query->where('status', 1);
            } elseif ($statusInput === '0' || $statusInput === 'inactive' || (int) $statusInput === 0) {
                $query->where('status', 0);
            }
        }

        $allowedSorts = [
            'id' => 'id',
            'name' => 'name',
            'customer_code' => 'customer_code',
            'category' => fn ($q, $dir) => $q->orderBy(CustomerCategory::select('name')->whereColumn('customer_categories.id', 'customers.customer_category_id'), $dir),
            'status' => 'status',
            'mobile' => 'mobile',
            'created_at' => 'created_at',
        ];
        $this->applySorting($query, $allowedSorts, ['name' => 'asc']);

        $customers = $query->paginate($this->perPage())->withQueryString();
        $categories = CustomerCategory::orderBy('name')->pluck('name', 'id');

        return view('master.customers.index', compact('customers', 'categories', 'stats', 'filter'));
    }

    private function getPetStatsAndFilterIds(): array
    {
        $todayDay = (int) date('d');
        $todayMonth = (int) date('m');
        $todayYear = (int) date('Y');

        $todaysBirthdaysCustIds = [];
        $monthBirthdaysCustIds = [];
        $babyCustIds = [];
        $puppyCustIds = [];
        $adultCustIds = [];
        $seniorCustIds = [];

        $totalPets = 0;
        $todayBirthdaysCount = 0;
        $monthBirthdaysCount = 0;
        $babyCount = 0;
        $puppyCount = 0;
        $adultCount = 0;
        $seniorCount = 0;

        $pets = \App\Models\CustomerPet::select('id', 'customer_id', 'birth_date', 'age')->get();
        $totalPets = $pets->count();

        foreach ($pets as $pet) {
            $raw = trim((string) ($pet->birth_date ?? ''));
            $day = null;
            $month = null;
            $year = null;

            if ($raw !== '') {
                if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m)) {
                    $day = (int) $m[1];
                    $month = (int) $m[2];
                    $year = (int) $m[3];
                } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw, $m)) {
                    $year = (int) $m[1];
                    $month = (int) $m[2];
                    $day = (int) $m[3];
                } elseif (preg_match('/^(\d{1,2})-(\d{4})$/', $raw, $m)) {
                    $month = (int) $m[1];
                    $year = (int) $m[2];
                }
            }

            // Fallback: parse age string if available (e.g. "2 yrs", "5 months")
            if (! $month && ! empty($pet->age)) {
                $ageStr = strtolower(trim($pet->age));
                if (preg_match('/(\d+)\s*(yr|year)/', $ageStr, $am)) {
                    $ageYears = (int) $am[1];
                    $year = $todayYear - $ageYears;
                    $month = $todayMonth;
                } elseif (preg_match('/(\d+)\s*(m|month)/', $ageStr, $am)) {
                    $ageMonths = (int) $am[1];
                    $totMonths = ($todayYear * 12 + $todayMonth) - $ageMonths;
                    $year = (int) floor($totMonths / 12);
                    $month = ($totMonths % 12) ?: 12;
                }
            }

            if ($month) {
                if ($month === $todayMonth) {
                    $monthBirthdaysCount++;
                    $monthBirthdaysCustIds[] = $pet->customer_id;
                    if ($day && $day === $todayDay) {
                        $todayBirthdaysCount++;
                        $todaysBirthdaysCustIds[] = $pet->customer_id;
                    }
                }

                if ($year) {
                    $ageInMonths = ($todayYear - $year) * 12 + ($todayMonth - $month);
                    if ($day && $todayDay < $day) {
                        $ageInMonths--;
                    }
                    if ($ageInMonths < 0) {
                        $ageInMonths = 0;
                    }

                    if ($ageInMonths < 3) {
                        $babyCount++;
                        $babyCustIds[] = $pet->customer_id;
                    } elseif ($ageInMonths < 15) {
                        $puppyCount++;
                        $puppyCustIds[] = $pet->customer_id;
                    } elseif ($ageInMonths <= 84) {
                        $adultCount++;
                        $adultCustIds[] = $pet->customer_id;
                    } else {
                        $seniorCount++;
                        $seniorCustIds[] = $pet->customer_id;
                    }
                }
            }
        }

        $totalCustomers = Customer::count();

        return [
            'counts' => [
                'total_customers' => $totalCustomers,
                'today_birthdays' => $todayBirthdaysCount,
                'month_birthdays' => $monthBirthdaysCount,
                'total_pets' => $totalPets,
                'baby' => $babyCount,
                'puppy' => $puppyCount,
                'adult' => $adultCount,
                'senior' => $seniorCount,
            ],
            'customer_ids' => [
                'today_birthdays' => array_values(array_unique($todaysBirthdaysCustIds)),
                'month_birthdays' => array_values(array_unique($monthBirthdaysCustIds)),
                'baby' => array_values(array_unique($babyCustIds)),
                'puppy' => array_values(array_unique($puppyCustIds)),
                'adult' => array_values(array_unique($adultCustIds)),
                'senior' => array_values(array_unique($seniorCustIds)),
            ],
        ];
    }

    public function create()
    {
        return view('master.customers.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $user = $request->user();
        if ($user && $user->branch_id !== null && ! $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']) && $user->email !== 'admin@urbanpos.com') {
            $data['branch_id'] = $user->branch_id;
        }
        $customer = Customer::create($data);
        $this->syncPets($request, $customer);
        $this->syncAddresses($request, $customer);
        if ($request->has('custom_fields')) {
            $customer->syncCustomFields($request->input('custom_fields', []));
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                    'customer_type' => $customer->customer_type,
                    'sales_type' => $customer->sales_type,
                    'text' => $customer->displayName,
                ],
                'message' => 'Customer created successfully.',
            ]);
        }

        return redirect()->route('master.customers.index')->with('status', 'Customer created successfully.');
    }

    public function show(Request $request, Customer $customer)
    {
        $customer->load(['pets', 'addresses.area']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'pets' => $customer->pets,
                'addresses' => $customer->addresses,
            ]);
        }

        // No dedicated HTML show view exists; the edit form is the customer detail page.
        return redirect()->route('master.customers.edit', $customer);
    }

    public function edit(Request $request, Customer $customer)
    {
        $customer->load(['pets', 'addresses.area']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'pets' => $customer->pets,
                'addresses' => $customer->addresses,
            ]);
        }

        return view('master.customers.edit', array_merge(['customer' => $customer], $this->formOptions($customer)));
    }

    public function update(Request $request, Customer $customer)
    {
        $request->mergeIfMissing([
            'status' => $customer->status ?? true,
            'gst_type' => $customer->gst_type ?? 'Unregistered',
            'sms_consent' => $customer->sms_consent ?? false,
            'payment_mode' => $customer->payment_mode ?? 'Cash Only',
            'sales_type' => $customer->sales_type ?? 'Local',
            'customer_type' => $customer->customer_type ?? (\App\Models\CustomerType::where('status', true)->value('name') ?? 'Regular'),
            'credit_limit' => $customer->credit_limit ?? 0,
            'credit_balance' => $customer->credit_balance ?? 0,
            'monthly_credit_balance' => $customer->monthly_credit_balance ?? 0,
            'credit_days' => $customer->credit_days ?? 0,
        ]);

        $data = $this->validateData($request, $customer);
        $user = $request->user();
        if ($user && $user->branch_id !== null && ! $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']) && $user->email !== 'admin@urbanpos.com') {
            $data['branch_id'] = $user->branch_id;
        }
        $this->assertCreditFieldsUnchangedUnlessOwner($request, $customer, $data);
        $customer->update($data);
        $this->syncPets($request, $customer);
        $this->syncAddresses($request, $customer);
        if ($request->has('custom_fields')) {
            $customer->syncCustomFields($request->input('custom_fields', []));
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                    'text' => $customer->mobile ? "{$customer->name} ({$customer->mobile})" : $customer->name,
                    'address1' => $customer->address1,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'google_map_link' => $customer->google_map_link,
                    'addresses' => $customer->addresses()->orderByDesc('is_primary')->orderBy('id')->get(),
                ],
                'message' => 'Customer updated successfully.',
            ]);
        }

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
    private function assertCreditFieldsUnchangedUnlessOwner(Request $request, Customer $customer, array &$data): void
    {
        $user = $request->user() ?: auth()->user();
        $isOwnerOrAdmin = $user && (
            $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']) ||
            $user->email === 'admin@urbanpos.com'
        );

        if ($isOwnerOrAdmin) {
            return;
        }

        // For non-owner roles (Manager, Cashier, Sales Executive), silently preserve existing credit terms
        // so routine updates to customer details (name, phone, address, GST, pets) never fail.
        $numericFields = ['credit_limit', 'credit_balance', 'monthly_credit_balance'];
        foreach ($numericFields as $field) {
            $data[$field] = $customer->$field ?? 0;
        }

        $data['credit_days'] = $customer->credit_days ?? 0;
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

            // Ensure pet is not dropped if any detail is provided or if it's an existing pet
            $hasMeaningfulData = !empty($pet['id'])
                || !empty(trim($pet['name'] ?? ''))
                || !empty($pet['pet_type_id'])
                || !empty($pet['breed_id'])
                || !empty($pet['color_id'])
                || !empty(trim($pet['age'] ?? ''))
                || !empty($pet['birth_date'])
                || !empty(trim($pet['remarks'] ?? ''));

            if (!$hasMeaningfulData) {
                continue;
            }

            $gender = !empty($pet['gender']) && in_array($pet['gender'], ['Male', 'Female']) ? $pet['gender'] : 'Male';

            $attributes = [
                'pet_type_id' => !empty($pet['pet_type_id']) ? $pet['pet_type_id'] : null,
                'breed_id' => !empty($pet['breed_id']) ? $pet['breed_id'] : null,
                'color_id' => !empty($pet['color_id']) ? $pet['color_id'] : null,
                'name' => isset($pet['name']) && trim($pet['name']) !== '' ? trim($pet['name']) : null,
                'gender' => $gender,
                'age' => isset($pet['age']) && trim($pet['age']) !== '' ? trim($pet['age']) : null,
                'remarks' => isset($pet['remarks']) && trim($pet['remarks']) !== '' ? trim($pet['remarks']) : null,
                'birth_date' => !empty($pet['birth_date']) ? $pet['birth_date'] : null,
            ];

            if (! empty($pet['id'])) {
                $customer->pets()->where('id', $pet['id'])->update($attributes);
            } else {
                $customer->pets()->create($attributes);
            }
        }
    }

    private function syncAddresses(Request $request, Customer $customer): void
    {
        // 1. If request comes from Quick Customer Modal (POS terminal) which submits address1 directly (no addresses array)
        if (! $request->has('addresses') && $request->has('address1')) {
            $addrLine = trim((string) $request->input('address1', ''));
            $mapLink = trim((string) ($request->input('google_map_link') ?? '')) ?: null;
            $areaId = $request->input('area_id') ?: null;
            $city = trim((string) ($request->input('city') ?? '')) ?: 'Ahmedabad';
            $state = trim((string) ($request->input('state') ?? '')) ?: 'Gujarat';
            $postalCode = trim((string) ($request->input('postal_code') ?? '')) ?: null;

            if ($addrLine === '') {
                // User intentionally cleared/emptied the address in POS modal
                $customer->addresses()->delete();
                $customer->updateQuietly([
                    'address1' => null,
                    'area_id' => null,
                    'postal_code' => null,
                    'google_map_link' => null,
                ]);
                return;
            } else {
                // User entered or edited the address in POS modal
                $primary = $customer->addresses()->where('is_primary', true)->first();
                if ($primary) {
                    $primary->update([
                        'address_line' => $addrLine,
                        'area_id' => $areaId,
                        'city' => $city,
                        'state' => $state,
                        'postal_code' => $postalCode,
                        'google_map_link' => $mapLink ?: $primary->google_map_link,
                    ]);
                } else {
                    $customer->addresses()->create([
                        'address_type' => 'Home',
                        'address_line' => $addrLine,
                        'area_id' => $areaId,
                        'city' => $city,
                        'state' => $state,
                        'country' => 'India',
                        'postal_code' => $postalCode,
                        'google_map_link' => $mapLink,
                        'is_primary' => true,
                    ]);
                }
                $customer->updateQuietly([
                    'address1' => $addrLine,
                    'area_id' => $areaId,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $postalCode,
                    'google_map_link' => $mapLink,
                ]);
                return;
            }
        }

        $addresses = $request->input('addresses', []);
        if (! is_array($addresses)) {
            $addresses = [];
        }

        $existingIds = [];
        $hasPrimary = false;

        foreach ($addresses as $index => $addr) {
            if (! empty($addr['_delete'])) {
                if (! empty($addr['id'])) {
                    $customer->addresses()->where('id', $addr['id'])->delete();
                }
                continue;
            }

            $addrLine = trim($addr['address_line'] ?? '');
            $areaId = !empty($addr['area_id']) ? $addr['area_id'] : null;
            $areaName = trim($addr['area_name'] ?? '');

            if ($addrLine === '' && empty($areaId) && $areaName === '') {
                continue;
            }

            $isPrimary = !empty($addr['is_primary']) && ((string)$addr['is_primary'] === '1' || (string)$addr['is_primary'] === 'true');
            if ($isPrimary) {
                $hasPrimary = true;
            }

            $attributes = [
                'address_type' => $addr['address_type'] ?? 'Home',
                'address_line' => $addrLine,
                'area_id' => $areaId,
                'area_name' => $areaName,
                'city' => trim($addr['city'] ?? '') ?: 'Ahmedabad',
                'state' => trim($addr['state'] ?? '') ?: 'Gujarat',
                'country' => trim($addr['country'] ?? '') ?: 'India',
                'postal_code' => trim($addr['postal_code'] ?? '') ?: null,
                'landmark' => trim($addr['landmark'] ?? '') ?: null,
                'google_map_link' => trim($addr['google_map_link'] ?? '') ?: null,
                'is_primary' => $isPrimary,
            ];

            if (! empty($addr['id'])) {
                $customer->addresses()->where('id', $addr['id'])->update($attributes);
                $existingIds[] = $addr['id'];
            } else {
                $created = $customer->addresses()->create($attributes);
                $existingIds[] = $created->id;
            }
        }

        if (! empty($addresses)) {
            $customer->addresses()->whereNotIn('id', $existingIds)->delete();
        }

        // Guarantee at least one address is marked primary if any exist
        if (! $hasPrimary && count($existingIds) > 0) {
            $customer->addresses()->where('id', $existingIds[0])->update(['is_primary' => true]);
        }

        // Legacy fallback: if no addresses array provided, but address1 exists, create initial address
        if (empty($addresses) && ! empty($customer->address1)) {
            if ($customer->addresses()->count() === 0) {
                $customer->addresses()->create([
                    'address_type' => 'Home',
                    'address_line' => $customer->address1,
                    'area_id' => $customer->area_id,
                    'city' => $customer->city ?: 'Ahmedabad',
                    'state' => $customer->state ?: 'Gujarat',
                    'country' => $customer->country ?: 'India',
                    'postal_code' => $customer->postal_code,
                    'google_map_link' => $customer->google_map_link,
                    'is_primary' => true,
                ]);
            }
        }

        // Mirror primary address to customers table columns for backwards compatibility
        $primary = $customer->addresses()->where('is_primary', true)->first();
        if ($primary) {
            $customer->updateQuietly([
                'address1' => $primary->address_line,
                'area_id' => $primary->area_id,
                'city' => $primary->city,
                'state' => $primary->state,
                'postal_code' => $primary->postal_code,
                'google_map_link' => $primary->google_map_link ?: $customer->google_map_link,
            ]);
        }
    }

    private function formOptions(?Customer $customer = null): array
    {
        $customerTypesQuery = CustomerType::where('status', true);
        if ($customer?->customer_type) {
            $customerTypesQuery->orWhere('name', $customer->customer_type);
        }
        $customerTypes = $customerTypesQuery->orderBy('name')->pluck('name', 'name');
        if ($customerTypes->isEmpty()) {
            $customerTypes = collect([
                'RETAIL INVOICE' => 'RETAIL INVOICE',
                'TAX INVOICE' => 'TAX INVOICE',
                'EXEMPTED' => 'EXEMPTED',
                'E-COMMERCE' => 'E-COMMERCE',
            ]);
        }

        // Customer's sales_type represents Tax Territory (Local vs Interstate)
        $salesTypes = collect([
            'Local' => 'Local (Intra-state)',
            'Interstate' => 'Interstate',
        ]);

        $user = auth()->user();
        $isGlobalAdmin = ! $user || $user->branch_id === null || $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']) || $user->email === 'admin@urbanpos.com';
        $branches = (! $isGlobalAdmin && $user->branch_id)
            ? Branch::where('id', $user->branch_id)->pluck('name', 'id')
            : Branch::where('status', true)->orderBy('name')->pluck('name', 'id');

        return [
            'customerCategories' => CustomerCategory::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'customerTypes' => $customerTypes,
            'salesTypes' => $salesTypes,
            'branches' => $branches,
            'areas' => Area::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'petTypes' => PetType::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'breeds' => Breed::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'colors' => Color::where('status', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request, ?Customer $customer = null): array
    {
        $activeCustomerTypes = CustomerType::where('status', true)->pluck('name')->toArray();
        if ($customer?->customer_type) {
            $activeCustomerTypes[] = $customer->customer_type;
        }
        if (empty($activeCustomerTypes)) {
            $activeCustomerTypes = ['RETAIL INVOICE', 'TAX INVOICE', 'EXEMPTED', 'E-COMMERCE'];
        }

        // Normalize sales_type if sent as "Local (Intra-state)" or similar
        if ($request->has('sales_type')) {
            $st = trim((string) $request->input('sales_type'));
            if (stripos($st, 'local') !== false) {
                $request->merge(['sales_type' => 'Local']);
            } elseif (stripos($st, 'inter') !== false) {
                $request->merge(['sales_type' => 'Interstate']);
            }
        }

        // Normalize google_map_link to always include https:// if user pasted plain domain
        if ($request->filled('google_map_link')) {
            $rawMap = trim((string) $request->input('google_map_link'));
            if ($rawMap !== '' && !preg_match('#^https?://#i', $rawMap)) {
                $request->merge(['google_map_link' => 'https://' . $rawMap]);
            }
        }

        // Normalize multiple addresses map links and auto-mirror primary address to top-level fields
        $inputAddresses = $request->input('addresses', []);
        if (is_array($inputAddresses) && !empty($inputAddresses)) {
            $primaryAddr = null;
            foreach ($inputAddresses as $k => $addr) {
                if (!empty($addr['google_map_link'])) {
                    $rawAddrMap = trim((string) $addr['google_map_link']);
                    if ($rawAddrMap !== '' && !preg_match('#^https?://#i', $rawAddrMap)) {
                        $inputAddresses[$k]['google_map_link'] = 'https://' . $rawAddrMap;
                    }
                }
                if (empty($addr['_delete'])) {
                    if (!empty($addr['is_primary']) || $primaryAddr === null) {
                        $primaryAddr = $inputAddresses[$k];
                    }
                }
            }
            $request->merge(['addresses' => $inputAddresses]);

            if ($primaryAddr) {
                $request->mergeIfMissing([
                    'address1' => $primaryAddr['address_line'] ?? null,
                    'area_id' => !empty($primaryAddr['area_id']) ? $primaryAddr['area_id'] : null,
                    'city' => !empty($primaryAddr['city']) ? $primaryAddr['city'] : 'Ahmedabad',
                    'state' => !empty($primaryAddr['state']) ? $primaryAddr['state'] : 'Gujarat',
                    'postal_code' => $primaryAddr['postal_code'] ?? null,
                    'google_map_link' => $primaryAddr['google_map_link'] ?? null,
                ]);
            }
        }

        // Customer's sales_type represents Tax Territory (Local vs Interstate) as defined in customers enum.
        $allowedSalesTypes = ['Local', 'Interstate'];
        $dbSalesTypes = SalesType::where('status', true)->pluck('name')->toArray();
        $allowedSalesTypes = array_unique(array_merge($allowedSalesTypes, $dbSalesTypes));
        if ($customer?->sales_type) {
            $allowedSalesTypes[] = $customer->sales_type;
        }

        $rules = [
            // General
            'title' => ['nullable', 'in:Mr,Ms,Mrs,M/s,Dr'],
            'name' => ['required', 'string', 'max:255'],
            'customer_category_id' => ['nullable', 'exists:customer_categories,id'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'sales_type' => ['required', Rule::in($allowedSalesTypes)],
            'payment_mode' => ['required', 'in:Cash Only,No Credit,Credit Only,Both Cash and Credit,Cash on Delivery'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'credit_balance' => ['required', 'numeric', 'min:0'],
            'monthly_credit_balance' => ['required', 'numeric', 'min:0'],
            'credit_days' => ['required', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['required', 'boolean'],
            'sales_formula' => ['nullable', 'string', 'max:255'],
            'gst_type' => ['required', 'string', 'max:100'],
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
            'google_map_link' => ['nullable', 'url', 'max:500'],
            'remarks' => ['nullable', 'string'],
            'gst_no' => ['nullable', 'string', 'size:15', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[A-Z\d]{1}Z[A-Z\d]{1}$/'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'mobile' => ['required', 'string', 'digits:10', Rule::unique('customers', 'mobile')->ignore($customer?->id)],

            // Others
            'gender' => ['nullable', 'in:Male,Female'],
            'exempted_reason' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', Rule::in($activeCustomerTypes)],
        ];

        $messages = [
            'mobile.required' => 'Customer mobile number is required.',
            'mobile.digits' => 'Customer mobile number must be exactly 10 digits.',
            'mobile.unique' => 'A customer with this mobile number already exists.',
            'customer_type.in' => 'Selected Customer Type is invalid or inactive.',
            'sales_type.in' => 'Selected Sales Type is invalid or inactive.',
        ];

        app(\App\Services\DynamicValidationService::class)->applyTo('customers', $rules, $messages, $customer?->id);

        $validated = $request->validate($rules, $messages);

        if ($request->has('pets')) {
            $pets = $request->input('pets', []);
            $modified = false;
            foreach ($pets as $idx => $pet) {
                if (!empty($pet['_delete'])) {
                    continue;
                }
                $hasPetInfo = !empty($pet['id'])
                    || !empty(trim($pet['name'] ?? ''))
                    || !empty($pet['pet_type_id'])
                    || !empty($pet['breed_id'])
                    || !empty($pet['color_id'])
                    || !empty(trim($pet['age'] ?? ''))
                    || !empty($pet['birth_date'])
                    || !empty(trim($pet['remarks'] ?? ''));

                if ($hasPetInfo && (empty($pet['gender']) || !in_array($pet['gender'], ['Male', 'Female']))) {
                    $pets[$idx]['gender'] = 'Male';
                    $modified = true;
                }
            }
            if ($modified) {
                $request->merge(['pets' => $pets]);
            }
        }

        return $validated;
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
