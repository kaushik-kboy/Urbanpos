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
        <x-field name="name" label="Branch Name" :value="$b->name ?? ''" />
        <x-field name="address_line1" label="Address Line1" :value="$b->address_line1 ?? ''" />
        <x-field name="address_line2" label="Address Line2" :value="$b->address_line2 ?? ''" />
        <x-field name="city" label="City" :value="$b->city ?? ''" />
        <x-field name="postal_code" label="Postal Code" :value="$b->postal_code ?? ''" />
        <x-field name="state" label="State" :value="$b->state ?? ''" />
        <x-field name="country" label="Country" :value="$b->country ?? 'India'" />
        <x-field name="contact_person" label="Contact" :value="$b->contact_person ?? ''" />
        <x-field name="phone" label="Phone" :value="$b->phone ?? ''" />
        <x-field name="email" label="Email" type="email" :value="$b->email ?? ''" />
        <x-field name="mobile" label="Mobile" :value="$b->mobile ?? ''" />
        <x-field name="language" label="Language" :value="$b->language ?? 'ENGLISH'" />
        <x-field name="area_code" label="Area Code" :value="$b->area_code ?? ''" />
        <x-field name="circle_code" label="Circle Code" :value="$b->circle_code ?? ''" />
        <x-select name="business_type" label="Business Type" :options="array_combine($businessTypes, $businessTypes)" :selected="$b->business_type ?? 'COCO'" />
        <x-bool-select name="webstore" label="Webstore" :value="$b->webstore ?? false" true-label="Yes" false-label="No" />
        <x-field name="erp_code" label="ERP Code" :value="$b->erp_code ?? ''" />
        <x-field name="country_code" label="Country Code" :value="$b->country_code ?? '1'" />
        <x-field name="license_id" label="License ID" :value="$b->license_id ?? ''" />
        <x-field name="cst" label="CST" :value="$b->cst ?? ''" />
        <x-field name="website_link" label="Website Link" :value="$b->website_link ?? ''" />
        <x-field name="social_media_link" label="Social Media Link" :value="$b->social_media_link ?? ''" />
        <x-bool-select name="enable_thirdparty_loyalty" label="Enable Thirdparty Loyalty" :value="$b->enable_thirdparty_loyalty ?? false" true-label="Yes" false-label="No" />
        <x-bool-select name="status" label="Status" :value="$b->status ?? true" />
    </div>

    <div class="tab-pane" id="tab-gst">
        <x-field name="gst_no" label="GST No" :value="$b->gst_no ?? ''" />
        <x-field name="pan_no" label="Pan No" :value="$b->pan_no ?? ''" />
        <x-select name="gst_type" label="GST Type" :options="['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']" :selected="$b->gst_type ?? 'Regular'" />
        <x-select name="gst_filing" label="GST Filing" :options="['Monthly' => 'Monthly', 'Quarterly' => 'Quarterly']" :selected="$b->gst_filing ?? 'Monthly'" />
    </div>
</div>
