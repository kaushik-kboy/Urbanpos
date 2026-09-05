@php
    $entry = $openingStock ?? null;
    $existingItems = $entry?->items ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="branch_id" label="Location" :options="$branches" :selected="$entry->branch_id ?? ''" placeholder="Select a branch" />
<x-field name="entry_date" label="Date" type="date" :value="optional($entry->entry_date ?? now())->format('Y-m-d')" />

<hr>
<h5 class="mb-3">Items</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="items-table">
        <thead>
            <tr>
                <th style="min-width:200px">Code / Description</th>
                <th style="min-width:150px">Supplier</th>
                <th style="width:130px">Exp Dt</th>
                <th style="width:90px">Qty</th>
                <th style="width:100px">Cost Price</th>
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
                @include('inventory.opening-stocks._item-row', ['items' => $items, 'suppliers' => $suppliers, 'index' => $index, 'line' => $line])
            @empty
                @include('inventory.opening-stocks._item-row', ['items' => $items, 'suppliers' => $suppliers, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<x-textarea name="remarks" label="Remarks" :value="$entry->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$entry->message ?? ''" />

<template id="row-template">
    @include('inventory.opening-stocks._item-row', ['items' => $items, 'suppliers' => $suppliers, 'index' => '__INDEX__', 'line' => null])
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
