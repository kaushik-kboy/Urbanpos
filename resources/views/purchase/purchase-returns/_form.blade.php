@php
    $ret = $purchaseReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="supplier_id" class="font-weight-bold">Supplier <span class="text-danger">*</span></label>
        <select name="supplier_id" id="supplier_id" class="form-control select2" required>
            <option value="">-- Select Supplier --</option>
            @foreach ($suppliers as $id => $name)
                <option value="{{ $id }}" @selected(old('supplier_id', $ret->supplier_id ?? '') == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label for="branch_id" class="font-weight-bold">Branch <span class="text-danger">*</span></label>
        <select name="branch_id" id="branch_id" class="form-control select2" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $id => $name)
                <option value="{{ $id }}" @selected(old('branch_id', $ret->branch_id ?? '') == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 mb-3">
        <label for="return_date" class="font-weight-bold">Return Date <span class="text-danger">*</span></label>
        <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', optional($ret->return_date ?? now())->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label for="purchase_type" class="font-weight-bold">Purchase Type <span class="text-danger">*</span></label>
        <select name="purchase_type" id="purchase_type" class="form-control" required>
            <option value="Local" @selected(old('purchase_type', $ret->purchase_type ?? 'Local') === 'Local')>Local (CGST + SGST)</option>
            <option value="Interstate" @selected(old('purchase_type', $ret->purchase_type ?? '') === 'Interstate')>Interstate (IGST)</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="purchase_invoice_id" class="font-weight-bold">Original Purchase Invoice</label>
        <div class="input-group">
            <select name="purchase_invoice_id" id="purchase_invoice_id" class="form-control select2">
                <option value="">-- No Original Invoice / Direct Return --</option>
                @foreach ($purchaseInvoices as $id => $no)
                    <option value="{{ $id }}" @selected(old('purchase_invoice_id', $ret->purchase_invoice_id ?? '') == $id)>{{ $no }}</option>
                @endforeach
            </select>
            <div class="input-group-append">
                <button type="button" id="btn-load-invoice" class="btn btn-outline-info" title="Load items from this purchase invoice">
                    <i class="fas fa-file-import mr-1"></i> Load Items
                </button>
            </div>
        </div>
        <small class="text-muted">Select invoice and click "Load Items" to auto-populate returned lines.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label for="supplier_debit_note_no" class="font-weight-bold">Supplier Debit Note No</label>
        <input type="text" name="supplier_debit_note_no" id="supplier_debit_note_no" class="form-control" value="{{ old('supplier_debit_note_no', $ret->supplier_debit_note_no ?? '') }}" placeholder="e.g. DN-2026-001">
    </div>
    <div class="col-md-4 mb-3">
        <label for="supplier_debit_note_date" class="font-weight-bold">Debit Note Date</label>
        <input type="date" name="supplier_debit_note_date" id="supplier_debit_note_date" class="form-control" value="{{ old('supplier_debit_note_date', optional($ret->supplier_debit_note_date ?? null)->format('Y-m-d')) }}">
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 font-weight-bold text-dark">
        <i class="fas fa-boxes mr-1 text-primary"></i> Return Items
    </h5>
    <button type="button" id="pr-add-row" class="btn btn-outline-primary btn-sm font-weight-bold">
        <i class="fas fa-plus-circle mr-1"></i> Add Item Line
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover" id="pr-items-table">
        <thead class="bg-light">
            <tr>
                <th style="min-width: 250px;">Item Description <span class="text-danger">*</span></th>
                <th style="width: 140px;">Exp Date</th>
                <th style="width: 100px;" class="text-right">Qty <span class="text-danger">*</span></th>
                <th style="width: 120px;" class="text-right">Cost Price <span class="text-danger">*</span></th>
                <th style="width: 90px;" class="text-right">Disc %</th>
                <th style="width: 110px;" class="text-right">Disc Amt</th>
                <th style="width: 90px;" class="text-right">GST %</th>
                <th style="width: 130px;" class="text-right">Net Amount</th>
                <th style="width: 45px;" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="pr-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-returns._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-returns._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="2" class="text-right align-middle">Summary Totals:</td>
                <td class="text-right align-middle text-primary" id="footer-pr-qty">0.000</td>
                <td></td>
                <td></td>
                <td class="text-right align-middle text-danger" id="footer-pr-disc">₹0.00</td>
                <td></td>
                <td class="text-right align-middle text-success h6 mb-0" id="footer-pr-net">₹0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<hr>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="remarks" class="font-weight-bold">Remarks / Return Reason</label>
            <textarea name="remarks" id="remarks" rows="4" class="form-control" placeholder="Reason for returning items to supplier (damage, expired, excess stock, etc.)">{{ old('remarks', $ret->remarks ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline card-secondary shadow-none border bg-light">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Items Taxable Amount:</span>
                    <strong id="display-taxable">₹0.00</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total GST Tax:</span>
                    <strong class="text-primary" id="display-gst">₹0.00</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Round Off:</span>
                    <input type="number" step="0.01" name="round_off" id="round_off" value="{{ old('round_off', $ret->round_off ?? 0) }}" class="form-control form-control-sm text-right" style="width: 110px;">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="h5 font-weight-bold mb-0">Total Return Value:</span>
                    <span class="h4 font-weight-bold text-success mb-0" id="display-grand-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="pr-row-template">
    @include('purchase.purchase-returns._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max(count($existingItems), 1) }};

        function recalculateRow(row) {
            const qty = parseFloat(row.querySelector('.pr-qty')?.value) || 0;
            const cost = parseFloat(row.querySelector('.pr-cost')?.value) || 0;
            let discPercent = parseFloat(row.querySelector('.pr-disc-percent')?.value) || 0;
            let discAmount = parseFloat(row.querySelector('.pr-disc-amount')?.value) || 0;
            const gstPercent = parseFloat(row.querySelector('.pr-gst-percent')?.value) || 0;

            const base = qty * cost;
            if (discAmount <= 0 && discPercent > 0) {
                discAmount = Math.round((base * discPercent / 100) * 100) / 100;
                const discAmtInput = row.querySelector('.pr-disc-amount');
                if (discAmtInput && document.activeElement !== discAmtInput) {
                    discAmtInput.value = discAmount ? discAmount.toFixed(2) : '';
                }
            }

            const taxable = Math.max(0, base - discAmount);
            const gstAmount = Math.round((taxable * gstPercent / 100) * 100) / 100;
            const net = taxable + gstAmount;

            const netSpan = row.querySelector('.pr-net-amount');
            if (netSpan) {
                netSpan.innerText = net.toFixed(2);
            }

            return { qty, cost, discAmount, taxable, gstAmount, net };
        }

        function recalculateAll() {
            let totalQty = 0;
            let totalDisc = 0;
            let totalTaxable = 0;
            let totalGst = 0;
            let totalNet = 0;

            document.querySelectorAll('#pr-items-body .pr-item-row').forEach(row => {
                const res = recalculateRow(row);
                totalQty += res.qty;
                totalDisc += res.discAmount;
                totalTaxable += res.taxable;
                totalGst += res.gstAmount;
                totalNet += res.net;
            });

            const roundOff = parseFloat(document.getElementById('round_off')?.value) || 0;
            const grandTotal = totalNet + roundOff;

            document.getElementById('footer-pr-qty').innerText = totalQty.toFixed(3);
            document.getElementById('footer-pr-disc').innerText = '₹' + totalDisc.toFixed(2);
            document.getElementById('footer-pr-net').innerText = '₹' + totalNet.toFixed(2);
            document.getElementById('display-taxable').innerText = '₹' + totalTaxable.toFixed(2);
            document.getElementById('display-gst').innerText = '₹' + totalGst.toFixed(2);
            document.getElementById('display-grand-total').innerText = '₹' + grandTotal.toFixed(2);

            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                const hasValidItems = totalQty > 0 && document.querySelectorAll('#pr-items-body .pr-item-row').length > 0;
                submitBtn.disabled = !hasValidItems;
            }
        }

        document.getElementById('pr-add-row')?.addEventListener('click', function () {
            const template = document.getElementById('pr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('pr-items-body');
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            const newRow = tempWrapper.firstElementChild;
            tbody.appendChild(newRow);
            if (window.jQuery && jQuery.fn.select2) {
                $(newRow).find('.select2').select2({ theme: 'bootstrap4', width: '100%' });
            }
            rowIndex++;
            recalculateAll();
        });

        document.getElementById('pr-items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.pr-row-remove');
            if (!btn) return;
            const rows = document.querySelectorAll('#pr-items-body .pr-item-row');
            if (rows.length <= 1) {
                alert('At least one item row is required.');
                return;
            }
            btn.closest('tr').remove();
            recalculateAll();
        });

        document.getElementById('pr-items-body')?.addEventListener('input', function (e) {
            if (e.target.matches('.pr-qty, .pr-cost, .pr-disc-percent, .pr-disc-amount, .pr-gst-percent')) {
                recalculateAll();
            }
        });

        document.getElementById('round_off')?.addEventListener('input', recalculateAll);

        // Load items from Invoice
        document.getElementById('btn-load-invoice')?.addEventListener('click', function () {
            const invId = document.getElementById('purchase_invoice_id')?.value;
            if (!invId) {
                alert('Please select a Purchase Invoice first.');
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading…';

            fetch(`/purchase/purchase-returns/invoice-items/${invId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.supplier_id) {
                    $('#supplier_id').val(data.supplier_id).trigger('change');
                }
                if (data.branch_id) {
                    $('#branch_id').val(data.branch_id).trigger('change');
                }
                if (data.purchase_type) {
                    $('#purchase_type').val(data.purchase_type).trigger('change');
                }

                if (data.items && data.items.length > 0) {
                    const tbody = document.getElementById('pr-items-body');
                    tbody.innerHTML = '';
                    data.items.forEach((item, idx) => {
                        const template = document.getElementById('pr-row-template').innerHTML;
                        const html = template.replaceAll('__INDEX__', idx);
                        const tempWrapper = document.createElement('tbody');
                        tempWrapper.innerHTML = html;
                        const row = tempWrapper.firstElementChild;

                        const select = row.querySelector('.pr-item-select');
                        if (select) select.value = item.item_id;
                        const qtyInput = row.querySelector('.pr-qty');
                        if (qtyInput) qtyInput.value = item.qty;
                        const costInput = row.querySelector('.pr-cost');
                        if (costInput) costInput.value = item.cost_price;
                        const discPctInput = row.querySelector('.pr-disc-percent');
                        if (discPctInput) discPctInput.value = item.disc_percent;
                        const discAmtInput = row.querySelector('.pr-disc-amount');
                        if (discAmtInput) discAmtInput.value = item.disc_amount;
                        const gstPctInput = row.querySelector('.pr-gst-percent');
                        if (gstPctInput) gstPctInput.value = item.gst_percent;
                        const expInput = row.querySelector('input[type="date"]');
                        if (expInput && item.exp_date) expInput.value = item.exp_date;

                        tbody.appendChild(row);
                        if (window.jQuery && jQuery.fn.select2) {
                            $(row).find('.select2').select2({ theme: 'bootstrap4', width: '100%' });
                        }
                    });
                    rowIndex = data.items.length;
                    recalculateAll();
                } else {
                    alert('No items found in selected invoice.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to load items from invoice.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-import mr-1"></i> Load Items';
            });
        });

        // Initialize calculations
        recalculateAll();
    })();
</script>
@endpush
