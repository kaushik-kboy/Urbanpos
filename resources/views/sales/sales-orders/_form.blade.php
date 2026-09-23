@php
    $order = $salesOrder ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($order?->items ?? ($convertedItems ?? collect()));
    $selectedCust = $order->customer_id ?? ($sourceQuotation->customer_id ?? old('customer_id'));
    $selectedBranch = old('branch_id', $order->branch_id ?? ($sourceQuotation->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)))));
    $selectedSalesType = $order->sales_type ?? ($sourceQuotation->sales_type ?? old('sales_type', 'Local'));
@endphp

@if(isset($sourceQuotation))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Creating Sales Order from <strong>Quotation #{{ $sourceQuotation->quotation_number }}</strong> (Customer: {{ $sourceQuotation->customer?->name }}).
        <input type="hidden" name="from_quotation_id" value="{{ $sourceQuotation->id }}">
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-shopping-cart text-primary mr-1"></i> Sales Order Details</h6>
    <x-form-layout-customizer
        form-key="sales_orders.header"
        container-id="so-header-fields-grid"
        title="Customize Sales Order Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="so-header-fields-grid">
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
        <label>Active Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Top Navbar</span></label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedBranch] ?? 'Active Branch' }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
            <div class="input-group-append">
                <span class="input-group-text bg-light text-primary" title="Branch is selected globally from top navbar"><i class="fas fa-lock"></i></span>
            </div>
        </div>
    </div>
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
    <div class="field-wrapper col-md-8 form-group" data-field="remarks" data-label="Remarks" data-default-order="8">
        <label>Remarks</label>
        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional delivery notes or customer remarks..." value="{{ $order->remarks ?? old('remarks') }}">
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 text-primary font-weight-bold"><i class="fas fa-boxes mr-1"></i> Order Items</h5>
    <button type="button" class="btn btn-sm btn-outline-primary" id="so-add-row-btn">
        <i class="fas fa-plus mr-1"></i> Add Row
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="so-items-table">
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

<div class="row justify-content-end mt-3">
    <div class="col-md-4">
        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Sub Total:</span>
                    <span class="font-weight-bold" id="so-summary-subtotal">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Total Discount:</span>
                    <span class="text-danger font-weight-bold" id="so-summary-disc">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">GST Amount:</span>
                    <span class="text-info font-weight-bold" id="so-summary-gst">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Round Off:</span>
                    <input type="number" step="0.01" name="round_off" id="so-round-off" class="form-control form-control-sm text-right font-weight-bold" style="width: 100px;" value="{{ $order->round_off ?? old('round_off', '0.00') }}">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between text-lg font-weight-bold">
                    <span>Grand Total:</span>
                    <span class="text-success" id="so-summary-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row Template for JS --}}
<table class="d-none">
    <tbody id="so-row-template">
        @include('sales.sales-orders._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
    </tbody>
</table>

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
                    <div class="col-md-2 text-right">
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
    const SO_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

    $('#so-add-row-btn').on('click', function() {
        let html = $('#so-row-template').html().replace(/__INDEX__/g, nextIndex++);
        let $newRow = $(html);
        $('#so-items-body').append($newRow);
        recalcAll();
        $newRow.find('.so-item-code').focus();
    });

    $(document).off('keydown', '.so-disc-amount, .so-gst-percent').on('keydown', '.so-disc-amount, .so-gst-percent', function (e) {
        if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
            let $currentRow = $(this).closest('tr');
            let $nextRow = $currentRow.next('tr');
            if ($nextRow.length) {
                e.preventDefault();
                $nextRow.find('.so-item-code').focus();
            } else {
                e.preventDefault();
                $('#so-add-row-btn').trigger('click');
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

    $(document).off('click focus', '.so-item-code').on('click focus', '.so-item-code', function (e) {
        if (soModalOpen || soModalClosing) return;
        let $row = $(this).closest('tr');
        if (e.type === 'focus' && $row.find('.so-item-select').val()) return;
        soActiveSearchRow = $row;
        let prefill = $.trim($(this).val());
        $('#so-isl-filter-name').val(prefill);
        $('#so-isl-filter-code').val('');
        fetchSoItemList();
        soModalOpen = true;
        $('#so-item-search-modal').modal('show');
        $('#so-item-search-modal').one('shown.bs.modal', function () {
            $('#so-isl-filter-name').focus().select();
        });
    });

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
                calculateSoTotals();
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

        $.getJSON(SO_ISL_URL, { branch_id: branchId, search: srch, code: code }, function (res) {
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
                        <td class="align-middle text-center text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center ${stockClass}">${parseFloat(it.qty || 0).toFixed(2)}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-right">${parseFloat(it.gst_percent || 0).toFixed(0)}%</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 so-isl-btn-select">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#so-isl-table-wrap').removeClass('d-none');
            $('#so-isl-count-label').text(items.length + ' item(s) found');
        }).fail(function () {
            $('#so-isl-loading').addClass('d-none');
        });
    }

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
        $row.find('.so-item-code').val(itemData.code);
        $row.find('.so-item-desc').val(itemData.name + (itemData.code ? ' [' + itemData.code + ']' : ''));
        $row.find('.so-item-select').val(itemData.id);

        if (!$row.find('.so-qty').val()) {
            $row.find('.so-qty').val(1);
        }
        $row.find('.so-sell-price').val(parseFloat(itemData.sell_price || 0).toFixed(2));
        $row.find('.so-mrp').val(parseFloat(itemData.mrp || 0).toFixed(2));
        $row.find('.so-gst-percent').val(parseFloat(itemData.gst_percent || 0).toFixed(2));

        recalcRow($row);
        calculateSoTotals();

        $('#so-item-search-modal').modal('hide');
    });

    $(document).on('input', '.so-qty, .so-sell-price, .so-disc-percent, .so-disc-amount, .so-gst-percent', function() {
        let $row = $(this).closest('tr');
        let isDiscPct = $(this).hasClass('so-disc-percent');
        recalcRow($row, isDiscPct);
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

        if (itemId) {
            if (qty <= 0) {
                $qtyInput.addClass('is-invalid border-danger text-danger').attr('title', 'Quantity must be greater than 0');
            } else {
                $qtyInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
            }

            if (mrp > 0 && price > mrp) {
                $priceInput.addClass('is-invalid border-danger text-danger').attr('title', `Selling price cannot exceed MRP (₹${mrp})`);
            } else {
                $priceInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
            }
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
    }

    function recalcAll() {
        $('#so-items-body tr').each(function() {
            recalcRow($(this));
        });
    }

    // Form Submit Guard (Task 11)
    $('form').on('submit', function (e) {
        let cust = $('select[name="customer_id"]').val();
        let $custContainer = $('select[name="customer_id"]').next('.select2-container').find('.select2-selection');
        if (!cust) {
            e.preventDefault();
            $custContainer.addClass('border-danger');
            alert('Please select a Customer for this sales order.');
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
                    $q.addClass('is-invalid border-danger');
                    alert(`Row #${idx + 1}: Quantity must be greater than 0.`);
                    $q.focus();
                    hasError = true;
                    return false;
                }
                if (m > 0 && p > m) {
                    $(this).find('.so-sell-price').addClass('is-invalid border-danger');
                    alert(`Row #${idx + 1}: Selling price cannot exceed MRP.`);
                    $(this).find('.so-sell-price').focus();
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
            alert('Please add at least one valid item with quantity > 0.');
            return false;
        }
    });

    recalcAll();
});
</script>
@endpush
