@php
    $ret = $purchaseReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

    <div class="d-flex justify-content-end mb-2">
        <x-form-layout-customizer
            form-key="purchase_returns.header"
            container-id="purchase-return-header-grid"
            title="Customize Purchase Return Header"
        />
    </div>

    <div class="row form-fields-grid" id="purchase-return-header-grid">
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
        <div class="field-wrapper col-md-3 mb-3" data-field="branch_id" data-label="Branch" data-default-order="2" data-core="1">
            <label for="branch_id" class="font-weight-bold">Active Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Top Navbar</span></label>
            <div class="input-group">
                <input type="text" class="form-control font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedBranch] ?? 'Active Branch' }}">
                <input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">
                <div class="input-group-append">
                    <span class="input-group-text bg-light text-primary" title="Branch is selected globally from top navbar"><i class="fas fa-lock"></i></span>
                </div>
            </div>
        </div>
        <div class="field-wrapper col-md-2 mb-3" data-field="return_date" data-label="Return Date" data-default-order="3" data-core="1">
            <label for="return_date" class="font-weight-bold">Return Date <span class="text-danger">*</span></label>
            <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', optional($ret->return_date ?? now())->format('Y-m-d')) }}" required>
        </div>
        <div class="field-wrapper col-md-3 mb-3" data-field="purchase_type" data-label="Purchase Type" data-default-order="4" data-core="1">
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
                <th style="width: 170px;">Code / Barcode <span class="text-danger">*</span></th>
                <th style="min-width: 220px;">Item Description</th>
                <th style="width: 130px;">Exp Date</th>
                <th style="width: 95px;" class="text-right">Qty <span class="text-danger">*</span></th>
                <th style="width: 110px;" class="text-right">Cost Price <span class="text-danger">*</span></th>
                <th style="width: 85px;" class="text-right">Disc %</th>
                <th style="width: 95px;" class="text-right">Disc Amt</th>
                <th style="width: 85px;" class="text-right">GST %</th>
                <th style="width: 110px;" class="text-right">Net Amount</th>
                <th style="width: 40px;" class="text-center"></th>
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

        $(document).off('keydown', '.pr-disc-amount, .pr-gst-percent').on('keydown', '.pr-disc-amount, .pr-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr.pr-item-row');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.pr-item-code').focus();
                } else {
                    e.preventDefault();
                    $('#pr-add-row').trigger('click');
                    let $newRow = $('#pr-items-body tr.pr-item-row').last();
                    setTimeout(function () {
                        $newRow.find('.pr-item-code').focus();
                    }, 60);
                }
            }
        });

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL (Triggered on Click or Focus/Tab of Code field)
           ---------------------------------------------------------------- */
        function assertSupplierSelected() {
            let supplierId = $('#supplier_id').val();
            let invoiceId = $('#purchase_invoice_id').val();
            if (!supplierId && !invoiceId) {
                alert('Please select a Supplier first.');
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

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL (Triggered on Click or Focus/Tab of Code field)
           ---------------------------------------------------------------- */
        $(document).off('click focus', '.pr-item-code').on('click focus', '.pr-item-code', function (e) {
            if (prModalOpen || prModalClosing) return;
            let $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.pr-item-id').val()) return;
            if (!assertSupplierSelected()) return;

            prActiveSearchRow = $row;
            let prefill = $.trim($(this).val());
            $('#pr-isl-filter-name').val(prefill);
            $('#pr-isl-filter-code').val('');
            updateModalHeaderBadge();
            fetchPrItemList();
            prModalOpen = true;
            $('#pr-item-search-modal').modal('show');
            $('#pr-item-search-modal').one('shown.bs.modal', function () {
                $('#pr-isl-filter-name').focus().select();
            });
        });

        $(document).on('click', '.pr-search-btn', function (e) {
            e.preventDefault();
            if (!assertSupplierSelected()) return;

            prActiveSearchRow = $(this).closest('tr');
            let prefill = $.trim(prActiveSearchRow.find('.pr-item-code').val());
            $('#pr-isl-filter-name').val(prefill);
            $('#pr-isl-filter-code').val('');
            updateModalHeaderBadge();
            fetchPrItemList();
            prModalOpen = true;
            $('#pr-item-search-modal').modal('show');
            $('#pr-item-search-modal').one('shown.bs.modal', function () {
                $('#pr-isl-filter-name').focus().select();
            });
        });

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
                    let qtyCol = it.invoiced_qty !== null
                        ? `<span class="text-success font-weight-bold" title="Invoiced Quantity">${parseFloat(it.invoiced_qty).toFixed(2)}</span> <small class="text-muted d-block">(Stock: ${parseFloat(it.qty || 0).toFixed(2)})</small>`
                        : `<span class="${stockClass}">${parseFloat(it.qty || 0).toFixed(2)}</span>`;

                    html += `
                        <tr class="pr-isl-item-row" style="cursor:pointer;"
                            data-id="${it.id}"
                            data-code="${it.code || ''}"
                            data-name="${it.name}"
                            data-cost="${it.cost_price || 0}"
                            data-gst="${it.gst_percent || 0}"
                            data-disc-percent="${it.disc_percent || 0}"
                            data-disc-amount="${it.disc_amount || 0}"
                            data-exp-date="${it.exp_date || ''}">
                            <td class="align-middle text-center text-muted">${idx + 1}</td>
                            <td class="align-middle font-weight-bold text-dark">${it.name}</td>
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
                cost_price: $tr.data('cost'),
                gst_percent: $tr.data('gst'),
                disc_percent: $tr.data('disc-percent'),
                disc_amount: $tr.data('disc-amount'),
                exp_date: $tr.data('exp-date')
            };

            if (!prActiveSearchRow || !itemData.id) return;
            prItemSelectedInModal = true;
            prCancellingRow = null;
            prLastSelectedRow = prActiveSearchRow;

            let $row = prActiveSearchRow;
            $row.find('.pr-item-code').val(itemData.code);
            $row.find('.pr-item-desc').val(itemData.name);
            $row.find('.pr-item-id').val(itemData.id);

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

            recalculateAll();

            $('#pr-item-search-modal').modal('hide');
            setTimeout(function () {
                $row.find('.pr-qty').focus().select();
            }, 100);

            prActiveSearchRow = null;
        });

        // Direct Code typing and Enter/Blur lookup
        $(document).on('keydown blur', '.pr-item-code', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            if (e.type === 'keydown' && e.key === 'Enter') e.preventDefault();

            let $input = $(this);
            let query = $.trim($input.val());
            let $row = $input.closest('tr');
            if (!query) return;

            let supplierId = $('#supplier_id').val();
            let invoiceId = $('#purchase_invoice_id').val();

            if (!supplierId && !invoiceId) {
                alert('Please select a Supplier first.');
                $input.val('');
                if ($('#supplier_id').hasClass('select2-hidden-accessible')) {
                    $('#supplier_id').select2('open');
                } else {
                    $('#supplier_id').focus();
                }
                return;
            }

            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            $.getJSON(PR_LOOKUP_URL, {
                query: query,
                branch_id: branchId,
                supplier_id: supplierId,
                purchase_invoice_id: invoiceId
            }, function (res) {
                if (res && res.id) {
                    $row.find('.pr-item-code').val(res.code || query);
                    $row.find('.pr-item-desc').val(res.name);
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
                    $row.find('.pr-qty').focus().select();
                }
            }).fail(function (xhr) {
                let msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'This product was not found for the selected supplier/invoice.';
                alert(msg);
                $input.val('').focus();
                $row.find('.pr-item-id').val('');
                $row.find('.pr-item-desc').val('');
            });
        });

        // Filter Invoices when Supplier changes
        $('#supplier_id').on('change', function () {
            const supplierId = $(this).val();
            const $invSelect = $('#purchase_invoice_id');
            const currentSelected = $invSelect.val();
            $invSelect.empty().append('<option value="">-- No Original Invoice / Direct Return --</option>');

            let hasItems = false;
            $('#pr-items-body tr.pr-item-row').each(function () {
                if ($(this).find('.pr-item-id').val()) {
                    hasItems = true;
                }
            });

            if (hasItems) {
                if (confirm('Changing the supplier will clear the existing return items. Do you want to proceed?')) {
                    $('#pr-items-body').empty();
                    $('#pr-add-row').trigger('click');
                    recalculateAll();
                }
            }

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
                        if (qtyInput) qtyInput.value = item.qty;
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

        // Initialize calculations
        recalculateAll();
    })();
</script>
@endpush
