@php
    $po = $purchaseOrder ?? null;
    $indent = $indent ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($initialItems ?? ($po?->items ?? collect()));
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
        $selectedBranch = $po->branch_id ?? ($indent->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: ($branches->keys()->first() ?? 3))));
    @endphp
    <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Branch" data-default-order="2" data-core="1">
        <label class="font-weight-bold">Active Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Top Navbar</span></label>
        <div class="input-group">
            <input type="text" class="form-control font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedBranch] ?? 'Active Branch' }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
            <div class="input-group-append">
                <span class="input-group-text bg-light text-primary" title="Branch is selected globally from top navbar"><i class="fas fa-lock"></i></span>
            </div>
        </div>
    </div>

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
    <span class="badge badge-info px-3 py-2"><i class="fas fa-info-circle mr-1"></i> Click Code/Barcode to open Item Search</span>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="po-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width:35px" class="text-center">#</th>
                <th style="width:120px">Code / Barcode</th>
                <th style="min-width:220px">Item Description</th>
                <th style="width:85px" class="text-right">Stock</th>
                <th style="width:90px" class="text-right">Qty</th>
                <th style="width:90px" class="text-right">Free</th>
                <th style="width:105px" class="text-right">Cost Price</th>
                <th style="width:105px" class="text-right">Sell Price</th>
                <th style="width:100px" class="text-right">MRP</th>
                <th style="width:80px" class="text-right">Disc %</th>
                <th style="width:100px" class="text-right">Disc Amt</th>
                <th style="width:80px" class="text-right">GST%</th>
                <th style="width:115px" class="text-right font-weight-bold text-success">Net Amount</th>
                <th style="width:40px"></th>
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
                <td class="text-right align-middle text-primary" id="po-footer-qty">0</td>
                <td class="text-right align-middle text-muted" id="po-footer-free">0</td>
                <td colspan="4"></td>
                <td class="text-right align-middle text-danger" id="po-footer-disc">0.00</td>
                <td></td>
                <td class="text-right align-middle text-success h6 mb-0" id="po-footer-net">0.00</td>
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
                <small class="text-muted d-block">Total Discount</small>
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
<h5 class="mb-3"><i class="fas fa-calculator mr-1 text-primary"></i> Totals</h5>
<x-field name="freight" label="Freight" type="number" step="0.01" :value="$po->freight ?? 0" />
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$po->round_off ?? 0" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="$po->scheme_item_disc_amt ?? 0" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="$po->other_disc_amt ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$po->total_extra_cess ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$po->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$po->remarks ?? ($indent ? 'Requisition from Indent #' . $indent->indent_number . ($indent->remarks ? ' - ' . $indent->remarks : '') : '')" />
<x-textarea name="message" label="Message" :value="$po->message ?? ''" />

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

            let codeVal = data.item_code || data.code || data.ean_upc_code || '';
            $row.find('.po-item-code').val(codeVal);
            $row.find('.po-item-desc').val(data.name + (codeVal ? ' [' + codeVal + ']' : ''));
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
                code: $tr.data('code'),
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

        // Open modal on Code/Barcode field click or focus
        $(document).off('click focus', '.po-item-code').on('click focus', '.po-item-code', function (e) {
            if (islModalOpen || islModalClosing) return;
            let $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.po-item-select').val()) return;
            activeSearchRow = $row;
            let prefill = $.trim($(this).val());
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
        });

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

            if (!poItemSelectedInModal && poCancellingRow && poCancellingRow.length) {
                let totalRows = $('#po-items-body tr').length;
                if (totalRows > 1) {
                    poCancellingRow.remove();
                    updateRowNumbers();
                    calculatePoTotals();
                } else {
                    poCancellingRow.find('.po-item-code').val('');
                    poCancellingRow.find('.po-item-desc').val('');
                }
                poCancellingRow = null;
                activeSearchRow = null;
                pendingFocusQtyRow = null;
                setTimeout(function () {
                    let $target = $('#po-add-row, #freight, button[type=submit]');
                    $target.first().focus();
                }, 60);
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

        // Barcode / Code direct typing and Enter/Blur
        $(document).on('change blur keydown', '.po-item-code', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            if (e.type === 'keydown' && e.key === 'Enter') e.preventDefault();

            let $input = $(this);
            let $row = $input.closest('tr');
            let query = $.trim($input.val());
            if (!query) return;

            let currentId = $row.find('.po-item-select').val();
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || '';

            $.getJSON(LOOKUP_URL, { query: query, branch_id: branchId }, function (data) {
                if (data && data.id) {
                    populatePoRow($row, data);
                    setTimeout(function () {
                        $row.find('.po-qty').focus().select();
                    }, 50);
                }
            });
        });

        /* ================================================================
           CALCULATIONS & ROW EVENTS
           ================================================================ */
        function calculatePoRow($row) {
            let qty      = parseFloat($row.find('.po-qty').val()) || 0;
            let cost     = parseFloat($row.find('.po-cost').val()) || 0;
            let discPct  = parseFloat($row.find('.po-disc-percent').val()) || 0;
            let discAmt  = parseFloat($row.find('.po-disc-amount').val()) || 0;
            let gstPct   = parseFloat($row.find('.po-gst').val()) || 0;

            let base = qty * cost;

            // Sync disc percent <-> disc amount
            if (discPct > 0 && base > 0) {
                discAmt = Math.round((base * discPct / 100) * 100) / 100;
                $row.find('.po-disc-amount').val(discAmt > 0 ? discAmt.toFixed(2) : '');
            } else if (discAmt > 0 && base > 0 && discPct <= 0) {
                discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                $row.find('.po-disc-percent').val(discPct > 0 ? discPct.toFixed(2) : '');
            }

            // Net = (qty × cost) - disc + GST
            let afterDisc = Math.max(0, base - discAmt);
            let gstAmt    = Math.round((afterDisc * gstPct / 100) * 100) / 100;
            let net       = afterDisc + gstAmt;

            $row.find('.po-row-net').text(net.toFixed(2));

            calculatePoTotals();
            return { qty, base, discAmt, net };
        }

        function calculatePoTotals() {
            let totalQty  = 0;
            let totalFree = 0;
            let totalDisc = 0;
            let totalNet  = 0;

            $('#po-items-body tr').each(function () {
                let qty  = parseFloat($(this).find('.po-qty').val()) || 0;
                let free = parseFloat($(this).find('.po-free-qty').val()) || 0;
                let cost = parseFloat($(this).find('.po-cost').val()) || 0;
                let discAmt = parseFloat($(this).find('.po-disc-amount').val()) || 0;
                let gstPct  = parseFloat($(this).find('.po-gst').val()) || 0;

                let base = qty * cost;
                let afterDisc = Math.max(0, base - discAmt);
                let gstAmt    = Math.round((afterDisc * gstPct / 100) * 100) / 100;
                let net       = afterDisc + gstAmt;

                // Update row net display
                $(this).find('.po-row-net').text(net.toFixed(2));

                totalQty  += qty;
                totalFree += free;
                totalDisc += discAmt;
                totalNet  += net;
            });

            // Footer row
            $('#po-footer-qty').text(totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3));
            $('#po-footer-free').text(totalFree % 1 === 0 ? totalFree : totalFree.toFixed(3));
            $('#po-footer-disc').text(totalDisc.toFixed(2));
            $('#po-footer-net').text(totalNet.toFixed(2));

            // Summary card
            let freight    = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff   = parseFloat($('input[name="round_off"]').val()) || 0;
            let extraCess  = parseFloat($('input[name="total_extra_cess"]').val()) || 0;
            let grandTotal = totalNet + freight + roundOff + extraCess;

            $('#po-summary-qty').text(totalQty % 1 === 0 ? totalQty : totalQty.toFixed(3));
            $('#po-summary-disc').text('₹' + totalDisc.toFixed(2));
            $('#po-summary-items').text('₹' + totalNet.toFixed(2));
            $('#po-summary-grand').text('₹' + grandTotal.toFixed(2));
        }

        $(document).on('input', '.po-qty, .po-free-qty, .po-cost, .po-disc-percent, .po-disc-amount, .po-gst', function () {
            calculatePoRow($(this).closest('tr'));
        });

        // Recalculate grand total when freight/cess/roundoff changes
        $(document).on('input', 'input[name="freight"], input[name="round_off"], input[name="total_extra_cess"]', function () {
            calculatePoTotals();
        });

        // Initial calculation on page load (for edit forms with existing items)
        calculatePoTotals();


        function addPoRowAndOpenSearchModal() {
            let html = $('#po-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#po-items-body');
            let $newRow = $(html);
            $tbody.append($newRow);
            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
            setTimeout(function () {
                $newRow.find('.po-item-code').focus().trigger('click');
            }, 60);
        }

        // Last columns: pressing Tab or Enter advances to next row or adds a new row and opens search modal
        $(document).on('keydown', '.po-gst, .po-disc-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($(this).hasClass('po-gst') || e.key === 'Enter') {
                    if (!$nextRow.length) {
                        e.preventDefault();
                        addPoRowAndOpenSearchModal();
                    }
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

        // Submit Loader
        $('form').on('submit', function () {
            let $btn = $(this).find('button[type="submit"]');
            if ($btn.length) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            }
        });

        // Initial setup
        updateRowNumbers();
        $('form input').attr('autocomplete', 'off');

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
