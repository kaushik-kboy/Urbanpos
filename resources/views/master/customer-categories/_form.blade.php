@php
    $businessTypes = ['ALL', 'COCO', 'FRANCHISE', 'BRANCH', 'DISTRIBUTION CENTER', 'SERVICE UNIT', 'FOFO', 'ASP'];
@endphp

<x-field name="name" label="Name" :value="$customerCategory->name ?? ''" />
<x-bool-select name="app_access" label="App Access" :value="$customerCategory->app_access ?? false" true-label="Yes" false-label="No" />
<x-bool-select name="enable_loyalty" label="Enable Loyalty" :value="$customerCategory->enable_loyalty ?? false" true-label="Yes" false-label="No" />
<x-field name="discount_percent" label="Discount Percent" type="number" step="0.01" :value="$customerCategory->discount_percent ?? 0" />
<x-select name="business_type" label="Business Type" :options="array_combine($businessTypes, $businessTypes)" :selected="$customerCategory->business_type ?? 'ALL'" />
<x-bool-select name="status" label="Status" :value="$customerCategory->status ?? true" />
