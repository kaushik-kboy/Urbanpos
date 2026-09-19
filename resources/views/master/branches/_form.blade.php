@php
    $businessTypes = ['COCO', 'FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'];
    $b = $branch ?? null;
@endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gst">GST</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-building mr-1 text-primary"></i> General Branch Info</h6>
            <x-form-layout-customizer
                form-key="master_branches.general"
                container-id="branch-general-fields-grid"
                title="Customize Branch Form Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="branch-general-fields-grid">
            <div class="field-wrapper col-md-6" data-field="name" data-label="Branch Name" data-default-order="1" data-core="1">
                <x-field name="name" label="Branch Name" :value="$b->name ?? ''" required />
            </div>
            <div class="field-wrapper col-md-6" data-field="business_type" data-label="Business Type" data-default-order="2" data-core="1">
                <x-select name="business_type" label="Business Type" :options="array_combine($businessTypes, $businessTypes)" :selected="$b->business_type ?? 'COCO'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="contact_person" data-label="Contact Person" data-default-order="3">
                <x-field name="contact_person" label="Contact" :value="$b->contact_person ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="mobile" data-label="Mobile" data-default-order="4">
                <x-field name="mobile" label="Mobile" :value="$b->mobile ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="phone" data-label="Phone" data-default-order="5">
                <x-field name="phone" label="Phone" :value="$b->phone ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="email" data-label="Email" data-default-order="6">
                <x-field name="email" label="Email" type="email" :value="$b->email ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="address_line1" data-label="Address Line 1" data-default-order="7">
                <x-field name="address_line1" label="Address Line1" :value="$b->address_line1 ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="address_line2" data-label="Address Line 2" data-default-order="8">
                <x-field name="address_line2" label="Address Line2" :value="$b->address_line2 ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="city" data-label="City" data-default-order="9">
                <x-field name="city" label="City" :value="$b->city ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="postal_code" data-label="Postal Code" data-default-order="10">
                <x-field name="postal_code" label="Postal Code" :value="$b->postal_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="state" data-label="State" data-default-order="11">
                <x-field name="state" label="State" :value="$b->state ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="country" data-label="Country" data-default-order="12">
                <x-field name="country" label="Country" :value="$b->country ?? 'India'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="area_code" data-label="Area Code" data-default-order="13">
                <x-field name="area_code" label="Area Code" :value="$b->area_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="circle_code" data-label="Circle Code" data-default-order="14">
                <x-field name="circle_code" label="Circle Code" :value="$b->circle_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="erp_code" data-label="ERP Code" data-default-order="15">
                <x-field name="erp_code" label="ERP Code" :value="$b->erp_code ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="country_code" data-label="Country Code" data-default-order="16">
                <x-field name="country_code" label="Country Code" :value="$b->country_code ?? '1'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="license_id" data-label="License ID" data-default-order="17">
                <x-field name="license_id" label="License ID" :value="$b->license_id ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="cst" data-label="CST" data-default-order="18">
                <x-field name="cst" label="CST" :value="$b->cst ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="website_link" data-label="Website Link" data-default-order="19">
                <x-field name="website_link" label="Website Link" :value="$b->website_link ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="social_media_link" data-label="Social Media Link" data-default-order="20">
                <x-field name="social_media_link" label="Social Media Link" :value="$b->social_media_link ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="language" data-label="Language" data-default-order="21">
                <x-field name="language" label="Language" :value="$b->language ?? 'ENGLISH'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="webstore" data-label="Webstore" data-default-order="22">
                <x-bool-select name="webstore" label="Webstore" :value="$b->webstore ?? false" true-label="Yes" false-label="No" />
            </div>
            <div class="field-wrapper col-md-6" data-field="enable_thirdparty_loyalty" data-label="Enable Thirdparty Loyalty" data-default-order="23">
                <x-bool-select name="enable_thirdparty_loyalty" label="Enable Thirdparty Loyalty" :value="$b->enable_thirdparty_loyalty ?? false" true-label="Yes" false-label="No" />
            </div>
            <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="24">
                <x-bool-select name="status" label="Status" :value="$b->status ?? true" />
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-gst">
        <x-field name="gst_no" label="GST No" :value="$b->gst_no ?? ''" maxlength="15" placeholder="e.g. 22AAAAA0000A1Z5" hint="Format: 2-digit state + 10-char PAN + 1 entity + Z + check (15 chars)" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);" />
        <x-field name="pan_no" label="Pan No" :value="$b->pan_no ?? ''" />
        <x-select name="gst_type" label="GST Type" :options="['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']" :selected="$b->gst_type ?? 'Regular'" />
        <x-select name="gst_filing" label="GST Filing" :options="['Monthly' => 'Monthly', 'Quarterly' => 'Quarterly']" :selected="$b->gst_filing ?? 'Monthly'" />
    </div>
</div>
