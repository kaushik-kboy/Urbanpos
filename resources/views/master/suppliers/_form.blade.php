@php $s = $supplier ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-contact">Contact details</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <x-field name="name" label="Name" :value="$s->name ?? ''" />
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

    <div class="tab-pane" id="tab-contact">
        <x-field name="address" label="Address" :value="$s->address ?? ''" />
        <x-field name="city" label="City" :value="$s->city ?? ''" />
        <x-field name="postal_code" label="Postal Code" :value="$s->postal_code ?? ''" />
        <x-field name="state" label="State" :value="$s->state ?? ''" />
        <x-field name="country" label="Country" :value="$s->country ?? 'India'" />
        <x-field name="phone" label="Phone" :value="$s->phone ?? ''" />
        <x-field name="email" label="Email" type="email" :value="$s->email ?? ''" />
        <x-field name="mobile" label="Mobile" :value="$s->mobile ?? ''" />
        <x-field name="aadhar_no" label="Aadhar No" :value="$s->aadhar_no ?? ''" />
        <x-field name="pan_no" label="Pan No" :value="$s->pan_no ?? ''" />
        <x-field name="gst_no" label="GST No" :value="$s->gst_no ?? ''" />
    </div>
</div>
