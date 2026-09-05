@php
    $v = $voucher ?? null;
    $existingLines = $v?->lines ?? collect();
@endphp

<h5 class="mb-3">Header</h5>
<x-select name="voucher_type" label="Transaction" :options="['Payment' => 'Payment', 'Receipt' => 'Receipt', 'Journal' => 'Journal', 'Contra' => 'Contra']" :selected="$v->voucher_type ?? 'Payment'" />
<x-field name="voucher_date" label="Date" type="date" :value="optional($v->voucher_date ?? now())->format('Y-m-d')" />
<x-select name="branch_id" label="Location" :options="$branches" :selected="$v->branch_id ?? ''" placeholder="Select a branch" />

<hr>
<h5 class="mb-3">Particulars</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="lines-table">
        <thead>
            <tr>
                <th style="min-width:250px">Particulars (Ledger)</th>
                <th style="width:140px">Debit</th>
                <th style="width:140px">Credit</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        <tbody id="lines-body">
            @forelse ($existingLines as $index => $line)
                @include('finance.vouchers._line-row', ['ledgers' => $ledgers, 'index' => $index, 'line' => $line])
            @empty
                @include('finance.vouchers._line-row', ['ledgers' => $ledgers, 'index' => 0, 'line' => null])
                @include('finance.vouchers._line-row', ['ledgers' => $ledgers, 'index' => 1, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<button type="button" id="add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<x-textarea name="narration" label="Narration" :value="$v->narration ?? ''" />

<template id="row-template">
    @include('finance.vouchers._line-row', ['ledgers' => $ledgers, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max($existingLines->count(), 2) }};

        document.getElementById('add-row').addEventListener('click', function () {
            const html = document.getElementById('row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('lines-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
        });

        document.getElementById('lines-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.row-remove');
            if (! btn) return;
            const rows = document.querySelectorAll('#lines-body tr');
            if (rows.length <= 2) return;
            btn.closest('tr').remove();
        });
    })();
</script>
@endpush
