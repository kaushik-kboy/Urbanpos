@php $i = $item ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-taxes">Taxes</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-sales">Sales</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-category">Category</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gst">GST</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <x-field name="ean_upc_code" label="EAN/UPC Code" :value="$i->ean_upc_code ?? ''" />
        <x-field name="name" label="Item Name" :value="$i->name ?? ''" />
        <x-field name="alias" label="Alias" :value="$i->alias ?? ''" />
        <x-select name="brand_id" label="Brand" :options="$brands" :selected="$i->brand_id ?? ''" placeholder="Select a Brand" />
        <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$i->supplier_id ?? ''" placeholder="Select a Supplier" />
        <x-select name="product_type" label="Product Type" :options="['Standard' => 'Standard', 'Serialized' => 'Serialized', 'Service Component' => 'Service Component', 'Gift Voucher' => 'Gift Voucher']" :selected="$i->product_type ?? 'Standard'" />
        <x-field name="cost_price" label="Cost Price" type="number" step="0.01" :value="$i->cost_price ?? 0" />
        <x-field name="landing_cost" label="Landing Cost" type="number" step="0.01" :value="$i->landing_cost ?? 0" />
        <x-field name="sell_price" label="Sell Price" type="number" step="0.01" :value="$i->sell_price ?? 0" />
        <x-field name="mrp" label="MRP (Maximum Retail Price)" type="number" step="0.01" :value="$i->mrp ?? 0" />
        <x-bool-select name="status" label="Status" :value="$i->status ?? true" />
        <x-bool-select name="store_pickup" label="Store Pickup" :value="$i->store_pickup ?? false" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-taxes">
        <x-bool-select name="tax_inclusive" label="Tax Inclusive" :value="$i->tax_inclusive ?? false" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-sales">
        <x-select name="batch_expiry_details" label="Batch/Expiry Details" :options="['Not Required' => 'Not Required', 'Optional' => 'Optional', 'Mandatory' => 'Mandatory', 'Days' => 'Days', 'Month' => 'Month']" :selected="$i->batch_expiry_details ?? 'Not Required'" />
        <x-field name="shelf_life_days" label="Shelf Life (days)" type="number" :value="$i->shelf_life_days ?? ''" />
        <x-field name="minimum_shelf_life_days" label="Minimum Shelf Life (days)" type="number" :value="$i->minimum_shelf_life_days ?? ''" />
        <x-bool-select name="allow_negative_stock" label="Allow Negative Stock" :value="$i->allow_negative_stock ?? false" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-category">
        <x-select name="department_value_id" label="DEPARTMENT" :options="$departmentValues" :selected="$i->department_value_id ?? ''" placeholder="Select a value" />
        <x-select name="category_value_id" label="CATEGORY" :options="$categoryValues" :selected="$i->category_value_id ?? ''" placeholder="Select a value" />
        <x-select name="brand_value_id" label="Brands" :options="$brandValues" :selected="$i->brand_value_id ?? ''" placeholder="Select a value" />
    </div>

    <div class="tab-pane" id="tab-gst">
        <x-select name="gst_tax_id" label="GST Tax" :options="$gstTaxes" :selected="$i->gst_tax_id ?? ''" placeholder="Select a GST tax" />
        <x-field name="hsn_code" label="HSN Code" :value="$i->hsn_code ?? ''" />
    </div>
</div>
