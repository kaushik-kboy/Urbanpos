@php $c = $customer ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contact">Contact Details</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-others">Others</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-pets">Pet Details</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <x-select name="title" label="Title" :options="['Mr' => 'Mr', 'Ms' => 'Ms', 'Mrs' => 'Mrs', 'M/s' => 'M/s', 'Dr' => 'Dr']" :selected="$c->title ?? 'Mr'" />
        <x-field name="name" label="Name" :value="$c->name ?? ''" />
        <x-select name="customer_category_id" label="Category" :options="$customerCategories" :selected="$c->customer_category_id ?? ''" placeholder="Select a category" />
        <x-field name="customer_code" label="Customer Id" :value="$c->customer_code ?? ''" />
        <x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$c->sales_type ?? 'Local'" />
        <x-select name="payment_mode" label="Payment Mode" :options="['Cash Only' => 'Cash Only', 'No Credit' => 'No Credit', 'Credit Only' => 'Credit Only', 'Both Cash and Credit' => 'Both Cash and Credit', 'Cash on Delivery' => 'Cash on Delivery']" :selected="$c->payment_mode ?? 'Cash Only'" />
        <x-field name="credit_limit" label="Credit Limit" type="number" step="0.01" :value="$c->credit_limit ?? 1000000" />
        <x-field name="credit_balance" label="Credit Balance" type="number" step="0.01" :value="$c->credit_balance ?? 0" />
        <x-field name="monthly_credit_balance" label="Monthly Credit Balance" type="number" step="0.01" :value="$c->monthly_credit_balance ?? 0" />
        <x-field name="credit_days" label="Credit Days" type="number" :value="$c->credit_days ?? 1000" />
        <x-select name="branch_id" label="Branch" :options="$branches" :selected="$c->branch_id ?? ''" placeholder="GLOBAL" />
        <x-bool-select name="status" label="Status" :value="$c->status ?? true" />
        <x-field name="sales_formula" label="Sales Formula" :value="$c->sales_formula ?? ''" />
        <x-select name="gst_type" label="GST Type" :options="['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']" :selected="$c->gst_type ?? 'Un Register'" />
        <x-bool-select name="sms_consent" label="I wish to receive SMS" :value="$c->sms_consent ?? true" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-contact">
        <x-field name="address1" label="Address1" :value="$c->address1 ?? ''" />
        <x-select name="area_id" label="Area" :options="$areas" :selected="$c->area_id ?? ''" placeholder="Select an area" />
        <x-field name="city" label="City" :value="$c->city ?? ''" />
        <x-field name="state" label="State" :value="$c->state ?? ''" />
        <x-field name="country" label="Country" :value="$c->country ?? 'India'" />
        <x-field name="postal_code" label="Postal Code" :value="$c->postal_code ?? ''" />
        <x-field name="std_code" label="STD Code" :value="$c->std_code ?? ''" />
        <x-field name="phone" label="Phone" :value="$c->phone ?? ''" />
        <x-field name="email" label="Email" type="email" :value="$c->email ?? ''" />
        <x-field name="mobile" label="Mobile" :value="$c->mobile ?? ''" />
        <x-textarea name="remarks" label="Remarks" :value="$c->remarks ?? ''" />
        <x-field name="gst_no" label="GST No" :value="$c->gst_no ?? ''" />
        <x-field name="aadhar_no" label="Aadhar No" :value="$c->aadhar_no ?? ''" />
        <x-field name="pan_no" label="Pan No" :value="$c->pan_no ?? ''" />
    </div>

    <div class="tab-pane" id="tab-others">
        <x-select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female']" :selected="$c->gender ?? ''" placeholder="Select" />
        <x-select name="exempted_reason" label="Exempted Reason" :options="['Other exemption' => 'Other exemption', 'SEZ-Exempt' => 'SEZ-Exempt', 'SEZ-LUT' => 'SEZ-LUT', 'BOND' => 'BOND', 'SEZ-Taxable' => 'SEZ-Taxable']" :selected="$c->exempted_reason ?? ''" placeholder="Select" />
        <x-select name="customer_type" label="Customer Type" :options="['RETAIL INVOICE' => 'RETAIL INVOICE', 'TAX INVOICE' => 'TAX INVOICE', 'EXEMPTED' => 'EXEMPTED', 'E-COMMERCE' => 'E-COMMERCE']" :selected="$c->customer_type ?? 'RETAIL INVOICE'" />
    </div>

    <div class="tab-pane" id="tab-pets">
        <div id="pet-rows">
            @forelse (($c->pets ?? collect())->all() ?: [null] as $index => $pet)
                @if ($pet || ! ($c->pets ?? null)?->count())
                    <div class="pet-row border rounded p-3 mb-3">
                        <input type="hidden" name="pets[{{ $index }}][id]" value="{{ $pet->id ?? '' }}">
                        <input type="hidden" name="pets[{{ $index }}][_delete]" class="pet-delete-flag" value="0">

                        <x-select name="pets[{{ $index }}][breed_id]" label="Breed" :options="$breeds" :selected="$pet->breed_id ?? ''" placeholder="NA" />
                        <x-select name="pets[{{ $index }}][pet_type_id]" label="Pet Type" :options="$petTypes" :selected="$pet->pet_type_id ?? ''" placeholder="NA" />
                        <x-field name="pets[{{ $index }}][name]" label="Name" :value="$pet->name ?? ''" />
                        <x-select name="pets[{{ $index }}][gender]" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female']" :selected="$pet->gender ?? 'Male'" />
                        <x-field name="pets[{{ $index }}][age]" label="Age" :value="$pet->age ?? ''" />
                        <x-textarea name="pets[{{ $index }}][remarks]" label="Remarks" :value="$pet->remarks ?? ''" col="6" rows="2" />
                        <x-field name="pets[{{ $index }}][birth_date]" label="Birth Date" type="date" :value="optional($pet->birth_date ?? null)->format('Y-m-d')" />

                        <div class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-pet-row"><i class="fas fa-trash"></i> Remove</button>
                        </div>
                    </div>
                @endif
            @empty
            @endforelse
        </div>

        <button type="button" id="add-pet-detail" class="btn btn-link"><i class="fas fa-plus-circle"></i> Add Pet Detail</button>
    </div>
</div>

<template id="pet-row-template">
    <div class="pet-row border rounded p-3 mb-3">
        <input type="hidden" name="pets[__INDEX__][id]" value="">
        <input type="hidden" name="pets[__INDEX__][_delete]" class="pet-delete-flag" value="0">

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Breed</label>
            <div class="col-sm-6">
                <select name="pets[__INDEX__][breed_id]" class="form-control">
                    <option value="">NA</option>
                    @foreach ($breeds as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Pet Type</label>
            <div class="col-sm-6">
                <select name="pets[__INDEX__][pet_type_id]" class="form-control">
                    <option value="">NA</option>
                    @foreach ($petTypes as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Name</label>
            <div class="col-sm-6"><input type="text" name="pets[__INDEX__][name]" class="form-control"></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Gender</label>
            <div class="col-sm-6">
                <select name="pets[__INDEX__][gender]" class="form-control">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Age</label>
            <div class="col-sm-6"><input type="text" name="pets[__INDEX__][age]" class="form-control"></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Remarks</label>
            <div class="col-sm-6"><textarea name="pets[__INDEX__][remarks]" rows="2" class="form-control"></textarea></div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Birth Date</label>
            <div class="col-sm-6"><input type="date" name="pets[__INDEX__][birth_date]" class="form-control"></div>
        </div>

        <div class="text-right">
            <button type="button" class="btn btn-sm btn-outline-danger remove-pet-row"><i class="fas fa-trash"></i> Remove</button>
        </div>
    </div>
</template>

@push('js')
<script>
    (function () {
        let petIndex = {{ ($c->pets ?? collect())->count() ?: 1 }};

        document.getElementById('add-pet-detail').addEventListener('click', function () {
            const template = document.getElementById('pet-row-template').innerHTML.replaceAll('__INDEX__', petIndex);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template;
            document.getElementById('pet-rows').appendChild(wrapper.firstElementChild);
            petIndex++;
        });

        document.getElementById('pet-rows').addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-pet-row');
            if (! btn) return;
            const row = btn.closest('.pet-row');
            const deleteFlag = row.querySelector('.pet-delete-flag');
            if (deleteFlag) {
                deleteFlag.value = '1';
            }
            row.style.display = 'none';
        });
    })();
</script>
@endpush
