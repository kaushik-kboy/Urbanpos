@php
    $quote = $salesQuotation ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($quote?->items ?? collect());
    $selectedCust = $quote->customer_id ?? old('customer_id');
    $selectedBranch = $quote->branch_id ?? old('branch_id');
    $selectedSalesType = $quote->sales_type ?? old('sales_type', 'Local');
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
@endpush

<div class="d-flex justify-content-between align-items-center mb-2 tx-compact-section-header">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-file-invoice text-primary mr-1"></i> Quotation Details</h6>
    <x-form-layout-customizer
        form-key="sales_quotations.header"
        container-id="sq-header-fields-grid"
        title="Customize Sales Quotation Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-2 tx-header-fields-grid" id="sq-header-fields-grid">
    <div class="field-wrapper col-md-3 form-group" data-field="customer_id" data-label="Customer" data-default-order="1" data-core="1">
        <label>Customer <span class="text-danger">*</span></label>
        <select name="customer_id" class="form-control form-control-sm select2" required>
            <option value="">Select a customer</option>
            @foreach($customers as $id => $name)
                <option value="{{ $id }}" @selected($selectedCust == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
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
</div>

<hr>
@php
    $sqItemColumns = [
        'seq'          => ['label' => '#', 'default' => true],
        'code'         => ['label' => 'Code / Barcode', 'default' => true],
        'item'         => ['label' => 'Item Description', 'default' => true],
        'qty'          => ['label' => 'Qty', 'default' => true],
        'sell_price'   => ['label' => 'Sell Price', 'default' => true],
        'mrp'          => ['label' => 'MRP', 'default' => true],
        'disc_percent' => ['label' => 'Disc %', 'default' => true],
        'disc_amt'     => ['label' => 'Disc Amt', 'default' => true],
        'gst_percent'  => ['label' => 'GST %', 'default' => true],
        'net_amt'      => ['label' => 'Net Amount', 'default' => true],
        'actions'      => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 text-primary font-weight-bold"><i class="fas fa-boxes mr-1"></i> Line Items</h5>
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold mr-2 btn-reset-table" id="sq-btn-reset-table" title="Clear all table items and reset to 1 empty row">
            <i class="fas fa-undo mr-1"></i> Reset Table
        </button>
        <x-table-column-customizer
            table-key="sales.sales-quotations.items"
            table-id="sq-items-table"
            :columns="$sqItemColumns"
        />
        <button type="button" class="btn btn-sm btn-outline-primary ml-2" id="sq-add-row-btn">
            <i class="fas fa-plus mr-1"></i> Add Row
        </button>
    </div>
</div>

<div class="table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-items-dense mb-0" id="sq-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 35px;" class="text-center" data-col-key="seq">#</th>
                <th style="width: 130px;" data-col-key="code">Code / Barcode</th>
                <th style="min-width: 250px;" data-col-key="item">Item Description</th>
                <th style="width: 100px;" class="text-right" data-col-key="qty">Qty</th>
                <th style="width: 120px;" class="text-right" data-col-key="sell_price">Sell Price</th>
                <th style="width: 110px;" class="text-right" data-col-key="mrp">MRP</th>
                <th style="width: 48px;" class="text-right" data-col-key="disc_percent">Disc %</th>
                <th style="width: 100px;" class="text-right" data-col-key="disc_amt">Disc Amt</th>
                <th style="width: 45px;" class="text-right" data-col-key="gst_percent">GST %</th>
                <th style="width: 120px;" class="text-right" data-col-key="net_amt">Net Amount</th>
                <th style="width: 35px;" class="text-center" data-col-key="actions"></th>
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

<x-custom-fields-renderer :module="'SalesQuotation'" :model="$quote ?? null" :cardStyle="true" />

<div class="d-flex justify-content-between align-items-center mb-1 tx-compact-section-header">
    <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Notes</h6>
    <x-form-layout-customizer
        form-key="sales_quotations.additional"
        container-id="sq-additional-fields-grid"
        title="Customize Totals & Notes Layout"
    />
</div>

<div class="row g-2 form-fields-grid align-items-end mb-1" id="sq-additional-fields-grid">
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-12" data-field="remarks" data-label="Remarks" data-default-order="1">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="remarks">Remarks</label>
            <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Optional remarks or terms..." value="{{ $quote->remarks ?? old('remarks') }}">
        </div>
    </div>
    <div class="field-wrapper col-lg-1 col-md-2 col-sm-3 col-6" data-field="round_off" data-label="Round Off" data-default-order="2">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="sq-round-off">Round Off</label>
            <input type="number" step="0.01" name="round_off" id="sq-round-off" value="{{ $quote->round_off ?? old('round_off', '0.00') }}" class="form-control text-right font-weight-bold">
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="subtotal" data-label="Sub Total" data-default-order="3">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Sub Total:</label>
            <div class="form-control text-right font-weight-bold bg-light" style="line-height: 24px;" id="sq-summary-subtotal">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="total_discount" data-label="Total Discount" data-default-order="4">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Total Discount:</label>
            <div class="form-control text-right font-weight-bold text-danger bg-light" style="line-height: 24px;" id="sq-summary-disc">-₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="gst_amount" data-label="GST Amount" data-default-order="5">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">GST Amount:</label>
            <div class="form-control text-right font-weight-bold text-info bg-light" style="line-height: 24px;" id="sq-summary-gst">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-4 col-sm-5 col-12" data-field="grand_total" data-label="Grand Total" data-default-order="6">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-success" style="white-space: nowrap;">Grand Total:</label>
            <div class="form-control text-right font-weight-bold text-success bg-white border-success" style="line-height: 24px; font-size: 0.95rem;" id="sq-summary-total">₹0.00</div>
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
    let sqIslSelectedIdx = -1;
    const SQ_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

    function canAddSqRow() {
        let $lastRow = $('#sq-items-body tr:last');
        if ($lastRow.length) {
            let itemId = $lastRow.find('.sq-item-id').val();
            let qtyVal = parseFloat($lastRow.find('.sq-qty').val()) || 0;

            if (!itemId) {
                let msg = 'Pehle current row me item select karein.';
                if (window.toastr) toastr.warning(msg, 'Incomplete Row');
                else alert(msg);
                $lastRow.find('.sq-item-code').focus();
                return false;
            }

            if (qtyVal <= 0) {
                let msg = 'Pehle item ki valid quantity enter karein.';
                if (window.toastr) toastr.warning(msg, 'Quantity Required');
                else alert(msg);
                $lastRow.find('.sq-qty').focus().select();
                return false;
            }
        }
        return true;
    }

    $('#sq-add-row-btn').on('click', function() {
        if (!canAddSqRow()) return;
        let html = $('#sq-row-template').html().replace(/__INDEX__/g, nextIndex++);
        let $newRow = $(html);
        $('#sq-items-body').append($newRow);
        recalcAll();
        $newRow.find('.sq-item-code').focus();
    });

    $('#sq-btn-reset-table').on('click', function(e) {
        e.preventDefault();
        let html = $('#sq-row-template').html().replace(/__INDEX__/g, 0);
        $('#sq-items-body').empty().append(html);
        nextIndex = 1;
        recalcAll();
        setTimeout(function() {
            $('#sq-items-body tr:first .sq-item-code').focus();
        }, 50);
    });

    $(document).off('keydown', '.sq-disc-amount, .sq-gst-percent, .sq-mrp').on('keydown', '.sq-disc-amount, .sq-gst-percent, .sq-mrp', function (e) {
        if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
            let $currentRow = $(this).closest('tr');
            let $nextRow = $currentRow.next('tr');
            if ($nextRow.length) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $nextRow.find('.sq-item-code').focus();
                }
            } else {
                e.preventDefault();
                if (!canAddSqRow()) return;
                $('#sq-add-row-btn').trigger('click');
                let $newRow = $('#sq-items-body tr:last');
                setTimeout(function () {
                    $newRow.find('.sq-item-code').focus();
                    $newRow.find('.sq-item-code').trigger($.Event('keydown', { key: 'Enter' }));
                }, 60);
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
    let sqMouseDown = false;
    $(document).on('mousedown', '.sq-item-code', function () {
        sqMouseDown = true;
    });

    function checkCustomerAndOpenSqModal($input) {
        let custId = $('#customer_id').val() || $('select[name="customer_id"]').val();
        if (!custId) {
            if (typeof toastr !== 'undefined') {
                toastr.warning('Please select a Customer first.');
            } else {
                alert('Please select a Customer first.');
            }
            let $c = $('#customer_id, select[name="customer_id"]');
            if ($c.data('select2')) $c.select2('open'); else $c.focus();
            return false;
        }
        if (sqModalOpen || sqModalClosing) return false;
        let $row = $input.closest('tr');
        if ($row.find('.sq-item-select').val()) return false;
        sqActiveSearchRow = $row;
        let prefill = $.trim($input.val());
        $('#sq-isl-filter-name').val(prefill);
        $('#sq-isl-filter-code').val('');
        fetchSqItemList();
        sqModalOpen = true;
        $('#sq-item-search-modal').modal('show');
        $('#sq-item-search-modal').one('shown.bs.modal', function () {
            $('#sq-isl-filter-name').focus().select();
        });
        return true;
    }

    const SQ_LOOKUP_URL = '{{ route("sales.sales-bills.lookup-item") }}';

    function processSqItemLookup($row, itemId, query, isDirectLookup = false) {
        let custId = $('#customer_id').val() || $('select[name="customer_id"]').val();
        if (!custId) {
            if (typeof toastr !== 'undefined') toastr.warning('Please select a Customer first.');
            else alert('Please select a Customer first.');
            let $c = $('#customer_id, select[name="customer_id"]');
            if ($c.data('select2')) $c.select2('open'); else $c.focus();
            return;
        }

        if (query && window.PosScanGuard) {
            let scanCheck = window.PosScanGuard.filterScan(query);
            if (!scanCheck.allowed) {
                return; // Ignore duplicate bounce
            }
        }

        let branchId = $('#branch_id').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
        let params = { branch_id: branchId };
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

        $.getJSON(SQ_LOOKUP_URL, params, function (res) {
            if (res && res.found && res.item) {
                let it = res.item;
                $row.data('last-processed-code', query || it.item_code || it.ean_upc_code || it.id);
                $row.find('.sq-item-code').removeClass('is-invalid border-danger').val(it.item_code || it.ean_upc_code || it.id);
                $row.find('.sq-item-desc').val(it.name + (it.item_code ? ' [' + it.item_code + ']' : ''));
                $row.find('.sq-item-select').val(it.id);

                let batches = res.batches || [];
                let sell = batches.length && batches[0].sell_price > 0 ? batches[0].sell_price : (it.sell_price || 0);
                let mrp = batches.length && batches[0].mrp > 0 ? batches[0].mrp : (it.mrp || 0);
                $row.find('.sq-sell-price').val(parseFloat(sell).toFixed(2));
                $row.find('.sq-mrp').val(parseFloat(mrp).toFixed(2));
                $row.find('.sq-gst-percent').val(parseFloat(it.gst_tax ? it.gst_tax.percentage : (it.gst_percent || 0)).toFixed(2));

                recalcRow($row);
                setTimeout(function () {
                    $row.find('.sq-qty').focus().select();
                }, 60);
            } else {
                $row.data('last-processed-code', null);
                $row.find('.sq-item-code').addClass('is-invalid border-danger');
                let msg = "Product not found for this Item Code/Barcode.";
                if (typeof toastr !== 'undefined') {
                    toastr.warning(msg, 'Item Not Found');
                } else {
                    alert(msg);
                }
                setTimeout(function () {
                    $row.find('.sq-item-code').focus().select();
                }, 50);
            }
        });
    }

    // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
    $(document).off('keydown change input', '.sq-item-code')
        .on('keydown', '.sq-item-code', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr');
                if (val) {
                    processSqItemLookup($row, null, val, true);
                } else {
                    checkCustomerAndOpenSqModal($(this));
                }
            } else if (e.key === 'Tab' && !e.shiftKey) {
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr');
                if (val) {
                    e.preventDefault();
                    processSqItemLookup($row, null, val, true);
                } else {
                    e.preventDefault();
                    checkCustomerAndOpenSqModal($(this));
                }
            } else if (e.key === 'F2') {
                e.preventDefault();
                checkCustomerAndOpenSqModal($(this));
            } else if (e.key === 'Escape') {
                let $row = $(this).closest('tr');
                let itemId = $row.find('.sq-item-select').val();
                if (!itemId && $('#sq-items-body tr').length > 1) {
                    e.preventDefault();
                    let $prevRow = $row.prev('tr');
                    $row.remove();
                    recalcAll();
                    if ($prevRow.length) {
                        $prevRow.find('.sq-qty').focus().select();
                    }
                }
            }
        })
        .on('change', '.sq-item-code', function () {
            let $input = $(this);
            let query = $.trim($input.val());
            let $row = $input.closest('tr');
            if (!query) {
                $row.find('.sq-item-select').val('');
                $row.find('.sq-item-desc').val('');
                $row.data('last-processed-code', '');
                recalcRow($row);
                return;
            }
            if ($row.data('last-processed-code') === query) return;
            processSqItemLookup($row, null, query, true);
        })
        .on('input', '.sq-item-code', function () {
            $(this).removeClass('is-invalid border-danger');
        });

    // Clicking on description also opens item search modal
    $(document).on('click', '.sq-item-desc', function () {
        let $code = $(this).closest('tr').find('.sq-item-code');
        checkCustomerAndOpenSqModal($code);
    });

    // Tab starts from first field (customer_id) on page load
    setTimeout(function () {
        let $cust = $('#customer_id, select[name="customer_id"]');
        if ($cust.length && $cust.data('select2')) {
            $cust.data('select2').$container.find('.select2-selection').focus();
        } else if ($cust.length) {
            $cust.focus();
        }
    }, 150);

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

        if (!sqItemSelectedInModal) {
            if (sqCancellingRow && sqCancellingRow.length) {
                let totalRows = $('#sq-items-body tr').length;
                if (totalRows > 1) {
                    sqCancellingRow.remove();
                    updateSqRowNumbers();
                    calculateSqTotals();
                } else {
                    sqCancellingRow.find('.sq-item-code').val('');
                    sqCancellingRow.find('.sq-item-desc').val('');
                }
            }
            sqCancellingRow = null;
            sqActiveSearchRow = null;
            setTimeout(function () {
                let $target = $('form button[type="submit"]:not(.btn-navbar)').first();
                if ($target.length) {
                    $target.focus();
                }
            }, 80);
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
        let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
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
            sqIslSelectedIdx = items.length > 0 ? 0 : -1;
            updateSqModalHighlight();
        }).fail(function () {
            $('#sq-isl-loading').addClass('d-none');
        });
    }

    function updateSqModalHighlight() {
        let $rows = $('#sq-isl-items-body tr.sq-isl-item-row');
        $rows.removeClass('table-primary');
        if (sqIslSelectedIdx >= 0 && sqIslSelectedIdx < $rows.length) {
            let $target = $rows.eq(sqIslSelectedIdx);
            $target.addClass('table-primary');
            let container = $('#sq-isl-table-wrap')[0];
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

    $('#sq-isl-filter-name, #sq-isl-filter-code').on('keydown', function (e) {
        let $rows = $('#sq-isl-items-body tr.sq-isl-item-row');
        if ($rows.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            sqIslSelectedIdx = Math.min(sqIslSelectedIdx + 1, $rows.length - 1);
            updateSqModalHighlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            sqIslSelectedIdx = Math.max(sqIslSelectedIdx - 1, 0);
            updateSqModalHighlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (sqIslSelectedIdx >= 0 && sqIslSelectedIdx < $rows.length) {
                $rows.eq(sqIslSelectedIdx).trigger('click');
            } else if ($rows.length === 1) {
                $rows.eq(0).trigger('click');
            }
        }
    });

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
        $row.find('.sq-item-code').val(itemData.code || itemData.id);
        $row.find('.sq-item-desc').val(itemData.name + (itemData.code ? ' [' + itemData.code + ']' : ''));
        $row.find('.sq-item-select').val(itemData.id);

        // Do not default qty to 1; keep blank as requested

        $row.find('.sq-sell-price').val(parseFloat(itemData.sell_price || 0).toFixed(2));
        $row.find('.sq-mrp').val(parseFloat(itemData.mrp || 0).toFixed(2));
        $row.find('.sq-gst-percent').val(parseFloat(itemData.gst_percent || 0).toFixed(2));

        recalcRow($row);
        $('#sq-item-search-modal').modal('hide');
        setTimeout(function() {
            $row.find('.sq-qty').focus().select();
        }, 120);
    });

    $(document).on('input', '.sq-qty, .sq-sell-price, .sq-disc-percent, .sq-disc-amount, .sq-gst-percent', function() {
        let $row = $(this).closest('tr');
        let isDiscPct = $(this).hasClass('sq-disc-percent');
        recalcRow($row, isDiscPct);
    });

    $(document).on('keydown', '.sq-qty', function(e) {
        if (e.key === 'Tab' || e.key === 'Enter') {
            let qty = parseFloat($(this).val()) || 0;
            let $row = $(this).closest('tr');
            let itemId = $row.find('.sq-item-select').val();
            if (itemId && qty <= 0) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('is-invalid border-danger text-danger');
                let $feedback = $(this).siblings('.sq-qty-error-msg');
                if (!$feedback.length) {
                    $feedback = $('<div class="sq-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size:11px;"></div>');
                    $(this).after($feedback);
                }
                $feedback.text('Quantity must be greater than 0').css('display', 'block');
                $(this).focus().select();
                return false;
            }
        }
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

        // Real-time inline field validation (Task 11)
        let $qtyInput = $row.find('.sq-qty');
        let itemId = $row.find('.sq-item-select').val();
        let mrp = parseFloat($row.find('.sq-mrp').val()) || 0;
        let $priceInput = $row.find('.sq-sell-price');
        let $qtyFeedback = $qtyInput.siblings('.sq-qty-error-msg');
        if (!$qtyFeedback.length) {
            $qtyFeedback = $('<div class="sq-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size:11px;"></div>');
            $qtyInput.after($qtyFeedback);
        }

        if (itemId) {
            if (qty <= 0) {
                $qtyInput.addClass('is-invalid border-danger text-danger');
                $qtyFeedback.text('Quantity must be greater than 0').css('display', 'block');
            } else {
                $qtyInput.removeClass('is-invalid border-danger text-danger');
                $qtyFeedback.text('').css('display', 'none');
            }

            if (mrp > 0 && price > mrp) {
                $priceInput.addClass('is-invalid border-danger text-danger').attr('title', `Selling price cannot exceed MRP (₹${mrp})`);
            } else {
                $priceInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
            }

            let isDiscPctInvalid = discPct < 0 || discPct > 100;
            let isDiscAmtInvalid = discAmt < 0 || (base > 0 && discAmt > base);

            if (isDiscPctInvalid) {
                discPctInput.addClass('is-invalid border-danger text-danger').attr('title', 'Discount % cannot exceed 100%');
            } else {
                discPctInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
            }

            if (isDiscAmtInvalid) {
                discAmtInput.addClass('is-invalid border-danger text-danger').attr('title', 'Discount amount cannot exceed item gross total (₹' + base.toFixed(2) + ')');
            } else {
                discAmtInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
            }
        } else {
            $qtyInput.removeClass('is-invalid border-danger text-danger');
            $qtyFeedback.text('').css('display', 'none');
        }

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

        // Update universal rich footer total
        $('#display-sq-final-total').text(grandTotal.toFixed(2));

        updateSqSaveButtonState();
    }

    function updateSqSaveButtonState() {
        let cust = $('select[name="customer_id"]').val();
        let hasError = false;
        let validRows = 0;
        let reason = '';

        if (!cust) {
            hasError = true;
            reason = 'Please select a Customer for this quotation.';
        }

        $('#sq-items-body tr').each(function (idx) {
            let id = $(this).find('.sq-item-select').val();
            let $q = $(this).find('.sq-qty');
            let q = parseFloat($q.val()) || 0;
            let p = parseFloat($(this).find('.sq-sell-price').val()) || 0;
            let m = parseFloat($(this).find('.sq-mrp').val()) || 0;

            if (id) {
                if (q <= 0) {
                    hasError = true;
                    if (!reason) reason = `Row #${idx + 1}: Quantity must be greater than 0.`;
                }
                if (m > 0 && p > m) {
                    hasError = true;
                    if (!reason) reason = `Row #${idx + 1}: Selling price cannot exceed MRP.`;
                }
                validRows++;
            }
        });

        if (validRows === 0 && !hasError) {
            hasError = true;
            reason = 'Pehle item add karein. Please add at least one item before saving.';
        }

        $('#sq-total-items-badge').html('<span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">' + validRows + ' Items</span>');

        let $btn = $('button[type="submit"], #sq-main-save-btn');
        if (hasError) {
            $btn.prop('disabled', true).addClass('disabled').attr('title', reason);
        } else {
            $btn.prop('disabled', false).removeClass('disabled').attr('title', '');
        }
    }

    $(document).on('change', 'select[name="customer_id"]', function () {
        updateSqSaveButtonState();
    });

    function recalcAll() {
        $('#sq-items-body tr').each(function() {
            recalcRow($(this));
        });
        updateSqSaveButtonState();
    }

    // Form Submit Guard (Task 11)
    $('form').on('submit', function (e) {
        let $btn = $(this).find('button[type="submit"]');
        if ($btn.prop('disabled') || $btn.hasClass('disabled')) {
            e.preventDefault();
            return false;
        }
        let cust = $('select[name="customer_id"]').val();
        let $custContainer = $('select[name="customer_id"]').next('.select2-container').find('.select2-selection');
        if (!cust) {
            e.preventDefault();
            $custContainer.addClass('border-danger');
            $('select[name="customer_id"]').select2('open');
            return false;
        } else {
            $custContainer.removeClass('border-danger');
        }

        let hasError = false;
        let validRows = 0;
        $('#sq-items-body tr').each(function (idx) {
            let id = $(this).find('.sq-item-select').val();
            let $q = $(this).find('.sq-qty');
            let q = parseFloat($q.val()) || 0;
            let p = parseFloat($(this).find('.sq-sell-price').val()) || 0;
            let m = parseFloat($(this).find('.sq-mrp').val()) || 0;

            if (id) {
                if (q <= 0) {
                    $q.addClass('is-invalid border-danger text-danger');
                    let $fb = $q.siblings('.sq-qty-error-msg');
                    if ($fb.length) {
                        $fb.text('Quantity must be greater than 0').css('display', 'block');
                    }
                    $q.focus().select();
                    hasError = true;
                    return false;
                }
                if (m > 0 && p > m) {
                    let $sp = $(this).find('.sq-sell-price');
                    $sp.addClass('is-invalid border-danger text-danger').focus();
                    hasError = true;
                    return false;
                }

                let $discPct = $(this).find('.sq-disc-percent');
                let discPctVal = parseFloat($discPct.val()) || 0;
                let $discAmt = $(this).find('.sq-disc-amount');
                let discAmtVal = parseFloat($discAmt.val()) || 0;
                let baseTotal = q * p;

                if (discPctVal < 0 || discPctVal > 100) {
                    $discPct.addClass('is-invalid border-danger text-danger');
                    let msg = `Row #${idx + 1}: Discount % (${discPctVal}%) cannot exceed 100%.`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount %');
                    else alert(msg);
                    $discPct.focus().select();
                    hasError = true;
                    return false;
                }

                if (discAmtVal < 0 || (baseTotal > 0 && discAmtVal > baseTotal)) {
                    $discAmt.addClass('is-invalid border-danger text-danger');
                    let msg = `Row #${idx + 1}: Discount amount (₹${discAmtVal}) cannot exceed item gross total (₹${baseTotal.toFixed(2)}).`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount Amount');
                    else alert(msg);
                    $discAmt.focus().select();
                    hasError = true;
                    return false;
                }

                validRows++;
            }
        });

        if (hasError) {
            e.preventDefault();
            return false;
        }

        if (validRows === 0) {
            e.preventDefault();
            if (window.toastr) {
                toastr.warning('Please add at least one item before saving.', 'No Items Added');
            }
            $('#sq-items-body tr:first .sq-item-code').focus();
            return false;
        }

        // Remove purely empty rows before submitting
        $('#sq-items-body tr').each(function () {
            let id = $(this).find('.sq-item-select').val();
            if (!id) {
                $(this).remove();
            }
        });
    });

    recalcAll();

    // Initialize universal compact transaction layout auto-fit engine
    if (window.initTransactionCompactLayout) {
        window.initTransactionCompactLayout({
            containerSelector: '.tx-items-scroll-container',
            footerSelector: '.tx-rich-footer',
            tableSelector: '#sq-items-table',
            minHeight: 160
        });
    }
});
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
@endpush
