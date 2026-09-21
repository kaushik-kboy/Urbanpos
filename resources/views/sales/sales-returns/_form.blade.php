@php
    $ret = $salesReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-undo text-primary mr-1"></i> Sales Return Header</h6>
    <x-form-layout-customizer
        form-key="sales_returns.header"
        container-id="sr-header-fields-grid"
        title="Customize Sales Return Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="sr-header-fields-grid">
    <div class="field-wrapper col-md-4 mb-3" data-field="customer_id" data-label="Customer" data-default-order="1" data-core="1">
        <label for="customer_id" class="font-weight-bold">Customer <span class="text-danger">*</span></label>
        <select name="customer_id" id="customer_id" class="form-control select2" required>
            <option value="">-- Select Customer --</option>
            @php
                $selectedCustId = old('customer_id', $ret->customer_id ?? ($presetCustomerId ?? ''));
                $selectedBillId = old('sales_bill_id', $ret->sales_bill_id ?? ($presetBillId ?? ''));
            @endphp
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}" @selected($selectedCustId == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    @php
        $selectedBranch = old('branch_id', $ret->branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?? 3));
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
    <div class="field-wrapper col-md-3 mb-3" data-field="sales_type" data-label="Sales Type" data-default-order="4" data-core="1">
        <label for="sales_type" class="font-weight-bold">Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="sales_type" class="form-control" required>
            <option value="Local" @selected(old('sales_type', $ret->sales_type ?? 'Local') === 'Local')>Local (CGST + SGST)</option>
            <option value="Interstate" @selected(old('sales_type', $ret->sales_type ?? '') === 'Interstate')>Interstate (IGST)</option>
        </select>
    </div>
    <div class="field-wrapper col-md-6 mb-3" data-field="sales_bill_id" data-label="Original Sales Bill" data-default-order="5">
        <label for="sales_bill_id" class="font-weight-bold">Original Sales Bill</label>
        <select name="sales_bill_id" id="sales_bill_id" class="form-control select2">
            <option value="">-- No Original Bill / Direct Return --</option>
            @foreach ($salesBills as $id => $no)
                <option value="{{ $id }}" @selected($selectedBillId == $id)>{{ $no }}</option>
            @endforeach
        </select>
        <small class="text-muted">Bill select karte hi items automatically load ho jayenge.</small>
    </div>
    <div class="field-wrapper col-md-6 mb-3" data-field="return_mode" data-label="Return Mode" data-default-order="6" data-core="1">
        <label for="return_mode" class="font-weight-bold">Return Mode <span class="text-danger">*</span></label>
        <select name="return_mode" id="return_mode" class="form-control" required>
            @foreach (['Cash' => 'Cash', 'Credit Note' => 'Credit Note', 'Wallet' => 'Wallet', 'Card' => 'Card', 'RRN' => 'RRN'] as $val => $lbl)
                <option value="{{ $val }}" @selected(old('return_mode', $ret->return_mode ?? 'Cash') === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
</div>

<hr>
{{-- Smart Bill Item Picker: shown when a Sales Bill is selected --}}
<div id="sr-bill-picker-wrap" class="card border-primary mb-3 bg-light shadow-sm" style="display: none;">
    <div class="card-body py-2 px-3">
        <div class="row align-items-center">
            <div class="col-md-7 mb-2 mb-md-0">
                <label class="small font-weight-bold text-primary mb-1">
                    <i class="fas fa-receipt mr-1"></i> Select Item from Sales Bill to Return:
                </label>
                <div class="input-group input-group-sm">
                    <select id="sr-bill-item-select" class="form-control form-control-sm">
                        <option value="">-- Choose an item from this bill --</option>
                    </select>
                </div>
                <small class="text-muted">Return quantity cannot exceed original bill quantity. Select 1 item or click "Add All Items".</small>
            </div>
            <div class="col-md-5 text-md-right pt-2 pt-md-0">
                <button type="button" id="btn-add-bill-item" class="btn btn-primary btn-sm font-weight-bold mr-1">
                    <i class="fas fa-plus mr-1"></i> Add to Return
                </button>
                <button type="button" id="btn-add-all-bill-items" class="btn btn-outline-secondary btn-sm font-weight-bold">
                    <i class="fas fa-layer-group mr-1"></i> Add All Bill Items
                </button>
            </div>
        </div>
    </div>
</div>

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
                <th style="width: 115px;">Code / Barcode</th>
                <th style="min-width: 220px;">Item Description <span class="text-danger">*</span></th>
                <th style="width: 135px;">Exp Date</th>
                <th style="width: 90px;" class="text-right">Qty <span class="text-danger">*</span></th>
                <th style="width: 110px;" class="text-right">Sell Price <span class="text-danger">*</span></th>
                <th style="width: 100px;" class="text-right">MRP</th>
                <th style="width: 85px;" class="text-right">Disc %</th>
                <th style="width: 100px;" class="text-right">Disc Amt</th>
                <th style="width: 75px;" class="text-right">GST %</th>
                <th style="width: 115px;" class="text-right">Net Amount</th>
                <th style="width: 40px;" class="text-center"></th>
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
                <td colspan="3" class="text-right align-middle">Summary Totals:</td>
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

<x-custom-fields-renderer :module="'SalesReturn'" :model="$ret ?? null" :cardStyle="true" />

<template id="sr-row-template">
    @include('sales.sales-returns._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

{{-- ============================================================
     ITEM SEARCH MODAL for Sales Return — opens on Code/Barcode click
     ============================================================ --}}
<div class="modal fade" id="sr-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="srItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="srItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Return Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="sr-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="sr-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="sr-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <div id="sr-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="sr-isl-no-results" class="text-center py-4">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div class="table-responsive d-none" id="sr-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="sr-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 120px;">Code</th>
                                <th class="text-center" style="width: 130px;">Expiry</th>
                                <th class="text-right" style="width: 85px;">Sell Price</th>
                                <th class="text-right" style="width: 85px;">MRP</th>
                                <th class="text-right" style="width: 80px;">GST %</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="sr-isl-items-body"></tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="sr-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    (function () {
        let rowIndex = {{ max(count($existingItems), 1) }};
        let srActiveSearchRow = null;
        let srIslDebounce = null;
        const SR_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL — open on click of Code/Barcode field
           ---------------------------------------------------------------- */
        let srCancellingRow = null;

        $(document).off('click focus', '.sr-item-code').on('click focus', '.sr-item-code', function (e) {
            let custId = $('#customer_id').val();
            if (!custId) {
                if (e.type === 'click') {
                    alert('Please select a Customer first. Items are restricted to products purchased by that customer.');
                    $('#customer_id').select2('open');
                }
                return;
            }
            if ($('#sales_bill_id').val()) {
                if (e.type === 'click') {
                    alert('Items are restricted to the selected Sales Bill. Please select items from the "Select Item from Sales Bill" dropdown above.');
                }
                return;
            }
            let $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.sr-item-select').val()) return;
            srActiveSearchRow = $row;
            let prefill = $.trim($(this).val());
            $('#sr-isl-filter-name').val(prefill);
            $('#sr-isl-filter-code').val('');
            srFetchItemList();
            $('#sr-item-search-modal').modal('show');
            $('#sr-item-search-modal').one('shown.bs.modal', function () {
                $('#sr-isl-filter-name').focus().select();
            });
        });

        // Filter inputs — debounced
        $('#sr-isl-filter-name, #sr-isl-filter-code').on('input', function () {
            clearTimeout(srIslDebounce);
            srIslDebounce = setTimeout(srFetchItemList, 400);
        });

        $('#sr-isl-btn-clear').on('click', function () {
            $('#sr-isl-filter-name, #sr-isl-filter-code').val('');
            srFetchItemList();
        });

        let srIslCache = {};

        function srFetchItemList() {
            let srch = $('#sr-isl-filter-name').val().trim();
            let code = $('#sr-isl-filter-code').val().trim();
            let custId = $('#customer_id').val() || '';

            if (!srch && !code) {
                $('#sr-isl-loading').addClass('d-none');
                $('#sr-isl-table-wrap').addClass('d-none');
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">Start typing to search customer purchased items…</p>'
                );
                $('#sr-isl-count-label').text('');
                return;
            }

            let cacheKey = srch + '|' + code + '|' + custId;
            if (srIslCache[cacheKey]) {
                srRenderItems(srIslCache[cacheKey]);
                return;
            }

            $('#sr-isl-loading').removeClass('d-none');
            $('#sr-isl-no-results').addClass('d-none');
            $('#sr-isl-table-wrap').addClass('d-none');

            $.getJSON(SR_ISL_URL, { search: srch, code: code, customer_id: custId }, function (res) {
                $('#sr-isl-loading').addClass('d-none');
                srIslCache[cacheKey] = res.items || [];
                setTimeout(function () { delete srIslCache[cacheKey]; }, 60000);
                srRenderItems(res.items || []);
            }).fail(function () {
                $('#sr-isl-loading').addClass('d-none');
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-exclamation-circle fa-2x text-danger"></i>' +
                    '<p class="mt-2 text-muted">Error loading items. Please try again.</p>'
                );
            });
        }

        function srRenderItems(items) {
            let $tbody = $('#sr-isl-items-body');
            $tbody.empty();
            if (items.length === 0) {
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#sr-isl-count-label').text('');
                return;
            }
            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = it.exp_date
                    ? `<span class="badge badge-danger px-2 py-1">${it.exp_date}</span>`
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                html += `
                    <tr class="sr-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name || ''}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-gst="${it.gst_percent || 0}"
                        data-exp="${it.exp_date || ''}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx+1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right text-success font-weight-bold">${it.sell_price > 0 ? '\u20b9'+parseFloat(it.sell_price).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-right text-muted">${it.mrp > 0 ? '\u20b9'+parseFloat(it.mrp).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-right">${it.gst_percent || 0}%</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 sr-isl-btn-select"
                                data-id="${it.id}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            $('#sr-isl-table-wrap').removeClass('d-none');
            $('#sr-isl-no-results').addClass('d-none');
            $('#sr-isl-count-label').text(items.length + ' item(s) found');
        }

        // Row or Select button click — populate the active row
        $(document).on('click', '.sr-isl-item-row, .sr-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('sr-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');
            let itemName = $row.data('name');
            let sell     = $row.data('sell');
            let mrp      = $row.data('mrp');
            let gst      = $row.data('gst');
            let exp      = $row.data('exp');

            if (!srActiveSearchRow || !itemId) return;

            srItemSelectedInModal = true;
            srCancellingRow = null;

            srActiveSearchRow.find('.sr-item-code').val(itemId);
            srActiveSearchRow.find('.sr-item-desc').val(itemName + (itemCode ? ' [' + itemCode + ']' : ''));
            srActiveSearchRow.find('.sr-item-select').val(itemId);
            srActiveSearchRow.find('.sr-exp-date').val(exp || '');
            srActiveSearchRow.find('.sr-price').val(sell > 0 ? parseFloat(sell).toFixed(2) : '');
            srActiveSearchRow.find('.sr-mrp').val(mrp > 0 ? parseFloat(mrp).toFixed(2) : '');
            srActiveSearchRow.find('.sr-gst-percent').val(gst || '');
            srActiveSearchRow.find('.sr-disc-percent').val('').trigger('input');
            srActiveSearchRow.find('.sr-disc-amount').val('');

            // Focus qty
            srActiveSearchRow.find('.sr-qty').val('').focus();
            $('#sr-item-search-modal').modal('hide');
        });

        let srItemSelectedInModal = false;

        // When modal closes, cleanly dismiss
        $('#sr-item-search-modal').on('show.bs.modal', function () {
            srItemSelectedInModal = false;
            srCancellingRow = null;
        });

        $('#sr-item-search-modal').on('hide.bs.modal', function () {
            if (!srItemSelectedInModal && srActiveSearchRow && srActiveSearchRow.length) {
                let selectedId = srActiveSearchRow.find('.sr-item-select').val();
                if (!selectedId) {
                    srCancellingRow = srActiveSearchRow;
                }
            }
        });
        $('#sr-item-search-modal').on('hidden.bs.modal', function () {
            if (!srItemSelectedInModal && srCancellingRow && srCancellingRow.length) {
                let totalRows = $('#sr-items-body tr').length;
                if (totalRows > 1) {
                    srCancellingRow.remove();
                    updateSrRowNumbers();
                    calculateSrTotals();
                } else {
                    srCancellingRow.find('.sr-item-code').val('');
                    srCancellingRow.find('.sr-item-desc').val('');
                }
                srCancellingRow = null;
                srActiveSearchRow = null;
                setTimeout(function () {
                    let $target = $('#sr-add-row, #freight, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }
            srItemSelectedInModal = false;
            srCancellingRow = null;
            srActiveSearchRow = null;
        });

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

            // GST included in price (Tax-Inclusive matching Sales Bill - Task 7)
            const net = Math.max(0, base - discAmount);
            const taxable = gstPercent > 0 ? (net / (1 + (gstPercent / 100))) : net;
            const gstAmount = Math.round((net - taxable) * 100) / 100;

            const netSpan = row.querySelector('.sr-net-amount');
            if (netSpan) {
                netSpan.innerText = net.toFixed(2);
            }

            // Real-time inline field validation (Task 11)
            const qtyInput = row.querySelector('.sr-qty');
            if (qtyInput) {
                const maxQty = parseFloat(qtyInput.getAttribute('data-original-qty') || qtyInput.getAttribute('max')) || 0;
                if (qty <= 0) {
                    qtyInput.classList.add('is-invalid', 'border-danger');
                    qtyInput.title = 'Quantity must be greater than 0';
                } else if (maxQty > 0 && qty > maxQty) {
                    qtyInput.classList.add('is-invalid', 'border-danger');
                    qtyInput.title = `Return quantity cannot exceed original bill quantity (${maxQty})`;
                } else {
                    qtyInput.classList.remove('is-invalid', 'border-danger');
                    qtyInput.title = '';
                }
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
            // Focus the code field on new row
            setTimeout(function () {
                newRow.querySelector('.sr-item-code')?.focus();
            }, 50);
            rowIndex++;
            recalculateAll();
        });

        document.getElementById('sr-items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sr-row-remove');
            if (!btn) return;
            btn.closest('tr').remove();
            recalculateAll();
        });

        document.getElementById('sr-items-body')?.addEventListener('input', function (e) {
            if (e.target.matches('.sr-qty, .sr-price, .sr-mrp, .sr-disc-percent, .sr-disc-amount, .sr-gst-percent')) {
                recalculateAll();
            }
        });

        $(document).off('keydown', '.sr-disc-amount, .sr-gst-percent').on('keydown', '.sr-disc-amount, .sr-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.sr-item-code').focus();
                } else {
                    e.preventDefault();
                    $('#sr-add-row').trigger('click');
                }
            }
        });

        // Cap return quantity to original bill quantity
        $(document).on('input change', '.sr-qty', function () {
            let maxQty = parseFloat($(this).attr('data-original-qty') || $(this).attr('max'));
            let currentVal = parseFloat($(this).val()) || 0;
            if (maxQty > 0 && currentVal > maxQty) {
                alert(`Return quantity cannot exceed original bill quantity (${maxQty}). Quantity adjusted.`);
                $(this).val(maxQty);
            }
            recalculateAll();
        });

        document.getElementById('round_off')?.addEventListener('input', recalculateAll);
        document.getElementById('total_extra_cess')?.addEventListener('input', recalculateAll);
        document.getElementById('gst_calamity_cess')?.addEventListener('input', recalculateAll);

        // Customer Select2 Remote AJAX search (search any customer by name or mobile)
        let $custSelect = $('#customer_id');
        if ($custSelect.hasClass('select2-hidden-accessible')) {
            $custSelect.select2('destroy');
        }
        $custSelect.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Search Customer by Name or Mobile --',
            allowClear: true,
            ajax: {
                url: '{{ route("sales.sales-bills.customer-search") }}',
                dataType: 'json',
                delay: 200,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            }
        });

        // Smart Bill Items Picker & Automatic Loader when Bill is selected
        let cachedBillItems = [];
        let isAutoLoadingBill = false;

        function loadBillItems(billId, preserveExisting = false) {
            if (!billId || isAutoLoadingBill) return;

            isAutoLoadingBill = true;
            const tbody = document.getElementById('sr-items-body');
            const originalRows = tbody.innerHTML;
            if (!preserveExisting) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-primary"><i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2 font-weight-bold">Loading items from sales bill...</div></td></tr>';
            }

            fetch(`/sales/sales-returns/bill-items/${billId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.branch_id) {
                    $('#branch_id').val(data.branch_id).trigger('change');
                }
                if (data.sales_type) {
                    $('#sales_type').val(data.sales_type).trigger('change');
                }

                cachedBillItems = data.items || [];

                // Populate smart item picker dropdown
                let $pickerSelect = $('#sr-bill-item-select');
                let optHtml = '<option value="">-- Choose item from original bill (' + cachedBillItems.length + ' items) --</option>';
                cachedBillItems.forEach((item, idx) => {
                    let codeStr = item.item_code ? ' [' + item.item_code + ']' : '';
                    let expStr = item.exp_date ? ' (Exp: ' + item.exp_date + ')' : '';
                    optHtml += `<option value="${idx}">${item.item_name}${codeStr} - Sold: ${item.original_qty} @ ₹${parseFloat(item.sell_price).toFixed(2)}${expStr}</option>`;
                });
                $pickerSelect.html(optHtml);

                if (preserveExisting) {
                    // Update max on existing rows
                    $('#sr-items-body .sr-item-row').each(function () {
                        let itemId = $(this).find('.sr-item-select').val();
                        let found = cachedBillItems.find(b => String(b.item_id) === String(itemId));
                        if (found) {
                            $(this).find('.sr-qty').attr('max', found.original_qty).attr('data-original-qty', found.original_qty);
                            $(this).find('.sr-max-qty-label').text('Max: ' + found.original_qty).show();
                            $(this).find('.sr-item-code').prop('readonly', true);
                        }
                    });
                    recalculateAll();
                } else {
                    // Show helpful instruction placeholder
                    tbody.innerHTML = `<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-info-circle text-primary mr-1"></i> Original Sales Bill loaded (${cachedBillItems.length} items). Select the item being returned from the dropdown above, or click <strong>Add All Bill Items</strong>.</td></tr>`;
                    recalculateAll();
                }
            })
            .catch(err => {
                console.error(err);
                if (!preserveExisting) {
                    tbody.innerHTML = originalRows;
                }
                alert('Failed to load items from sales bill.');
            })
            .finally(() => {
                isAutoLoadingBill = false;
            });
        }

        function appendBillItemRow(item, initialQty) {
            const template = document.getElementById('sr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            const row = tempWrapper.firstElementChild;

            // Fill item fields
            const hiddenId = row.querySelector('.sr-item-select');
            if (hiddenId) hiddenId.value = item.item_id;

            const codeInput = row.querySelector('.sr-item-code');
            if (codeInput) {
                codeInput.value = item.item_id;
                codeInput.readOnly = true;
                codeInput.title = 'Item ID from Sales Bill (cannot be changed)';
            }

            const descInput = row.querySelector('.sr-item-desc');
            if (descInput) descInput.value = item.item_name + (item.item_code ? ' [' + item.item_code + ']' : '');

            const qtyInput = row.querySelector('.sr-qty');
            if (qtyInput) {
                qtyInput.value = initialQty;
                qtyInput.max = item.original_qty;
                qtyInput.setAttribute('data-original-qty', item.original_qty);
            }

            const maxLabel = row.querySelector('.sr-max-qty-label');
            if (maxLabel) {
                maxLabel.innerText = 'Max: ' + item.original_qty;
                maxLabel.style.display = 'block';
            }

            const priceInput = row.querySelector('.sr-price');
            if (priceInput) priceInput.value = parseFloat(item.sell_price).toFixed(2);

            const mrpInput = row.querySelector('.sr-mrp');
            if (mrpInput) mrpInput.value = parseFloat(item.mrp || 0).toFixed(2);

            const discPctInput = row.querySelector('.sr-disc-percent');
            if (discPctInput) discPctInput.value = item.disc_percent || 0;

            const discAmtInput = row.querySelector('.sr-disc-amount');
            if (discAmtInput) discAmtInput.value = item.disc_amount || 0;

            const gstPctInput = row.querySelector('.sr-gst-percent');
            if (gstPctInput) gstPctInput.value = item.gst_percent || 0;

            const expInput = row.querySelector('.sr-exp-date');
            if (expInput && item.exp_date) expInput.value = item.exp_date;

            document.getElementById('sr-items-body').appendChild(row);
            rowIndex++;
            recalculateAll();

            // Focus the quantity input
            setTimeout(() => {
                row.querySelector('.sr-qty')?.focus();
                row.querySelector('.sr-qty')?.select();
            }, 50);
        }

        // Add 1 selected item from bill to return table
        $('#btn-add-bill-item').on('click', function () {
            let idx = $('#sr-bill-item-select').val();
            if (idx === '' || idx === null || typeof cachedBillItems[idx] === 'undefined') {
                alert('Please select an item from the bill dropdown first.');
                return;
            }

            let item = cachedBillItems[idx];
            let tbody = document.getElementById('sr-items-body');

            // If already in table, focus its qty input
            let existingRow = $(tbody).find(`.sr-item-row .sr-item-select[value="${item.item_id}"]`).closest('tr');
            if (existingRow.length > 0) {
                alert(`"${item.item_name}" is already in the return list. You can edit its return quantity below.`);
                existingRow.find('.sr-qty').focus();
                return;
            }

            // Remove placeholder if present
            if ($(tbody).find('td[colspan]').length > 0) {
                tbody.innerHTML = '';
            }

            appendBillItemRow(item, 1 <= item.original_qty ? 1 : item.original_qty);
        });

        // Add all items from bill to return table
        $('#btn-add-all-bill-items').on('click', function () {
            if (!cachedBillItems || cachedBillItems.length === 0) {
                alert('No items found in selected bill.');
                return;
            }
            let tbody = document.getElementById('sr-items-body');
            tbody.innerHTML = '';
            cachedBillItems.forEach(item => {
                appendBillItemRow(item, item.original_qty);
            });
        });

        function updateBillModeUI() {
            let billId = $('#sales_bill_id').val();
            if (billId) {
                $('#sr-add-row').hide();
                $('#sr-bill-picker-wrap').slideDown(200);
            } else {
                $('#sr-add-row').show();
                $('#sr-bill-picker-wrap').slideUp(200);
                cachedBillItems = [];
                $('#sr-items-body .sr-item-row').each(function () {
                    $(this).find('.sr-qty').removeAttr('max').removeAttr('data-original-qty');
                    $(this).find('.sr-max-qty-label').hide();
                    $(this).find('.sr-item-code').prop('readonly', false);
                });
            }
        }

        $('#sales_bill_id').on('change', function () {
            let billId = $(this).val();
            updateBillModeUI();
            if (billId) {
                loadBillItems(billId, false);
            }
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
                updateBillModeUI();
            });
        }

        // Header Validation (Task 11)
        function validateSrHeader(showAlert = false) {
            let isValid = true;
            let $cust = $('#customer_id');
            let custVal = $cust.val();
            let $custContainer = $cust.next('.select2-container').find('.select2-selection');

            if (!custVal) {
                $cust.addClass('is-invalid');
                $custContainer.addClass('border-danger');
                if (showAlert) {
                    alert('Please select a Customer first before entering return items.');
                    $cust.select2('open');
                }
                isValid = false;
            } else {
                $cust.removeClass('is-invalid');
                $custContainer.removeClass('border-danger');
            }

            let $date = $('#return_date');
            if (!$date.val()) {
                $date.addClass('is-invalid border-danger');
                if (showAlert && isValid) {
                    alert('Please select a Return Date.');
                    $date.focus();
                }
                isValid = false;
            } else {
                $date.removeClass('is-invalid border-danger');
            }

            return isValid;
        }

        $(document).on('click focusin', '#sr-add-row, #btn-add-bill-item, #btn-add-all-bill-items, #sr-items-body .sr-item-code', function (e) {
            if (!$('#customer_id').val()) {
                e.preventDefault();
                validateSrHeader(true);
                return false;
            }
        });

        $('#customer_id').on('change', function () {
            validateSrHeader(false);
            let custId = $(this).val();
            loadCustomerBills(custId);
        });

        $('#return_date').on('change blur', function () {
            validateSrHeader(false);
        });

        let initialCustId = $('#customer_id').val();
        let initialBillId = '{{ old("sales_bill_id", $ret->sales_bill_id ?? "") }}';
        if (initialCustId) {
            loadCustomerBills(initialCustId, initialBillId);
        } else if (initialBillId) {
            updateBillModeUI();
            loadBillItems(initialBillId, true);
        }

        // Form Submit Handler: validate header, prune invalid rows and re-index contiguous names
        $('form').on('submit', function (e) {
            if (!validateSrHeader(true)) {
                e.preventDefault();
                return false;
            }

            let hasBill = !!$('#sales_bill_id').val();
            let validRows = 0;
            let hasError = false;

            $('#sr-items-body .sr-item-row').each(function () {
                let itemId = $(this).find('.sr-item-select').val();
                let qty = parseFloat($(this).find('.sr-qty').val()) || 0;
                let origQty = parseFloat($(this).find('.sr-qty').attr('data-original-qty') || $(this).find('.sr-qty').attr('max')) || 0;

                if (!itemId || qty <= 0) {
                    $(this).remove();
                    return;
                }

                if (hasBill && origQty > 0 && qty > origQty + 0.0001) {
                    alert(`Return quantity (${qty}) cannot exceed original bill quantity (${origQty}).`);
                    $(this).find('.sr-qty').focus();
                    hasError = true;
                    return false;
                }
                validRows++;
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            if (validRows === 0) {
                alert('Please add at least one valid item to return.');
                e.preventDefault();
                return false;
            }

            // Re-index remaining rows so items[0], items[1] are contiguous
            $('#sr-items-body .sr-item-row').each(function (idx) {
                $(this).attr('data-row-index', idx);
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

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });

        @if(!empty($selectedBillId))
            setTimeout(function() {
                if ($('#sales_bill_id').val()) {
                    $('#sales_bill_id').trigger('change');
                }
            }, 300);
        @endif
    })();
</script>
@endpush
