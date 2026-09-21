@php $s = $supplier ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general"><i class="fas fa-info-circle mr-1"></i> General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-address"><i class="fas fa-map-marker-alt mr-1"></i> Address & Statutory</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contacts"><i class="fas fa-users mr-1"></i> Contact Persons <span class="badge badge-primary ml-1" id="contact-count">{{ ($s->contacts ?? collect())->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-custom-fields"><i class="fas fa-sliders-h text-primary mr-1"></i> Custom Fields</a></li>
</ul>

<div class="tab-content pt-3">
    <!-- General Tab -->
    <div class="tab-pane active" id="tab-general">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-truck mr-1 text-primary"></i> General Fields</h6>
            <x-form-layout-customizer
                form-key="master_suppliers.general"
                container-id="supplier-general-fields-grid"
                title="Customize Supplier Form Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="supplier-general-fields-grid">
            <div class="field-wrapper col-md-6" data-field="name" data-label="Supplier Name" data-default-order="1" data-core="1">
                <x-field name="name" label="Supplier Name" :value="$s->name ?? ''" required />
            </div>
            <div class="field-wrapper col-md-6" data-field="currency" data-label="Currency" data-default-order="2">
                <x-field name="currency" label="Currency" :value="$s->currency ?? 'INR'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="purchase_type" data-label="Purchase Type" data-default-order="3" data-core="1">
                <x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate', 'Import' => 'Import']" :selected="$s->purchase_type ?? 'Local'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="purchase_mode" data-label="Purchase Mode" data-default-order="4">
                <x-select name="purchase_mode" label="Purchase Mode" :options="['Credit' => 'Credit', 'Cash' => 'Cash', 'Consignment' => 'Consignment']" :selected="$s->purchase_mode ?? 'Credit'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_limit" data-label="Credit Limit" data-default-order="5">
                <x-field name="credit_limit" label="Credit Limit" type="number" step="0.01" :value="$s->credit_limit ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_balance" data-label="Credit Balance" data-default-order="6">
                <x-field name="credit_balance" label="Credit Balance" type="number" step="0.01" :value="$s->credit_balance ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="credit_days" data-label="Credit Days" data-default-order="7">
                <x-field name="credit_days" label="Credit Days" type="number" :value="$s->credit_days ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="gst_type" data-label="GST Type" data-default-order="8">
                @php
                    $gstTypeOptions = \App\Models\GstType::where('status', true)->orderBy('name')->pluck('name', 'name');
                    if ($gstTypeOptions->isEmpty()) {
                        $gstTypeOptions = collect(['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']);
                    }
                @endphp
                <x-select name="gst_type" label="GST Type" :options="$gstTypeOptions" :selected="$s->gst_type ?? 'Regular'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="mail_type" data-label="Mail Type" data-default-order="9">
                <x-select name="mail_type" label="Mail Type" :options="['None' => 'None', 'Inline HTML' => 'Inline HTML', 'CSV' => 'CSV', 'SAP' => 'SAP', 'EDI' => 'EDI']" :selected="$s->mail_type ?? 'None'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="10">
                <x-bool-select name="status" label="Status" :value="$s->status ?? true" />
            </div>
        </div>
    </div>

    <!-- Address & Statutory Tab -->
    <div class="tab-pane" id="tab-address">
        <x-field name="address" label="Address" :value="$s->address ?? ''" />
        <div class="form-group row">
            <label for="state" class="col-sm-3 col-form-label">State</label>
            <div class="col-sm-6">
                <select name="state" id="state" class="form-control select2 @error('state') is-invalid @enderror">
                    <option value="">-- Select State --</option>
                    <optgroup label="⭐ Top States">
                        <option value="Gujarat" @selected(old('state', $s->state ?? '') === 'Gujarat')>Gujarat</option>
                        <option value="Rajasthan" @selected(old('state', $s->state ?? '') === 'Rajasthan')>Rajasthan</option>
                        <option value="Maharashtra" @selected(old('state', $s->state ?? '') === 'Maharashtra')>Maharashtra</option>
                    </optgroup>
                    <optgroup label="Other States & UTs">
                        @foreach(\App\Helpers\IndianStates::states() as $stVal => $stLabel)
                            @if(!in_array($stVal, ['Gujarat', 'Rajasthan', 'Maharashtra']))
                                <option value="{{ $stVal }}" @selected(old('state', $s->state ?? '') === $stVal)>{{ $stLabel }}</option>
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
            <label for="city" class="col-sm-3 col-form-label">City</label>
            <div class="col-sm-6">
                <select name="city" id="city" class="form-control select2 @error('city') is-invalid @enderror">
                    <option value="">-- Select City --</option>
                    @if(!empty(old('city', $s->city ?? '')))
                        <option value="{{ old('city', $s->city ?? '') }}" selected>{{ old('city', $s->city ?? '') }}</option>
                    @endif
                </select>
                @error('city')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <x-field name="postal_code" label="Postal Code" :value="$s->postal_code ?? ''" />
        <x-field name="country" label="Country" :value="$s->country ?? 'India'" />
        <x-field name="phone" label="Main Phone" :value="$s->phone ?? ''" />
        <x-field name="email" label="Main Email" type="email" :value="$s->email ?? ''" />
        <x-field name="mobile" label="Main Mobile" :value="$s->mobile ?? ''" />
        <x-field name="aadhar_no" label="Aadhar No" :value="$s->aadhar_no ?? ''" />
        <x-field name="pan_no" label="Pan No" :value="$s->pan_no ?? ''" />
        <x-field name="gst_no" label="GST No" :value="$s->gst_no ?? ''" maxlength="15" placeholder="e.g. 22AAAAA0000A1Z5" hint="Format: 2-digit state + 10-char PAN + 1 entity + Z + check (15 chars)" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" />
    </div>

    <!-- Multiple Contacts Tab -->
    <div class="tab-pane" id="tab-contacts">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-secondary"><i class="fas fa-address-book mr-1"></i> Supplier Contact Persons</h5>
            <button type="button" class="btn btn-success btn-sm" id="btn-add-contact">
                <i class="fas fa-plus mr-1"></i> Add Contact Person
            </button>
        </div>

        <div id="contact-rows">
            @php
                $contacts = ($s->contacts ?? collect())->all();
            @endphp

            @forelse ($contacts as $index => $contact)
                <div class="contact-row card card-outline card-secondary mb-3">
                    <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $contact->id }}">
                    <input type="hidden" name="contacts[{{ $index }}][_delete]" class="contact-delete-flag" value="0">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong class="text-muted"><i class="fas fa-user mr-1"></i> Contact Person #{{ $index + 1 }}</strong>
                        <button type="button" class="btn btn-outline-danger btn-xs btn-remove-contact">
                            <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="small font-weight-bold">Contact Person Name</label>
                                <input type="text" name="contacts[{{ $index }}][contact_person]" class="form-control form-control-sm" value="{{ $contact->contact_person }}" placeholder="Full Name">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small font-weight-bold">Designation / Role</label>
                                <input type="text" name="contacts[{{ $index }}][designation]" class="form-control form-control-sm" value="{{ $contact->designation }}" placeholder="e.g. Sales Manager, Accountant">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small font-weight-bold">Mobile Number</label>
                                <input type="text" name="contacts[{{ $index }}][mobile]" class="form-control form-control-sm" value="{{ $contact->mobile }}" placeholder="Mobile">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small font-weight-bold">Phone (Landline / Extension)</label>
                                <input type="text" name="contacts[{{ $index }}][phone]" class="form-control form-control-sm" value="{{ $contact->phone }}" placeholder="Office Phone">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small font-weight-bold">Email Address</label>
                                <input type="email" name="contacts[{{ $index }}][email]" class="form-control form-control-sm" value="{{ $contact->email }}" placeholder="email@domain.com">
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div id="no-contacts-msg" class="alert alert-light border text-center py-4 text-muted">
                    <i class="fas fa-address-book fa-2x mb-2 d-block text-secondary"></i>
                    No additional contact persons added yet. Click <strong>"Add Contact Person"</strong> to add multiple contacts.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Custom Fields Tab -->
    <div class="tab-pane" id="tab-custom-fields">
        <div class="p-2">
            <x-custom-fields-renderer module="Supplier" :model="$s" :showHeader="false" colClass="col-md-6 col-12 mb-3" />
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let contactIndex = {{ count($contacts ?? []) }};

    function updateCount() {
        const visibleRows = document.querySelectorAll('.contact-row:not([style*="display: none"])').length;
        const badge = document.getElementById('contact-count');
        if (badge) badge.textContent = visibleRows;
    }

    document.getElementById('btn-add-contact').addEventListener('click', function () {
        const noContactsMsg = document.getElementById('no-contacts-msg');
        if (noContactsMsg) {
            noContactsMsg.style.display = 'none';
        }

        const template = `
            <div class="contact-row card card-outline card-primary mb-3">
                <input type="hidden" name="contacts[${contactIndex}][id]" value="">
                <input type="hidden" name="contacts[${contactIndex}][_delete]" class="contact-delete-flag" value="0">
                <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                    <strong class="text-primary"><i class="fas fa-user-plus mr-1"></i> New Contact Person</strong>
                    <button type="button" class="btn btn-outline-danger btn-xs btn-remove-contact">
                        <i class="fas fa-trash mr-1"></i> Remove
                    </button>
                </div>
                <div class="card-body py-2">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Contact Person Name</label>
                            <input type="text" name="contacts[${contactIndex}][contact_person]" class="form-control form-control-sm" placeholder="Full Name">
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Designation / Role</label>
                            <input type="text" name="contacts[${contactIndex}][designation]" class="form-control form-control-sm" placeholder="e.g. Sales Manager, Accountant">
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">Mobile Number</label>
                            <input type="text" name="contacts[${contactIndex}][mobile]" class="form-control form-control-sm" placeholder="Mobile">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Phone (Landline / Extension)</label>
                            <input type="text" name="contacts[${contactIndex}][phone]" class="form-control form-control-sm" placeholder="Office Phone">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="small font-weight-bold">Email Address</label>
                            <input type="email" name="contacts[${contactIndex}][email]" class="form-control form-control-sm" placeholder="email@domain.com">
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('contact-rows').insertAdjacentHTML('beforeend', template);
        contactIndex++;
        updateCount();
    });

    document.getElementById('contact-rows').addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-contact')) {
            const row = e.target.closest('.contact-row');
            const deleteFlag = row.querySelector('.contact-delete-flag');
            const idInput = row.querySelector('input[name*="[id]"]');

            if (idInput && idInput.value) {
                deleteFlag.value = '1';
                row.style.display = 'none';
            } else {
                row.remove();
            }

            updateCount();

            const visibleRows = document.querySelectorAll('.contact-row:not([style*="display: none"])').length;
            if (visibleRows === 0) {
                const noContactsMsg = document.getElementById('no-contacts-msg');
                if (noContactsMsg) noContactsMsg.style.display = 'block';
            }
        }
    });

    // Auto-detect and badge tabs containing validation errors
    var $firstInvalidTab = null;
    $('.tab-pane').each(function () {
        var $pane = $(this);
        if ($pane.find('.is-invalid, .text-danger.small:not(:empty), .invalid-feedback:not(:empty)').length > 0) {
            var tabId = $pane.attr('id');
            var $tabLink = $('a[href="#' + tabId + '"]');
            if ($tabLink.length && !$tabLink.find('.badge-danger').length) {
                $tabLink.append(' <span class="badge badge-danger">!</span>');
            }
            if (!$firstInvalidTab) {
                $firstInvalidTab = $tabLink;
            }
        }
    });
    if ($firstInvalidTab) {
        $firstInvalidTab.tab('show');
    }

    if (typeof initIndianStateCity === 'function') {
        initIndianStateCity('#state', '#city', '{{ old('state', $s->state ?? '') }}', '{{ old('city', $s->city ?? '') }}');
    }
});
</script>
<script src="{{ asset('js/indian-states-cities.js') }}"></script>
<script>
$(document).ready(function () {
    if (typeof initIndianStateCity === 'function') {
        initIndianStateCity('#state', '#city', '{{ old('state', $s->state ?? '') }}', '{{ old('city', $s->city ?? '') }}');
    }
});
</script>
