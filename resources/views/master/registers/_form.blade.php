@php
    $statuses = ['Active' => 'Active', 'Inactive' => 'Inactive', 'Yet to Active' => 'Yet to Active'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-cash-register mr-1 text-primary"></i> POS Register Configuration</h6>
    <x-form-layout-customizer
        form-key="master_registers.general"
        container-id="register-fields-grid"
        title="Customize POS Register Layout"
    />
</div>

<div class="row g-2 form-fields-grid" id="register-fields-grid">
    <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Location" data-default-order="1" data-core="1">
        <x-select name="branch_id" label="Location" :options="$branches" :selected="$register->branch_id ?? ''" placeholder="Select a branch" />
    </div>
    <div class="field-wrapper col-md-6" data-field="name" data-label="Register Name" data-default-order="2" data-core="1">
        <x-field name="name" label="Register Name" :value="$register->name ?? ''" required />
    </div>
    <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="3">
        <x-select name="status" label="Status" :options="$statuses" :selected="$register->status ?? 'Yet to Active'" />
    </div>
    <div class="field-wrapper col-md-6" data-field="product_type" data-label="Product Type" data-default-order="4">
        <x-field name="product_type" label="Product Type" :value="$register->product_type ?? 'TruePOS'" />
    </div>
    <div class="field-wrapper col-md-6" data-field="inv_seq_no" data-label="Inv Seq No" data-default-order="5">
        <x-field name="inv_seq_no" label="Inv Seq No" type="number" :value="$register->inv_seq_no ?? 1" />
    </div>
    <div class="field-wrapper col-md-6" data-field="device_id" data-label="Device Id" data-default-order="6">
        <x-field name="device_id" label="Device Id" :value="$register->device_id ?? ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="online_sales_allowed" data-label="Online Sales Allowed" data-default-order="7">
        <x-bool-select name="online_sales_allowed" label="Online Sales Allowed" :value="$register->online_sales_allowed ?? false" true-label="Yes" false-label="No" />
    </div>
    <div class="field-wrapper col-md-6" data-field="register_prefix" data-label="Register Prefix" data-default-order="8">
        <x-field name="register_prefix" label="Register Prefix" :value="$register->register_prefix ?? ''" />
    </div>
</div>
