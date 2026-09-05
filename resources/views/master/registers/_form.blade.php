@php
    $statuses = ['Active' => 'Active', 'Inactive' => 'Inactive', 'Yet to Active' => 'Yet to Active'];
@endphp

<x-select name="branch_id" label="Location" :options="$branches" :selected="$register->branch_id ?? ''" placeholder="Select a branch" />
<x-field name="name" label="Register Name" :value="$register->name ?? ''" />
<x-select name="status" label="Status" :options="$statuses" :selected="$register->status ?? 'Yet to Active'" />
<x-field name="product_type" label="Product Type" :value="$register->product_type ?? 'TruePOS'" />
<x-field name="inv_seq_no" label="Inv Seq No" type="number" :value="$register->inv_seq_no ?? 1" />
<x-field name="device_id" label="Device Id" :value="$register->device_id ?? ''" />
<x-bool-select name="online_sales_allowed" label="Online Sales Allowed" :value="$register->online_sales_allowed ?? false" true-label="Yes" false-label="No" />
<x-field name="register_prefix" label="Register Prefix" :value="$register->register_prefix ?? ''" />
