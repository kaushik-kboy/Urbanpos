@php
    $order = $salesOrder ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($order?->items ?? ($convertedItems ?? collect()));
    $selectedCust = $order->customer_id ?? ($sourceQuotation->customer_id ?? old('customer_id'));
    $selectedBranch = old('branch_id', $order->branch_id ?? ($sourceQuotation->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)))));
    $selectedSalesType = $order->sales_type ?? ($sourceQuotation->sales_type ?? old('sales_type', 'Local'));
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
<style>
    #so-header-fields-grid .btn-open-datepicker,
    #so-header-fields-grid .btn-date-settings-modal,
    #so-header-fields-grid .urbanpos-date-group .input-group-append {
        display: none !important;
    }
    #so-header-fields-grid .urbanpos-date-group input {
        border-top-right-radius: 0.25rem !important;
        border-bottom-right-radius: 0.25rem !important;
    }
</style>
@endpush

@if(isset($sourceQuotation))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Creating Sales Order from <strong>Quotation #{{ $sourceQuotation->quotation_number }}</strong> (Customer: {{ $sourceQuotation->customer?->name }}).
        <input type="hidden" name="from_quotation_id" value="{{ $sourceQuotation->id }}">
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-2 tx-compact-section-header">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-shopping-cart text-primary mr-1"></i> Sales Order Details</h6>
    <x-form-layout-customizer
        form-key="sales_orders.header"
        container-id="so-header-fields-grid"
        title="Customize Sales Order Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-2 tx-header-fields-grid" id="so-header-fields-grid">
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
    <div class="field-wrapper col-md-2 form-group" data-field="order_date" data-label="Order Date" data-default-order="3" data-core="1">
        <label>Order Date <span class="text-danger">*</span></label>
        <input type="date" name="order_date" class="form-control form-control-sm" value="{{ optional($order?->order_date ?? now())->format('Y-m-d') }}" required>
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="expected_delivery_date" data-label="Expected Delivery" data-default-order="4">
        <label>Expected Delivery</label>
        <input type="date" name="expected_delivery_date" class="form-control form-control-sm" value="{{ optional($order?->expected_delivery_date ?? now())->format('Y-m-d') }}">
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="sales_type" data-label="Sales Type" data-default-order="5" data-core="1">
        <label>Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="so-sales-type" class="form-control form-control-sm" required>
            <option value="Local" @selected($selectedSalesType === 'Local')>Local</option>
            <option value="Interstate" @selected($selectedSalesType === 'Interstate')>Interstate</option>
        </select>
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="advance_amount" data-label="Advance Amount (₹)" data-default-order="6">
        <label>Advance Amount (₹)</label>
        <input type="number" step="0.01" min="0" name="advance_amount" class="form-control form-control-sm font-weight-bold text-primary" placeholder="0.00" value="{{ $order->advance_amount ?? old('advance_amount', '0.00') }}">
    </div>
    <div class="field-wrapper col-md-2 form-group" data-field="status" data-label="Status" data-default-order="7" data-core="1">
        <label>Status</label>
        <select name="status" class="form-control form-control-sm">
            @foreach(['Open', 'Partially Fulfilled'] as $st)
                <option value="{{ $st }}" @selected(($order->status ?? 'Open') === $st)>{{ $st }}</option>
            @endforeach
            @if(isset($order) && in_array($order->status, ['Converted', 'Cancelled']))
                <option value="{{ $order->status }}" selected>{{ $order->status }}</option>
            @endif
        </select>
    </div>
</div>

<hr>
@php
    $soItemColumns = [
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
    <h5 class="mb-0 text-primary font-weight-bold"><i class="fas fa-boxes mr-1"></i> Order Items</h5>
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold mr-2 btn-reset-table" id="so-btn-reset-table" title="Clear all table items and reset to 1 empty row">
            <i class="fas fa-undo mr-1"></i> Reset Table
        </button>
        <x-table-column-customizer
            table-key="sales.sales-orders.items"
            table-id="so-items-table"
            :columns="$soItemColumns"
        />
        <button type="button" class="btn btn-sm btn-outline-primary ml-2" id="so-add-row-btn">
            <i class="fas fa-plus mr-1"></i> Add Row
        </button>
    </div>
</div>

<div class="table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-items-dense mb-0" id="so-items-table">
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
        <tbody id="so-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-orders._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-orders._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="3" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="so-footer-qty">0.00</td>
                <td colspan="2" class="align-middle"></td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="so-footer-disc">0.00</td>
                <td class="align-middle"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="so-footer-net">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<x-custom-fields-renderer :module="'SalesOrder'" :model="$order ?? null" :cardStyle="true" />

<div class="d-flex justify-content-between align-items-center mb-1 tx-compact-section-header">
    <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Notes</h6>
    <x-form-layout-customizer
        form-key="sales_orders.additional"
        container-id="so-additional-fields-grid"
        title="Customize Totals & Notes Layout"
    />
</div>

<div class="row g-2 form-fields-grid align-items-end mb-1" id="so-additional-fields-grid">
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-12" data-field="remarks" data-label="Remarks" data-default-order="1">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="remarks">Remarks</label>
            <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Optional delivery notes or customer remarks..." value="{{ $order->remarks ?? old('remarks') }}">
        </div>
    </div>
    <div class="field-wrapper col-lg-1 col-md-2 col-sm-3 col-6" data-field="round_off" data-label="Round Off" data-default-order="2">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="so-round-off">Round Off</label>
            <input type="number" step="0.01" name="round_off" id="so-round-off" value="{{ $order->round_off ?? old('round_off', '0.00') }}" class="form-control text-right font-weight-bold">
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="subtotal" data-label="Sub Total" data-default-order="3">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Sub Total:</label>
            <div class="form-control text-right font-weight-bold bg-light" style="line-height: 24px;" id="so-summary-subtotal">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="total_discount" data-label="Total Discount" data-default-order="4">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Total Discount:</label>
            <div class="form-control text-right font-weight-bold text-danger bg-light" style="line-height: 24px;" id="so-summary-disc">-₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="gst_amount" data-label="GST Amount" data-default-order="5">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">GST Amount:</label>
            <div class="form-control text-right font-weight-bold text-info bg-light" style="line-height: 24px;" id="so-summary-gst">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-4 col-sm-5 col-12" data-field="grand_total" data-label="Grand Total" data-default-order="6">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-success" style="white-space: nowrap;">Grand Total:</label>
            <div class="form-control text-right font-weight-bold text-success bg-white border-success" style="line-height: 24px; font-size: 0.95rem;" id="so-summary-total">₹0.00</div>
        </div>
    </div>
</div>

{{-- Row Template for JS --}}
<template id="so-row-template">
    @include('sales.sales-orders._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

{{-- ============================================================
     ITEM SEARCH MODAL for Sales Order
     ============================================================ --}}
<div class="modal fade" id="so-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="soItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow border-dark">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="soItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Order Item
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
                            <input type="text" id="so-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="so-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 text-right d-flex justify-content-end align-items-center">
                        <x-table-column-customizer table-key="modal.sales-orders.item-search" table-id="so-isl-items-table" button-class="btn btn-sm btn-outline-secondary mr-2" button-text="Columns" title="Customize Columns & Order" />
                        <button type="button" id="so-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <div id="so-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="so-isl-no-results" class="text-center py-4">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div class="table-responsive d-none" id="so-isl-table-wrap" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="so-isl-items-table">
                        <thead class="bg-dark text-white sticky-top">
                            <tr>
                                <th class="text-center" style="width: 40px;" data-col-key="seq">#</th>
                                <th data-col-key="name">Product Name</th>
                                <th class="text-center" style="width: 120px;" data-col-key="code">Code</th>
                                <th class="text-center" style="width: 110px;" data-col-key="qty">Stock</th>
                                <th class="text-right" style="width: 90px;" data-col-key="sell_price">Sell Price</th>
                                <th class="text-right" style="width: 90px;" data-col-key="mrp">MRP</th>
                                <th class="text-right" style="width: 80px;" data-col-key="gst">GST %</th>
                                <th class="text-center" style="width: 80px;" data-col-key="action">Select</th>
                            </tr>
                        </thead>
                        <tbody id="so-isl-items-body"></tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="so-isl-count-label"></small>
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
    let soActiveSearchRow = null;
    let soIslDebounce = null;
    let soModalOpen = false;
    let soModalClosing = false;
    let soIslSelectedIdx = -1;
    const SO_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

    function canAddSoRow() {
        let $lastRow = $('#so-items-body tr:last');
        if ($lastRow.length) {
            let itemId = $lastRow.find('.so-item-id').val();
            let qtyVal = parseFloat($lastRow.find('.so-qty').val()) || 0;

            if (!itemId) {
                let msg = 'Pehle current row me item select karein.';
                if (window.toastr) toastr.warning(msg, 'Incomplete Row');
                else alert(msg);
                $lastRow.find('.so-item-code').focus();
                return false;
            }

            if (qtyVal <= 0) {
                let msg = 'Pehle item ki valid quantity enter karein.';
                if (window.toastr) toastr.warning(msg, 'Quantity Required');
                else alert(msg);
                $lastRow.find('.so-qty').focus().select();
                return false;
            }
        }
        return true;
    }

    $('#so-add-row-btn').on('click', function() {
        if (!canAddSoRow()) return;
        let html = $('#so-row-template').html().replace(/__INDEX__/g, nextIndex++);
        let $newRow = $(html);
        $('#so-items-body').append($newRow);
        recalcAll();
        $newRow.find('.so-item-code').focus();
    });

    $('#so-btn-reset-table').on('click', function(e) {
        e.preventDefault();
        let html = $('#so-row-template').html().replace(/__INDEX__/g, 0);
        $('#so-items-body').empty().append(html);
        nextIndex = 1;
        recalcAll();
        setTimeout(function() {
            $('#so-items-body tr:first .so-item-code').focus();
        }, 50);
    });

    $(document).off('keydown', '.so-disc-amount, .so-gst-percent, .so-mrp').on('keydown', '.so-disc-amount, .so-gst-percent, .so-mrp', function (e) {
        if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
            let $currentRow = $(this).closest('tr');
            let $nextRow = $currentRow.next('tr');
            if ($nextRow.length) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $nextRow.find('.so-item-code').focus();
                }
            } else {
                e.preventDefault();
                if (!canAddSoRow()) return;
                $('#so-add-row-btn').trigger('click');
                let $newRow = $('#so-items-body tr:last');
                setTimeout(function () {
                    $newRow.find('.so-item-code').focus();
                    $newRow.find('.so-item-code').trigger($.Event('keydown', { key: 'Enter' }));
                }, 60);
            }
        }
    });

    $(document).on('click', '.so-remove-row', function() {
        if ($('#so-items-body tr').length > 1) {
            $(this).closest('tr').remove();
            recalcAll();
        } else {
            alert('At least one line item is required.');
        }
    });

    /* ----------------------------------------------------------------
       ITEM SEARCH MODAL — open on click of Code/Barcode or F2
       ---------------------------------------------------------------- */
    let soCancellingRow = null;
    let soMouseDown = false;
    $(document).on('mousedown', '.so-item-code', function () {
        soMouseDown = true;
    });

    function checkCustomerAndOpenSoModal($input) {
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
        if (soModalOpen || soModalClosing) return false;
        let $row = $input.closest('tr');
        if ($row.find('.so-item-select').val()) return false;
        soActiveSearchRow = $row;
        let prefill = $.trim($input.val());
        $('#so-isl-filter-name').val(prefill);
        $('#so-isl-filter-code').val('');
        fetchSoItemList();
        soModalOpen = true;
        $('#so-item-search-modal').modal('show');
        $('#so-item-search-modal').one('shown.bs.modal', function () {
            $('#so-isl-filter-name').focus().select();
        });
        return true;
    }

    const SO_LOOKUP_URL = '{{ route("sales.sales-bills.lookup-item") }}';

    function processSoItemLookup($row, itemId, query, isDirectLookup = false) {
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

        $.getJSON(SO_LOOKUP_URL, params, function (res) {
            if (res && res.found && res.item) {
                let it = res.item;
                $row.data('last-processed-code', query || it.item_code || it.ean_upc_code || it.id);
                $row.find('.so-item-code').removeClass('is-invalid border-danger').val(it.item_code || it.ean_upc_code || it.id);
                $row.find('.so-item-desc').val(it.name + (it.item_code ? ' [' + it.item_code + ']' : ''));
                $row.find('.so-item-select').val(it.id);

                let batches = res.batches || [];
                let sell = batches.length && batches[0].sell_price > 0 ? batches[0].sell_price : (it.sell_price || 0);
                let mrp = batches.length && batches[0].mrp > 0 ? batches[0].mrp : (it.mrp || 0);
                $row.find('.so-sell-price').val(parseFloat(sell).toFixed(2));
                $row.find('.so-mrp').val(parseFloat(mrp).toFixed(2));
                $row.find('.so-gst-percent').val(parseFloat(it.gst_tax ? it.gst_tax.percentage : (it.gst_percent || 0)).toFixed(2));

                recalcRow($row);
                setTimeout(function () {
                    $row.find('.so-qty').focus().select();
                }, 60);
            } else {
                $row.data('last-processed-code', null);
                $row.find('.so-item-code').addClass('is-invalid border-danger');
                let msg = "Product not found for this Item Code/Barcode.";
                if (typeof toastr !== 'undefined') {
                    toastr.warning(msg, 'Item Not Found');
                } else {
                    alert(msg);
                }
                setTimeout(function () {
                    $row.find('.so-item-code').focus().select();
                }, 50);
            }
        });
    }

    // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
    $(document).off('keydown change input', '.so-item-code')
        .on('keydown', '.so-item-code', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr');
                if (val) {
                    processSoItemLookup($row, null, val, true);
                } else {
                    checkCustomerAndOpenSoModal($(this));
                }
            } else if (e.key === 'Tab' && !e.shiftKey) {
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr');
                if (val) {
                    e.preventDefault();
                    processSoItemLookup($row, null, val, true);
                } else {
                    e.preventDefault();
                    checkCustomerAndOpenSoModal($(this));
                }
            } else if (e.key === 'F2') {
                e.preventDefault();
                checkCustomerAndOpenSoModal($(this));
            } else if (e.key === 'Escape') {
                let $row = $(this).closest('tr');
                let itemId = $row.find('.so-item-select').val();
                if (!itemId && $('#so-items-body tr').length > 1) {
                    e.preventDefault();
                    let $prevRow = $row.prev('tr');
                    $row.remove();
                    recalcAll();
                    if ($prevRow.length) {
                        $prevRow.find('.so-qty').focus().select();
                    }
                }
            }
        })
        .on('change', '.so-item-code', function () {
            let $input = $(this);
            let query = $.trim($input.val());
            let $row = $input.closest('tr');
            if (!query) {
                $row.find('.so-item-select').val('');
                $row.find('.so-item-desc').val('');
                $row.data('last-processed-code', '');
                recalcRow($row);
                return;
            }
            if ($row.data('last-processed-code') === query) return;
            processSoItemLookup($row, null, query, true);
        })
        .on('input', '.so-item-code', function () {
            $(this).removeClass('is-invalid border-danger');
        });

    // Clicking on description also opens item search modal
    $(document).on('click', '.so-item-desc', function () {
        let $code = $(this).closest('tr').find('.so-item-code');
        checkCustomerAndOpenSoModal($code);
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

    let soItemSelectedInModal = false;

    $('#so-item-search-modal').on('show.bs.modal', function () {
        soModalOpen = true;
        soModalClosing = false;
        soItemSelectedInModal = false;
        soCancellingRow = null;
    });
    $('#so-item-search-modal').on('hide.bs.modal', function () {
        soModalOpen = false;
        soModalClosing = true;
        if (!soItemSelectedInModal && soActiveSearchRow && soActiveSearchRow.length) {
            let selectedId = soActiveSearchRow.find('.so-item-select').val();
            if (!selectedId) {
                soCancellingRow = soActiveSearchRow;
            }
        }
    });
    $('#so-item-search-modal').on('hidden.bs.modal', function () {
        soModalOpen = false;
        soModalClosing = true;
        setTimeout(function () { soModalClosing = false; }, 350);

        if (!soItemSelectedInModal && soCancellingRow && soCancellingRow.length) {
            let totalRows = $('#so-items-body tr').length;
            if (totalRows > 1) {
                soCancellingRow.remove();
                updateSoRowNumbers();
                recalcAll();
            } else {
                soCancellingRow.find('.so-item-code').val('');
                soCancellingRow.find('.so-item-desc').val('');
            }
            soCancellingRow = null;
            soActiveSearchRow = null;
            setTimeout(function () {
                let $target = $('#so-add-row, #freight, button[type=submit]');
                $target.first().focus();
            }, 60);
            return;
        }

        soItemSelectedInModal = false;
        soCancellingRow = null;
        soActiveSearchRow = null;
    });

    $('#so-isl-filter-name, #so-isl-filter-code').on('input', function () {
        clearTimeout(soIslDebounce);
        soIslDebounce = setTimeout(fetchSoItemList, 300);
    });

    $('#so-isl-btn-clear').on('click', function () {
        $('#so-isl-filter-name, #so-isl-filter-code').val('');
        fetchSoItemList();
    });

    function fetchSoItemList() {
        let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
        let srch = $.trim($('#so-isl-filter-name').val());
        let code = $.trim($('#so-isl-filter-code').val());

        if (!srch && !code) {
            $('#so-isl-loading').addClass('d-none');
            $('#so-isl-table-wrap').addClass('d-none');
            $('#so-isl-items-body').empty();
            $('#so-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i><p class="mt-2 text-muted">Start typing to search items…</p>'
            );
            $('#so-isl-count-label').text('');
            return;
        }

        $('#so-isl-loading').removeClass('d-none');
        $('#so-isl-no-results').addClass('d-none');
        $('#so-isl-table-wrap').addClass('d-none');

        $.getJSON(SO_ISL_URL, { branch_id: branchId, search: srch, code: code, show_all: 1 }, function (res) {
            $('#so-isl-loading').addClass('d-none');
            let items = res.items || [];
            let $tbody = $('#so-isl-items-body').empty();

            if (items.length === 0) {
                $('#so-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted">No items found.</p>'
                );
                $('#so-isl-count-label').text('');
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';
                let stockClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';

                html += `
                    <tr class="so-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-gst="${it.gst_percent || 0}">
                        <td data-col-key="seq" class="align-middle text-center text-muted">${idx + 1}</td>
                        <td data-col-key="name" class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td data-col-key="code" class="align-middle text-center">${codeBadge}</td>
                        <td data-col-key="qty" class="align-middle text-center ${stockClass}">${parseFloat(it.qty || 0).toFixed(2)}</td>
                        <td data-col-key="sell_price" class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td data-col-key="mrp" class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td data-col-key="gst" class="align-middle text-right">${parseFloat(it.gst_percent || 0).toFixed(0)}%</td>
                        <td data-col-key="action" class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 so-isl-btn-select">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            if (window.applyTablePreferences) {
                window.applyTablePreferences('so-isl-items-table');
            }
            $('#so-isl-table-wrap').removeClass('d-none');
            $('#so-isl-count-label').text(items.length + ' item(s) found');
            soIslSelectedIdx = items.length > 0 ? 0 : -1;
            updateSoModalHighlight();
        }).fail(function () {
            $('#so-isl-loading').addClass('d-none');
        });
    }

    function updateSoModalHighlight() {
        let $rows = $('#so-isl-items-body tr.so-isl-item-row');
        $rows.removeClass('table-primary');
        if (soIslSelectedIdx >= 0 && soIslSelectedIdx < $rows.length) {
            let $target = $rows.eq(soIslSelectedIdx);
            $target.addClass('table-primary');
            let container = $('#so-isl-table-wrap')[0];
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

    $('#so-isl-filter-name, #so-isl-filter-code').on('keydown', function (e) {
        let $rows = $('#so-isl-items-body tr.so-isl-item-row');
        if ($rows.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            soIslSelectedIdx = Math.min(soIslSelectedIdx + 1, $rows.length - 1);
            updateSoModalHighlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            soIslSelectedIdx = Math.max(soIslSelectedIdx - 1, 0);
            updateSoModalHighlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (soIslSelectedIdx >= 0 && soIslSelectedIdx < $rows.length) {
                $rows.eq(soIslSelectedIdx).trigger('click');
            } else if ($rows.length === 1) {
                $rows.eq(0).trigger('click');
            }
        }
    });

    $(document).on('click', '.so-isl-item-row, .so-isl-btn-select', function (e) {
        e.stopPropagation();
        let $tr = $(this).hasClass('so-isl-item-row') ? $(this) : $(this).closest('tr');
        let itemData = {
            id: $tr.data('id'),
            name: $tr.data('name'),
            code: $tr.data('code'),
            sell_price: $tr.data('sell'),
            mrp: $tr.data('mrp'),
            gst_percent: $tr.data('gst')
        };
        if (!soActiveSearchRow || !itemData.id) return;

        soItemSelectedInModal = true;
        soCancellingRow = null;

        let $row = soActiveSearchRow;
        $row.find('.so-item-code').val(itemData.code || itemData.id);
        $row.find('.so-item-desc').val(itemData.name + (itemData.code ? ' [' + itemData.code + ']' : ''));
        $row.find('.so-item-select').val(itemData.id);

        // Do not default qty to 1; keep blank as requested

        $row.find('.so-sell-price').val(parseFloat(itemData.sell_price || 0).toFixed(2));
        $row.find('.so-mrp').val(parseFloat(itemData.mrp || 0).toFixed(2));
        $row.find('.so-gst-percent').val(parseFloat(itemData.gst_percent || 0).toFixed(2));

        recalcRow($row);
        recalcAll();

        $('#so-item-search-modal').modal('hide');
        setTimeout(function() {
            $row.find('.so-qty').focus().select();
        }, 120);
    });

    $(document).on('input', '.so-qty, .so-sell-price, .so-disc-percent, .so-disc-amount, .so-gst-percent', function() {
        let $row = $(this).closest('tr');
        let isDiscPct = $(this).hasClass('so-disc-percent');
        recalcRow($row, isDiscPct);
    });

    $(document).on('keydown', '.so-qty', function(e) {
        if (e.key === 'Tab' || e.key === 'Enter') {
            let qty = parseFloat($(this).val()) || 0;
            let $row = $(this).closest('tr');
            let itemId = $row.find('.so-item-select').val();
            if (itemId && qty <= 0) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('is-invalid border-danger text-danger');
                let $feedback = $(this).siblings('.so-qty-error-msg');
                if (!$feedback.length) {
                    $feedback = $('<div class="so-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size:11px;"></div>');
                    $(this).after($feedback);
                }
                $feedback.text('Quantity must be greater than 0').css('display', 'block');
                $(this).focus().select();
                return false;
            }
        }
    });

    $('#so-round-off').on('input', function() {
        recalcSummary();
    });

    function recalcRow($row, isDiscPctChanged) {
        let qty = parseFloat($row.find('.so-qty').val()) || 0;
        let price = parseFloat($row.find('.so-sell-price').val()) || 0;
        let base = qty * price;

        let discPctInput = $row.find('.so-disc-percent');
        let discAmtInput = $row.find('.so-disc-amount');
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
        let gstPct = parseFloat($row.find('.so-gst-percent').val()) || 0;
        let gstAmt = (taxable * gstPct) / 100;
        let net = taxable + gstAmt;

        $row.find('.so-row-net').text(net.toFixed(2));

        // Real-time inline field validation (Task 11)
        let $qtyInput = $row.find('.so-qty');
        let itemId = $row.find('.so-item-select').val();
        let mrp = parseFloat($row.find('.so-mrp').val()) || 0;
        let $priceInput = $row.find('.so-sell-price');
        let $qtyFeedback = $qtyInput.siblings('.so-qty-error-msg');
        if (!$qtyFeedback.length) {
            $qtyFeedback = $('<div class="so-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size:11px;"></div>');
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

        $('#so-items-body tr').each(function(i) {
            $(this).find('.so-sr-no').text(i + 1);
            let qty = parseFloat($(this).find('.so-qty').val()) || 0;
            let price = parseFloat($(this).find('.so-sell-price').val()) || 0;
            let base = qty * price;
            let disc = parseFloat($(this).find('.so-disc-amount').val()) || 0;
            let taxable = Math.max(0, base - disc);
            let gstPct = parseFloat($(this).find('.so-gst-percent').val()) || 0;
            let gst = (taxable * gstPct) / 100;
            let net = taxable + gst;

            totQty += qty;
            totBase += base;
            totDisc += disc;
            totGst += gst;
            totNet += net;
        });

        let roundOff = parseFloat($('#so-round-off').val()) || 0;
        let grandTotal = totNet + roundOff;

        $('#so-footer-qty').text(totQty.toFixed(2));
        $('#so-footer-disc').text('₹' + totDisc.toFixed(2));
        $('#so-footer-net').text('₹' + totNet.toFixed(2));

        $('#so-summary-subtotal').text('₹' + totBase.toFixed(2));
        $('#so-summary-disc').text('-₹' + totDisc.toFixed(2));
        $('#so-summary-gst').text('₹' + totGst.toFixed(2));
        $('#so-summary-total').text('₹' + grandTotal.toFixed(2));

        // Update universal rich footer total
        $('#display-so-final-total').text(grandTotal.toFixed(2));

        updateSoSaveButtonState();
    }

    function updateSoSaveButtonState() {
        let cust = $('select[name="customer_id"]').val();
        let hasError = false;
        let validRows = 0;
        let reason = '';

        if (!cust) {
            hasError = true;
            reason = 'Please select a Customer for this sales order.';
        }

        $('#so-items-body tr').each(function (idx) {
            let id = $(this).find('.so-item-select').val();
            let $q = $(this).find('.so-qty');
            let q = parseFloat($q.val()) || 0;
            let p = parseFloat($(this).find('.so-sell-price').val()) || 0;
            let m = parseFloat($(this).find('.so-mrp').val()) || 0;

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

        $('#so-total-items-badge').html('<span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">' + validRows + ' Items</span>');

        let $btn = $('#so-form button[type="submit"], #so-main-save-btn, button[type="submit"]:not(.btn-navbar)');
        if (hasError) {
            $btn.prop('disabled', true).addClass('disabled').attr('title', reason);
        } else {
            $btn.prop('disabled', false).removeClass('disabled').attr('title', '');
        }
    }

    $(document).on('change', 'select[name="customer_id"]', function () {
        updateSoSaveButtonState();
    });

    function recalcAll() {
        $('#so-items-body tr').each(function() {
            recalcRow($(this));
        });
        updateSoSaveButtonState();
    }

    let isSubmitting = false;
    $('form').on('submit', function (e) {
        let $btn = $(this).find('button[type="submit"]:not(.btn-navbar)');
        if (isSubmitting || $btn.prop('disabled') || $btn.hasClass('disabled')) {
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
        $('#so-items-body tr').each(function (idx) {
            let id = $(this).find('.so-item-select').val();
            let $q = $(this).find('.so-qty');
            let q = parseFloat($q.val()) || 0;
            let p = parseFloat($(this).find('.so-sell-price').val()) || 0;
            let m = parseFloat($(this).find('.so-mrp').val()) || 0;

            if (id) {
                if (q <= 0) {
                    $q.addClass('is-invalid border-danger text-danger');
                    let $fb = $q.siblings('.so-qty-error-msg');
                    if ($fb.length) {
                        $fb.text('Quantity must be greater than 0').css('display', 'block');
                    }
                    $q.focus().select();
                    hasError = true;
                    return false;
                }
                if (m > 0 && p > m) {
                    let $sp = $(this).find('.so-sell-price');
                    $sp.addClass('is-invalid border-danger text-danger').focus();
                    hasError = true;
                    return false;
                }

                let $discPct = $(this).find('.so-disc-percent');
                let discPctVal = parseFloat($discPct.val()) || 0;
                let $discAmt = $(this).find('.so-disc-amount');
                let discAmtVal = parseFloat($discAmt.val()) || 0;
                let baseTotal = q * p;

                if (discPctVal < 0 || discPctVal > 100) {
                    e.preventDefault();
                    $discPct.addClass('is-invalid border-danger text-danger');
                    let msg = `Row #${idx + 1}: Discount % (${discPctVal}%) cannot exceed 100%.`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount %');
                    else alert(msg);
                    $discPct.focus().select();
                    hasError = true;
                    return false;
                }

                if (discAmtVal < 0 || (baseTotal > 0 && discAmtVal > baseTotal)) {
                    e.preventDefault();
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
            $('#so-items-body tr:first .so-item-code').focus();
            return false;
        }

        // Remove purely empty rows before submitting
        $('#so-items-body tr').each(function () {
            let id = $(this).find('.so-item-select').val();
            if (!id) {
                $(this).remove();
            }
        });

        // Re-index remaining rows so items[0], items[1] are contiguous
        $('#so-items-body tr').each(function (idx) {
            $(this).find('input, select').each(function () {
                let name = $(this).attr('name');
                if (name && name.indexOf('items[') !== -1) {
                    $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                }
            });
        });

        isSubmitting = true;
        setTimeout(function() {
            if ($btn.length) {
                $btn.prop('disabled', true).addClass('disabled').html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            }
        }, 10);
    });

    recalcAll();

    // Initialize universal compact transaction layout auto-fit engine
    if (window.initTransactionCompactLayout) {
        window.initTransactionCompactLayout({
            containerSelector: '.tx-items-scroll-container',
            footerSelector: '.tx-rich-footer',
            tableSelector: '#so-items-table',
            minHeight: 160
        });
    }
});
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
@endpush
