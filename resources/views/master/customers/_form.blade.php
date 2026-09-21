@php $c = $customer ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contact">Contact Details</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-others">Others</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-pets">Pet Details</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-custom-fields"><i class="fas fa-sliders-h text-primary mr-1"></i> Custom Fields</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-user-tag mr-1 text-primary"></i> General Fields</h6>
            <x-form-layout-customizer
                form-key="master_customers.general"
                container-id="customer-general-fields-grid"
                title="Customize Customer Form Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="customer-general-fields-grid">
            <div class="field-wrapper col-md-6" data-field="title" data-label="Title" data-default-order="1">
                <x-select name="title" label="Title" :options="['Mr' => 'Mr', 'Ms' => 'Ms', 'Mrs' => 'Mrs', 'M/s' => 'M/s', 'Dr' => 'Dr']" :selected="$c->title ?? 'Mr'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="name" data-label="Name" data-default-order="2" data-core="1">
                <x-field name="name" label="Name" :value="$c->name ?? ''" required />
            </div>
            <div class="field-wrapper col-md-6" data-field="mobile" data-label="Mobile Number" data-default-order="3" data-core="1">
                <x-field name="mobile" label="Mobile Number" :value="$c->mobile ?? ''" required />
            </div>
            <div class="field-wrapper col-md-6" data-field="customer_category_id" data-label="Category" data-default-order="4">
                <x-select name="customer_category_id" label="Category" :options="$customerCategories" :selected="$c->customer_category_id ?? ''" placeholder="Select a category" />
            </div>
            <div class="field-wrapper col-md-6" data-field="customer_code" data-label="Customer Id" data-default-order="5">
                <x-field name="customer_code" label="Customer Id" :value="$c->customer_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sales_type" data-label="Sales Type" data-default-order="6">
                <x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$c->sales_type ?? 'Local'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="payment_mode" data-label="Payment Mode" data-default-order="7">
                <x-select name="payment_mode" label="Payment Mode" :options="['Cash Only' => 'Cash Only', 'No Credit' => 'No Credit', 'Credit Only' => 'Credit Only', 'Both Cash and Credit' => 'Both Cash and Credit', 'Cash on Delivery' => 'Cash on Delivery']" :selected="$c->payment_mode ?? 'Cash Only'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_limit" data-label="Credit Limit" data-default-order="8">
                <x-field name="credit_limit" label="Credit Limit" type="number" step="0.01" :value="$c->credit_limit ?? 1000000" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_balance" data-label="Credit Balance" data-default-order="9">
                <x-field name="credit_balance" label="Credit Balance" type="number" step="0.01" :value="$c->credit_balance ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="monthly_credit_balance" data-label="Monthly Credit Balance" data-default-order="10">
                <x-field name="monthly_credit_balance" label="Monthly Credit Balance" type="number" step="0.01" :value="$c->monthly_credit_balance ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_days" data-label="Credit Days" data-default-order="11">
                <x-field name="credit_days" label="Credit Days" type="number" :value="$c->credit_days ?? 1000" />
            </div>
            <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Branch" data-default-order="12">
                <x-select name="branch_id" label="Branch" :options="$branches" :selected="$c->branch_id ?? ''" placeholder="GLOBAL" />
            </div>
            <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="13">
                <x-bool-select name="status" label="Status" :value="$c->status ?? true" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sales_formula" data-label="Sales Formula" data-default-order="14">
                <x-field name="sales_formula" label="Sales Formula" :value="$c->sales_formula ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="gst_type" data-label="GST Type" data-default-order="15">
                @php
                    $gstTypeOptions = \App\Models\GstType::where('status', true)->orderBy('name')->pluck('name', 'name');
                    if ($gstTypeOptions->isEmpty()) {
                        $gstTypeOptions = collect(['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']);
                    }
                @endphp
                <x-select name="gst_type" label="GST Type" :options="$gstTypeOptions" :selected="$c->gst_type ?? 'Un Register'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sms_consent" data-label="I wish to receive SMS" data-default-order="16">
                <x-bool-select name="sms_consent" label="I wish to receive SMS" :value="$c->sms_consent ?? true" true-label="Yes" false-label="No" />
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-contact">
        <x-field name="address1" label="Address1" :value="$c->address1 ?? ''" />
        <x-select name="area_id" label="Area" :options="$areas" :selected="$c->area_id ?? ''" placeholder="Select an area" />
        <div class="form-group row">
            <label for="customer_state" class="col-sm-3 col-form-label">State</label>
            <div class="col-sm-6">
                <select name="state" id="customer_state" class="form-control select2 @error('state') is-invalid @enderror">
                    <option value="">-- Select State --</option>
                    <optgroup label="⭐ Top States">
                        <option value="Gujarat" @selected(old('state', $c->state ?? '') === 'Gujarat')>Gujarat</option>
                        <option value="Rajasthan" @selected(old('state', $c->state ?? '') === 'Rajasthan')>Rajasthan</option>
                        <option value="Maharashtra" @selected(old('state', $c->state ?? '') === 'Maharashtra')>Maharashtra</option>
                    </optgroup>
                    <optgroup label="Other States & UTs">
                        @foreach(\App\Helpers\IndianStates::states() as $stVal => $stLabel)
                            @if(!in_array($stVal, ['Gujarat', 'Rajasthan', 'Maharashtra']))
                                <option value="{{ $stVal }}" @selected(old('state', $c->state ?? '') === $stVal)>{{ $stLabel }}</option>
                            @endif
                        @endforeach
                    </optgroup>
                </select>
                @error('state')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="form-group row">
            <label for="customer_city" class="col-sm-3 col-form-label">City</label>
            <div class="col-sm-6">
                <select name="city" id="customer_city" class="form-control select2 @error('city') is-invalid @enderror">
                    <option value="">-- Select City --</option>
                    @if(!empty(old('city', $c->city ?? '')))
                        <option value="{{ old('city', $c->city ?? '') }}" selected>{{ old('city', $c->city ?? '') }}</option>
                    @endif
                </select>
                @error('city')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <x-field name="country" label="Country" :value="$c->country ?? 'India'" />
        <x-field name="postal_code" label="Postal Code" :value="$c->postal_code ?? ''" />
        <x-field name="std_code" label="STD Code" :value="$c->std_code ?? ''" />
        <x-field name="phone" label="Phone" :value="$c->phone ?? ''" />
        <x-field name="email" label="Email" type="email" :value="$c->email ?? ''" />
        <x-textarea name="remarks" label="Remarks" :value="$c->remarks ?? ''" />
        <x-field name="gst_no" label="GST No" :value="$c->gst_no ?? ''" maxlength="15" placeholder="e.g. 22AAAAA0000A1Z5" hint="Format: 2-digit state + 10-char PAN + 1 entity + Z + check (15 chars)" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" />
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

    <div class="tab-pane" id="tab-custom-fields">
        <div class="p-2">
            <x-custom-fields-renderer module="Customer" :model="$c" :showHeader="false" colClass="col-md-6 col-12 mb-3" />
        </div>
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
<script src="{{ asset('js/indian-states-cities.js') }}"></script>
<script>
    $(document).ready(function () {
        if (typeof initIndianStateCity === 'function') {
            initIndianStateCity('#customer_state', '#customer_city', '{{ old('state', $c->state ?? '') }}', '{{ old('city', $c->city ?? '') }}');
        }
    });
</script>
@endpush
