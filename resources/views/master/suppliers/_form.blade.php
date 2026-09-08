@php $s = $supplier ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general"><i class="fas fa-info-circle mr-1"></i> General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-address"><i class="fas fa-map-marker-alt mr-1"></i> Address & Statutory</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contacts"><i class="fas fa-users mr-1"></i> Contact Persons <span class="badge badge-primary ml-1" id="contact-count">{{ ($s->contacts ?? collect())->count() }}</span></a></li>
</ul>

<div class="tab-content pt-3">
    <!-- General Tab -->
    <div class="tab-pane active" id="tab-general">
        <x-field name="name" label="Supplier Name" :value="$s->name ?? ''" required />
        <x-field name="currency" label="Currency" :value="$s->currency ?? 'INR'" />
        <x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate', 'Import' => 'Import']" :selected="$s->purchase_type ?? 'Local'" />
        <x-select name="purchase_mode" label="Purchase Mode" :options="['Credit' => 'Credit', 'Cash' => 'Cash', 'Consignment' => 'Consignment']" :selected="$s->purchase_mode ?? 'Credit'" />
        <x-field name="credit_limit" label="Credit Limit" type="number" step="0.01" :value="$s->credit_limit ?? 0" />
        <x-field name="credit_balance" label="Credit Balance" type="number" step="0.01" :value="$s->credit_balance ?? 0" />
        <x-field name="credit_days" label="Credit Days" type="number" :value="$s->credit_days ?? 0" />
        <x-select name="gst_type" label="GST Type" :options="['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']" :selected="$s->gst_type ?? 'Regular'" />
        <x-select name="mail_type" label="Mail Type" :options="['None' => 'None', 'Inline HTML' => 'Inline HTML', 'CSV' => 'CSV', 'SAP' => 'SAP', 'EDI' => 'EDI']" :selected="$s->mail_type ?? 'None'" />
        <x-bool-select name="status" label="Status" :value="$s->status ?? true" />
    </div>

    <!-- Address & Statutory Tab -->
    <div class="tab-pane" id="tab-address">
        <x-field name="address" label="Address" :value="$s->address ?? ''" />
        <x-field name="city" label="City" :value="$s->city ?? ''" />
        <x-field name="postal_code" label="Postal Code" :value="$s->postal_code ?? ''" />
        <x-field name="state" label="State" :value="$s->state ?? ''" />
        <x-field name="country" label="Country" :value="$s->country ?? 'India'" />
        <x-field name="phone" label="Main Phone" :value="$s->phone ?? ''" />
        <x-field name="email" label="Main Email" type="email" :value="$s->email ?? ''" />
        <x-field name="mobile" label="Main Mobile" :value="$s->mobile ?? ''" />
        <x-field name="aadhar_no" label="Aadhar No" :value="$s->aadhar_no ?? ''" />
        <x-field name="pan_no" label="Pan No" :value="$s->pan_no ?? ''" />
        <x-field name="gst_no" label="GST No" :value="$s->gst_no ?? ''" />
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
});
</script>
