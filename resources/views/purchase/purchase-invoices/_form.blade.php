@php
    $inv = $purchaseInvoice ?? null;
    $existingItems = $inv?->items ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$inv->supplier_id ?? ''" placeholder="Select a supplier" />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$inv->branch_id ?? ''" placeholder="Select a branch" />
<x-select name="purchase_order_id" label="PO No" :options="$purchaseOrders" :selected="$inv->purchase_order_id ?? ''" placeholder="(direct purchase - no PO)" />
<x-field name="invoice_date" label="Invoice Date" type="date" :value="optional($inv->invoice_date ?? now())->format('Y-m-d')" />
<x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$inv->purchase_type ?? 'Local'" />
<x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$inv->c_form ?? 'Against C-Form'" />
<x-field name="grn_number" label="GRN No" :value="$inv->grn_number ?? ''" />
<x-field name="grn_date" label="GRN Date" type="date" :value="optional($inv->grn_date ?? null)->format('Y-m-d')" />
<x-field name="supplier_inv_no" label="Inv No (Supplier)" :value="$inv->supplier_inv_no ?? ''" />
<x-field name="supplier_inv_date" label="Inv Date (Supplier)" type="date" :value="optional($inv->supplier_inv_date ?? null)->format('Y-m-d')" />
<x-field name="supplier_inv_amount" label="Inv Amount (Supplier)" type="number" step="0.01" :value="$inv->supplier_inv_amount ?? ''" />

<hr>
<h5 class="mb-3">Items</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="pinv-items-table">
        <thead>
            <tr>
                <th style="min-width:220px">Code / Description</th>
                <th style="width:130px">Exp Dt</th>
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
        <tbody id="pinv-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="pinv-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3">Totals</h5>
<x-field name="freight" label="Freight" type="number" step="0.01" :value="$inv->freight ?? 0" />
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$inv->round_off ?? 0" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="$inv->scheme_item_disc_amt ?? 0" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="$inv->other_disc_amt ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$inv->total_extra_cess ?? 0" />
<x-field name="tcs_amount" label="TCS Amt" type="number" step="0.01" :value="$inv->tcs_amount ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$inv->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$inv->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$inv->message ?? ''" />

<template id="pinv-row-template">
    @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        document.getElementById('pinv-add-row').addEventListener('click', function () {
            const html = document.getElementById('pinv-row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('pinv-items-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
        });

        document.getElementById('pinv-items-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.pinv-remove-row');
            if (! btn) return;
            const rows = document.querySelectorAll('#pinv-items-body tr');
            if (rows.length <= 1) return;
            btn.closest('tr').remove();
        });
    })();
</script>
@endpush
