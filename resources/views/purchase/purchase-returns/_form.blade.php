@php
    $ret = $purchaseReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
@endpush

<div class="d-flex justify-content-between align-items-center mb-2 tx-compact-section-header">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-undo-alt text-warning mr-1"></i> Purchase Return Header</h6>
    <x-form-layout-customizer
        form-key="purchase_returns.header"
        container-id="purchase-return-header-grid"
        title="Customize Purchase Return Header"
    />
</div>

<style>
    #purchase-return-header-grid .btn-open-datepicker,
    #purchase-return-header-grid .btn-date-settings-modal,
    #pr-items-table .btn-open-datepicker,
    #pr-items-table .btn-date-settings-modal {
        display: none !important;
    }
    #purchase-return-header-grid .input-group-append:empty,
    #purchase-return-header-grid .urbanpos-date-group .input-group-append,
    #pr-items-table .urbanpos-date-group .input-group-append {
        display: none !important;
    }
    #purchase-return-header-grid .urbanpos-date-group input,
    #pr-items-table .urbanpos-date-group input {
        border-top-right-radius: 0.25rem !important;
        border-bottom-right-radius: 0.25rem !important;
    }
</style>

<div class="row form-fields-grid tx-header-fields-grid" id="purchase-return-header-grid">
        <div class="field-wrapper col-md-4 mb-3" data-field="supplier_id" data-label="Supplier" data-default-order="1" data-core="1">
            <label for="supplier_id" class="font-weight-bold">Supplier <span class="text-danger">*</span></label>
            <select name="supplier_id" id="supplier_id" class="form-control select2" required>
                <option value="">-- Select Supplier --</option>
                @foreach ($suppliers as $id => $name)
                    <option value="{{ $id }}" @selected(old('supplier_id', $ret->supplier_id ?? '') == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        @php
            $selectedBranch = old('branch_id', $ret->branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
        @endphp
        <input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">
        <div class="field-wrapper col-md-3 mb-3" data-field="return_date" data-label="Return Date" data-default-order="3" data-core="1">
            <label for="return_date" class="font-weight-bold">Return Date <span class="text-danger">*</span></label>
            <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', optional($ret->return_date ?? now())->format('Y-m-d')) }}" required>
        </div>
        <div class="field-wrapper col-md-4 mb-3" data-field="purchase_type" data-label="Purchase Type" data-default-order="4" data-core="1">
            <label for="purchase_type" class="font-weight-bold">Purchase Type <span class="text-danger">*</span></label>
            <select name="purchase_type" id="purchase_type" class="form-control" required>
                <option value="Local" @selected(old('purchase_type', $ret->purchase_type ?? 'Local') === 'Local')>Local (CGST + SGST)</option>
                <option value="Interstate" @selected(old('purchase_type', $ret->purchase_type ?? '') === 'Interstate')>Interstate (IGST)</option>
            </select>
        </div>
        <div class="field-wrapper col-md-4 mb-3" data-field="purchase_invoice_id" data-label="Original Purchase Invoice" data-default-order="5">
            <label for="purchase_invoice_id" class="font-weight-bold">Original Purchase Invoice</label>
            <select name="purchase_invoice_id" id="purchase_invoice_id" class="form-control select2">
                <option value="">-- No Original Invoice / Direct Return --</option>
                @foreach ($purchaseInvoices as $id => $no)
                    <option value="{{ $id }}" @selected(old('purchase_invoice_id', $ret->purchase_invoice_id ?? '') == $id)>{{ $no }}</option>
                @endforeach
            </select>
            <small class="text-muted" id="invoice-loading-hint">Select supplier & invoice to automatically load items.</small>
        </div>
        <div class="field-wrapper col-md-4 mb-3" data-field="supplier_debit_note_no" data-label="Supplier Debit Note No" data-default-order="6">
            <label for="supplier_debit_note_no" class="font-weight-bold">Supplier Debit Note No</label>
            <input type="text" name="supplier_debit_note_no" id="supplier_debit_note_no" class="form-control" value="{{ old('supplier_debit_note_no', $ret->supplier_debit_note_no ?? '') }}" placeholder="e.g. DN-2026-001">
        </div>
        <div class="field-wrapper col-md-4 mb-3" data-field="supplier_debit_note_date" data-label="Debit Note Date" data-default-order="7">
            <label for="supplier_debit_note_date" class="font-weight-bold">Debit Note Date</label>
            <input type="date" name="supplier_debit_note_date" id="supplier_debit_note_date" class="form-control" value="{{ old('supplier_debit_note_date', optional($ret->supplier_debit_note_date ?? null)->format('Y-m-d')) }}">
        </div>
    </div>

<hr>
@php
    $prItemColumns = [
        'code'         => ['label' => 'Code / Barcode', 'default' => true],
        'item'         => ['label' => 'Description', 'default' => true],
        'expiry'       => ['label' => 'Exp Date', 'default' => true],
        'qty'          => ['label' => 'Qty', 'default' => true],
        'cost_price'   => ['label' => 'Cost Price', 'default' => true],
        'disc_percent' => ['label' => 'Disc %', 'default' => true],
        'disc_amt'     => ['label' => 'Disc Amt', 'default' => true],
        'gst_percent'  => ['label' => 'GST %', 'default' => true],
        'net_amt'      => ['label' => 'Net Amount', 'default' => true],
        'actions'      => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 font-weight-bold text-dark">
        <i class="fas fa-boxes mr-1 text-primary"></i> Return Items
    </h5>
    <div class="d-flex align-items-center">
        <button type="button" id="pr-btn-reset-table" class="btn btn-outline-danger btn-sm font-weight-bold mr-2">
            <i class="fas fa-undo mr-1"></i> Reset Table
        </button>
        <x-table-column-customizer
            table-key="purchase.purchase-returns.items"
            table-id="pr-items-table"
            :columns="$prItemColumns"
        />
        <button type="button" id="pr-add-row" class="btn btn-outline-primary btn-sm font-weight-bold ml-2">
            <i class="fas fa-plus-circle mr-1"></i> Add Item Line
        </button>
    </div>
</div>

<div class="table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-hover table-items-dense mb-0" id="pr-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 150px;" data-col-key="code">Code / Barcode <span class="text-danger">*</span></th>
                <th style="width: 180px; min-width: 160px;" data-col-key="item">Item Description</th>
                <th style="width: 155px; min-width: 150px;" data-col-key="expiry">Exp Date</th>
                <th style="width: 95px;" class="text-right" data-col-key="qty">Qty <span class="text-danger">*</span></th>
                <th style="width: 110px;" class="text-right" data-col-key="cost_price">Cost Price <span class="text-danger">*</span></th>
                <th style="width: 48px;" class="text-right" data-col-key="disc_percent">Disc %</th>
                <th style="width: 95px;" class="text-right" data-col-key="disc_amt">Disc Amt</th>
                <th style="width: 45px;" class="text-right" data-col-key="gst_percent">GST %</th>
                <th style="width: 110px;" class="text-right" data-col-key="net_amt">Net Amount</th>
                <th style="width: 40px;" class="text-center" data-col-key="actions"></th>
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
                <td colspan="3" class="text-right align-middle">Summary Totals:</td>
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

<div class="d-flex justify-content-between align-items-center mt-2 mb-2 tx-compact-section-header">
    <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Notes</h6>
    <x-form-layout-customizer
        form-key="purchase_returns.additional"
        container-id="pr-additional-fields-grid"
        title="Customize Purchase Return Totals Layout"
    />
</div>

<div class="row g-2 form-fields-grid align-items-end mb-1" id="pr-additional-fields-grid">
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-12" data-field="remarks" data-label="Remarks / Return Reason" data-default-order="1">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="remarks">Remarks / Reason</label>
            <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Optional return reason..." value="{{ old('remarks', $ret->remarks ?? '') }}">
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-2 col-sm-3 col-6" data-field="round_off" data-label="Round Off" data-default-order="2">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="round_off">Round Off</label>
            <input type="number" step="0.01" name="round_off" id="round_off" value="{{ old('round_off', $ret->round_off ?? 0) }}" class="form-control text-right font-weight-bold">
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="taxable_amount" data-label="Items Taxable Amount" data-default-order="3">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Items Taxable Amount:</label>
            <div class="form-control text-right font-weight-bold bg-light" style="line-height: 24px;" id="display-taxable">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="gst_tax" data-label="Total GST Tax" data-default-order="4">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Total GST Tax:</label>
            <div class="form-control text-right font-weight-bold text-primary bg-light" style="line-height: 24px;" id="display-gst">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-5 col-12" data-field="grand_total" data-label="Total Return Value" data-default-order="5">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-success" style="white-space: nowrap;">Total Return Value:</label>
            <div class="form-control text-right font-weight-bold text-success bg-white border-success" style="line-height: 24px; font-size: 0.95rem;" id="display-grand-total">₹0.00</div>
        </div>
    </div>
</div>

<x-custom-fields-renderer :module="'PurchaseReturn'" :model="$ret ?? null" :cardStyle="true" />

<template id="pr-row-template">
    @include('purchase.purchase-returns._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

{{-- ================================================================ --}}
{{-- ITEM SEARCH MODAL (Shared standard popup)                        --}}
{{-- ================================================================ --}}
<div class="modal fade" id="pr-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="prItemSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold" id="prItemSearchModalLabel">
                    <i class="fas fa-search mr-2"></i> Select Item <span id="pr-modal-filter-badge" class="ml-2 font-weight-normal"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-font text-muted"></i></span>
                            </div>
                            <input type="text" id="pr-isl-filter-name" class="form-control" placeholder="Search by item name..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode text-muted"></i></span>
                            </div>
                            <input type="text" id="pr-isl-filter-code" class="form-control font-weight-bold" placeholder="Filter by Code / Barcode..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="pr-isl-btn-clear" class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>

                <div id="pr-isl-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted mb-0">Searching products…</p>
                </div>

                <div id="pr-isl-no-results" class="text-center py-4 text-muted">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div id="pr-isl-table-wrap" class="table-responsive d-none" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm table-striped mb-0">
                        <thead class="thead-dark sticky-top">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th>Item Name</th>
                                <th style="width: 140px;" class="text-center">Code / Barcode</th>
                                <th style="width: 90px;" class="text-center">Stock</th>
                                <th style="width: 110px;" class="text-right">Cost Price</th>
                                <th style="width: 100px;" class="text-right">Sell Price</th>
                                <th style="width: 80px;" class="text-right">GST %</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="pr-isl-items-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between bg-light">
                <span class="text-muted small" id="pr-isl-count-label"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max(count($existingItems), 1) }};
        const PR_ISL_URL = "{{ route('purchase.purchase-returns.item-list') }}";
        const PR_LOOKUP_URL = "{{ route('purchase.purchase-returns.lookup-item') }}";

        let prActiveSearchRow = null;
        let prModalOpen = false;
        let prModalClosing = false;
        let prIslDebounce = null;

        function recalculateRow(row) {
            const qty = parseFloat(row.querySelector('.pr-qty')?.value) || 0;
            const cost = parseFloat(row.querySelector('.pr-cost')?.value) || 0;
            let discPercent = parseFloat(row.querySelector('.pr-disc-percent')?.value) || 0;
            let discAmount = parseFloat(row.querySelector('.pr-disc-amount')?.value) || 0;
            const gstPercent = parseFloat(row.querySelector('.pr-gst-percent')?.value) || 0;

            const base = qty * cost;
            const isDiscPctInvalid = !isNaN(discPercent) && (discPercent > 100 || discPercent < 0);
            const isDiscAmtInvalid = !isNaN(discAmount) && (discAmount > (base + 0.001) || discAmount < 0);

            const $pctInput = $(row).find('.pr-disc-percent');
            const $amtInput = $(row).find('.pr-disc-amount');

            if (isDiscPctInvalid) {
                $pctInput.addClass('is-invalid border-danger text-danger bg-light-danger');
                $pctInput.attr('title', 'Discount % cannot exceed 100%');
            } else {
                $pctInput.removeClass('is-invalid border-danger text-danger bg-light-danger');
                $pctInput.removeAttr('title');
            }

            if (isDiscAmtInvalid) {
                $amtInput.addClass('is-invalid border-danger text-danger bg-light-danger');
                $amtInput.attr('title', 'Discount amount cannot exceed line cost total');
            } else {
                $amtInput.removeClass('is-invalid border-danger text-danger bg-light-danger');
                $amtInput.removeAttr('title');
            }

            const clampedDiscAmt = Math.min(Math.max(0, discAmount), base);
            const taxable = Math.max(0, base - clampedDiscAmt);
            const gstAmount = Math.round((taxable * gstPercent / 100) * 100) / 100;
            const net = taxable + gstAmount;

            const netSpan = row.querySelector('.pr-net-amount');
            if (netSpan) {
                netSpan.innerText = net.toFixed(2);
            }

            return { qty, cost, discAmount: clampedDiscAmt, taxable, gstAmount, net, isInvalid: isDiscPctInvalid || isDiscAmtInvalid };
        }

        function recalculateAll(isManualRoundOff) {
            let totalQty = 0;
            let totalDisc = 0;
            let totalTaxable = 0;
            let totalGst = 0;
            let totalNet = 0;
            let hasInvalidDisc = false;

            document.querySelectorAll('#pr-items-body .pr-item-row').forEach(row => {
                const res = recalculateRow(row);
                totalQty += res.qty;
                totalDisc += res.discAmount;
                totalTaxable += res.taxable;
                totalGst += res.gstAmount;
                totalNet += res.net;
                if (res.isInvalid) {
                    hasInvalidDisc = true;
                }
            });

            const rawTotal = totalNet;
            let roundOff = 0;
            let grandTotal = 0;
            const roundOffInput = document.getElementById('round_off');

            if (isManualRoundOff) {
                roundOff = parseFloat(roundOffInput?.value) || 0;
                grandTotal = Math.round((rawTotal + roundOff) * 100) / 100;
            } else {
                const roundedTotal = Math.round(rawTotal);
                roundOff = Math.round((roundedTotal - rawTotal) * 100) / 100;
                if (roundOffInput) {
                    roundOffInput.value = roundOff !== 0 ? roundOff.toFixed(2) : '0.00';
                }
                grandTotal = roundedTotal;
            }

            document.getElementById('footer-pr-qty').innerText = totalQty.toFixed(3);
            document.getElementById('footer-pr-disc').innerText = '₹' + totalDisc.toFixed(2);
            document.getElementById('footer-pr-net').innerText = '₹' + totalNet.toFixed(2);
            document.getElementById('display-taxable').innerText = '₹' + totalTaxable.toFixed(2);
            document.getElementById('display-gst').innerText = '₹' + totalGst.toFixed(2);
            document.getElementById('display-grand-total').innerText = '₹' + grandTotal.toFixed(2);

            // Universal rich footer total and items badge
            const displayPrFinal = document.getElementById('display-pr-final-total');
            if (displayPrFinal) {
                displayPrFinal.innerText = grandTotal.toFixed(2);
            }
            const itemsBadge = document.getElementById('pr-total-items-badge');
            if (itemsBadge) {
                const rowCount = document.querySelectorAll('#pr-items-body .pr-item-row').length;
                itemsBadge.innerHTML = '<span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">' + rowCount + ' Items</span>';
            }

            const submitBtn = document.querySelector('button[type="submit"], #pr-main-save-btn');
            if (submitBtn) {
                const hasValidItems = totalQty > 0 && document.querySelectorAll('#pr-items-body .pr-item-row').length > 0;
                submitBtn.disabled = !hasValidItems || hasInvalidDisc;
                if (hasInvalidDisc) {
                    submitBtn.title = 'Discount percentage ya discount amount 100% / line total se zyada hai. Kripya use theek karein.';
                } else {
                    submitBtn.removeAttribute('title');
                }
            }
        }

        document.getElementById('pr-btn-reset-table')?.addEventListener('click', function () {
            const tbody = document.getElementById('pr-items-body');
            const template = document.getElementById('pr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', 0);
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            tbody.innerHTML = '';
            tbody.appendChild(tempWrapper.firstElementChild);
            rowIndex = 1;
            recalculateAll();
            setTimeout(function () {
                const firstCode = tbody.querySelector('.pr-item-code');
                if (firstCode) firstCode.focus();
            }, 60);
        });

        // Reset Form: Clears header and table
        $(document).on('click', '#btn-reset-form, .btn-reset-form', function (e) {
            e.preventDefault();
            $('#supplier_id').val('').trigger('change.select2');
            $('#purchase_invoice_id').val('').trigger('change.select2');
            $('input[name="remarks"]').val('');
            $('#pr-btn-reset-table').trigger('click');
            if (window.toastr) {
                toastr.info('Purchase Return form has been reset.');
            }
            setTimeout(function () {
                let $s = $('#supplier_id');
                if ($s.data('select2')) {
                    $s.data('select2').$container.find('.select2-selection').focus();
                }
            }, 100);
        });

        function canAddPrRow() {
            let $lastRow = $('#pr-items-body tr.pr-item-row').last();
            if ($lastRow.length) {
                let itemId = $lastRow.find('.pr-item-id').val();
                let qtyVal = parseFloat($lastRow.find('.pr-qty').val()) || 0;

                if (!itemId) {
                    let msg = 'Pehle current row me item select karein.';
                    if (window.toastr) toastr.warning(msg, 'Incomplete Row');
                    else alert(msg);
                    $lastRow.find('.pr-item-code').focus();
                    return false;
                }

                if (qtyVal <= 0) {
                    let msg = 'Pehle item ki valid quantity enter karein.';
                    if (window.toastr) toastr.warning(msg, 'Quantity Required');
                    else alert(msg);
                    $lastRow.find('.pr-qty').focus().select();
                    return false;
                }
            }
            return true;
        }

        document.getElementById('pr-add-row')?.addEventListener('click', function (e) {
            if (!assertSupplierSelected()) {
                e?.preventDefault?.();
                return;
            }
            if (!canAddPrRow()) {
                e?.preventDefault?.();
                return;
            }
            const template = document.getElementById('pr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('pr-items-body');
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            const newRow = tempWrapper.firstElementChild;
            tbody.appendChild(newRow);
            rowIndex++;
            recalculateAll();
        });

        document.getElementById('pr-items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.pr-row-remove');
            if (!btn) return;
            const rows = document.querySelectorAll('#pr-items-body .pr-item-row');
            if (rows.length <= 1) {
                notifyWarn('At least one item row is required.', 'Item Row Required');
                return;
            }
            btn.closest('tr').remove();
            recalculateAll();
        });

        document.getElementById('pr-items-body')?.addEventListener('input', function (e) {
            const target = e.target;
            const row = target.closest('.pr-item-row');
            if (!row) return;

            if (target.matches('.pr-disc-percent')) {
                let valStr = target.value;
                let val = parseFloat(valStr);
                const qty = parseFloat(row.querySelector('.pr-qty')?.value) || 0;
                const cost = parseFloat(row.querySelector('.pr-cost')?.value) || 0;
                const base = qty * cost;
                const discAmt = (!isNaN(val) && val >= 0 && base > 0) ? Math.round((base * val / 100) * 100) / 100 : 0;
                const discAmtInput = row.querySelector('.pr-disc-amount');
                if (discAmtInput) {
                    discAmtInput.value = discAmt > 0 ? discAmt.toFixed(2) : '';
                }
            } else if (target.matches('.pr-disc-amount')) {
                let valStr = target.value;
                let val = parseFloat(valStr);
                const qty = parseFloat(row.querySelector('.pr-qty')?.value) || 0;
                const cost = parseFloat(row.querySelector('.pr-cost')?.value) || 0;
                const base = qty * cost;
                const discPct = (!isNaN(val) && val >= 0 && base > 0) ? Math.round((val / base * 100) * 100) / 100 : 0;
                const discPctInput = row.querySelector('.pr-disc-percent');
                if (discPctInput) {
                    discPctInput.value = discPct > 0 ? discPct.toFixed(2) : '';
                }
            } else if (target.matches('.pr-qty, .pr-cost')) {
                const qty = parseFloat(row.querySelector('.pr-qty')?.value) || 0;
                const cost = parseFloat(row.querySelector('.pr-cost')?.value) || 0;
                const base = qty * cost;
                const discPct = parseFloat(row.querySelector('.pr-disc-percent')?.value) || 0;
                if (discPct > 0 && base > 0) {
                    const discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    const discAmtInput = row.querySelector('.pr-disc-amount');
                    if (discAmtInput) {
                        discAmtInput.value = discAmt > 0 ? discAmt.toFixed(2) : '';
                    }
                }
            }

            if (target.matches('.pr-qty, .pr-cost, .pr-disc-percent, .pr-disc-amount, .pr-gst-percent')) {
                recalculateAll();
            }
        });

        document.getElementById('round_off')?.addEventListener('input', function() { recalculateAll(true); });

        $(document).off('keydown', '.pr-disc-amount, .pr-gst-percent').on('keydown', '.pr-disc-amount, .pr-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr.pr-item-row');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.pr-item-code').focus();
                } else {
                    e.preventDefault();
                    if (!assertSupplierSelected()) return;
                    if (!canAddPrRow()) return;
                    $('#pr-add-row').trigger('click');
                    let $newRow = $('#pr-items-body tr.pr-item-row').last();
                    setTimeout(function () {
                        $newRow.find('.pr-search-btn').trigger('click');
                    }, 60);
                }
            }
        });

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL (Triggered on Enter/F2 or Search Button, NOT on Click/Focus)
           ---------------------------------------------------------------- */
        function assertSupplierSelected() {
            let supplierId = $('#supplier_id').val();
            let invoiceId = $('#purchase_invoice_id').val();
            if (!supplierId && !invoiceId) {
                if (window.toastr) {
                    toastr.warning('Please select a Supplier first before proceeding to items.', 'Supplier Required');
                }
                if ($('#supplier_id').hasClass('select2-hidden-accessible')) {
                    $('#supplier_id').select2('open');
                } else {
                    $('#supplier_id').focus();
                }
                return false;
            }
            return true;
        }

        function updateModalHeaderBadge() {
            let invoiceId = $('#purchase_invoice_id').val();
            let invoiceText = $('#purchase_invoice_id option:selected').text();
            let supplierText = $('#supplier_id option:selected').text();

            if (invoiceId) {
                $('#pr-modal-filter-badge').html('<span class="badge badge-warning text-dark"><i class="fas fa-file-invoice mr-1"></i> Invoice: ' + invoiceText.split('(')[0].trim() + '</span>');
            } else if ($('#supplier_id').val()) {
                $('#pr-modal-filter-badge').html('<span class="badge badge-light text-dark border"><i class="fas fa-truck mr-1"></i> Supplier: ' + supplierText + '</span>');
            } else {
                $('#pr-modal-filter-badge').empty();
            }
        }

        let prMouseDown = false;
        $(document).on('mousedown', '.pr-item-code', function () {
            prMouseDown = true;
        });

        function checkSupplierAndOpenPrModal($input) {
            if (prModalOpen || prModalClosing) return false;
            let $row = $input.closest('tr');
            if ($row.find('.pr-item-id').val()) return false;
            if (!assertSupplierSelected()) return false;

            prActiveSearchRow = $row;
            let prefill = $.trim($input.val());
            $('#pr-isl-filter-name').val(prefill);
            $('#pr-isl-filter-code').val('');
            updateModalHeaderBadge();
            fetchPrItemList();
            prModalOpen = true;
            $('#pr-item-search-modal').modal('show');
            $('#pr-item-search-modal').one('shown.bs.modal', function () {
                $('#pr-isl-filter-name').focus().select();
            });
            return true;
        }

        // Standardized Barcode & Item Code events are bound in the Item Lookup section below

        // Tab starts from supplier on page load
        setTimeout(function () {
            let $supplier = $('#supplier_id');
            if ($supplier.length && $supplier.data('select2')) {
                $supplier.data('select2').$container.find('.select2-selection').focus();
            } else if ($supplier.length) {
                $supplier.focus();
            }
        }, 150);

        $('#pr-item-search-modal').on('show.bs.modal', function () {
            prModalOpen = true;
            prModalClosing = false;
            prItemSelectedInModal = false;
            prCancellingRow = null;
        });

        $('#pr-item-search-modal').on('hide.bs.modal', function () {
            prModalOpen = false;
            prModalClosing = true;
            if (!prItemSelectedInModal && prActiveSearchRow && prActiveSearchRow.length) {
                let selectedId = prActiveSearchRow.find('.pr-item-id').val();
                if (!selectedId) {
                    prCancellingRow = prActiveSearchRow;
                }
            }
        });

        let prIslSelectedIdx = -1;
        let prLastSelectedRow = null;

        function updatePrModalHighlight() {
            let $rows = $('#pr-isl-items-body tr.pr-isl-item-row');
            $('#pr-isl-items-body tr').removeClass('table-primary');
            if (prIslSelectedIdx >= 0 && prIslSelectedIdx < $rows.length) {
                let $target = $rows.eq(prIslSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#pr-isl-table-wrap')[0];
                let rowEl = $target[0];
                if (container && rowEl) {
                    let cTop = container.scrollTop;
                    let cBottom = cTop + container.clientHeight;
                    let rTop = rowEl.offsetTop;
                    let rBottom = rTop + rowEl.clientHeight;
                    if (rTop < cTop) container.scrollTop = rTop;
                    else if (rBottom > cBottom) container.scrollTop = rBottom - container.clientHeight;
                }
            }
        }

        $('#pr-item-search-modal').on('keydown', function (e) {
            let $rows = $('#pr-isl-items-body tr.pr-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                prIslSelectedIdx = (prIslSelectedIdx + 1) >= $rows.length ? 0 : prIslSelectedIdx + 1;
                updatePrModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                prIslSelectedIdx = (prIslSelectedIdx - 1) < 0 ? $rows.length - 1 : prIslSelectedIdx - 1;
                updatePrModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                let $target = (prIslSelectedIdx >= 0 && prIslSelectedIdx < $rows.length)
                    ? $rows.eq(prIslSelectedIdx)
                    : $rows.first();
                if ($target.length) {
                    $target.trigger('click');
                }
            }
        });

        $('#pr-item-search-modal').on('hidden.bs.modal', function () {
            prModalOpen = false;
            prModalClosing = true;
            setTimeout(function () { prModalClosing = false; }, 350);

            if (!prItemSelectedInModal && prCancellingRow && prCancellingRow.length) {
                let totalRows = $('#pr-items-body tr.pr-item-row').length;
                if (totalRows > 1) {
                    prCancellingRow.remove();
                    recalculateAll();
                } else {
                    prCancellingRow.find('.pr-item-code').val('');
                    prCancellingRow.find('.pr-item-desc').val('');
                }
                prCancellingRow = null;
                prActiveSearchRow = null;
                setTimeout(function () {
                    let $target = $('#pr-add-row, #remarks, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }

            if (prItemSelectedInModal && prLastSelectedRow && prLastSelectedRow.length) {
                let $r = prLastSelectedRow;
                prLastSelectedRow = null;
                setTimeout(function () {
                    $r.find('.pr-qty').focus().select();
                }, 60);
            }

            prItemSelectedInModal = false;
            prCancellingRow = null;
            prActiveSearchRow = null;
        });

        $('#pr-isl-filter-name, #pr-isl-filter-code').on('input', function () {
            clearTimeout(prIslDebounce);
            prIslDebounce = setTimeout(fetchPrItemList, 300);
        });

        $('#pr-isl-btn-clear').on('click', function () {
            $('#pr-isl-filter-name, #pr-isl-filter-code').val('');
            fetchPrItemList();
        });

        function fetchPrItemList() {
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let supplierId = $('#supplier_id').val();
            let invoiceId = $('#purchase_invoice_id').val();
            let srch = $.trim($('#pr-isl-filter-name').val());
            let code = $.trim($('#pr-isl-filter-code').val());

            if (!supplierId && !invoiceId) {
                $('#pr-isl-loading').addClass('d-none');
                $('#pr-isl-table-wrap').addClass('d-none');
                $('#pr-isl-items-body').empty();
                $('#pr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-exclamation-triangle fa-2x text-warning"></i><p class="mt-2 text-dark font-weight-bold">Please select a Supplier first.</p>'
                );
                $('#pr-isl-count-label').text('');
                return;
            }

            $('#pr-isl-loading').removeClass('d-none');
            $('#pr-isl-no-results').addClass('d-none');
            $('#pr-isl-table-wrap').addClass('d-none');

            $.getJSON(PR_ISL_URL, {
                branch_id: branchId,
                supplier_id: supplierId,
                purchase_invoice_id: invoiceId,
                search: srch,
                code: code
            }, function (res) {
                $('#pr-isl-loading').addClass('d-none');
                let items = res.items || [];
                let $tbody = $('#pr-isl-items-body').empty();

                if (items.length === 0) {
                    let noMsg = invoiceId
                        ? 'No products found in the selected Purchase Invoice.'
                        : 'No products found for this Supplier.';
                    $('#pr-isl-no-results').removeClass('d-none').html(
                        '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted font-weight-bold">' + noMsg + '</p>'
                    );
                    $('#pr-isl-count-label').text('');
                    return;
                }

                let html = '';
                items.forEach(function (it, idx) {
                    let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                    let costDisplay = it.cost_price > 0 ? '₹' + parseFloat(it.cost_price).toFixed(2) : '—';
                    let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                    let stockClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';
                    let remainingDisplay = (it.remaining_qty !== null && it.remaining_qty !== undefined)
                        ? (parseFloat(it.remaining_qty) <= 0 ? '<span class="badge badge-danger">Fully Returned (0)</span>' : `<span class="badge badge-success">Eligible: ${parseFloat(it.remaining_qty).toFixed(2)}</span>`)
                        : '';
                    let qtyCol = it.invoiced_qty !== null
                        ? `<span class="text-dark font-weight-bold" title="Invoiced Quantity">Inv: ${parseFloat(it.invoiced_qty).toFixed(2)}</span> ${remainingDisplay} <small class="text-muted d-block">(Stock: ${parseFloat(it.qty || 0).toFixed(2)})</small>`
                        : `<span class="${stockClass}">${parseFloat(it.qty || 0).toFixed(2)}</span>`;

                    html += `
                        <tr class="pr-isl-item-row" style="cursor:pointer;"
                            data-id="${it.id}"
                            data-code="${it.code || ''}"
                            data-batch="${it.batch_no || ''}"
                            data-name="${it.name}"
                            data-cost="${it.cost_price || 0}"
                            data-gst="${it.gst_percent || 0}"
                            data-disc-percent="${it.disc_percent || 0}"
                            data-disc-amount="${it.disc_amount || 0}"
                            data-exp-date="${it.exp_date || ''}"
                            data-original-qty="${it.original_qty !== null && it.original_qty !== undefined ? it.original_qty : (it.invoiced_qty || '')}"
                            data-returned-qty="${it.already_returned || 0}"
                            data-remaining-qty="${it.remaining_qty !== null && it.remaining_qty !== undefined ? it.remaining_qty : ''}">
                            <td class="align-middle text-center text-muted">${idx + 1}</td>
                            <td class="align-middle font-weight-bold text-dark">
                                ${it.name}
                                ${it.batch_no ? `<span class="badge badge-info ml-1">Batch: ${it.batch_no}</span>` : ''}
                            </td>
                            <td class="align-middle text-center">${codeBadge}</td>
                            <td class="align-middle text-center">${qtyCol}</td>
                            <td class="align-middle text-right font-weight-bold text-dark">${costDisplay}</td>
                            <td class="align-middle text-right text-success">${sellDisplay}</td>
                            <td class="align-middle text-right">${parseFloat(it.gst_percent || 0).toFixed(0)}%</td>
                            <td class="align-middle text-center">
                                <button type="button" class="btn btn-success btn-xs px-2 pr-isl-btn-select"
                                    data-id="${it.id}">
                                    <i class="fas fa-check mr-1"></i>Select
                                </button>
                            </td>
                        </tr>`;
                });

                $tbody.html(html);
                $('#pr-isl-table-wrap').removeClass('d-none');
                $('#pr-isl-count-label').text(items.length + ' item(s) found');

                prIslSelectedIdx = items.length > 0 ? 0 : -1;
                updatePrModalHighlight();
            }).fail(function () {
                $('#pr-isl-loading').addClass('d-none');
            });
        }

        $(document).on('click', '.pr-isl-item-row, .pr-isl-btn-select', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('pr-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemData = {
                id: $tr.data('id'),
                name: $tr.data('name'),
                code: $tr.data('code'),
                batch_no: $tr.data('batch') || '',
                cost_price: $tr.data('cost'),
                gst_percent: $tr.data('gst'),
                disc_percent: $tr.data('disc-percent'),
                disc_amount: $tr.data('disc-amount'),
                exp_date: $tr.data('exp-date'),
                original_qty: $tr.data('original-qty'),
                already_returned: $tr.data('returned-qty'),
                remaining_qty: $tr.data('remaining-qty')
            };

            if (!prActiveSearchRow || !itemData.id) return;

            if ($('#purchase_invoice_id').val() && itemData.remaining_qty !== '' && itemData.remaining_qty !== undefined && parseFloat(itemData.remaining_qty) <= 0) {
                notifyWarn('No returnable quantity available for this item.', 'Return Not Allowed');
                return;
            }

            prItemSelectedInModal = true;
            prCancellingRow = null;
            prLastSelectedRow = prActiveSearchRow;

            let $row = prActiveSearchRow;
            $row.find('.pr-item-code').val(itemData.code || itemData.id);
            $row.find('.pr-item-desc').val(itemData.name);
            $row.find('.pr-item-id').val(itemData.id);
            $row.find('.pr-batch-no').val(itemData.batch_no || '');

            let $qtyInput = $row.find('.pr-qty');
            if (itemData.original_qty !== '' && itemData.original_qty !== undefined) {
                $qtyInput.attr('data-original-qty', itemData.original_qty);
                $qtyInput.attr('data-returned-qty', itemData.already_returned || 0);
                $qtyInput.attr('data-remaining-qty', itemData.remaining_qty);
                let defaultQty = (itemData.remaining_qty !== '' && parseFloat(itemData.remaining_qty) > 0) ? parseFloat(itemData.remaining_qty) : 1;
                $qtyInput.val(defaultQty);
                let remDisp = itemData.remaining_qty !== '' ? (parseFloat(itemData.remaining_qty) == parseInt(itemData.remaining_qty, 10) ? parseInt(itemData.remaining_qty, 10) : parseFloat(itemData.remaining_qty)) : '';
                $row.find('.pr-remaining-qty-label').text(remDisp !== '' ? 'Remaining: ' + remDisp : '').show();
            }

            if (parseFloat(itemData.cost_price) > 0) {
                $row.find('.pr-cost').val(parseFloat(itemData.cost_price).toFixed(2));
            }
            if (parseFloat(itemData.gst_percent) >= 0) {
                $row.find('.pr-gst-percent').val(parseFloat(itemData.gst_percent).toFixed(2));
            }
            if (parseFloat(itemData.disc_percent) > 0) {
                $row.find('.pr-disc-percent').val(parseFloat(itemData.disc_percent).toFixed(2));
            }
            if (parseFloat(itemData.disc_amount) > 0) {
                $row.find('.pr-disc-amount').val(parseFloat(itemData.disc_amount).toFixed(2));
            }
            if (itemData.exp_date) {
                $row.find('.pr-exp-date').val(itemData.exp_date);
            }

            validatePrQuantity($qtyInput, false);
            recalculateAll();

            $('#pr-item-search-modal').modal('hide');
            setTimeout(function () {
                $row.find('.pr-qty').focus().select();
            }, 100);

            prActiveSearchRow = null;
        });

        function processPrItemLookup($row, itemId, query, isDirectLookup = false) {
            let supplierId = $('#supplier_id').val();
            let invoiceId = $('#purchase_invoice_id').val();
            let $input = $row.find('.pr-item-code');

            if (!supplierId && !invoiceId) {
                if (window.toastr) {
                    toastr.warning('Please select a Supplier first before adding items.', 'Supplier Required');
                }
                $input.val('');
                if ($('#supplier_id').hasClass('select2-hidden-accessible')) {
                    $('#supplier_id').select2('open');
                } else {
                    $('#supplier_id').focus();
                }
                return;
            }

            if (query && window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(query);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let params = {
                branch_id: branchId,
                supplier_id: supplierId,
                purchase_invoice_id: invoiceId
            };
            if (itemId) {
                params.item_id = itemId;
            } else if (query) {
                params.query = query;
                if (isDirectLookup) {
                    params.exact_match_only = 1;
                }
            } else {
                return;
            }

            $.getJSON(PR_LOOKUP_URL, params, function (res) {
                if (res && res.id) {
                    $row.data('last-processed-code', query || res.item_code || res.ean_upc_code || res.id);
                    $input.removeClass('is-invalid border-danger');
                    $input.val(res.item_code || res.ean_upc_code || res.id || query);
                    $row.find('.pr-item-desc').val(res.name + (res.item_code ? ' [' + res.item_code + ']' : ''));
                    $row.find('.pr-item-id').val(res.id);

                    if (parseFloat(res.cost_price) > 0) {
                        $row.find('.pr-cost').val(parseFloat(res.cost_price).toFixed(2));
                    }
                    if (parseFloat(res.gst_percent) >= 0) {
                        $row.find('.pr-gst-percent').val(parseFloat(res.gst_percent).toFixed(2));
                    }
                    if (parseFloat(res.disc_percent) > 0) {
                        $row.find('.pr-disc-percent').val(parseFloat(res.disc_percent).toFixed(2));
                    }
                    if (parseFloat(res.disc_amount) > 0) {
                        $row.find('.pr-disc-amount').val(parseFloat(res.disc_amount).toFixed(2));
                    }
                    if (res.exp_date) {
                        $row.find('.pr-exp-date').val(res.exp_date);
                    }
                    recalculateAll();
                    setTimeout(function () {
                        $row.find('.pr-qty').focus().select();
                    }, 60);
                } else {
                    $row.data('last-processed-code', null);
                    $input.addClass('is-invalid border-danger');
                    let msg = "Product not found for this Item Code/Barcode.";
                    if (window.toastr) {
                        toastr.warning(msg, 'Item Not Found');
                    }
                    setTimeout(function () {
                        $input.focus().select();
                    }, 50);
                }
            }).fail(function (xhr) {
                $row.data('last-processed-code', null);
                $input.addClass('is-invalid border-danger');
                let msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'This product was not found for the selected supplier/invoice.';
                if (window.toastr) {
                    toastr.warning(msg, 'Product Lookup');
                }
                setTimeout(function () {
                    $input.focus().select();
                }, 50);
            });
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
        $(document).off('keydown change input', '.pr-item-code')
            .on('keydown', '.pr-item-code', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        processPrItemLookup($row, null, val, true);
                    } else {
                        checkSupplierAndOpenPrModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        e.preventDefault();
                        processPrItemLookup($row, null, val, true);
                    } else {
                        e.preventDefault();
                        checkSupplierAndOpenPrModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkSupplierAndOpenPrModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr');
                    let itemId = $row.find('.pr-item-id').val();
                    if (!itemId && $('#pr-items-body tr').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr');
                        $row.remove();
                        updateRowNumbers();
                        recalculateAll();
                        if ($prevRow.length) {
                            $prevRow.find('.pr-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.pr-item-code', function () {
                let $input = $(this);
                let query = $.trim($input.val());
                let $row = $input.closest('tr');
                if (!query) {
                    $row.find('.pr-item-id').val('');
                    $row.find('.pr-item-desc').val('');
                    $row.data('last-processed-code', '');
                    recalculateAll();
                    return;
                }
                if ($row.data('last-processed-code') === query) return;
                processPrItemLookup($row, null, query, true);
            })
            .on('input', '.pr-item-code', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Clicking on description also opens item search modal
        $(document).on('click', '.pr-item-desc', function () {
            let $code = $(this).closest('tr').find('.pr-item-code');
            checkSupplierAndOpenPrModal($code);
        });

        // Filter Invoices when Supplier changes
        $('#supplier_id').on('change', function () {
            const supplierId = $(this).val();
            const $invSelect = $('#purchase_invoice_id');
            const currentSelected = $invSelect.val();
            $invSelect.empty().append('<option value="">-- No Original Invoice / Direct Return --</option>');

            // Reset item table on supplier change so cross-supplier items cannot remain
            $('#pr-items-body').empty();
            const template = document.getElementById('pr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', 0);
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            document.getElementById('pr-items-body').appendChild(tempWrapper.firstElementChild);
            rowIndex = 1;
            recalculateAll();

            if (!supplierId) return;

            fetch(`/purchase/purchase-returns/supplier-invoices/${supplierId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.invoices && data.invoices.length > 0) {
                    data.invoices.forEach(inv => {
                        const opt = new Option(`${inv.invoice_number} (${inv.invoice_date} - ₹${inv.total})`, inv.id, false, inv.id == currentSelected);
                        $invSelect.append(opt);
                    });
                }
                $invSelect.trigger('change.select2');
            })
            .catch(err => console.error(err));
        });

        // Automatically load items as soon as an Invoice is selected
        let isAutoLoadingInvoice = false;
        $('#purchase_invoice_id').on('change', function () {
            const invId = $(this).val();
            const hint = document.getElementById('invoice-loading-hint');
            if (!invId) {
                if (hint) hint.textContent = 'Select supplier & invoice to automatically load items.';
                return;
            }

            if (isAutoLoadingInvoice) return;
            isAutoLoadingInvoice = true;

            if (hint) {
                hint.innerHTML = '<i class="fas fa-spinner fa-spin text-primary mr-1"></i> Loading items from selected invoice…';
            }

            fetch(`/purchase/purchase-returns/invoice-items/${invId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.supplier_id && $('#supplier_id').val() != data.supplier_id) {
                    $('#supplier_id').val(data.supplier_id).trigger('change.select2');
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

                        const idInput = row.querySelector('.pr-item-id');
                        if (idInput) idInput.value = item.item_id;
                        const codeInput = row.querySelector('.pr-item-code');
                        if (codeInput) codeInput.value = item.item_code || '';
                        const descInput = row.querySelector('.pr-item-desc');
                        if (descInput) descInput.value = item.item_name || '';

                        const qtyInput = row.querySelector('.pr-qty');
                        if (qtyInput) {
                            qtyInput.value = item.qty;
                            if (item.original_qty !== undefined && item.original_qty !== null) {
                                qtyInput.setAttribute('data-original-qty', item.original_qty);
                                qtyInput.setAttribute('data-returned-qty', item.already_returned || 0);
                                qtyInput.setAttribute('data-remaining-qty', item.remaining_qty !== undefined ? item.remaining_qty : '');
                                let remDisp = item.remaining_qty !== undefined ? (parseFloat(item.remaining_qty) == parseInt(item.remaining_qty, 10) ? parseInt(item.remaining_qty, 10) : parseFloat(item.remaining_qty)) : '';
                                const remLabel = row.querySelector('.pr-remaining-qty-label');
                                if (remLabel && remDisp !== '') {
                                    remLabel.innerText = 'Remaining: ' + remDisp;
                                    remLabel.style.display = 'block';
                                }
                            }
                        }
                        const costInput = row.querySelector('.pr-cost');
                        if (costInput) costInput.value = item.cost_price;
                        const discPctInput = row.querySelector('.pr-disc-percent');
                        if (discPctInput) discPctInput.value = item.disc_percent;
                        const discAmtInput = row.querySelector('.pr-disc-amount');
                        if (discAmtInput) discAmtInput.value = item.disc_amount;
                        const gstPctInput = row.querySelector('.pr-gst-percent');
                        if (gstPctInput) gstPctInput.value = item.gst_percent;
                        const expInput = row.querySelector('.pr-exp-date');
                        if (expInput && item.exp_date) expInput.value = item.exp_date;

                        tbody.appendChild(row);
                    });
                    rowIndex = data.items.length;
                    recalculateAll();
                    if (hint) {
                        hint.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> ' + data.items.length + ' item(s) auto-loaded successfully.</span>';
                    }
                } else {
                    if (hint) hint.textContent = 'No items found in selected invoice.';
                }
            })
            .catch(err => {
                console.error(err);
                if (hint) hint.textContent = 'Failed to load items from invoice.';
            })
            .finally(() => {
                isAutoLoadingInvoice = false;
            });
        });

        // Real-time instant Purchase Return quantity validation
        function validatePrQuantity($input, showAlert = true) {
            let $row = $input.closest('tr');
            let itemId = $row.find('.pr-item-id').val();
            let enteredVal = parseFloat($input.val()) || 0;

            let origQtyStr = $input.attr('data-original-qty');
            if (origQtyStr === undefined || origQtyStr === '' || origQtyStr === null) {
                $input.removeClass('is-invalid border-danger');
                return true;
            }

            let origQty = parseFloat(origQtyStr) || 0;
            let returnedQty = parseFloat($input.attr('data-returned-qty')) || 0;
            let remainingQty = parseFloat($input.attr('data-remaining-qty'));
            if (isNaN(remainingQty)) {
                remainingQty = Math.max(0, origQty - returnedQty);
            }

            // Sum quantities across all rows for this same item_id and batch_no
            let currentBatch = $row.find('.pr-batch-no').val() || '';
            let totalRequestedForThisItem = 0;
            $('#pr-items-body tr.pr-item-row').each(function () {
                let thisItemId = $(this).find('.pr-item-id').val();
                let thisBatch = $(this).find('.pr-batch-no').val() || '';
                if (thisItemId && String(thisItemId) === String(itemId) && thisBatch === currentBatch) {
                    totalRequestedForThisItem += (parseFloat($(this).find('.pr-qty').val()) || 0);
                }
            });

            let isOverLimit = (origQty > 0) && ((totalRequestedForThisItem > remainingQty + 0.0001) || (enteredVal > remainingQty + 0.0001));

            let $feedback = $input.siblings('.pr-qty-error-msg');
            if (!$feedback.length) {
                $feedback = $('<div class="pr-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size: 11px;"></div>');
                $input.after($feedback);
            }

            if (isOverLimit) {
                $input.addClass('is-invalid border-danger');
                let remDisp = (remainingQty === parseInt(remainingQty, 10)) ? parseInt(remainingQty, 10) : remainingQty;
                let errMsg = `Maximum returnable quantity is ${remDisp}.`;

                $input.attr('title', errMsg);
                $feedback.text(errMsg).css('display', 'block');
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return false;
            } else {
                $input.removeClass('is-invalid border-danger').removeAttr('title');
                $feedback.text('').css('display', 'none');
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                return true;
            }
        }

        $(document).on('input change keyup', '.pr-qty', function () {
            validatePrQuantity($(this), false);
            recalculateAll();
        });

        $(document).on('keydown', '.pr-qty', function (e) {
            if (e.key === 'Tab' || e.key === 'Enter') {
                if (!validatePrQuantity($(this), false)) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).focus().select();
                    return false;
                }
            }
        });

        function notifyWarn(msg, title) {
            if (window.toastr && typeof window.toastr.warning === 'function') {
                toastr.warning(msg, title || 'Warning');
            } else {
                console.warn((title ? title + ': ' : '') + msg);
            }
        }

        // Form Submit Handler
        $('form').on('submit', function (e) {
            let supplierId = $('#supplier_id').val();
            if (!supplierId) {
                e.preventDefault();
                notifyWarn('Please select a Supplier first.', 'Supplier Required');
                $('#supplier_id').select2('open');
                return false;
            }

            let validCount = 0;
            let hasError = false;

            $('#pr-items-body .pr-item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.pr-item-id').val();
                let itemCode = $.trim($row.find('.pr-item-code').val());
                let itemName = $.trim($row.find('.pr-item-desc').val()) || itemCode || 'Selected Item';
                let $qtyInput = $row.find('.pr-qty');
                let qtyVal = $qtyInput.val();
                let qty = parseFloat(qtyVal) || 0;

                // Completely blank row
                if (!itemId && !itemCode) {
                    return;
                }

                // Item code entered but not selected
                if (!itemId && itemCode) {
                    e.preventDefault();
                    notifyWarn(`Please select a valid item for: "${itemCode}"`, 'Item Required');
                    $row.find('.pr-item-code').focus();
                    hasError = true;
                    return false;
                }

                // Item selected
                validCount++;
                if (!qtyVal || qty <= 0) {
                    e.preventDefault();
                    notifyWarn(`Please enter quantity for item: "${itemName}"`, 'Quantity Required');
                    $qtyInput.focus().select();
                    hasError = true;
                    return false;
                }

                if (!validatePrQuantity($qtyInput, true)) {
                    e.preventDefault();
                    $qtyInput.focus().select();
                    hasError = true;
                    return false;
                }

                let discPct = parseFloat($row.find('.pr-disc-percent').val()) || 0;
                let discAmt = parseFloat($row.find('.pr-disc-amount').val()) || 0;
                let costVal = parseFloat($row.find('.pr-cost').val()) || 0;
                let baseVal = qty * costVal;

                if (discPct > 100 || discPct < 0) {
                    e.preventDefault();
                    notifyWarn(`Discount % 100 se zyada nahi ho sakta for "${itemName}"! Kripya sahi discount enter karein.`, 'Invalid Discount');
                    $row.find('.pr-disc-percent').focus().select();
                    hasError = true;
                    return false;
                }

                if (discAmt > (baseVal + 0.001) || discAmt < 0) {
                    e.preventDefault();
                    notifyWarn(`Discount amount line total se zyada nahi ho sakta for "${itemName}"! Kripya sahi discount enter karein.`, 'Invalid Discount');
                    $row.find('.pr-disc-amount').focus().select();
                    hasError = true;
                    return false;
                }
            });

            if (hasError) return false;

            if (validCount === 0) {
                e.preventDefault();
                notifyWarn('Pehle item add karein. Please add at least one item before saving.', 'No Items Added');
                $('#pr-items-body .pr-item-row:first .pr-item-code').focus();
                return false;
            }

            // Remove purely empty rows before submitting
            $('#pr-items-body .pr-item-row').each(function () {
                let itemId = $(this).find('.pr-item-id').val();
                if (!itemId) {
                    $(this).remove();
                }
            });

            // Re-index remaining rows so items[0], items[1] are contiguous
            $('#pr-items-body .pr-item-row').each(function (idx) {
                $(this).find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.indexOf('items[') !== -1) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        // Initialize calculations
        recalculateAll();

        // Universal compact layout auto-fit engine
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#pr-items-table',
                minHeight: 160
            });
        }
    })();
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
@endpush
