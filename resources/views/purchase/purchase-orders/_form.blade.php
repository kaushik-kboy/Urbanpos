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

<h5 class="mb-3"><i class="fas fa-file-invoice mr-1 text-primary"></i> Header</h5>
<x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$po->supplier_id ?? ''" placeholder="Select a supplier" required />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$po->branch_id ?? ($indent->branch_id ?? '')" placeholder="Select a branch" required />
<x-field name="po_date" label="PO Date" type="date" :value="optional($po->po_date ?? now())->format('Y-m-d')" required />
<x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$po->purchase_type ?? 'Local'" required />
<x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$po->c_form ?? 'Against C-Form'" required />
<x-select name="status" label="Status" :options="['Open' => 'Open', 'Closed' => 'Closed']" :selected="$po->status ?? 'Open'" required />
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
                <th style="width:100px" class="text-right">Disc Amount</th>
                <th style="width:80px" class="text-right">GST%</th>
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
    </table>
</div>

<button type="button" id="po-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

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
        let islSelectedIdx = -1;
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';
        const LOOKUP_URL = '{{ route("purchase.purchase-invoices.lookup-item") }}';

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
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
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
                                data-id="${it.id}" data-code="${it.code}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#po-isl-table-wrap').removeClass('d-none');
            $('#po-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
            islSelectedIdx = items.length > 0 ? 0 : -1;
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
            $row.find('.po-qty').focus();
        }

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
                gst_percent: $tr.data('gst')
            };

            $('#po-item-search-modal').modal('hide');

            if (!activeSearchRow || !itemData.id) return;
            populatePoRow(activeSearchRow, itemData);
            activeSearchRow = null;
        });

        // Open modal on Code/Barcode field click or focus
        $(document).on('click focus', '.po-item-code', function (e) {
            if (islModalOpen) return;
            activeSearchRow = $(this).closest('tr');
            let prefill = $.trim($(this).val());
            $('#po-isl-filter-name').val(prefill);
            $('#po-isl-filter-code').val('');
            $('#po-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#po-item-search-modal').modal('show');
            $('#po-item-search-modal').one('shown.bs.modal', function () {
                $('#po-isl-filter-name').focus();
                if (prefill) fetchItemList();
            });
        });

        $('#po-item-search-modal').on('show.bs.modal', function () { islModalOpen = true; });
        $('#po-item-search-modal').on('hidden.bs.modal', function () {
            islModalOpen = false;
            setTimeout(function () { islModalOpen = false; }, 300);
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
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;

            $.getJSON(LOOKUP_URL, { query: query, branch_id: branchId }, function (data) {
                if (data && data.id) {
                    populatePoRow($row, data);
                }
            });
        });

        /* ================================================================
           CALCULATIONS & ROW EVENTS
           ================================================================ */
        function calculatePoRow($row) {
            let qty = parseFloat($row.find('.po-qty').val()) || 0;
            let cost = parseFloat($row.find('.po-cost').val()) || 0;
            let discPct = parseFloat($row.find('.po-disc-percent').val()) || 0;
            let discAmt = parseFloat($row.find('.po-disc-amount').val()) || 0;

            let base = qty * cost;
            if (discPct > 0 && base > 0) {
                discAmt = Math.round((base * discPct / 100) * 100) / 100;
                $row.find('.po-disc-amount').val(discAmt > 0 ? discAmt.toFixed(2) : '');
            } else if (discAmt > 0 && base > 0) {
                discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                $row.find('.po-disc-percent').val(discPct > 0 ? discPct.toFixed(2) : '');
            }
        }

        $(document).on('input', '.po-qty, .po-cost, .po-disc-percent, .po-disc-amount', function () {
            calculatePoRow($(this).closest('tr'));
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
