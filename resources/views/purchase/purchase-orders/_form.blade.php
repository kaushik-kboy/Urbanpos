@php
    $po = $purchaseOrder ?? null;
    $existingItems = $po?->items ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$po->supplier_id ?? ''" placeholder="Select a supplier" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$po->branch_id ?? ''" placeholder="Select a branch" />
<x-field name="po_date" label="PO Date" type="date" :value="optional($po->po_date ?? now())->format('Y-m-d')" />
<x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$po->purchase_type ?? 'Local'" />
<x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$po->c_form ?? 'Against C-Form'" />
<x-select name="status" label="Status" :options="['Open' => 'Open', 'Closed' => 'Closed', 'Cancelled' => 'Cancelled']" :selected="$po->status ?? 'Open'" />

<hr>
<h5 class="mb-3">Items</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="po-items-table">
        <thead>
            <tr>
                <th style="min-width:220px">Code / Description</th>
                <th style="width:90px">Qty</th>
                <th style="width:90px">Free</th>
                <th style="width:100px">Cost Price</th>
                <th style="width:100px">Sell Price</th>
                <th style="width:100px">MRP</th>
                <th style="width:80px">Disc %</th>
                <th style="width:100px">Disc Amount</th>
                <th style="width:80px">GST%</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        <tbody id="po-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="po-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3">Totals</h5>
<x-field name="freight" label="Freight" type="number" step="0.01" :value="$po->freight ?? 0" />
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$po->round_off ?? 0" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="$po->scheme_item_disc_amt ?? 0" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="$po->other_disc_amt ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$po->total_extra_cess ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$po->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$po->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$po->message ?? ''" />

<template id="po-row-template">
    @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        document.getElementById('po-add-row').addEventListener('click', function () {
            const html = document.getElementById('po-row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('po-items-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
        });

        document.getElementById('po-items-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.po-remove-row');
            if (! btn) return;
            const rows = document.querySelectorAll('#po-items-body tr');
            if (rows.length <= 1) return;
            btn.closest('tr').remove();
        });
    })();
</script>
@endpush
