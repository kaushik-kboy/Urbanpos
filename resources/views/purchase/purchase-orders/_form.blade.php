@php
    $po = $purchaseOrder ?? null;
    $indent = $indent ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($initialItems ?? ($po?->items ?? collect()));
    $supplierPurchaseTypes = \App\Models\Supplier::pluck('purchase_type', 'id')->filter();
@endphp

@if ($indent)
    <input type="hidden" name="purchase_indent_id" value="{{ $indent->id }}">
    <div class="alert alert-info mb-3">
        <i class="fas fa-link mr-1"></i> Creating Purchase Order from Indent <strong>#{{ $indent->indent_number }}</strong>
        ({{ $indent->branch->name ?? 'Branch' }}, Priority: <span class="badge badge-warning">{{ $indent->priority }}</span>, Department: <strong>{{ $indent->department }}</strong>)
    </div>
@elseif ($po?->purchase_indent_id)
    <div class="alert alert-info mb-3">
        <i class="fas fa-link mr-1"></i> Linked to Purchase Indent <strong>#{{ $po->purchaseIndent->indent_number ?? $po->purchase_indent_id }}</strong>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 text-muted font-weight-bold text-uppercase small"><i class="fas fa-file-invoice text-primary mr-1"></i> PO Header</h5>
    <x-form-layout-customizer
        form-key="purchase_orders.header"
        container-id="po-header-fields-grid"
        title="Customize Purchase Order Header"
    />
</div>

<div class="row g-2 form-fields-grid" id="po-header-fields-grid">
    <div class="field-wrapper col-md-6" data-field="supplier_id" data-label="Supplier" data-default-order="1" data-core="1">
        <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$po->supplier_id ?? ''" placeholder="Select a supplier" required />
    </div>

    @php
        $selectedBranch = $po->branch_id ?? ($indent->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: ($branches->keys()->first() ?: (\App\Models\Branch::value('id') ?? 1)))));
    @endphp
    <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">

    <div class="field-wrapper col-md-6" data-field="po_date" data-label="PO Date" data-default-order="3" data-core="1">
        <x-field name="po_date" label="PO Date" type="date" :value="optional($po->po_date ?? now())->format('Y-m-d')" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="purchase_type" data-label="Purchase Type" data-default-order="4" data-core="1">
        <x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$po->purchase_type ?? 'Local'" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="c_form" data-label="C-Form" data-default-order="5">
        <x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$po->c_form ?? 'Against C-Form'" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="6" data-core="1">
        <x-select name="status" label="Status" :options="['Open' => 'Open', 'Closed' => 'Closed']" :selected="$po->status ?? 'Open'" required />
    </div>
</div>
@if (($po->status ?? null) === 'Cancelled')
    <div class="alert alert-secondary">
        This Purchase Order was cancelled on {{ $po->cancelled_at->format('d-m-Y H:i') }}
        @if ($po->cancellation_reason) — "{{ $po->cancellation_reason }}" @endif.
        It cannot be edited.
    </div>
@endif

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Items</h5>
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-outline-danger btn-xs font-weight-bold mr-2 btn-reset-table" id="po-btn-reset-table" title="Clear all table items and reset to 1 empty row">
            <i class="fas fa-undo mr-1"></i> Reset Table
        </button>
        <x-table-column-customizer
            table-key="purchase.purchase-orders.items"
            table-id="po-items-table"
            button-class="btn btn-xs btn-outline-secondary mr-2 shadow-sm font-weight-bold"
            button-text="Columns"
            title="Show/Hide & Arrange Item Columns"
        />
        <span class="badge badge-info px-3 py-2"><i class="fas fa-keyboard mr-1"></i> Press Enter on Code/Barcode to open Item Search</span>
    </div>
</div>

<style>
    #po-items-table th {
        vertical-align: middle;
        padding: 4px 2px !important;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    #po-items-table td {
        vertical-align: middle;
        padding: 1px 1px !important;
    }
    #po-items-table input.form-control-sm {
        font-size: 0.82rem;
        padding: 1px 3px !important;
        height: 27px !important;
        border-radius: 2px;
    }
</style>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-items-dense" id="po-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width:30px" class="text-center" data-col-key="sr" data-can-hide="false">#</th>
                <th style="width:110px" data-col-key="code" data-can-hide="false">Code / Barcode</th>
                <th style="min-width:210px" data-col-key="desc" data-can-hide="false">Item Description</th>
                <th style="width:75px" class="text-right" data-col-key="stock">Stock</th>
                <th style="width:75px" class="text-right" data-col-key="qty" data-can-hide="false">Qty</th>
                <th style="width:45px" class="text-right" data-col-key="free">Free</th>
                <th style="width:90px" class="text-right" data-col-key="cost">Cost Price</th>
                <th style="width:90px" class="text-right" data-col-key="landing_cost" title="Landing Cost Price (Effective unit cost after free qty & discount)">Landing Cost</th>
                <th style="width:90px" class="text-right" data-col-key="sell">Sell Price</th>
                <th style="width:85px" class="text-right" data-col-key="mrp">MRP</th>
                <th style="width:50px" class="text-right" data-col-key="margin" title="Margin %">Margin %</th>
                <th style="width:50px" class="text-right" data-col-key="profit" title="Profit %">Profit %</th>
                <th style="width:48px" class="text-right" data-col-key="disc_pct">Disc %</th>
                <th style="width:85px" class="text-right" data-col-key="disc_amt">Disc Amt</th>
                <th style="width:45px" class="text-right" data-col-key="gst">GST%</th>
                <th style="width:105px" class="text-right font-weight-bold text-success" data-col-key="net" data-can-hide="false">Net Amount</th>
                <th style="width:35px" data-col-key="action" data-can-hide="false"></th>
            </tr>
        </thead>
        <tbody id="po-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="4" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary" id="po-footer-qty" data-col-key="qty">0</td>
                <td class="text-right align-middle text-muted" id="po-footer-free" data-col-key="free">0</td>
                <td colspan="7"></td>
                <td class="text-right align-middle text-danger" id="po-footer-disc" data-col-key="disc_amt">0.00</td>
                <td></td>
                <td class="text-right align-middle text-success h6 mb-0" id="po-footer-net" data-col-key="net">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<button type="button" id="po-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

{{-- Live Total Summary --}}
<div class="card card-outline card-primary shadow-sm mt-3 mb-3">
    <div class="card-body py-2 px-3">
        <div class="row text-center">
            <div class="col-md-3 border-right">
                <small class="text-muted d-block">Total Qty</small>
                <strong class="h5 text-primary" id="po-summary-qty">0</strong>
            </div>
            <div class="col-md-3 border-right">
                <small class="text-muted d-block">Total Discount (Line + Scheme + Other)</small>
                <strong class="h5 text-danger" id="po-summary-disc">₹0.00</strong>
            </div>
            <div class="col-md-3 border-right">
                <small class="text-muted d-block">Items Total (before freight)</small>
                <strong class="h5 text-dark" id="po-summary-items">₹0.00</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Grand Total</small>
                <strong class="h4 text-success" id="po-summary-grand">₹0.00</strong>
            </div>
        </div>
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Charges</h5>
    <x-form-layout-customizer
        form-key="purchase_orders.totals"
        container-id="po-totals-fields-grid"
        title="Customize Purchase Order Totals Layout"
    />
</div>

<div class="row g-2 form-fields-grid" id="po-totals-fields-grid">
    <div class="field-wrapper col-md-6" data-field="freight" data-label="Freight" data-default-order="1">
        <x-field name="freight" label="Freight" type="number" step="0.01" :value="$po->freight ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="round_off" data-label="Round off Amount" data-default-order="2">
        <x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$po->round_off ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="scheme_item_disc_amt" data-label="Scheme ItemDiscAmt" data-default-order="3">
        <x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="$po->scheme_item_disc_amt ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="scheme_item_disc_percent" data-label="Scheme ItemDisc%" data-default-order="4">
        <x-field name="scheme_item_disc_percent" label="Scheme ItemDisc%" type="number" step="0.01" min="0" max="100" :value="$po->scheme_item_disc_percent ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="other_disc_amt" data-label="OtherDiscAmt" data-default-order="5">
        <x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="$po->other_disc_amt ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_extra_cess" data-label="Total Extra Cess" data-default-order="6">
        <x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$po->total_extra_cess ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_weight" data-label="Total Weight" data-default-order="7">
        <x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$po->total_weight ?? 0" />
    </div>
    <div class="field-wrapper col-md-12" data-field="remarks" data-label="Remarks" data-default-order="8">
        <x-textarea name="remarks" label="Remarks" :value="$po->remarks ?? ($indent ? 'Requisition from Indent #' . $indent->indent_number . ($indent->remarks ? ' - ' . $indent->remarks : '') : '')" />
    </div>
    <div class="field-wrapper col-md-12" data-field="message" data-label="Message" data-default-order="9">
        <x-textarea name="message" label="Message" :value="$po->message ?? ''" />
    </div>
</div>

<x-custom-fields-renderer :module="'PurchaseOrder'" :model="$po ?? null" :cardStyle="true" />

<template id="po-row-template">
    @include('purchase.purchase-orders._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

<!-- ============================================================
     ITEM SEARCH MODAL — opens on Code/Barcode field focus/click
     ============================================================ -->
<div class="modal fade" id="po-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="poItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="poItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="po-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="po-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="po-isl-filter-expiry" class="form-control" placeholder="Filter expiry…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="po-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results / Hint states -->
                <div id="po-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="po-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="po-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="po-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 120px;">Code</th>
                                <th class="text-right" style="width: 95px;">Cost Price</th>
                                <th class="text-right" style="width: 95px;">Sell Price</th>
                                <th class="text-right" style="width: 90px;">MRP</th>
                                <th class="text-right" style="width: 85px;">Stock</th>
                                <th class="text-center" style="width: 120px;">Expiry / Batch</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="po-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="po-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeSearchRow = null;
        let islDebounce = null;
        let islCache = {};
        let islLastKey = null;
        let islModalOpen = false;
        let islModalClosing = false;
        let islSelectedIdx = -1;
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';
        const LOOKUP_URL = '{{ route("purchase.purchase-invoices.lookup-item") }}';

        $('[name="branch_id"]').on('change', function () {
            islCache = {};
            islLastKey = null;
        });

        function updateRowNumbers() {
            $('#po-items-body tr').each(function (idx) {
                $(this).find('.po-sr-no').text(idx + 1);
            });
        }

        /* ================================================================
           ITEM SEARCH MODAL
           ================================================================ */
        $('#po-isl-filter-name, #po-isl-filter-code, #po-isl-filter-expiry').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchItemList, 400);
        });

        $('#po-isl-btn-clear').on('click', function () {
            $('#po-isl-filter-name, #po-isl-filter-code, #po-isl-filter-expiry').val('');
            fetchItemList();
        });

        function showHintState(msg) {
            $('#po-isl-loading').addClass('d-none');
            $('#po-isl-table-wrap').addClass('d-none');
            $('#po-isl-items-body').empty();
            $('#po-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-search fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + (msg || 'Type at least 1 character to search…') + '</p>'
            );
            $('#po-isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || '';
            let srch     = $.trim($('#po-isl-filter-name').val());
            let code     = $.trim($('#po-isl-filter-code').val());
            let expiry   = $.trim($('#po-isl-filter-expiry').val());

            if (!srch && !code && !expiry) {
                showHintState('Type product name, code or barcode to search…');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;
            if (islCache[cacheKey]) {
                if (islLastKey !== cacheKey) {
                    islLastKey = cacheKey;
                    renderItems(islCache[cacheKey]);
                }
                return;
            }

            islLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $('#po-isl-loading').removeClass('d-none');
            $('#po-isl-no-results').addClass('d-none');
            $('#po-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#po-isl-loading').addClass('d-none');
                islCache[cacheKey] = res.items || [];
                setTimeout(function () { delete islCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#po-isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        function renderItems(items) {
            let $tbody = $('#po-isl-items-body');
            $tbody.empty();

            if (items.length === 0) {
                $('#po-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#po-isl-count-label').text('');
                islSelectedIdx = -1;
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = '<span class="text-muted">—</span>';
                if (it.exp_date) {
                    expBadge = `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`;
                } else if (['Mandatory', 'Days', 'Month'].includes(it.batch_expiry_details)) {
                    expBadge = `<span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i>${it.batch_expiry_details}</span>`;
                }

                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;

                let qtyClass = it.qty <= 0 ? 'text-muted' : 'text-primary font-weight-bold';
                let costDisplay = it.cost_price > 0 ? '₹' + parseFloat(it.cost_price).toFixed(2) : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay  = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';

                html += `
                    <tr class="po-isl-item-row ${idx === 0 ? 'table-primary' : ''}" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code}"
                        data-name="${it.name}"
                        data-cost="${it.cost_price || 0}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-stock="${it.qty || 0}"
                        data-gst="${it.gst_percent || 0}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-right font-weight-bold text-primary">${costDisplay}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-right ${qtyClass}">${parseFloat(it.qty).toFixed(2)}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 po-isl-btn-select"
                                data-id="${it.id}" data-code="${it.code}" data-stock="${it.qty || 0}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#po-isl-table-wrap').removeClass('d-none');
            $('#po-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
            islSelectedIdx = items.length > 0 ? 0 : -1;
            updateModalHighlight();
        }

        function updateModalHighlight() {
            let $rows = $('#po-isl-items-body tr.po-isl-item-row');
            $rows.removeClass('table-primary');
            if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                let $target = $rows.eq(islSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#po-isl-table-wrap')[0];
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

        $('#po-isl-filter-name, #po-isl-filter-code, #po-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#po-isl-items-body tr.po-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                islSelectedIdx = Math.min(islSelectedIdx + 1, $rows.length - 1);
                updateModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                islSelectedIdx = Math.max(islSelectedIdx - 1, 0);
                updateModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                    $rows.eq(islSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        function populatePoRow($row, data) {
            if (!data || !data.id) return;

            let codeVal = data.item_code || data.code || data.barcode || data.id || '';
            $row.find('.po-item-code').val(codeVal);
            $row.find('.po-item-desc').val(data.name + (data.item_code ? ' [' + data.item_code + ']' : ''));
            $row.find('.po-item-select').val(data.id);

            let stockVal = data.stock !== undefined ? data.stock : (data.qty !== undefined ? data.qty : 0);
            $row.find('.po-item-stock').val(Math.round(parseFloat(stockVal) || 0));

            if (parseFloat(data.cost_price) > 0) {
                $row.find('.po-cost').val(parseFloat(data.cost_price).toFixed(2));
            }
            if (parseFloat(data.sell_price) > 0) {
                $row.find('.po-sell').val(parseFloat(data.sell_price).toFixed(2));
            }
            if (parseFloat(data.mrp) > 0) {
                $row.find('.po-mrp').val(parseFloat(data.mrp).toFixed(2));
            }
            if (parseFloat(data.gst_percent) >= 0) {
                $row.find('.po-gst').val(parseFloat(data.gst_percent).toFixed(2));
            }

            calculatePoRow($row);
            setTimeout(function () {
                $row.find('.po-qty').focus().select();
            }, 20);
        }

        let poCancellingRow = null;
        let poItemSelectedInModal = false;
        let pendingFocusQtyRow = null;

        // Clicking row or select button in modal
        $(document).on('click', '.po-isl-item-row, .po-isl-btn-select', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('po-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemData = {
                id: $tr.data('id'),
                name: $tr.data('name'),
                code: $tr.data('id'),
                cost_price: $tr.data('cost'),
                sell_price: $tr.data('sell'),
                mrp: $tr.data('mrp'),
                stock: $tr.data('stock'),
                gst_percent: $tr.data('gst')
            };

            if (!activeSearchRow || !itemData.id) return;
            poItemSelectedInModal = true;
            poCancellingRow = null;
            let $targetRow = activeSearchRow;
            pendingFocusQtyRow = $targetRow;
            populatePoRow($targetRow, itemData);
            $('#po-item-search-modal').modal('hide');
        });

        let poMouseDown = false;
        $(document).on('mousedown', '.po-item-code', function () {
            poMouseDown = true;
        });

        function checkSupplierAndOpenPoModal($input) {
            let supplierId = $('#supplier_id').val();
            if (!supplierId) {
                if (window.toastr) {
                    toastr.warning('Please select a Supplier first.', 'Supplier Required');
                } else {
                    alert('Please select a Supplier first.');
                }
                $('#supplier_id').select2('open');
                return false;
            }
            if (islModalOpen || islModalClosing) return false;
            let $row = $input.closest('tr');
            if ($row.find('.po-item-select').val()) return false;
            activeSearchRow = $row;
            let prefill = $.trim($input.val());
            $('#po-isl-filter-name').val(prefill);
            $('#po-isl-filter-code').val('');
            $('#po-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#po-item-search-modal').modal('show');
            $('#po-item-search-modal').one('shown.bs.modal', function () {
                $('#po-isl-filter-name').focus().select();
                if (prefill) fetchItemList();
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

        $('#po-item-search-modal').on('show.bs.modal', function () {
            islModalOpen = true;
            islModalClosing = false;
            poItemSelectedInModal = false;
            poCancellingRow = null;
        });

        $('#po-item-search-modal').on('hide.bs.modal', function () {
            islModalOpen = false;
            islModalClosing = true;
            if (!poItemSelectedInModal && activeSearchRow && activeSearchRow.length) {
                let selectedId = activeSearchRow.find('.po-item-select').val();
                if (!selectedId) {
                    poCancellingRow = activeSearchRow;
                }
            }
        });

        $('#po-item-search-modal').on('hidden.bs.modal', function () {
            islModalOpen = false;
            islModalClosing = true;
            setTimeout(function () { islModalClosing = false; }, 350);

            if (!poItemSelectedInModal) {
                if (poCancellingRow && poCancellingRow.length) {
                    let totalRows = $('#po-items-body tr').length;
                    if (totalRows > 1) {
                        poCancellingRow.remove();
                        updateRowNumbers();
                        calculatePoTotals();
                    } else {
                        poCancellingRow.find('.po-item-code').val('');
                        poCancellingRow.find('.po-item-desc').val('');
                    }
                }
                poCancellingRow = null;
                activeSearchRow = null;
                pendingFocusQtyRow = null;
                setTimeout(function () {
                    let $target = $('input[name="freight"], #freight');
                    if ($target.length) {
                        $target.first().focus().select();
                    }
                }, 80);
                return;
            }

            if (pendingFocusQtyRow && pendingFocusQtyRow.length) {
                let $target = pendingFocusQtyRow;
                pendingFocusQtyRow = null;
                setTimeout(function () {
                    let $qty = $target.find('.po-qty');
                    $qty.focus().select();
                }, 50);
                setTimeout(function () {
                    let $qty = $target.find('.po-qty');
                    if (document.activeElement !== $qty[0]) {
                        $qty.focus().select();
                    }
                }, 150);
                setTimeout(function () {
                    let $qty = $target.find('.po-qty');
                    if (document.activeElement !== $qty[0]) {
                        $qty.focus().select();
                    }
                }, 300);
            }

            poItemSelectedInModal = false;
            poCancellingRow = null;
            activeSearchRow = null;
        });

        function processPoItemLookup($row, itemId, query, isDirectLookup = false) {
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || '';
            let $code = $row.find('.po-item-code');

            if (query && window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(query);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

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

            $.getJSON(LOOKUP_URL, params, function (data) {
                if (data && data.id) {
                    $row.data('last-processed-code', query || data.item_code || data.ean_upc_code || data.id);
                    $code.removeClass('is-invalid border-danger');
                    populatePoRow($row, data);
                    setTimeout(function () {
                        $row.find('.po-qty').focus().select();
                    }, 60);
                } else {
                    $row.data('last-processed-code', null);
                    $code.addClass('is-invalid border-danger');
                    const errMsg = "Product not found for this Item Code/Barcode.";
                    if (window.toastr && typeof window.toastr.warning === 'function') {
                        toastr.clear();
                        toastr.warning(errMsg, 'Item Not Found');
                    } else {
                        alert(errMsg);
                    }
                    setTimeout(function () {
                        $code.focus().select();
                    }, 50);
                }
            });
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
        $(document).off('keydown change input', '.po-item-code')
            .on('keydown', '.po-item-code', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        processPoItemLookup($row, null, val, true);
                    } else {
                        checkSupplierAndOpenPoModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        e.preventDefault();
                        processPoItemLookup($row, null, val, true);
                    } else {
                        e.preventDefault();
                        checkSupplierAndOpenPoModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkSupplierAndOpenPoModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr');
                    let itemId = $row.find('.po-item-select').val();
                    if (!itemId && $('#po-items-body tr').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr');
                        $row.remove();
                        updateRowNumbers();
                        calculatePoTotals();
                        if ($prevRow.length) {
                            $prevRow.find('.po-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.po-item-code', function () {
                let $input = $(this);
                let query = $.trim($input.val());
                let $row = $input.closest('tr');
                if (!query) {
                    $row.find('.po-item-select').val('');
                    $row.find('.po-item-desc').val('');
                    $row.data('last-processed-code', '');
                    calculatePoRow($row);
                    return;
                }

                if ($row.data('last-processed-code') === query) return;
                processPoItemLookup($row, null, query, true);
            })
            .on('input', '.po-item-code', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Clicking on description also opens item search modal
        $(document).on('click', '.po-item-desc', function () {
            let $code = $(this).closest('tr').find('.po-item-code');
            checkSupplierAndOpenPoModal($code);
        });

        /* ================================================================
           CALCULATIONS & ROW EVENTS (Option 1 Consistency)
           ================================================================ */
        let poTotalsRafId = null;
        function scheduleCalculatePoTotals() {
            if (poTotalsRafId) {
                cancelAnimationFrame(poTotalsRafId);
            }
            poTotalsRafId = requestAnimationFrame(function () {
                poTotalsRafId = null;
                calculatePoTotals();
            });
        }

        // Sync Scheme ItemDisc% <-> Scheme ItemDiscAmt
        let isSyncingSchemeDisc = false;
        function syncSchemeDiscount(source) {
            if (isSyncingSchemeDisc) return;
            isSyncingSchemeDisc = true;

            let totalBaseCost = 0;
            $('#po-items-body tr').each(function () {
                let qty = parseFloat($(this).find('.po-qty').val()) || 0;
                let cost = parseFloat($(this).find('.po-cost').val()) || 0;
                let disc = parseFloat($(this).find('.po-disc-amount').val()) || 0;
                totalBaseCost += Math.max(0, (qty * cost) - disc);
            });

            let $amtInput = $('input[name="scheme_item_disc_amt"]');
            let $pctInput = $('input[name="scheme_item_disc_percent"]');

            if (source === 'percent') {
                let pct = parseFloat($pctInput.val()) || 0;
                if (pct > 0 && totalBaseCost > 0) {
                    let amt = Math.round((totalBaseCost * pct / 100) * 100) / 100;
                    $amtInput.val(amt > 0 ? amt.toFixed(2) : '');
                } else if (!pct) {
                    $amtInput.val('');
                }
            } else if (source === 'amount') {
                let amt = parseFloat($amtInput.val()) || 0;
                if (amt > 0 && totalBaseCost > 0) {
                    let pct = Math.round(((amt / totalBaseCost) * 100) * 100) / 100;
                    $pctInput.val(pct > 0 ? pct.toFixed(2) : '');
                } else if (!amt) {
                    $pctInput.val('');
                }
            }

            isSyncingSchemeDisc = false;
            scheduleCalculatePoTotals();
        }

        $(document).on('input', 'input[name="scheme_item_disc_percent"]', function () {
            syncSchemeDiscount('percent');
        });
        $(document).on('input', 'input[name="scheme_item_disc_amt"]', function () {
            syncSchemeDiscount('amount');
        });

        function calculatePoRow($row, source = null) {
            let qty      = parseFloat($row.find('.po-qty').val()) || 0;
            let cost     = parseFloat($row.find('.po-cost').val()) || 0;
            let $discPct = $row.find('.po-disc-percent');
            let $discAmt = $row.find('.po-disc-amount');
            let discPctVal = ($discPct.val() || '').toString().trim();
            let discAmtVal = ($discAmt.val() || '').toString().trim();
            let discPct  = parseFloat(discPctVal) || 0;
            let discAmt  = parseFloat(discAmtVal) || 0;

            let base = qty * cost;

            // Sync disc percent <-> disc amount with source awareness
            if (source === 'percent') {
                if (discPctVal === '' || discPct <= 0) {
                    $discAmt.val('');
                    discAmt = 0;
                } else if (base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                }
            } else if (source === 'amount') {
                if (discAmtVal === '' || discAmt <= 0) {
                    $discPct.val('');
                    discPct = 0;
                } else if (base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                }
            } else {
                if (discPctVal !== '' && discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discAmtVal !== '' && discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                }
            }

            let isDiscPctInvalid = discPct < 0 || discPct > 100;
            let isDiscAmtInvalid = discAmt < 0 || (base > 0 && discAmt > base);

            if (isDiscPctInvalid) {
                $discPct.addClass('border-danger text-danger is-invalid').attr('title', 'Discount % cannot exceed 100%');
            } else {
                $discPct.removeClass('border-danger text-danger is-invalid').attr('title', '');
            }

            if (isDiscAmtInvalid) {
                $discAmt.addClass('border-danger text-danger is-invalid').attr('title', 'Discount amount cannot exceed item total (₹' + base.toFixed(2) + ')');
            } else {
                $discAmt.removeClass('border-danger text-danger is-invalid').attr('title', '');
            }

            scheduleCalculatePoTotals();
            return { qty, base, discAmt };
        }

        function calculatePoTotals() {
            let schemeDisc = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let otherDisc  = parseFloat($('input[name="other_disc_amt"]').val()) || 0;
            let totalHeaderDiscount = schemeDisc + otherDisc;

            let rowsData = [];
            let totalBaseAfterItemDisc = 0;

            $('#po-items-body tr').each(function () {
                let $r = $(this);
                let qty     = parseFloat($r.find('.po-qty').val()) || 0;
                let freeQty = parseFloat($r.find('.po-free-qty').val()) || 0;
                let cost    = parseFloat($r.find('.po-cost').val()) || 0;
                let sell    = parseFloat($r.find('.po-sell').val()) || 0;
                let mrp     = parseFloat($r.find('.po-mrp').val()) || 0;
                let discAmt = parseFloat($r.find('.po-disc-amount').val()) || 0;
                let gst     = parseFloat($r.find('.po-gst').val()) || 0;

                let base = qty * cost;
                let baseAfterDisc = Math.max(0, base - discAmt);
                totalBaseAfterItemDisc += baseAfterDisc;

                rowsData.push({
                    $row: $r,
                    qty: qty,
                    freeQty: freeQty,
                    cost: cost,
                    sell: sell,
                    mrp: mrp,
                    base: base,
                    discAmt: discAmt,
                    baseAfterDisc: baseAfterDisc,
                    gst: gst
                });
            });

            let totalQty     = 0;
            let totalFree    = 0;
            let totalLineDisc = 0;
            let totalNetAmt  = 0;
            let remainingDiscount = totalHeaderDiscount;

            for (let i = 0; i < rowsData.length; i++) {
                let d = rowsData[i];
                let isLast = (i === rowsData.length - 1);

                // Proportional header discount allocation
                let extraDeduction = 0;
                if (totalBaseAfterItemDisc > 0 && totalHeaderDiscount > 0) {
                    if (isLast) {
                        extraDeduction = Math.round(remainingDiscount * 100) / 100;
                    } else {
                        extraDeduction = Math.round(((d.baseAfterDisc / totalBaseAfterItemDisc) * totalHeaderDiscount) * 100) / 100;
                        remainingDiscount -= extraDeduction;
                    }
                }
                extraDeduction = Math.max(0, extraDeduction);

                // Row net calculation: (Base - Line Disc) + GST
                let taxAmt = Math.round((d.baseAfterDisc * (d.gst / 100)) * 100) / 100;
                let net    = Math.round((d.baseAfterDisc + taxAmt) * 100) / 100;

                d.$row.find('.po-row-net').text(net.toFixed(2));

                // Landing Cost = (Billed Base - Line Disc - Allocated Scheme/Other Disc) / (Qty + Free Qty)
                let totalUnits = d.qty + d.freeQty;
                let trueLandingCost = 0;
                if (totalUnits > 0 && d.cost > 0) {
                    let netCostAfterAllDisc = Math.max(0, d.baseAfterDisc - extraDeduction);
                    trueLandingCost = netCostAfterAllDisc / totalUnits;
                } else if (d.cost > 0) {
                    trueLandingCost = d.cost;
                }

                let $landingInput = d.$row.find('.po-landing-cost');
                if ($landingInput.length) {
                    $landingInput.val(trueLandingCost > 0 ? trueLandingCost.toFixed(2) : '');
                    let discBreakdown = [];
                    if (d.freeQty > 0) discBreakdown.push(d.freeQty + ' free');
                    if (d.discAmt > 0) discBreakdown.push('₹' + d.discAmt.toFixed(2) + ' item disc');
                    if (extraDeduction > 0) discBreakdown.push('₹' + extraDeduction.toFixed(2) + ' scheme/other disc');

                    if (discBreakdown.length > 0) {
                        $landingInput.attr('title', 'Landing Cost Price: ₹' + trueLandingCost.toFixed(2) + ' (Effective unit cost after ' + discBreakdown.join(', ') + ')');
                    } else {
                        $landingInput.attr('title', 'Landing Cost Price');
                    }
                }

                // Margins & Profit %
                let effectiveCost = trueLandingCost > 0 ? trueLandingCost : d.cost;
                let baseSell = d.sell > 0 ? d.sell : d.mrp;
                let sellExclGst = (baseSell > 0) ? (baseSell / (1 + (d.gst / 100))) : 0;
                let profitAmount = (sellExclGst > 0 && effectiveCost > 0) ? (sellExclGst - effectiveCost) : null;
                let marginPct = (sellExclGst > 0 && profitAmount !== null) ? ((profitAmount / sellExclGst) * 100) : null;
                let profitPct = (effectiveCost > 0 && profitAmount !== null) ? ((profitAmount / effectiveCost) * 100) : null;

                d.$row.find('.po-margin').val(marginPct !== null && isFinite(marginPct) ? marginPct.toFixed(1) + '%' : '');
                d.$row.find('.po-profit').val(profitPct !== null && isFinite(profitPct) ? profitPct.toFixed(1) + '%' : '');

                totalQty      += d.qty;
                totalFree     += d.freeQty;
                totalLineDisc += d.discAmt;
                totalNetAmt   += net;
            }

            // Footer row
            $('#po-footer-qty').text(totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3));
            $('#po-footer-free').text(totalFree % 1 === 0 ? totalFree : totalFree.toFixed(3));
            $('#po-footer-disc').text(totalLineDisc.toFixed(2));
            $('#po-footer-net').text(totalNetAmt.toFixed(2));

            // Summary card
            let freight    = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff   = parseFloat($('input[name="round_off"]').val()) || 0;
            let extraCess  = parseFloat($('input[name="total_extra_cess"]').val()) || 0;
            let totalAllDisc = totalLineDisc + totalHeaderDiscount;
            let grandTotal = totalNetAmt + freight + roundOff + extraCess - totalHeaderDiscount;

            $('#po-summary-qty').text(totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3));
            $('#po-summary-disc').text('₹' + totalAllDisc.toFixed(2));
            $('#po-summary-items').text('₹' + totalNetAmt.toFixed(2));
            $('#po-summary-grand').text('₹' + Math.max(0, grandTotal).toFixed(2));
        }

        $(document).on('input', '.po-qty, .po-free-qty, .po-cost, .po-sell, .po-mrp, .po-gst', function () {
            calculatePoRow($(this).closest('tr'));
        });
        $(document).on('input', '.po-disc-percent', function () {
            calculatePoRow($(this).closest('tr'), 'percent');
        });
        $(document).on('input', '.po-disc-amount', function () {
            calculatePoRow($(this).closest('tr'), 'amount');
        });

        // Recalculate when charges or discounts change
        $(document).on('input', 'input[name="other_disc_amt"], input[name="freight"], input[name="round_off"], input[name="total_extra_cess"]', function () {
            scheduleCalculatePoTotals();
        });

        // Initial calculation on page load
        scheduleCalculatePoTotals();


        function addPoRowAndOpenSearchModal() {
            let html = $('#po-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#po-items-body');
            let $newRow = $(html);
            $tbody.append($newRow);
            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
            setTimeout(function () {
                let $code = $newRow.find('.po-item-code');
                $code.focus();
                checkSupplierAndOpenPoModal($code);
            }, 60);
        }

        // Enter key inside row moves sequentially to the next editable field
        $(document).on('keydown', '.po-qty, .po-free-qty, .po-cost, .po-sell, .po-mrp, .po-disc-percent', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let $currentRow = $(this).closest('tr');
                let $inputs = $currentRow.find('input:visible:not([readonly]):not([tabindex="-1"])');
                let idx = $inputs.index(this);
                if (idx > -1 && idx + 1 < $inputs.length) {
                    $inputs.eq(idx + 1).focus().select();
                }
            }
        });

        // Disc Amount & GST (last editable fields): Tab or Enter adds new row & opens item popup, or moves to next row
        $(document).on('keydown', '.po-disc-amount, .po-gst', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if (!$nextRow.length) {
                    e.preventDefault();
                    addPoRowAndOpenSearchModal();
                } else {
                    e.preventDefault();
                    $nextRow.find('.po-item-code').focus();
                }
            }
        });

        // Add Row
        $('#po-add-row').on('click', function () {
            let html = $('#po-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#po-items-body');
            let $newRow = $(html);
            $tbody.append($newRow);
            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
        });

        // Remove Row
        $('#po-items-body').on('click', '.po-remove-row', function () {
            let rows = $('#po-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
        });

        // Task 4 & 9: Auto-fill Supplier Purchase Type and lock it
        const supplierPurchaseTypes = @json($supplierPurchaseTypes);
        function applySupplierPurchaseType() {
            let sId = $('#supplier_id').val();
            if (sId && supplierPurchaseTypes[sId]) {
                let pType = String(supplierPurchaseTypes[sId]).trim();
                let normalized = pType.charAt(0).toUpperCase() + pType.slice(1).toLowerCase();
                let finalVal = (normalized === 'Local' || normalized === 'Interstate') ? normalized : pType;
                $('#purchase_type').val(finalVal).trigger('change');
                $('#purchase_type').prop('disabled', true).addClass('bg-light');
                if (!$('#hidden-purchase-type').length) {
                    $('<input type="hidden" name="purchase_type" id="hidden-purchase-type">').appendTo('#po-header-fields-grid');
                }
                $('#hidden-purchase-type').val(finalVal);
            } else {
                $('#purchase_type').prop('disabled', false).removeClass('bg-light');
                $('#hidden-purchase-type').remove();
            }
        }
        $('#supplier_id').on('change', applySupplierPurchaseType);
        applySupplierPurchaseType();

        // Form Submit Handler
        let poIsSubmitting = false;
        $('form').on('submit', function (e) {
            let $btn = $(this).find('button[type="submit"]:not(.btn-navbar)');
            if (poIsSubmitting || $btn.prop('disabled') || $btn.hasClass('disabled')) {
                e.preventDefault();
                return false;
            }

            let supplierId = $('#supplier_id').val();
            if (!supplierId) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Please select a Supplier first.', 'Supplier Required');
                } else {
                    alert('Please select a Supplier first.');
                }
                $('#supplier_id').select2('open');
                return false;
            }

            let validCount = 0;
            let hasError = false;

            $('#po-items-body tr').each(function () {
                let $row = $(this);
                let itemId = $row.find('.po-item-select').val();
                let itemCode = $.trim($row.find('.po-item-code').val());
                let itemName = $.trim($row.find('.po-item-desc').val()) || itemCode || 'Selected Item';
                let $qtyInput = $row.find('.po-qty');
                let qtyVal = $qtyInput.val();
                let qty = parseFloat(qtyVal) || 0;

                // Completely blank row (no item, no code) -> skip
                if (!itemId && !itemCode) {
                    return;
                }

                // Item code entered but not selected
                if (!itemId && itemCode) {
                    e.preventDefault();
                    if (window.toastr) {
                        toastr.warning(`Please select a valid item for: "${itemCode}"`, 'Item Required');
                    } else {
                        alert(`Please select a valid item for: "${itemCode}"`);
                    }
                    $row.find('.po-item-code').focus();
                    hasError = true;
                    return false;
                }

                // Item IS selected:
                validCount++;
                if (!qtyVal || qty <= 0) {
                    e.preventDefault();
                    if (window.toastr) {
                        toastr.warning(`Please enter quantity for item: "${itemName}"`, 'Quantity Required');
                    } else {
                        alert(`Please enter quantity for item: "${itemName}"`);
                    }
                    $qtyInput.focus().select();
                    hasError = true;
                    return false;
                }

                let $discPct = $row.find('.po-disc-percent');
                let discPctVal = parseFloat($discPct.val()) || 0;
                let $discAmt = $row.find('.po-disc-amount');
                let discAmtVal = parseFloat($discAmt.val()) || 0;
                let costVal = parseFloat($row.find('.po-cost').val()) || 0;
                let baseTotal = qty * costVal;

                if (discPctVal < 0 || discPctVal > 100) {
                    e.preventDefault();
                    $discPct.addClass('is-invalid border-danger');
                    let msg = `Row for "${itemName}": Discount % (${discPctVal}%) cannot exceed 100%.`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount %');
                    else alert(msg);
                    $discPct.focus().select();
                    hasError = true;
                    return false;
                }

                if (discAmtVal < 0 || (baseTotal > 0 && discAmtVal > baseTotal)) {
                    e.preventDefault();
                    $discAmt.addClass('is-invalid border-danger');
                    let msg = `Row for "${itemName}": Discount amount (₹${discAmtVal}) cannot exceed item total (₹${baseTotal.toFixed(2)}).`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount Amount');
                    else alert(msg);
                    $discAmt.focus().select();
                    hasError = true;
                    return false;
                }
            });

            let $schemePct = $('input[name="scheme_item_disc_percent"]');
            let schemePctVal = parseFloat($schemePct.val()) || 0;
            if (schemePctVal < 0 || schemePctVal > 100) {
                e.preventDefault();
                $schemePct.addClass('is-invalid border-danger');
                if (window.toastr) {
                    toastr.warning('Scheme Item Discount % cannot exceed 100%.', 'Invalid Scheme Discount');
                } else {
                    alert('Scheme Item Discount % cannot exceed 100%.');
                }
                $schemePct.focus().select();
                return false;
            } else {
                $schemePct.removeClass('is-invalid border-danger');
            }

            if (hasError) return false;

            if (validCount === 0) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Pehle item add karein. Please add at least one item before saving.', 'No Items Added');
                } else {
                    alert('Pehle item add karein. Please add at least one item before saving.');
                }
                $('#po-items-body tr:first .po-item-code').focus();
                return false;
            }

            // Remove purely empty rows before submitting
            $('#po-items-body tr').each(function () {
                let itemId = $(this).find('.po-item-select').val();
                if (!itemId) {
                    $(this).remove();
                }
            });

            // Re-index remaining rows so items[0], items[1] are contiguous
            $('#po-items-body tr').each(function (idx) {
                $(this).find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.indexOf('items[') !== -1) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });

            poIsSubmitting = true;
            setTimeout(function () {
                if ($btn.length) {
                    $btn.prop('disabled', true).addClass('disabled').html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
                }
            }, 10);
        });

        // Initial setup
        updateRowNumbers();
        $('form input').attr('autocomplete', 'off');

        // Reset Table Button Handler: clears table and keeps exactly 1 empty row
        $(document).on('click', '.btn-reset-table', function (e) {
            e.preventDefault();
            let template = $('#po-row-template').html() || '';
            let html = template.replaceAll('__INDEX__', 0);
            $('#po-items-body').empty().append(html);
            rowIndex = 1;
            updateRowNumbers();
            scheduleCalculatePoTotals();
            setTimeout(function () {
                $('#po-items-body tr:first .po-item-code').focus();
            }, 60);
        });

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });
    });
</script>
@endpush
