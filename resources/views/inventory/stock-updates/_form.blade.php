@php
    $entry = $stockUpdate ?? null;
    $existingItems = $entry?->items ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="branch_id" label="Location" :options="$branches" :selected="$entry->branch_id ?? ''" placeholder="Select a branch" />
<x-field name="entry_date" label="Date" type="date" :value="optional($entry->entry_date ?? now())->format('Y-m-d')" />

<hr>
<h5 class="mb-3">Items</h5>
<p class="text-muted small">Enter the physically counted Qty. Current Stock is read from the system at the moment you Save, and the difference is posted as a +/- adjustment.</p>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="items-table">
        <thead>
            <tr>
                <th style="min-width:220px">Code / Description</th>
                <th style="width:130px">Exp Dt</th>
                <th style="width:110px">Qty (physical)</th>
                <th style="width:110px">Current Stock</th>
                <th style="width:100px">Sell Price</th>
                <th style="width:100px">MRP</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        <tbody id="items-body">
            @forelse ($existingItems as $index => $line)
                @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<x-textarea name="remarks" label="Remarks" :value="$entry->remarks ?? ''" />

<template id="row-template">
    @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
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

        // Keyboard shortcuts: F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F4') {
                e.preventDefault();
                const firstInput = document.querySelector('#items-body input, #items-body select');
                if (firstInput) firstInput.focus();
            } else if (e.key === 'F6') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) form.submit();
            } else if (e.key === 'F7') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.stock-updates.index') }}";
            } else if (e.key === 'F8') {
                e.preventDefault();
                window.print();
            } else if (e.key === 'F9') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) form.reset();
            } else if (e.key === 'F10') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.stock-updates.index') }}";
            }
        });
    })();
</script>
@endpush
