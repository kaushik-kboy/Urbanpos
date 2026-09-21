@extends('adminlte::page')

@section('title', 'Create Sales Delivery Note')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold"><i class="fas fa-truck-loading text-primary mr-2"></i>Create Delivery Note (Challan)</h1>
            <small class="text-muted">Outward goods dispatch from warehouse</small>
        </div>
        <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('sales.delivery-notes.store') }}" method="POST" id="sdn-form">
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle mr-1"></i> Please fix the errors below:</h5>
                <ul class="mb-0 pl-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($sourceOrder))
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="fas fa-link mr-1"></i> Dispatching against Sales Order: <strong>{{ $sourceOrder->order_number }}</strong>
                (Customer: {{ $sourceOrder->customer?->name }}, Branch: {{ $sourceOrder->branch?->name }}).
            </div>
        @endif

        <div class="card card-primary card-outline shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-shipping-fast mr-1"></i> Dispatch & Transport Details</h3>
                <x-form-layout-customizer
                    form-key="delivery_notes.header"
                    container-id="sdn-header-fields-grid"
                    title="Customize Delivery Note Header"
                />
            </div>
            <div class="card-body p-3">
                <div class="row g-2 form-fields-grid" id="sdn-header-fields-grid">
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="delivery_date" data-label="Dispatch Date" data-default-order="1" data-core="1">
                        <label class="font-weight-bold">Dispatch Date <span class="text-danger">*</span></label>
                        <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="branch_id" data-label="Branch" data-default-order="2" data-core="1">
                        <label class="font-weight-bold">Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-control select2" required>
                            <option value="">-- Select Branch --</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" {{ old('branch_id', $sourceOrder->branch_id ?? session('active_branch_id')) == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="customer_id" data-label="Customer" data-default-order="3" data-core="1">
                        <label class="font-weight-bold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control select2" required>
                            <option value="">-- Select Customer --</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ old('customer_id', $sourceOrder->customer_id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="sales_order_id" data-label="Sales Order (Ref)" data-default-order="4">
                        <label class="font-weight-bold">Sales Order (Ref)</label>
                        <select name="sales_order_id" class="form-control select2" id="sdn-so-select">
                            <option value="">-- Direct Dispatch (No SO) --</option>
                            @foreach ($salesOrders as $id => $soNumber)
                                <option value="{{ $id }}" {{ old('sales_order_id', $sourceOrder->id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $soNumber }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="reference_no" data-label="Customer Ref / PO No" data-default-order="5">
                        <label class="font-weight-bold">Customer Ref / PO No</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. PO-8492" value="{{ old('reference_no') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="vehicle_no" data-label="Vehicle No" data-default-order="6">
                        <label class="font-weight-bold">Vehicle No</label>
                        <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH-12-AB-1234" value="{{ old('vehicle_no') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="transporter_name" data-label="Transporter Name" data-default-order="7">
                        <label class="font-weight-bold">Transporter Name</label>
                        <input type="text" name="transporter_name" class="form-control" placeholder="e.g. Blue Dart / Own Fleet" value="{{ old('transporter_name') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="lr_no" data-label="LR / Bilty No" data-default-order="8">
                        <label class="font-weight-bold">LR / Bilty No</label>
                        <input type="text" name="lr_no" class="form-control" placeholder="e.g. LR-90812" value="{{ old('lr_no') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="lr_date" data-label="LR Date" data-default-order="9">
                        <label class="font-weight-bold">LR Date</label>
                        <input type="date" name="lr_date" class="form-control" value="{{ old('lr_date', date('Y-m-d')) }}">
                    </div>
                    <div class="field-wrapper col-md-9 col-sm-6 mb-3" data-field="delivery_address" data-label="Delivery Address" data-default-order="10">
                        <label class="font-weight-bold">Delivery / Destination Address</label>
                        <input type="text" name="delivery_address" class="form-control" placeholder="Site or client shipping address" value="{{ old('delivery_address') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-default shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold"><i class="fas fa-boxes mr-1"></i> Dispatched Goods Grid</h3>
                <button type="button" class="btn btn-xs btn-primary" id="add-row-btn">
                    <i class="fas fa-plus mr-1"></i> Add Item Line
                </button>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm mb-0" id="sdn-items-table">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th style="width: 40px;">#</th>
                            <th style="width: 155px;">Code / Barcode</th>
                            <th style="min-width: 250px;">Item Description</th>
                            <th style="width: 110px;">Ordered Qty</th>
                            <th style="width: 130px;">Dispatched Qty <span class="text-danger">*</span></th>
                            <th style="width: 120px;">Unit Price (₹) <span class="text-danger">*</span></th>
                            <th style="width: 110px;">MRP (₹)</th>
                            <th style="width: 120px;">Batch No</th>
                            <th style="width: 130px;">Expiry Date</th>
                            <th style="width: 130px;" class="text-right">Line Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="sdn-items-body">
                        @php
                            $oldRows = old('items');
                            $rowsToRender = !empty($oldRows) ? collect($oldRows) : (!empty($convertedItems) && $convertedItems->isNotEmpty() ? $convertedItems : collect([null]));
                        @endphp

                        @foreach ($rowsToRender as $idx => $row)
                            @php
                                $r = is_array($row) ? (object) $row : $row;
                                $itemId = $r->item_id ?? '';
                                $itemObj = isset($r->item) && is_object($r->item) ? $r->item : ($itemId ? \App\Models\Item::find($itemId) : null);
                                $displayCode = $itemObj?->item_code ?: ($itemObj?->ean_upc_code ?? '');
                                $displayName = $itemObj ? ($itemObj->name . ($itemObj->item_code ? ' [Code: ' . $itemObj->item_code . ']' : ($itemObj->ean_upc_code ? ' [Barcode: ' . $itemObj->ean_upc_code . ']' : ''))) : '';
                                $soItemId = $r->sales_order_item_id ?? '';
                                $ordered = $r->ordered_qty ?? 0;
                                $dispatched = $r->dispatched_qty ?? '';
                                $price = $r->unit_price ?? 0;
                                $mrp = $r->mrp ?? 0;
                                $batch = $r->batch_no ?? '';
                                $exp = $r->exp_date ?? '';
                            @endphp
                            <tr class="sdn-item-row" data-index="{{ $idx }}">
                                <td class="text-center align-middle row-number">{{ $idx + 1 }}</td>
                                <td style="min-width: 145px;">
                                    <input type="text" 
                                           class="form-control form-control-sm sdn-item-code" 
                                           value="{{ $displayCode }}" 
                                           placeholder="Code / Barcode" 
                                           autocomplete="off"
                                           title="Enter code or click/tab to search">
                                </td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][sales_order_item_id]" value="{{ $soItemId }}">
                                    <input type="hidden" name="items[{{ $idx }}][item_id]" class="item-select sdn-item-id" value="{{ $itemId }}" required>
                                    <input type="text" 
                                           class="form-control form-control-sm sdn-item-desc bg-light font-weight-bold text-truncate" 
                                           readonly 
                                           tabindex="-1"
                                           value="{{ $displayName }}" 
                                           placeholder="Product Description (auto-filled)"
                                           title="Product description (auto-filled on code entry)">
                                </td>
                                <td>
                                    <input type="number" step="0.001" name="items[{{ $idx }}][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="{{ $ordered }}" readonly tabindex="-1">
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001" name="items[{{ $idx }}][dispatched_qty]" class="form-control form-control-sm text-right font-weight-bold text-primary row-dispatched" value="{{ $dispatched }}" placeholder="0.00" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" class="form-control form-control-sm text-right row-price" value="{{ $price }}" placeholder="0.00" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][mrp]" class="form-control form-control-sm text-right row-mrp" value="{{ $mrp }}" placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $idx }}][batch_no]" class="form-control form-control-sm" value="{{ $batch }}" placeholder="Batch">
                                </td>
                                <td>
                                    <input type="date" name="items[{{ $idx }}][exp_date]" class="form-control form-control-sm" value="{{ $exp }}">
                                </td>
                                <td class="text-right align-middle font-weight-bold text-success row-total">₹0.00</td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <th colspan="3" class="text-right align-middle">Totals:</th>
                            <th class="text-right align-middle" id="summary-ordered">0.00</th>
                            <th class="text-right align-middle text-primary" id="summary-dispatched">0.00</th>
                            <th colspan="4" class="text-right align-middle">Total Challan Value:</th>
                            <th class="text-right align-middle text-success h6 mb-0" id="summary-amount">₹0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <x-custom-fields-renderer :module="'SalesDeliveryNote'" :model="null" :cardStyle="true" />

        <div class="card card-default shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <label class="font-weight-bold">Internal Remarks / Transport Notes</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Optional dispatch notes or special delivery instructions" value="{{ old('remarks') }}">
                    </div>
                    <div class="col-md-4 text-right pt-3">
                        <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="submit-btn">
                            <i class="fas fa-check-circle mr-1"></i> Save & Dispatch Goods
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Template for new row --}}
    <template id="row-template">
        <tr class="sdn-item-row" data-index="__INDEX__">
            <td class="text-center align-middle row-number">__NUM__</td>
            <td style="min-width: 145px;">
                <input type="text" 
                       class="form-control form-control-sm sdn-item-code" 
                       value="" 
                       placeholder="Code / Barcode" 
                       autocomplete="off"
                       title="Enter code or click/tab to search">
            </td>
            <td>
                <input type="hidden" name="items[__INDEX__][sales_order_item_id]" value="">
                <input type="hidden" name="items[__INDEX__][item_id]" class="item-select sdn-item-id" value="" required>
                <input type="text" 
                       class="form-control form-control-sm sdn-item-desc bg-light font-weight-bold text-truncate" 
                       readonly 
                       tabindex="-1"
                       value="" 
                       placeholder="Product Description (auto-filled)"
                       title="Product description (auto-filled on code entry)">
            </td>
            <td>
                <input type="number" step="0.001" name="items[__INDEX__][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="0" readonly tabindex="-1">
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[__INDEX__][dispatched_qty]" class="form-control form-control-sm text-right font-weight-bold text-primary row-dispatched" value="" placeholder="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control form-control-sm text-right row-price" value="0.00" placeholder="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][mrp]" class="form-control form-control-sm text-right row-mrp" value="" placeholder="0.00">
            </td>
            <td>
                <input type="text" name="items[__INDEX__][batch_no]" class="form-control form-control-sm" value="" placeholder="Batch">
            </td>
            <td>
                <input type="date" name="items[__INDEX__][exp_date]" class="form-control form-control-sm" value="">
            </td>
            <td class="text-right align-middle font-weight-bold text-success row-total">₹0.00</td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    </template>

    {{-- ================================================================ --}}
    {{-- ITEM SEARCH MODAL (Standard popup)                               --}}
    {{-- ================================================================ --}}
    <div class="modal fade" id="sdn-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="sdnItemSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title font-weight-bold" id="sdnItemSearchModalLabel">
                        <i class="fas fa-search mr-2"></i> Select Item
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
                                <input type="text" id="sdn-isl-filter-name" class="form-control" placeholder="Search by item name..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="fas fa-barcode text-muted"></i></span>
                                </div>
                                <input type="text" id="sdn-isl-filter-code" class="form-control font-weight-bold" placeholder="Filter by Code / Barcode..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="sdn-isl-btn-clear" class="btn btn-outline-secondary btn-block">
                                <i class="fas fa-times mr-1"></i> Clear
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted" id="sdn-isl-status">Type to search items...</small>
                        <small class="text-muted"><kbd>↑</kbd> <kbd>↓</kbd> to navigate, <kbd>Enter</kbd> to select, <kbd>Esc</kbd> to close</small>
                    </div>

                    <div id="sdn-isl-loading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted mb-0">Searching products…</p>
                    </div>

                    <div id="sdn-isl-no-results" class="text-center py-4 text-muted">
                        <i class="fas fa-keyboard fa-2x text-muted"></i>
                        <p class="mt-2 text-muted">Start typing to search items…</p>
                    </div>

                    <div id="sdn-isl-table-wrap" class="table-responsive d-none" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm table-striped mb-0">
                            <thead class="thead-dark sticky-top">
                                <tr>
                                    <th style="width: 45px;" class="text-center">#</th>
                                    <th>Item Name</th>
                                    <th style="width: 140px;" class="text-center">Code / Barcode</th>
                                    <th style="width: 90px;" class="text-center">Current Stock</th>
                                    <th style="width: 100px;" class="text-right">Sell Price</th>
                                    <th style="width: 100px;" class="text-right">MRP</th>
                                    <th style="width: 90px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="sdn-isl-items-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <span class="text-muted small" id="sdn-isl-count-label"></span>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
$(function () {
    const ISL_URL = "{{ route('sales.sales-bills.item-list') }}";
    const LOOKUP_URL = "{{ route('sales.sales-bills.lookup-item') }}";

    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    let rowIndex = {{ max(1, count($rowsToRender)) }};
    let sdnActiveSearchRow = null;
    let sdnModalOpen = false;
    let sdnModalClosing = false;
    let sdnCancellingRow = null;
    let sdnItemSelectedInModal = false;
    let sdnDebounce = null;

    function recalculate() {
        let totOrdered = 0;
        let totDispatched = 0;
        let totAmount = 0;

        $('#sdn-items-body tr.sdn-item-row').each(function (i) {
            $(this).find('.row-number').text(i + 1);

            let ord = parseFloat($(this).find('.row-ordered').val()) || 0;
            let disp = parseFloat($(this).find('.row-dispatched').val()) || 0;
            let prc = parseFloat($(this).find('.row-price').val()) || 0;

            let lineTot = Math.round(disp * prc * 100) / 100;
            $(this).find('.row-total').text('₹' + lineTot.toFixed(2));

            totOrdered += ord;
            totDispatched += disp;
            totAmount += lineTot;
        });

        $('#summary-ordered').text(totOrdered.toFixed(2));
        $('#summary-dispatched').text(totDispatched.toFixed(2));
        $('#summary-amount').text('₹' + totAmount.toFixed(2));
    }

    // Recalculate on input
    $(document).on('input change', '.row-dispatched, .row-price', function () {
        recalculate();
    });

    // Modal open function
    function openSdnItemModal($row, prefill) {
        if (sdnModalOpen || sdnModalClosing) return;
        sdnActiveSearchRow = $row;
        sdnItemSelectedInModal = false;
        sdnCancellingRow = null;

        let initial = $.trim(prefill !== undefined ? prefill : ($row.find('.sdn-item-code').val() || ''));
        $('#sdn-isl-filter-name').val(initial);
        $('#sdn-isl-filter-code').val('');
        fetchSdnItemList();

        sdnModalOpen = true;
        $('#sdn-item-search-modal').modal('show');
        $('#sdn-item-search-modal').one('shown.bs.modal', function () {
            $('#sdn-isl-filter-name').focus().select();
        });
    }

    // Trigger modal on click or focus of .sdn-item-code (Tab key or Click)
    $(document).off('click focus', '.sdn-item-code').on('click focus', '.sdn-item-code', function (e) {
        if (sdnModalOpen || sdnModalClosing) return;
        let $row = $(this).closest('tr');
        if (e.type === 'focus' && $row.find('.sdn-item-id').val()) return;
        openSdnItemModal($row, $(this).val());
    });

    // Handle modal hide / cancel empty rows gracefully
    $('#sdn-item-search-modal').on('hide.bs.modal', function () {
        sdnModalOpen = false;
        sdnModalClosing = true;
        if (!sdnItemSelectedInModal && sdnActiveSearchRow && sdnActiveSearchRow.length) {
            let selectedId = sdnActiveSearchRow.find('.sdn-item-id').val();
            if (!selectedId) {
                sdnCancellingRow = sdnActiveSearchRow;
            }
        }
    });

    $('#sdn-item-search-modal').on('hidden.bs.modal', function () {
        sdnModalOpen = false;
        sdnModalClosing = true;
        setTimeout(function () { sdnModalClosing = false; }, 350);

        if (!sdnItemSelectedInModal && sdnCancellingRow && sdnCancellingRow.length) {
            let totalRows = $('#sdn-items-body tr.sdn-item-row').length;
            if (totalRows > 1) {
                sdnCancellingRow.remove();
                recalculate();
            } else {
                sdnCancellingRow.find('.sdn-item-code').val('');
                sdnCancellingRow.find('.sdn-item-desc').val('');
                sdnCancellingRow.find('.sdn-item-id').val('');
            }
            sdnCancellingRow = null;
            sdnActiveSearchRow = null;
            setTimeout(function () {
                $('#add-row-btn, #remarks, #submit-btn').first().focus();
            }, 60);
            return;
        }

        sdnItemSelectedInModal = false;
        sdnCancellingRow = null;
        sdnActiveSearchRow = null;
    });

    // Filter typing
    $('#sdn-isl-filter-name, #sdn-isl-filter-code').on('input', function () {
        clearTimeout(sdnDebounce);
        sdnDebounce = setTimeout(fetchSdnItemList, 300);
    });

    $('#sdn-isl-btn-clear').on('click', function () {
        $('#sdn-isl-filter-name, #sdn-isl-filter-code').val('');
        fetchSdnItemList();
    });

    // Fetch items from backend
    function fetchSdnItemList() {
        let branchId = $('select[name="branch_id"]').val() || 3;
        let srch = $.trim($('#sdn-isl-filter-name').val());
        let code = $.trim($('#sdn-isl-filter-code').val());

        if (!srch && !code) {
            $('#sdn-isl-loading').addClass('d-none');
            $('#sdn-isl-table-wrap').addClass('d-none');
            $('#sdn-isl-items-body').empty();
            $('#sdn-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i><p class="mt-2 text-muted">Start typing to search items…</p>'
            );
            $('#sdn-isl-count-label').text('');
            return;
        }

        $('#sdn-isl-loading').removeClass('d-none');
        $('#sdn-isl-no-results').addClass('d-none');
        $('#sdn-isl-table-wrap').addClass('d-none');

        $.getJSON(ISL_URL, { branch_id: branchId, search: srch, code: code }, function (res) {
            $('#sdn-isl-loading').addClass('d-none');
            let items = res.items || [];
            let $tbody = $('#sdn-isl-items-body').empty();

            if (items.length === 0) {
                $('#sdn-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted">No items found.</p>'
                );
                $('#sdn-isl-count-label').text('');
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';
                let stockClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';

                html += `
                    <tr class="sdn-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name}"
                        data-qty="${it.qty || 0}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}">
                        <td class="align-middle text-center text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center ${stockClass}">${parseFloat(it.qty || 0).toFixed(3)}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 sdn-isl-btn-select">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#sdn-isl-table-wrap').removeClass('d-none');
            $('#sdn-isl-count-label').text(items.length + ' item(s) found');
            $tbody.find('tr.sdn-isl-item-row').first().addClass('table-primary');
        }).fail(function () {
            $('#sdn-isl-loading').addClass('d-none');
        });
    }

    // Apply selected item to target row
    function applyItemToSdnRow($row, item) {
        if (!$row || !$row.length || !item || !item.id) return;

        sdnItemSelectedInModal = true;
        sdnCancellingRow = null;

        $row.find('.sdn-item-id').val(item.id);
        $row.find('.sdn-item-desc').val(item.name || item.text || '');
        $row.find('.sdn-item-code').val(item.code || item.barcode || item.item_code || '');

        let sellPrice = parseFloat(item.sell_price || 0);
        let mrp = parseFloat(item.mrp || 0);

        if (sellPrice > 0 || !$row.find('.row-price').val() || parseFloat($row.find('.row-price').val()) === 0) {
            $row.find('.row-price').val(sellPrice.toFixed(2));
        }
        if (mrp > 0 || !$row.find('.row-mrp').val() || parseFloat($row.find('.row-mrp').val()) === 0) {
            $row.find('.row-mrp').val(mrp.toFixed(2));
        }

        let $disp = $row.find('.row-dispatched');
        if (!$disp.val() || parseFloat($disp.val()) <= 0) {
            $disp.val(1);
        }

        recalculate();
        $('#sdn-item-search-modal').modal('hide');

        setTimeout(function () {
            $disp.focus().select();
        }, 100);
    }

    // Selection from modal click
    $(document).on('click', '.sdn-isl-item-row, .sdn-isl-btn-select', function (e) {
        e.stopPropagation();
        let $tr = $(this).hasClass('sdn-isl-item-row') ? $(this) : $(this).closest('tr');
        let itemData = {
            id: $tr.data('id'),
            name: $tr.data('name'),
            code: $tr.data('code'),
            qty: $tr.data('qty'),
            sell_price: $tr.data('sell'),
            mrp: $tr.data('mrp')
        };

        if (!sdnActiveSearchRow || !itemData.id) return;
        applyItemToSdnRow(sdnActiveSearchRow, itemData);
    });

    // Keyboard navigation in modal (Arrow keys, Enter)
    $('#sdn-item-search-modal').on('keydown', function (e) {
        let $rows = $('#sdn-isl-items-body tr.sdn-isl-item-row');
        if (!$rows.length) return;

        let $current = $rows.filter('.table-primary');
        let idx = $rows.index($current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = (idx + 1) >= $rows.length ? 0 : idx + 1;
            $rows.removeClass('table-primary');
            let $target = $rows.eq(idx).addClass('table-primary');
            if ($target[0]) {
                $target[0].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = (idx - 1) < 0 ? $rows.length - 1 : idx - 1;
            $rows.removeClass('table-primary');
            let $target = $rows.eq(idx).addClass('table-primary');
            if ($target[0]) {
                $target[0].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            let $target = $current.length ? $current : $rows.first();
            if ($target.length) {
                $target.trigger('click');
            }
        }
    });

    // Barcode scanner or Enter in .sdn-item-code
    $(document).on('keydown', '.sdn-item-code', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let query = $.trim($(this).val());
            let $row = $(this).closest('tr');
            if (!query) {
                openSdnItemModal($row, '');
                return;
            }
            let branchId = $('select[name="branch_id"]').val() || 3;
            $.getJSON(LOOKUP_URL, { query: query, branch_id: branchId }, function (item) {
                if (item && item.id) {
                    applyItemToSdnRow($row, item);
                } else {
                    openSdnItemModal($row, query);
                }
            }).fail(function () {
                openSdnItemModal($row, query);
            });
        }
    });

    // Add Row
    $('#add-row-btn').on('click', function () {
        let tmpl = $('#row-template').html();
        tmpl = tmpl.replace(/__INDEX__/g, rowIndex);
        tmpl = tmpl.replace(/__NUM__/g, $('#sdn-items-body tr').length + 1);

        let $newRow = $(tmpl);
        $('#sdn-items-body').append($newRow);
        rowIndex++;
        recalculate();
        $newRow.find('.sdn-item-code').focus();
    });

    // Tab / Enter at end of row adds new row or focuses next code
    $(document).on('keydown', '#sdn-items-body input[name$="[exp_date]"]', function (e) {
        if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
            let $currentRow = $(this).closest('tr.sdn-item-row');
            let $nextRow = $currentRow.next('tr.sdn-item-row');
            if ($nextRow.length) {
                e.preventDefault();
                $nextRow.find('.sdn-item-code').focus();
            } else {
                e.preventDefault();
                $('#add-row-btn').trigger('click');
            }
        }
    });

    // Remove Row
    $(document).on('click', '.remove-row-btn', function () {
        if ($('#sdn-items-body tr').length <= 1) {
            alert('At least one item row is required.');
            return;
        }
        $(this).closest('tr').remove();
        recalculate();
    });

    // SO Ref change reloads form
    $('#sdn-so-select').on('change', function () {
        let soId = $(this).val();
        if (soId) {
            window.location.href = "{{ route('sales.delivery-notes.create') }}?from_order=" + soId;
        }
    });

    // Initial calculation
    recalculate();
});
</script>
@stop
