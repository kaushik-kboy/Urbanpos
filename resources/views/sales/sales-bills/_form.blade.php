@php
    $bill = $salesBill ?? null;
    $existingItems = $bill?->items ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="customer_id" label="Customer" :options="$customers" :selected="$bill->customer_id ?? ''" placeholder="Select a customer" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$bill->branch_id ?? ''" placeholder="Select a branch" />
<x-field name="bill_date" label="Bill Date" type="date" :value="optional($bill->bill_date ?? now())->format('Y-m-d')" />
<x-select name="invoice_type" label="Invoice Type" :options="['Retail Invoice' => 'Retail Invoice', 'Tax Invoice' => 'Tax Invoice', 'Exempted' => 'Exempted']" :selected="$bill->invoice_type ?? 'Retail Invoice'" />
<x-select name="delivery_type" label="Delivery Type" :options="['Delivered' => 'Delivered', 'Home Delivery' => 'Home Delivery', 'Pickup' => 'Pickup']" :selected="$bill->delivery_type ?? 'Delivered'" />
<x-field name="delivery_time" label="Delivery Time" type="time" :value="$bill->delivery_time ?? ''" />
<x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$bill->sales_type ?? 'Local'" />
<x-field name="payment_type" label="Payment Type" :value="$bill->payment_type ?? 'None'" />

<hr>
<h5 class="mb-3">Items</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="items-table">
        <thead>
            <tr>
                <th style="min-width:220px">Code / Description</th>
                <th style="width:130px">Exp Dt</th>
                <th style="width:90px">Qty</th>
                <th style="width:100px">Sell Price</th>
                <th style="width:100px">MRP</th>
                <th style="width:80px">Disc %</th>
                <th style="width:100px">Disc Amount</th>
                <th style="width:80px">GST%</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        <tbody id="items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3">Totals</h5>
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$bill->round_off ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$bill->total_extra_cess ?? 0" />
<x-field name="gst_calamity_cess" label="GST Calamity Cess" type="number" step="0.01" :value="$bill->gst_calamity_cess ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$bill->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$bill->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$bill->message ?? ''" />

<template id="row-template">
    @include('sales.sales-bills._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        document.getElementById('add-row').addEventListener('click', function () {
            const html = document.getElementById('row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('items-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
        });

        document.getElementById('items-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.row-remove');
            if (! btn) return;
            const rows = document.querySelectorAll('#items-body tr');
            if (rows.length <= 1) return;
            btn.closest('tr').remove();
        });
    })();
</script>
@endpush
