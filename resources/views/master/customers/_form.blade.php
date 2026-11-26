@php 
    $c = $customer ?? null; 
    $existingAddresses = ($c && $c->addresses && $c->addresses->isNotEmpty()) 
        ? $c->addresses 
        : ( ($c && !empty($c->address1)) ? collect([(object)[
            'id' => null,
            'address_type' => 'Home',
            'address_line' => $c->address1,
            'area_id' => $c->area_id,
            'area_name' => '',
            'city' => $c->city ?: 'Ahmedabad',
            'state' => $c->state ?: 'Gujarat',
            'country' => $c->country ?: 'India',
            'postal_code' => $c->postal_code,
            'landmark' => '',
            'google_map_link' => $c->google_map_link ?? '',
            'is_primary' => true,
        ]]) : collect([(object)[
            'id' => null,
            'address_type' => 'Home',
            'address_line' => '',
            'area_id' => '',
            'area_name' => '',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'India',
            'postal_code' => '',
            'landmark' => '',
            'google_map_link' => '',
            'is_primary' => true,
        ]]) );
@endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contact"><i class="fas fa-address-book text-primary mr-1"></i> Contact & Addresses (<span id="address-count-badge">{{ count($existingAddresses) }}</span>)</a></li>
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
            <div class="field-wrapper col-md-6" data-field="email" data-label="Email" data-default-order="4">
                <x-field name="email" label="Email" type="email" :value="$c->email ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="customer_category_id" data-label="Category" data-default-order="5">
                <x-select name="customer_category_id" label="Category" :options="$customerCategories" :selected="$c->customer_category_id ?? ''" placeholder="Select a category" />
            </div>
            <div class="field-wrapper col-md-6" data-field="customer_code" data-label="Customer Id" data-default-order="6">
                <x-field name="customer_code" label="Customer Id" :value="$c->customer_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sales_type" data-label="Purchase Type" data-default-order="7">
                <x-select name="sales_type" label="Purchase Type (Local / Interstate)" :options="$salesTypes ?? ['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$c->sales_type ?? 'Local'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="payment_mode" data-label="Payment Mode" data-default-order="8">
                <x-select name="payment_mode" label="Payment Mode" :options="['Cash Only' => 'Cash Only', 'No Credit' => 'No Credit', 'Credit Only' => 'Credit Only', 'Both Cash and Credit' => 'Both Cash and Credit', 'Cash on Delivery' => 'Cash on Delivery']" :selected="$c->payment_mode ?? 'Cash Only'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_limit" data-label="Credit Limit" data-default-order="9">
                <x-field name="credit_limit" label="Credit Limit" type="number" step="0.01" :value="$c->credit_limit ?? 1000000" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_balance" data-label="Credit Balance" data-default-order="10">
                <x-field name="credit_balance" label="Credit Balance" type="number" step="0.01" :value="$c->credit_balance ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="monthly_credit_balance" data-label="Monthly Credit Balance" data-default-order="11">
                <x-field name="monthly_credit_balance" label="Monthly Credit Balance" type="number" step="0.01" :value="$c->monthly_credit_balance ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_days" data-label="Credit Days" data-default-order="12">
                <x-field name="credit_days" label="Credit Days" type="number" :value="$c->credit_days ?? 1000" />
            </div>
            <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Branch" data-default-order="13">
                <x-select name="branch_id" label="Branch" :options="$branches" :selected="$c->branch_id ?? ''" placeholder="All Branches" />
            </div>
            <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="14">
                <x-bool-select name="status" label="Status" :value="$c->status ?? true" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sales_formula" data-label="Sales Formula" data-default-order="15">
                <x-field name="sales_formula" label="Sales Formula" :value="$c->sales_formula ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="gst_type" data-label="GST Type" data-default-order="16">
                @php
                    $gstTypeOptions = \App\Models\GstType::where('status', true)->orderBy('name')->pluck('name', 'name');
                    if ($gstTypeOptions->isEmpty()) {
                        $gstTypeOptions = collect(['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']);
                    }
                @endphp
                <x-select name="gst_type" label="GST Type" :options="$gstTypeOptions" :selected="$c->gst_type ?? 'Un Register'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="gst_no" data-label="GST No" data-default-order="17">
                <x-field name="gst_no" label="GST No" :value="$c->gst_no ?? ''" maxlength="15" placeholder="e.g. 22AAAAA0000A1Z5" hint="Format: 2-digit state + 10-char PAN + 1 entity + Z + check (15 chars)" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sms_consent" data-label="I wish to receive SMS" data-default-order="18">
                <x-bool-select name="sms_consent" label="I wish to receive SMS" :value="$c->sms_consent ?? true" true-label="Yes" false-label="No" />
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-contact">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-address-book mr-1 text-primary"></i> Contact & Communication</h6>
            <x-form-layout-customizer
                form-key="master_customers.contact"
                container-id="customer-contact-fields-grid"
                title="Customize Contact Details Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="customer-contact-fields-grid">
            <div class="field-wrapper col-md-6" data-field="phone" data-label="Phone" data-default-order="1">
                <x-field name="phone" label="Phone / Landline" :value="$c->phone ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="std_code" data-label="STD Code" data-default-order="2">
                <x-field name="std_code" label="STD Code" :value="$c->std_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="pan_no" data-label="Pan No" data-default-order="3">
                <x-field name="pan_no" label="Pan No" :value="$c->pan_no ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="aadhar_no" data-label="Aadhar No" data-default-order="4">
                <x-field name="aadhar_no" label="Aadhar No" :value="$c->aadhar_no ?? ''" />
            </div>
            <div class="field-wrapper col-md-12" data-field="remarks" data-label="Remarks" data-default-order="5">
                <x-textarea name="remarks" label="Remarks" :value="$c->remarks ?? ''" />
            </div>
        </div>

        <div class="customer-addresses-section mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-map-marked-alt text-primary mr-1"></i> Delivery & Physical Addresses (<span id="address-count-badge-inner">{{ count($existingAddresses) }}</span>)
                    </h6>
                    <small class="text-muted">Manage multiple delivery locations (Home, Office, Shop, Warehouse, Farmhouse, Clinic). Select one as Default / Primary Address.</small>
                </div>
                <button type="button" id="add-address-btn" class="btn btn-outline-primary btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-plus mr-1"></i> Add Another Address
                </button>
            </div>

            <div id="address-cards-container">
                @forelse($existingAddresses as $addrIdx => $addr)
                    <div class="address-row card mb-3 border shadow-sm">
                        <input type="hidden" name="addresses[{{ $addrIdx }}][id]" value="{{ $addr->id ?? '' }}">
                        <input type="hidden" name="addresses[{{ $addrIdx }}][_delete]" class="address-delete-flag" value="0">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-info mr-2 address-idx-label">#{{ $addrIdx + 1 }}</span>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="primary_addr_{{ $addrIdx }}" name="primary_address_radio" value="{{ $addrIdx }}" class="custom-control-input address-primary-radio" @checked(!empty($addr->is_primary))>
                                    <label class="custom-control-label font-weight-bold text-dark" for="primary_addr_{{ $addrIdx }}">Default / Primary Address</label>
                                </div>
                                <input type="hidden" name="addresses[{{ $addrIdx }}][is_primary]" class="address-is-primary-hidden" value="{{ !empty($addr->is_primary) ? '1' : '0' }}">
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-xs remove-address-row" title="Remove Address"><i class="fas fa-trash"></i> Remove</button>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="small font-weight-bold">Address Type <span class="text-danger">*</span></label>
                                    <select name="addresses[{{ $addrIdx }}][address_type]" class="form-control form-control-sm">
                                        <option value="Home" @selected(($addr->address_type ?? 'Home') === 'Home')>Home</option>
                                        <option value="Office" @selected(($addr->address_type ?? '') === 'Office')>Office</option>
                                        <option value="Shop" @selected(($addr->address_type ?? '') === 'Shop')>Shop</option>
                                        <option value="Warehouse" @selected(($addr->address_type ?? '') === 'Warehouse')>Warehouse</option>
                                        <option value="Farmhouse" @selected(($addr->address_type ?? '') === 'Farmhouse')>Farmhouse</option>
                                        <option value="Clinic" @selected(($addr->address_type ?? '') === 'Clinic')>Clinic</option>
                                        <option value="Other" @selected(($addr->address_type ?? '') === 'Other')>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="small font-weight-bold">Address Line / Flat / Street <span class="text-danger">*</span></label>
                                    <input type="text" name="addresses[{{ $addrIdx }}][address_line]" class="form-control form-control-sm" value="{{ $addr->address_line ?? '' }}" placeholder="Flat/Plot No, Society, Street">
                                </div>
                                <div class="col-md-4">
                                    <label class="small font-weight-bold">Area</label>
                                    <select name="addresses[{{ $addrIdx }}][area_id]" class="form-control form-control-sm">
                                        <option value="">-- Select Area --</option>
                                        @foreach($areas as $aId => $aName)
                                            <option value="{{ $aId }}" @selected(($addr->area_id ?? '') == $aId)>{{ $aName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mt-2">
                                    <label class="small font-weight-bold">State <span class="text-danger">*</span></label>
                                    <input type="text" name="addresses[{{ $addrIdx }}][state]" class="form-control form-control-sm" value="{{ $addr->state ?? 'Gujarat' }}" placeholder="Gujarat">
                                </div>
                                <div class="col-md-3 mt-2">
                                    <label class="small font-weight-bold">City <span class="text-danger">*</span></label>
                                    <input type="text" name="addresses[{{ $addrIdx }}][city]" class="form-control form-control-sm" value="{{ $addr->city ?? 'Ahmedabad' }}" placeholder="Ahmedabad">
                                </div>
                                <div class="col-md-3 mt-2">
                                    <label class="small font-weight-bold">Pincode</label>
                                    <input type="text" name="addresses[{{ $addrIdx }}][postal_code]" class="form-control form-control-sm" value="{{ $addr->postal_code ?? '' }}" placeholder="380001">
                                </div>
                                <div class="col-md-3 mt-2">
                                    <label class="small font-weight-bold">Landmark</label>
                                    <input type="text" name="addresses[{{ $addrIdx }}][landmark]" class="form-control form-control-sm" value="{{ $addr->landmark ?? '' }}" placeholder="Near SG Highway">
                                </div>
                                <div class="col-md-12 mt-2">
                                    <label class="small font-weight-bold"><i class="fas fa-map-marker-alt text-danger mr-1"></i> Google Map Link</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map-pin text-danger"></i></span>
                                        </div>
                                        <input type="url" name="addresses[{{ $addrIdx }}][google_map_link]" class="form-control form-control-sm address-map-link-input" value="{{ $addr->google_map_link ?? '' }}" placeholder="https://maps.google.com/?q=... or https://maps.app.goo.gl/...">
                                        @if(!empty($addr->google_map_link))
                                            <div class="input-group-append">
                                                <a href="{{ $addr->google_map_link }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Open Map Link">
                                                    <i class="fas fa-external-link-alt"></i> Open
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                @endforelse
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-others">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-ellipsis-h mr-1 text-primary"></i> Other Details</h6>
            <x-form-layout-customizer
                form-key="master_customers.others"
                container-id="customer-others-fields-grid"
                title="Customize Other Details Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="customer-others-fields-grid">
            <div class="field-wrapper col-md-6" data-field="gender" data-label="Gender" data-default-order="1">
                <x-select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female']" :selected="$c->gender ?? ''" placeholder="Select" />
            </div>
            <div class="field-wrapper col-md-6" data-field="exempted_reason" data-label="Exempted Reason" data-default-order="2">
                <x-select name="exempted_reason" label="Exempted Reason" :options="['Other exemption' => 'Other exemption', 'SEZ-Exempt' => 'SEZ-Exempt', 'SEZ-LUT' => 'SEZ-LUT', 'BOND' => 'BOND', 'SEZ-Taxable' => 'SEZ-Taxable']" :selected="$c->exempted_reason ?? ''" placeholder="Select" />
            </div>
            <div class="field-wrapper col-md-6" data-field="customer_type" data-label="Customer Type" data-default-order="3">
                <x-select name="customer_type" label="Customer Type" :options="$customerTypes ?? ['RETAIL INVOICE' => 'RETAIL INVOICE', 'TAX INVOICE' => 'TAX INVOICE', 'EXEMPTED' => 'EXEMPTED', 'E-COMMERCE' => 'E-COMMERCE']" :selected="$c->customer_type ?? 'RETAIL INVOICE'" />
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-pets">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-paw mr-1 text-primary"></i> Pet Details</h6>
            <x-form-layout-customizer
                form-key="master_customers.pets"
                container-id="customer-pet-fields-grid"
                title="Customize Pet Details Layout"
            />
        </div>
        <div id="pet-rows">
            @forelse (($c->pets ?? collect()) as $index => $pet)
                @if ($pet)
                    <div class="pet-row border rounded p-3 mb-3 bg-light shadow-sm">
                        <input type="hidden" name="pets[{{ $index }}][id]" value="{{ $pet->id ?? '' }}">
                        <input type="hidden" name="pets[{{ $index }}][_delete]" class="pet-delete-flag" value="0">

                        <div class="row g-2 form-fields-grid customer-pet-fields-grid" @if($index === 0) id="customer-pet-fields-grid" @endif>
                            <div class="field-wrapper col-md-6" data-field="breed_id" data-label="Breed" data-default-order="1">
                                <x-select name="pets[{{ $index }}][breed_id]" label="Breed" :options="$breeds" :selected="$pet->breed_id ?? ''" placeholder="NA" />
                            </div>
                            <div class="field-wrapper col-md-6" data-field="pet_type_id" data-label="Pet Type" data-default-order="2">
                                <x-select name="pets[{{ $index }}][pet_type_id]" label="Pet Type" :options="$petTypes" :selected="$pet->pet_type_id ?? ''" placeholder="NA" />
                            </div>
                            <div class="field-wrapper col-md-6" data-field="name" data-label="Name" data-default-order="3">
                                <x-field name="pets[{{ $index }}][name]" label="Name" :value="$pet->name ?? ''" />
                            </div>
                            <div class="field-wrapper col-md-6" data-field="gender" data-label="Gender" data-default-order="4">
                                <x-select name="pets[{{ $index }}][gender]" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female']" :selected="$pet->gender ?? 'Male'" />
                            </div>
                            <div class="field-wrapper col-md-6" data-field="age" data-label="Age" data-default-order="5">
                                <x-field name="pets[{{ $index }}][age]" label="Age" :value="$pet->age ?? ''" />
                            </div>
                            <div class="field-wrapper col-md-6" data-field="birth_date" data-label="Birth Date" data-default-order="6">
                                <x-field name="pets[{{ $index }}][birth_date]" label="Birth Date (DD-MM-YYYY or MM-YYYY)" placeholder="DD-MM-YYYY or MM-YYYY" :value="(string)($pet->birth_date ?? '')" />
                            </div>
                            <div class="field-wrapper col-md-12" data-field="remarks" data-label="Remarks" data-default-order="7">
                                <x-textarea name="pets[{{ $index }}][remarks]" label="Remarks" :value="$pet->remarks ?? ''" col="6" rows="2" />
                            </div>
                        </div>

                        <div class="text-right mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-pet-row"><i class="fas fa-trash"></i> Remove</button>
                        </div>
                    </div>
                @endif
            @empty
            @endforelse
        </div>

        <button type="button" id="add-pet-detail" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> Add Pet Detail</button>
    </div>

    <div class="tab-pane" id="tab-custom-fields">
        <div class="p-2">
            <x-custom-fields-renderer module="Customer" :model="$c" :showHeader="false" colClass="col-md-6 col-12 mb-3" />
        </div>
    </div>
</div>

<template id="address-row-template">
    <div class="address-row card mb-3 border shadow-sm">
        <input type="hidden" name="addresses[__INDEX__][id]" value="">
        <input type="hidden" name="addresses[__INDEX__][_delete]" class="address-delete-flag" value="0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <div class="d-flex align-items-center">
                <span class="badge badge-info mr-2 address-idx-label">#__DISPLAY_INDEX__</span>
                <div class="custom-control custom-radio">
                    <input type="radio" id="primary_addr___INDEX__" name="primary_address_radio" value="__INDEX__" class="custom-control-input address-primary-radio">
                    <label class="custom-control-label font-weight-bold text-dark" for="primary_addr___INDEX__">Default / Primary Address</label>
                </div>
                <input type="hidden" name="addresses[__INDEX__][is_primary]" class="address-is-primary-hidden" value="0">
            </div>
            <button type="button" class="btn btn-outline-danger btn-xs remove-address-row" title="Remove Address"><i class="fas fa-trash"></i> Remove</button>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="small font-weight-bold">Address Type <span class="text-danger">*</span></label>
                    <select name="addresses[__INDEX__][address_type]" class="form-control form-control-sm">
                        <option value="Home">Home</option>
                        <option value="Office">Office</option>
                        <option value="Shop">Shop</option>
                        <option value="Warehouse">Warehouse</option>
                        <option value="Farmhouse">Farmhouse</option>
                        <option value="Clinic">Clinic</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="small font-weight-bold">Address Line / Flat / Street <span class="text-danger">*</span></label>
                    <input type="text" name="addresses[__INDEX__][address_line]" class="form-control form-control-sm" placeholder="Flat/Plot No, Society, Street">
                </div>
                <div class="col-md-4">
                    <label class="small font-weight-bold">Area</label>
                    <select name="addresses[__INDEX__][area_id]" class="form-control form-control-sm">
                        <option value="">-- Select Area --</option>
                        @foreach($areas as $aId => $aName)
                            <option value="{{ $aId }}">{{ $aName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mt-2">
                    <label class="small font-weight-bold">State <span class="text-danger">*</span></label>
                    <input type="text" name="addresses[__INDEX__][state]" class="form-control form-control-sm" value="Gujarat" placeholder="Gujarat">
                </div>
                <div class="col-md-3 mt-2">
                    <label class="small font-weight-bold">City <span class="text-danger">*</span></label>
                    <input type="text" name="addresses[__INDEX__][city]" class="form-control form-control-sm" value="Ahmedabad" placeholder="Ahmedabad">
                </div>
                <div class="col-md-3 mt-2">
                    <label class="small font-weight-bold">Pincode</label>
                    <input type="text" name="addresses[__INDEX__][postal_code]" class="form-control form-control-sm" placeholder="380001">
                </div>
                <div class="col-md-3 mt-2">
                    <label class="small font-weight-bold">Landmark</label>
                    <input type="text" name="addresses[__INDEX__][landmark]" class="form-control form-control-sm" placeholder="Near SG Highway">
                </div>
                <div class="col-md-12 mt-2">
                    <label class="small font-weight-bold"><i class="fas fa-map-marker-alt text-danger mr-1"></i> Google Map Link</label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-map-pin text-danger"></i></span>
                        </div>
                        <input type="url" name="addresses[__INDEX__][google_map_link]" class="form-control form-control-sm address-map-link-input" placeholder="https://maps.google.com/?q=... or https://maps.app.goo.gl/...">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="pet-row-template">
    <div class="pet-row border rounded p-3 mb-3 bg-light shadow-sm">
        <input type="hidden" name="pets[__INDEX__][id]" value="">
        <input type="hidden" name="pets[__INDEX__][_delete]" class="pet-delete-flag" value="0">

        <div class="row g-2 form-fields-grid customer-pet-fields-grid">
            <div class="field-wrapper col-md-6" data-field="breed_id" data-label="Breed" data-default-order="1">
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
            </div>

            <div class="field-wrapper col-md-6" data-field="pet_type_id" data-label="Pet Type" data-default-order="2">
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
            </div>

            <div class="field-wrapper col-md-6" data-field="name" data-label="Name" data-default-order="3">
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Name</label>
                    <div class="col-sm-6"><input type="text" name="pets[__INDEX__][name]" class="form-control"></div>
                </div>
            </div>

            <div class="field-wrapper col-md-6" data-field="gender" data-label="Gender" data-default-order="4">
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Gender <span class="text-danger">*</span></label>
                    <div class="col-sm-6">
                        <select name="pets[__INDEX__][gender]" class="form-control">
                            <option value="Male" selected>Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="field-wrapper col-md-6" data-field="age" data-label="Age" data-default-order="5">
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Age</label>
                    <div class="col-sm-6"><input type="text" name="pets[__INDEX__][age]" class="form-control"></div>
                </div>
            </div>

            <div class="field-wrapper col-md-6" data-field="birth_date" data-label="Birth Date" data-default-order="6">
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Birth Date</label>
                    <div class="col-sm-6"><input type="text" name="pets[__INDEX__][birth_date]" class="form-control" placeholder="DD-MM-YYYY or MM-YYYY" maxlength="10"></div>
                </div>
            </div>

            <div class="field-wrapper col-md-12" data-field="remarks" data-label="Remarks" data-default-order="7">
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Remarks</label>
                    <div class="col-sm-6"><textarea name="pets[__INDEX__][remarks]" rows="2" class="form-control"></textarea></div>
                </div>
            </div>
        </div>

        <div class="text-right mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger remove-pet-row"><i class="fas fa-trash"></i> Remove</button>
        </div>
    </div>
</template>

@push('js')
<script>
    (function () {
        let petIndex = {{ ($c->pets ?? collect())->count() ?: 1 }};
        let lastPetMeta = null;

        // Auto-switch to tab containing invalid input so browser HTML5 validation works cleanly
        $('form').on('invalid', 'input, select, textarea', function () {
            const $tabPane = $(this).closest('.tab-pane');
            if ($tabPane.length && !$tabPane.hasClass('active')) {
                const tabId = $tabPane.attr('id');
                $(`a[href="#${tabId}"]`).tab('show');
            }
        });

        function syncPetRowLayout($grid, metaList) {
            if (!metaList || !metaList.length) return;
            metaList.forEach(function (f) {
                let $field = $grid.find(`.field-wrapper[data-field="${f.field}"]`);
                if ($field.length) {
                    $grid.append($field);
                    $field.removeClass('col-md-2 col-md-3 col-md-4 col-md-6 col-md-12').addClass(f.currentCol);
                    if (!f.visible) {
                        $field.addClass('form-field-hidden');
                    } else {
                        $field.removeClass('form-field-hidden');
                    }
                }
            });
        }

        $(document).on('form-layout:applied', function (e, data) {
            if (data.formKey === 'master_customers.pets') {
                lastPetMeta = data.fieldsMeta;
                $('.customer-pet-fields-grid').each(function () {
                    syncPetRowLayout($(this), data.fieldsMeta);
                });
            }
        });

        document.getElementById('add-pet-detail').addEventListener('click', function () {
            const template = document.getElementById('pet-row-template').innerHTML.replaceAll('__INDEX__', petIndex);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template;
            const newRow = wrapper.firstElementChild;
            document.getElementById('pet-rows').appendChild(newRow);
            if (lastPetMeta) {
                syncPetRowLayout($(newRow).find('.customer-pet-fields-grid'), lastPetMeta);
            }
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

        // Address Management Logic
        let addressIndex = {{ max(1, count($existingAddresses ?? [])) }};

        function updateAddressBadge() {
            const visibleCount = $('#address-cards-container .address-row:visible').length;
            $('#address-count-badge').text(visibleCount);
            $('#address-count-badge-inner').text(visibleCount);
        }

        $('#add-address-btn').on('click', function () {
            const displayIndex = $('#address-cards-container .address-row:visible').length + 1;
            let tmpl = document.getElementById('address-row-template').innerHTML;
            tmpl = tmpl.replaceAll('__INDEX__', addressIndex).replaceAll('__DISPLAY_INDEX__', displayIndex);
            const $newRow = $(tmpl);

            // If no address is currently marked primary, make this first one primary
            const hasPrimary = $('#address-cards-container .address-primary-radio:checked').length > 0;
            if (!hasPrimary) {
                $newRow.find('.address-primary-radio').prop('checked', true);
                $newRow.find('.address-is-primary-hidden').val('1');
            }

            $('#address-cards-container').append($newRow);
            addressIndex++;
            updateAddressBadge();
        });

        $('#address-cards-container').on('click', '.remove-address-row', function (e) {
            e.preventDefault();
            const $card = $(this).closest('.address-row');
            $card.find('.address-delete-flag').val('1');
            $card.find('.address-is-primary-hidden').val('0');
            $card.hide();

            // If the deleted address was primary, make the first visible one primary
            const $visible = $('#address-cards-container .address-row:visible');
            const hasPrimary = $visible.find('.address-primary-radio:checked').length > 0;
            if (!hasPrimary && $visible.length > 0) {
                $visible.first().find('.address-primary-radio').prop('checked', true);
                $visible.first().find('.address-is-primary-hidden').val('1');
            }

            updateAddressBadge();
        });

        $('#address-cards-container').on('change', '.address-primary-radio', function () {
            $('#address-cards-container .address-is-primary-hidden').val('0');
            $(this).closest('.address-row').find('.address-is-primary-hidden').val('1');
        });
    })();
</script>
<script src="{{ asset('js/indian-states-cities.js') }}"></script>
<script>
    $(document).ready(function () {
        if (typeof initIndianStateCity === 'function') {
            initIndianStateCity('#customer_state', '#customer_city', '{{ old('state', $c->state ?? ($c && $c->exists ? '' : 'Gujarat')) }}', '{{ old('city', $c->city ?? ($c && $c->exists ? '' : 'Ahmedabad')) }}');
        }
    });
</script>
@endpush
