@push('css')
    <link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
@endpush

@php
    $v = $voucher ?? null;
    $existingLines = $v?->lines ?? collect();
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-invoice mr-1 text-primary"></i> Voucher Header</h5>
    <x-form-layout-customizer
        form-key="finance_vouchers.header"
        container-id="voucher-header-grid"
        title="Customize Voucher Header"
    />
</div>

<div class="row g-2 form-fields-grid tx-header-fields-grid mb-3" id="voucher-header-grid">
    <div class="field-wrapper col-md-4" data-field="voucher_type" data-label="Transaction Type" data-default-order="1" data-core="1">
        <x-select name="voucher_type" label="Transaction" :options="['Payment' => 'Payment', 'Receipt' => 'Receipt', 'Journal' => 'Journal', 'Contra' => 'Contra']" :selected="$v->voucher_type ?? 'Payment'" />
    </div>
    <div class="field-wrapper col-md-4" data-field="voucher_date" data-label="Voucher Date" data-default-order="2" data-core="1">
        <x-field name="voucher_date" label="Date" type="date" :value="optional($v->voucher_date ?? now())->format('Y-m-d')" required />
    </div>
    <div class="field-wrapper col-md-4" data-field="branch_id" data-label="Location" data-default-order="3" data-core="1">
        <x-select name="branch_id" label="Location" :options="$branches" :selected="$v->branch_id ?? ''" placeholder="Select a branch" />
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-list-ol mr-1 text-primary"></i> Particulars (Ledgers)</h6>
    <div class="d-flex align-items-center">
        <span class="badge badge-secondary mr-2" id="voucher-balance-status"><i class="fas fa-balance-scale mr-1"></i> Checking balance...</span>
        <button type="button" id="add-row" class="btn btn-outline-primary btn-xs"><i class="fas fa-plus mr-1"></i> Add Row</button>
    </div>
</div>

<div class="table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-items-dense" id="lines-table">
        <thead>
            <tr>
                <th style="min-width:250px">Particulars (Ledger)</th>
                <th style="width:140px" class="text-right">Debit</th>
                <th style="width:140px" class="text-right">Credit</th>
                <th style="width:40px" class="text-center"></th>
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
        <tfoot class="bg-light font-weight-bold" style="position: sticky; bottom: 0; z-index: 5;">
            <tr>
                <td class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary" id="footer-total-debit">0.00</td>
                <td class="text-right align-middle text-success" id="footer-total-credit">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-2 mb-3">
    <button type="button" id="add-row-bottom" class="btn btn-link btn-sm p-0"><i class="fas fa-plus-circle mr-1"></i> Add Row</button>
    <div id="voucher-diff-hint" class="small"></div>
</div>

<hr>
<x-textarea name="narration" label="Narration" :value="$v->narration ?? ''" />

<template id="row-template">
    @include('finance.vouchers._line-row', ['ledgers' => $ledgers, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max($existingLines->count(), 2) }};

        function addRow() {
            const html = document.getElementById('row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('lines-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
            recalcVoucherTotals();
        }

        const addRowBtn = document.getElementById('add-row');
        if (addRowBtn) {
            addRowBtn.addEventListener('click', addRow);
        }
        const addRowBottomBtn = document.getElementById('add-row-bottom');
        if (addRowBottomBtn) {
            addRowBottomBtn.addEventListener('click', addRow);
        }

        document.getElementById('lines-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.row-remove');
            if (! btn) return;
            const rows = document.querySelectorAll('#lines-body tr');
            if (rows.length <= 2) {
                alert('A voucher must have at least 2 entry rows.');
                return;
            }
            btn.closest('tr').remove();
            recalcVoucherTotals();
        });

        function recalcVoucherTotals() {
            let totalDebit = 0;
            let totalCredit = 0;
            const rows = document.querySelectorAll('#lines-body tr');

            rows.forEach(function (row) {
                const debitInput = row.querySelector('input[name*="[debit]"]');
                const creditInput = row.querySelector('input[name*="[credit]"]');
                const debitVal = debitInput ? parseFloat(debitInput.value) || 0 : 0;
                const creditVal = creditInput ? parseFloat(creditInput.value) || 0 : 0;

                totalDebit += debitVal;
                totalCredit += creditVal;
            });

            // Update footer totals
            const footerDebit = document.getElementById('footer-total-debit');
            const footerCredit = document.getElementById('footer-total-credit');
            if (footerDebit) footerDebit.textContent = totalDebit.toFixed(2);
            if (footerCredit) footerCredit.textContent = totalCredit.toFixed(2);

            // Update lines badge
            const linesBadge = document.getElementById('voucher-total-lines-badge');
            if (linesBadge) {
                linesBadge.textContent = rows.length + (rows.length === 1 ? ' Entry' : ' Entries');
            }

            // Update main footer display total & status
            const displayTotal = document.getElementById('display-voucher-total');
            const statusBadge = document.getElementById('voucher-balance-status');
            const diffHint = document.getElementById('voucher-diff-hint');

            const diff = Math.abs(totalDebit - totalCredit);
            const isBalanced = diff < 0.001 && (totalDebit > 0 || totalCredit > 0);

            if (displayTotal) {
                displayTotal.textContent = totalDebit.toFixed(2);
            }

            if (statusBadge) {
                if (totalDebit === 0 && totalCredit === 0) {
                    statusBadge.className = 'badge badge-secondary';
                    statusBadge.innerHTML = '<i class="fas fa-balance-scale mr-1"></i> Empty (0.00)';
                } else if (isBalanced) {
                    statusBadge.className = 'badge badge-success';
                    statusBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Balanced (' + totalDebit.toFixed(2) + ')';
                } else {
                    statusBadge.className = 'badge badge-danger';
                    statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Mismatched';
                }
            }

            if (diffHint) {
                if (totalDebit === 0 && totalCredit === 0) {
                    diffHint.innerHTML = '';
                } else if (isBalanced) {
                    diffHint.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check mr-1"></i> Total Debit and Credit are equal.</span>';
                } else {
                    diffHint.innerHTML = '<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Difference: ' + diff.toFixed(2) + ' (Debit: ' + totalDebit.toFixed(2) + ', Credit: ' + totalCredit.toFixed(2) + ')</span>';
                }
            }
        }

        // Listen for user changes on debit and credit inputs
        document.getElementById('lines-body').addEventListener('input', function (e) {
            if (e.target.matches('input[name*="[debit]"], input[name*="[credit]"]')) {
                recalcVoucherTotals();
            }
        });

        // Form reset handler
        const resetBtn = document.getElementById('btn-reset-form');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (confirm('Are you sure you want to reset the form?')) {
                    const form = document.getElementById('voucher-form');
                    if (form) {
                        form.reset();
                        setTimeout(recalcVoucherTotals, 50);
                    }
                }
            });
        }

        // Initial calculation on load
        recalcVoucherTotals();
    })();
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
<script>
    $(document).ready(function () {
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#lines-table',
                minHeight: 180
            });
        }
    });
</script>
@endpush
