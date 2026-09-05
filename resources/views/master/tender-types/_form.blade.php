@php
    $types = ['Cash', 'Card', 'Coupon', 'Wallet', 'Credit', 'Finance'];
@endphp

<x-field name="name" label="Name" :value="$tenderType->name ?? ''" />
<x-select name="type" label="Type" :options="array_combine($types, $types)" :selected="$tenderType->type ?? 'Cash'" />
<x-field name="mode" label="Mode" :value="$tenderType->mode ?? 'Manual'" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$tenderType->branch_id ?? ''" placeholder="GLOBAL" />
<x-bool-select name="service_applicable" label="Service Applicable" :value="$tenderType->service_applicable ?? false" true-label="Yes" false-label="No" />
<x-field name="service_charge_perc" label="Service Charge Perc" type="number" step="0.01" :value="$tenderType->service_charge_perc ?? 0" />
<x-bool-select name="mandate_refno" label="Mandate Refno" :value="$tenderType->mandate_refno ?? false" true-label="Yes" false-label="No" />
<x-bool-select name="status" label="Status" :value="$tenderType->status ?? true" />
