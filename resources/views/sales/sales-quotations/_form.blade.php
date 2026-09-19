@php
    $quote = $salesQuotation ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($quote?->items ?? collect());
    $selectedCust = $quote->customer_id ?? old('customer_id');
    $selectedBranch = $quote->branch_id ?? old('branch_id');
    $selectedSalesType = $quote->sales_type ?? old('sales_type', 'Local');
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-file-invoice text-primary mr-1"></i> Quotation Details</h6>
    <x-form-layout-customizer
        form-key="sales_quotations.header"
        container-id="sq-header-fields-grid"
        title="Customize Sales Quotation Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="sq-header-fields-grid">
    <div class="field-wrapper col-md-3 form-group" data-field="customer_id" data-label="Customer" data-default-order="1" data-core="1">
        <label>Customer <span class="text-danger">*</span></label>
        <select name="customer_id" class="form-control form-control-sm select2" required>
            <option value="">Select a customer</option>
            @foreach($customers as $id => $name)
                <option value="{{ $id }}" @selected($selectedCust == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field-wrapper col-md-3 form-group" data-field="branch_id" data-label="Branch" data-default-order="2" data-core="1">
        <label>Branch <span class="text-danger">*</span></label>
        <select name="branch_id" class="form-control form-control-sm select2" required>
            <option value="">Select a branch</option>
            @foreach($branches as $id => $name)
                <option value="{{ $id }}" @selected($selectedBranch == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="quotation_date" data-label="Quotation Date" data-default-order="3" data-core="1">
        <label>Quotation Date <span class="text-danger">*</span></label>
        <input type="date" name="quotation_date" class="form-control form-control-sm" value="{{ optional($quote?->quotation_date ?? now())->format('Y-m-d') }}" required>
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="valid_until" data-label="Valid Until" data-default-order="4">
        <label>Valid Until</label>
        <input type="date" name="valid_until" class="form-control form-control-sm" value="{{ optional($quote?->valid_until ?? now())->format('Y-m-d') }}">
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="sales_type" data-label="Sales Type" data-default-order="5" data-core="1">
        <label>Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="sq-sales-type" class="form-control form-control-sm" required>
            <option value="Local" @selected($selectedSalesType === 'Local')>Local</option>
            <option value="Interstate" @selected($selectedSalesType === 'Interstate')>Interstate</option>
        </select>
    </div>
    <div class="field-wrapper col-md-3 form-group" data-field="status" data-label="Status" data-default-order="6" data-core="1">
        <label>Status</label>
        <select name="status" class="form-control form-control-sm">
            @foreach(['Draft', 'Sent', 'Accepted'] as $st)
                <option value="{{ $st }}" @selected(($quote->status ?? 'Draft') === $st)>{{ $st }}</option>
            @endforeach
            @if(isset($quote) && in_array($quote->status, ['Converted', 'Cancelled']))
                <option value="{{ $quote->status }}" selected>{{ $quote->status }}</option>
            @endif
        </select>
    </div>
    <div class="field-wrapper col-md-9 form-group" data-field="remarks" data-label="Remarks" data-default-order="7">
        <label>Remarks</label>
        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional remarks or terms..." value="{{ $quote->remarks ?? old('remarks') }}">
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 text-primary font-weight-bold"><i class="fas fa-boxes mr-1"></i> Line Items</h5>
    <button type="button" class="btn btn-sm btn-outline-primary" id="sq-add-row-btn">
        <i class="fas fa-plus mr-1"></i> Add Row
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="sq-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th style="width: 130px;">Code / Barcode</th>
                <th style="min-width: 250px;">Item Description</th>
                <th style="width: 100px;" class="text-right">Qty</th>
                <th style="width: 120px;" class="text-right">Sell Price</th>
                <th style="width: 110px;" class="text-right">MRP</th>
                <th style="width: 90px;" class="text-right">Disc %</th>
                <th style="width: 100px;" class="text-right">Disc Amt</th>
                <th style="width: 85px;" class="text-right">GST %</th>
                <th style="width: 120px;" class="text-right">Net Amount</th>
                <th style="width: 35px;" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="sq-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-quotations._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-quotations._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="3" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="sq-footer-qty">0.00</td>
                <td colspan="2" class="align-middle"></td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="sq-footer-disc">0.00</td>
                <td class="align-middle"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="sq-footer-net">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="row justify-content-end mt-3">
    <div class="col-md-4">
        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Sub Total:</span>
                    <span class="font-weight-bold" id="sq-summary-subtotal">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Total Discount:</span>
                    <span class="text-danger font-weight-bold" id="sq-summary-disc">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">GST Amount:</span>
                    <span class="text-info font-weight-bold" id="sq-summary-gst">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Round Off:</span>
                    <input type="number" step="0.01" name="round_off" id="sq-round-off" class="form-control form-control-sm text-right font-weight-bold" style="width: 100px;" value="{{ $quote->round_off ?? old('round_off', '0.00') }}">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between text-lg font-weight-bold">
                    <span>Grand Total:</span>
                    <span class="text-success" id="sq-summary-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row Template for JS --}}
<table class="d-none">
    <tbody id="sq-row-template">
        @include('sales.sales-quotations._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
    </tbody>
</table>

{{-- ============================================================
     ITEM SEARCH MODAL for Sales Quotation
     ============================================================ --}}
<div class="modal fade" id="sq-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="sqItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow border-dark">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="sqItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Quotation Item
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
                            <input type="text" id="sq-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="sq-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="sq-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <div id="sq-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="sq-isl-no-results" class="text-center py-4">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div class="table-responsive d-none" id="sq-isl-table-wrap" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="sq-isl-items-table">
                        <thead class="bg-dark text-white sticky-top">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 120px;">Code</th>
                                <th class="text-center" style="width: 110px;">Stock</th>
                                <th class="text-right" style="width: 90px;">Sell Price</th>
                                <th class="text-right" style="width: 90px;">MRP</th>
                                <th class="text-right" style="width: 80px;">GST %</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="sq-isl-items-body"></tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="sq-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
$(function() {
    let nextIndex = {{ count($existingItems) > 0 ? count($existingItems) : 1 }};
    let sqActiveSearchRow = null;
    let sqIslDebounce = null;
    let sqModalOpen = false;
    let sqModalClosing = false;
    const SQ_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

    $('#sq-add-row-btn').on('click', function() {
        let html = $('#sq-row-template').html().replace(/__INDEX__/g, nextIndex++);
        let $newRow = $(html);
        $('#sq-items-body').append($newRow);
        recalcAll();
        $newRow.find('.sq-item-code').focus();
    });

    $(document).off('keydown', '.sq-disc-amount, .sq-gst-percent').on('keydown', '.sq-disc-amount, .sq-gst-percent', function (e) {
        if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
            let $currentRow = $(this).closest('tr');
            let $nextRow = $currentRow.next('tr');
            if ($nextRow.length) {
                e.preventDefault();
                $nextRow.find('.sq-item-code').focus();
            } else {
                e.preventDefault();
                $('#sq-add-row-btn').trigger('click');
            }
        }
    });

    $(document).on('click', '.sq-remove-row', function() {
        if ($('#sq-items-body tr').length > 1) {
            $(this).closest('tr').remove();
            recalcAll();
        } else {
            alert('At least one line item is required.');
        }
    });

    /* ----------------------------------------------------------------
       ITEM SEARCH MODAL — open on click of Code/Barcode or F2
       ---------------------------------------------------------------- */
    let sqCancellingRow = null;

    $(document).off('click focus', '.sq-item-code').on('click focus', '.sq-item-code', function (e) {
        if (sqModalOpen || sqModalClosing) return;
        let $row = $(this).closest('tr');
        if (e.type === 'focus' && $row.find('.sq-item-select').val()) return;
        sqActiveSearchRow = $row;
        let prefill = $.trim($(this).val());
        $('#sq-isl-filter-name').val(prefill);
        $('#sq-isl-filter-code').val('');
        fetchSqItemList();
        sqModalOpen = true;
        $('#sq-item-search-modal').modal('show');
        $('#sq-item-search-modal').one('shown.bs.modal', function () {
            $('#sq-isl-filter-name').focus().select();
        });
    });

    let sqItemSelectedInModal = false;

    $('#sq-item-search-modal').on('show.bs.modal', function () {
        sqModalOpen = true;
        sqModalClosing = false;
        sqItemSelectedInModal = false;
        sqCancellingRow = null;
    });
    $('#sq-item-search-modal').on('hide.bs.modal', function () {
        sqModalOpen = false;
        sqModalClosing = true;
        if (!sqItemSelectedInModal && sqActiveSearchRow && sqActiveSearchRow.length) {
            let selectedId = sqActiveSearchRow.find('.sq-item-select').val();
            if (!selectedId) {
                sqCancellingRow = sqActiveSearchRow;
            }
        }
    });
    $('#sq-item-search-modal').on('hidden.bs.modal', function () {
        sqModalOpen = false;
        sqModalClosing = true;
        setTimeout(function () { sqModalClosing = false; }, 350);

        if (!sqItemSelectedInModal && sqCancellingRow && sqCancellingRow.length) {
            let totalRows = $('#sq-items-body tr').length;
            if (totalRows > 1) {
                sqCancellingRow.remove();
                updateSqRowNumbers();
                calculateSqTotals();
            } else {
                sqCancellingRow.find('.sq-item-code').val('');
                sqCancellingRow.find('.sq-item-desc').val('');
            }
            sqCancellingRow = null;
            sqActiveSearchRow = null;
            setTimeout(function () {
                let $target = $('#sq-add-row, #freight, button[type=submit]');
                $target.first().focus();
            }, 60);
            return;
        }

        sqItemSelectedInModal = false;
        sqCancellingRow = null;
        sqActiveSearchRow = null;
    });

    $('#sq-isl-filter-name, #sq-isl-filter-code').on('input', function () {
        clearTimeout(sqIslDebounce);
        sqIslDebounce = setTimeout(fetchSqItemList, 300);
    });

    $('#sq-isl-btn-clear').on('click', function () {
        $('#sq-isl-filter-name, #sq-isl-filter-code').val('');
        fetchSqItemList();
    });

    function fetchSqItemList() {
        let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
        let srch = $.trim($('#sq-isl-filter-name').val());
        let code = $.trim($('#sq-isl-filter-code').val());

        if (!srch && !code) {
            $('#sq-isl-loading').addClass('d-none');
            $('#sq-isl-table-wrap').addClass('d-none');
            $('#sq-isl-items-body').empty();
            $('#sq-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i><p class="mt-2 text-muted">Start typing to search items…</p>'
            );
            $('#sq-isl-count-label').text('');
            return;
        }

        $('#sq-isl-loading').removeClass('d-none');
        $('#sq-isl-no-results').addClass('d-none');
        $('#sq-isl-table-wrap').addClass('d-none');

        $.getJSON(SQ_ISL_URL, { branch_id: branchId, search: srch, code: code }, function (res) {
            $('#sq-isl-loading').addClass('d-none');
            let items = res.items || [];
            let $tbody = $('#sq-isl-items-body').empty();

            if (items.length === 0) {
                $('#sq-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted">No items found.</p>'
                );
                $('#sq-isl-count-label').text('');
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';
                let stockClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';

                html += `
                    <tr class="sq-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-gst="${it.gst_percent || 0}">
                        <td class="align-middle text-center text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center ${stockClass}">${parseFloat(it.qty || 0).toFixed(2)}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-right">${parseFloat(it.gst_percent || 0).toFixed(0)}%</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 sq-isl-btn-select">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#sq-isl-table-wrap').removeClass('d-none');
            $('#sq-isl-count-label').text(items.length + ' item(s) found');
        }).fail(function () {
            $('#sq-isl-loading').addClass('d-none');
        });
    }

    $(document).on('click', '.sq-isl-item-row, .sq-isl-btn-select', function (e) {
        e.stopPropagation();
        let $tr = $(this).hasClass('sq-isl-item-row') ? $(this) : $(this).closest('tr');
        let itemData = {
            id: $tr.data('id'),
            name: $tr.data('name'),
            code: $tr.data('code'),
            sell_price: $tr.data('sell'),
            mrp: $tr.data('mrp'),
            gst_percent: $tr.data('gst')
        };

        if (!sqActiveSearchRow || !itemData.id) return;

        sqItemSelectedInModal = true;
        sqCancellingRow = null;

        let $row = sqActiveSearchRow;
        $row.find('.sq-item-code').val(itemData.code);
        $row.find('.sq-item-desc').val(itemData.name + (itemData.code ? ' [' + itemData.code + ']' : ''));
        $row.find('.sq-item-select').val(itemData.id);

        if (!$row.find('.sq-qty').val()) {
            $row.find('.sq-qty').val(1);
        }
        $row.find('.sq-sell-price').val(parseFloat(itemData.sell_price || 0).toFixed(2));
        $row.find('.sq-mrp').val(parseFloat(itemData.mrp || 0).toFixed(2));
        $row.find('.sq-gst-percent').val(parseFloat(itemData.gst_percent || 0).toFixed(2));

        recalcRow($row);
        $row.find('.sq-qty').focus().select();

        $('#sq-item-search-modal').modal('hide');
    });

    $(document).on('input', '.sq-qty, .sq-sell-price, .sq-disc-percent, .sq-disc-amount, .sq-gst-percent', function() {
        let $row = $(this).closest('tr');
        let isDiscPct = $(this).hasClass('sq-disc-percent');
        recalcRow($row, isDiscPct);
    });

    $('#sq-round-off').on('input', function() {
        recalcSummary();
    });

    function recalcRow($row, isDiscPctChanged) {
        let qty = parseFloat($row.find('.sq-qty').val()) || 0;
        let price = parseFloat($row.find('.sq-sell-price').val()) || 0;
        let base = qty * price;

        let discPctInput = $row.find('.sq-disc-percent');
        let discAmtInput = $row.find('.sq-disc-amount');
        let discPct = parseFloat(discPctInput.val()) || 0;
        let discAmt = parseFloat(discAmtInput.val()) || 0;

        if (isDiscPctChanged) {
            discAmt = (base * discPct) / 100;
            discAmtInput.val(discAmt > 0 ? discAmt.toFixed(2) : '');
        } else if (discAmt > 0 && base > 0) {
            discPct = (discAmt / base) * 100;
            discPctInput.val(discPct.toFixed(2));
        }

        let taxable = Math.max(0, base - discAmt);
        let gstPct = parseFloat($row.find('.sq-gst-percent').val()) || 0;
        let gstAmt = (taxable * gstPct) / 100;
        let net = taxable + gstAmt;

        $row.find('.sq-row-net').text(net.toFixed(2));
        recalcSummary();
    }

    function recalcSummary() {
        let totQty = 0;
        let totBase = 0;
        let totDisc = 0;
        let totGst = 0;
        let totNet = 0;

        $('#sq-items-body tr').each(function(i) {
            $(this).find('.sq-sr-no').text(i + 1);
            let qty = parseFloat($(this).find('.sq-qty').val()) || 0;
            let price = parseFloat($(this).find('.sq-sell-price').val()) || 0;
            let base = qty * price;
            let disc = parseFloat($(this).find('.sq-disc-amount').val()) || 0;
            let taxable = Math.max(0, base - disc);
            let gstPct = parseFloat($(this).find('.sq-gst-percent').val()) || 0;
            let gst = (taxable * gstPct) / 100;
            let net = taxable + gst;

            totQty += qty;
            totBase += base;
            totDisc += disc;
            totGst += gst;
            totNet += net;
        });

        let roundOff = parseFloat($('#sq-round-off').val()) || 0;
        let grandTotal = totNet + roundOff;

        $('#sq-footer-qty').text(totQty.toFixed(2));
        $('#sq-footer-disc').text('₹' + totDisc.toFixed(2));
        $('#sq-footer-net').text('₹' + totNet.toFixed(2));

        $('#sq-summary-subtotal').text('₹' + totBase.toFixed(2));
        $('#sq-summary-disc').text('-₹' + totDisc.toFixed(2));
        $('#sq-summary-gst').text('₹' + totGst.toFixed(2));
        $('#sq-summary-total').text('₹' + grandTotal.toFixed(2));
    }

    function recalcAll() {
        $('#sq-items-body tr').each(function() {
            recalcRow($(this));
        });
    }

    recalcAll();
});
</script>
@endpush
