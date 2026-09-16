@php
    $ret = $salesReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="customer_id" class="font-weight-bold">Customer <span class="text-danger">*</span></label>
        <select name="customer_id" id="customer_id" class="form-control select2" required>
            <option value="">-- Select Customer --</option>
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}" @selected(old('customer_id', $ret->customer_id ?? '') == $id)>{{ $name }}</option>
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
        <label for="sales_type" class="font-weight-bold">Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="sales_type" class="form-control" required>
            <option value="Local" @selected(old('sales_type', $ret->sales_type ?? 'Local') === 'Local')>Local (CGST + SGST)</option>
            <option value="Interstate" @selected(old('sales_type', $ret->sales_type ?? '') === 'Interstate')>Interstate (IGST)</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="sales_bill_id" class="font-weight-bold">Original Sales Bill</label>
        <div class="input-group">
            <select name="sales_bill_id" id="sales_bill_id" class="form-control select2">
                <option value="">-- No Original Bill / Direct Return --</option>
                @foreach ($salesBills as $id => $no)
                    <option value="{{ $id }}" @selected(old('sales_bill_id', $ret->sales_bill_id ?? '') == $id)>{{ $no }}</option>
                @endforeach
            </select>
            <div class="input-group-append">
                <button type="button" id="btn-load-bill" class="btn btn-outline-info" title="Load items from this sales bill">
                    <i class="fas fa-file-import mr-1"></i> Load Items
                </button>
            </div>
        </div>
        <small class="text-muted">Select bill and click "Load Items" to auto-populate returned lines.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label for="return_mode" class="font-weight-bold">Return Mode <span class="text-danger">*</span></label>
        <select name="return_mode" id="return_mode" class="form-control" required>
            @foreach (['Cash' => 'Cash', 'Credit Note' => 'Credit Note', 'Wallet' => 'Wallet', 'Card' => 'Card', 'RRN' => 'RRN'] as $val => $lbl)
                <option value="{{ $val }}" @selected(old('return_mode', $ret->return_mode ?? 'Cash') === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 font-weight-bold text-dark">
        <i class="fas fa-boxes mr-1 text-primary"></i> Return Items
    </h5>
    <button type="button" id="sr-add-row" class="btn btn-outline-primary btn-sm font-weight-bold">
        <i class="fas fa-plus-circle mr-1"></i> Add Item Line
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover" id="sr-items-table">
        <thead class="bg-light">
            <tr>
                <th style="min-width: 250px;">Item Description <span class="text-danger">*</span></th>
                <th style="width: 140px;">Exp Date</th>
                <th style="width: 100px;" class="text-right">Qty <span class="text-danger">*</span></th>
                <th style="width: 110px;" class="text-right">Sell Price <span class="text-danger">*</span></th>
                <th style="width: 100px;" class="text-right">MRP</th>
                <th style="width: 85px;" class="text-right">Disc %</th>
                <th style="width: 100px;" class="text-right">Disc Amt</th>
                <th style="width: 85px;" class="text-right">GST %</th>
                <th style="width: 120px;" class="text-right">Net Amount</th>
                <th style="width: 45px;" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="sr-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-returns._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-returns._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="2" class="text-right align-middle">Summary Totals:</td>
                <td class="text-right align-middle text-primary" id="footer-sr-qty">0.000</td>
                <td colspan="2"></td>
                <td colspan="2" class="text-right align-middle text-danger" id="footer-sr-disc">₹0.00</td>
                <td></td>
                <td class="text-right align-middle text-success h6 mb-0" id="footer-sr-net">₹0.00</td>
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
            <textarea name="remarks" id="remarks" rows="4" class="form-control" placeholder="Reason for customer return...">{{ old('remarks', $ret->remarks ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline card-secondary shadow-none border bg-light">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Items Taxable Amount:</span>
                    <strong id="display-sr-taxable">₹0.00</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total GST Tax:</span>
                    <strong class="text-primary" id="display-sr-gst">₹0.00</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Round Off:</span>
                    <input type="number" step="0.01" name="round_off" id="round_off" value="{{ old('round_off', $ret->round_off ?? 0) }}" class="form-control form-control-sm text-right" style="width: 110px;">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Total Extra Cess:</span>
                    <input type="number" step="0.01" min="0" name="total_extra_cess" id="total_extra_cess" value="{{ old('total_extra_cess', $ret->total_extra_cess ?? 0) }}" class="form-control form-control-sm text-right" style="width: 110px;">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">GST Calamity Cess:</span>
                    <input type="number" step="0.01" min="0" name="gst_calamity_cess" id="gst_calamity_cess" value="{{ old('gst_calamity_cess', $ret->gst_calamity_cess ?? 0) }}" class="form-control form-control-sm text-right" style="width: 110px;">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="h5 font-weight-bold mb-0">Net Return Amount:</span>
                    <span class="h4 font-weight-bold text-success mb-0" id="display-sr-grand-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="sr-row-template">
    @include('sales.sales-returns._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max(count($existingItems), 1) }};

        function recalculateRow(row) {
            const qty = parseFloat(row.querySelector('.sr-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.sr-price')?.value) || 0;
            let discPercent = parseFloat(row.querySelector('.sr-disc-percent')?.value) || 0;
            let discAmount = parseFloat(row.querySelector('.sr-disc-amount')?.value) || 0;
            const gstPercent = parseFloat(row.querySelector('.sr-gst-percent')?.value) || 0;

            const base = qty * price;
            if (discAmount <= 0 && discPercent > 0) {
                discAmount = Math.round((base * discPercent / 100) * 100) / 100;
                const discAmtInput = row.querySelector('.sr-disc-amount');
                if (discAmtInput && document.activeElement !== discAmtInput) {
                    discAmtInput.value = discAmount ? discAmount.toFixed(2) : '';
                }
            }

            const taxable = Math.max(0, base - discAmount);
            const gstAmount = Math.round((taxable * gstPercent / 100) * 100) / 100;
            const net = taxable + gstAmount;

            const netSpan = row.querySelector('.sr-net-amount');
            if (netSpan) {
                netSpan.innerText = net.toFixed(2);
            }

            return { qty, price, discAmount, taxable, gstAmount, net };
        }

        function recalculateAll() {
            let totalQty = 0;
            let totalDisc = 0;
            let totalTaxable = 0;
            let totalGst = 0;
            let totalNet = 0;

            document.querySelectorAll('#sr-items-body .sr-item-row').forEach(row => {
                const res = recalculateRow(row);
                totalQty += res.qty;
                totalDisc += res.discAmount;
                totalTaxable += res.taxable;
                totalGst += res.gstAmount;
                totalNet += res.net;
            });

            const roundOff = parseFloat(document.getElementById('round_off')?.value) || 0;
            const extraCess = parseFloat(document.getElementById('total_extra_cess')?.value) || 0;
            const calamityCess = parseFloat(document.getElementById('gst_calamity_cess')?.value) || 0;
            const grandTotal = totalNet + roundOff + extraCess + calamityCess;

            document.getElementById('footer-sr-qty').innerText = totalQty.toFixed(3);
            document.getElementById('footer-sr-disc').innerText = '₹' + totalDisc.toFixed(2);
            document.getElementById('footer-sr-net').innerText = '₹' + totalNet.toFixed(2);
            document.getElementById('display-sr-taxable').innerText = '₹' + totalTaxable.toFixed(2);
            document.getElementById('display-sr-gst').innerText = '₹' + totalGst.toFixed(2);
            document.getElementById('display-sr-grand-total').innerText = '₹' + grandTotal.toFixed(2);

            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                const hasValidItems = totalQty > 0 && document.querySelectorAll('#sr-items-body .sr-item-row').length > 0;
                submitBtn.disabled = !hasValidItems;
            }
        }

        document.getElementById('sr-add-row')?.addEventListener('click', function () {
            const template = document.getElementById('sr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('sr-items-body');
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

        document.getElementById('sr-items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sr-row-remove');
            if (!btn) return;
            const rows = document.querySelectorAll('#sr-items-body .sr-item-row');
            if (rows.length <= 1) {
                alert('At least one item row is required.');
                return;
            }
            btn.closest('tr').remove();
            recalculateAll();
        });

        document.getElementById('sr-items-body')?.addEventListener('input', function (e) {
            if (e.target.matches('.sr-qty, .sr-price, .sr-mrp, .sr-disc-percent, .sr-disc-amount, .sr-gst-percent')) {
                recalculateAll();
            }
        });

        document.getElementById('round_off')?.addEventListener('input', recalculateAll);
        document.getElementById('total_extra_cess')?.addEventListener('input', recalculateAll);
        document.getElementById('gst_calamity_cess')?.addEventListener('input', recalculateAll);

        // Load items from Bill
        document.getElementById('btn-load-bill')?.addEventListener('click', function () {
            const billId = document.getElementById('sales_bill_id')?.value;
            if (!billId) {
                alert('Please select a Sales Bill first.');
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading…';

            fetch(`/sales/sales-returns/bill-items/${billId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.customer_id) {
                    $('#customer_id').val(data.customer_id).trigger('change');
                }
                if (data.branch_id) {
                    $('#branch_id').val(data.branch_id).trigger('change');
                }
                if (data.sales_type) {
                    $('#sales_type').val(data.sales_type).trigger('change');
                }

                if (data.items && data.items.length > 0) {
                    const tbody = document.getElementById('sr-items-body');
                    tbody.innerHTML = '';
                    data.items.forEach((item, idx) => {
                        const template = document.getElementById('sr-row-template').innerHTML;
                        const html = template.replaceAll('__INDEX__', idx);
                        const tempWrapper = document.createElement('tbody');
                        tempWrapper.innerHTML = html;
                        const row = tempWrapper.firstElementChild;

                        const select = row.querySelector('.sr-item-select');
                        if (select) select.value = item.item_id;
                        const qtyInput = row.querySelector('.sr-qty');
                        if (qtyInput) qtyInput.value = item.qty;
                        const priceInput = row.querySelector('.sr-price');
                        if (priceInput) priceInput.value = item.sell_price;
                        const mrpInput = row.querySelector('.sr-mrp');
                        if (mrpInput) mrpInput.value = item.mrp;
                        const discPctInput = row.querySelector('.sr-disc-percent');
                        if (discPctInput) discPctInput.value = item.disc_percent;
                        const discAmtInput = row.querySelector('.sr-disc-amount');
                        if (discAmtInput) discAmtInput.value = item.disc_amount;
                        const gstPctInput = row.querySelector('.sr-gst-percent');
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
                    alert('No items found in selected bill.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to load items from bill.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-import mr-1"></i> Load Items';
            });
        // Customer Sales Bills filter: only show invoices belonging to selected customer
        let customerBillsLoading = false;
        function loadCustomerBills(customerId, selectedBillId = null) {
            let $billSelect = $('#sales_bill_id');
            if (!customerId) {
                $billSelect.html('<option value="">-- No Original Bill / Direct Return --</option>').trigger('change');
                return;
            }

            customerBillsLoading = true;
            $billSelect.prop('disabled', true);

            $.getJSON('/sales/sales-returns/customer-bills/' + customerId, function (bills) {
                let currentVal = selectedBillId || $billSelect.val();
                let html = '<option value="">-- No Original Bill / Direct Return --</option>';
                if (bills && bills.length > 0) {
                    bills.forEach(function (b) {
                        let sel = (String(b.id) === String(currentVal)) ? 'selected' : '';
                        html += `<option value="${b.id}" ${sel}>${b.label || b.bill_number}</option>`;
                    });
                }
                $billSelect.html(html);
                if (currentVal) {
                    $billSelect.val(currentVal);
                }
            }).fail(function () {
                console.error('Failed to load customer bills');
            }).always(function () {
                $billSelect.prop('disabled', false);
                if (window.jQuery && jQuery.fn.select2) {
                    $billSelect.trigger('change.select2');
                }
                customerBillsLoading = false;
            });
        }

        $('#customer_id').on('change', function () {
            let custId = $(this).val();
            loadCustomerBills(custId);
        });

        let initialCustId = $('#customer_id').val();
        let initialBillId = '{{ old("sales_bill_id", $ret->sales_bill_id ?? "") }}';
        if (initialCustId) {
            loadCustomerBills(initialCustId, initialBillId);
        }

        // Initialize calculations
        recalculateAll();
    })();
</script>
@endpush
