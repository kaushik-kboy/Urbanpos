@php
    $transfer = $stockTransfer ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($transfer?->items ?? collect());
@endphp

<div class="row mb-3">
    <div class="col-md-3">
        <label for="from_branch_id" class="font-weight-bold">From Branch <span class="text-danger">*</span></label>
        <select name="from_branch_id" id="from_branch_id" class="form-control select2" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('from_branch_id', $transfer->from_branch_id ?? '') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="to_branch_id" class="font-weight-bold">To Branch <span class="text-danger">*</span></label>
        <select name="to_branch_id" id="to_branch_id" class="form-control select2" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('to_branch_id', $transfer->to_branch_id ?? '') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="transfer_date" class="font-weight-bold">Transfer Date <span class="text-danger">*</span></label>
        <input type="date" name="transfer_date" id="transfer_date" class="form-control" value="{{ old('transfer_date', optional($transfer->transfer_date ?? now())->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 d-flex align-items-end justify-content-end">
        <div class="text-muted small text-right">
            <span class="badge badge-primary p-1 mr-1">F2: Search Popup</span>
            <span class="badge badge-success p-1">F6: Dispatch</span>
        </div>
    </div>
</div>

<div id="from-branch-warning" class="alert alert-warning py-2 d-none">
    <i class="fas fa-exclamation-triangle mr-1"></i> Please select a "From Branch" first so item stock availability can be verified.
</div>

<div class="card card-outline card-secondary mb-3 shadow-none border">
    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-dolly-flatbed mr-1"></i> Transfer Items
        </h6>
        <div>
            <button type="button" id="btn-quick-item-search" class="btn btn-outline-info btn-xs px-2 mr-1" title="Open Item Search Modal (F2)">
                <i class="fas fa-search mr-1"></i> Search Item (F2)
            </button>
            <button type="button" id="add-row" class="btn btn-primary btn-xs px-2">
                <i class="fas fa-plus mr-1"></i> Add Row
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 520px; overflow-x: auto; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0" id="items-table" style="min-width: 900px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;">S.No</th>
                        <th style="width: 155px;">Code</th>
                        <th style="width: 270px;">Description</th>
                        <th style="width: 135px;">Exp Dt</th>
                        <th style="width: 100px;">Available</th>
                        <th style="width: 90px;">Qty</th>
                        <th style="width: 45px;"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @forelse ($existingItems as $index => $line)
                        @include('inventory.stock-transfers._item-row', ['index' => $index, 'line' => $line])
                    @empty
                        @include('inventory.stock-transfers._item-row', ['index' => 0, 'line' => null])
                    @endforelse
                </tbody>
                <tfoot class="bg-light font-weight-bold" style="position: sticky; bottom: 0; z-index: 10; border-top: 2px solid #dee2e6;">
                    <tr>
                        <td colspan="4" class="text-right align-middle">Total Qty:</td>
                        <td></td>
                        <td class="text-right align-middle text-primary" id="footer-total-qty">0.000</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <label for="remarks" class="font-weight-bold text-muted small">Remarks</label>
        <textarea name="remarks" id="remarks" rows="2" class="form-control form-control-sm" placeholder="Optional remarks...">{{ old('remarks', $transfer->remarks ?? '') }}</textarea>
    </div>
</div>

<!-- ============================================================
     ITEM SEARCH MODAL — identical to Sales Bill / Purchase Invoice
     ============================================================ -->
<div class="modal fade" id="st-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="stItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow border-dark">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="stItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item for Stock Transfer
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
                            <input type="text" id="st-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="st-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="st-isl-filter-expiry" class="form-control" placeholder="Filter expiry (YYYY-MM)…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="st-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results states -->
                <div id="st-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="st-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="st-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="st-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 140px;">Code / Barcode</th>
                                <th class="text-center" style="width: 130px;">Expiry</th>
                                <th class="text-right" style="width: 120px;">Available (Stock)</th>
                                <th class="text-center" style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="st-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="st-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<template id="row-template">
    @include('inventory.stock-transfers._item-row', ['index' => '__INDEX__', 'line' => null])
</template>

@push('css')
<style>
    .select2-container .select2-selection--single { height: 31px !important; border-color: #ced4da !important; font-size: 0.875rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px !important; padding-left: 6px; padding-right: 18px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px !important; right: 3px; }
    .st-isl-item-disabled { cursor: not-allowed !important; opacity: 0.65; }
</style>
@endpush

@push('js')
<script>
    (function () {
        const searchItemsUrl = "{{ route('inventory.stock-transfers.search-items') }}";
        const itemByCodeUrl = "{{ route('inventory.stock-transfers.item-by-code') }}";
        const ISL_URL = "{{ route('inventory.stock-transfers.item-list') }}";

        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeTargetRow = null;
        let stDebounce = null;
        let stIslCache = {};
        let stIslLastKey = null;
        let stModalOpen = false;

        function currentFromBranch() {
            return $('#from_branch_id').val() || '';
        }

        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return;
            const $select = $row.find('.item-select');
            const displayCode = item.code || item.barcode || '';
            const codeStr = displayCode ? " [" + displayCode + "]" : "";
            const optionText = (item.name || item.text || 'Item') + (item.text && item.text.indexOf('[') !== -1 ? '' : codeStr);
            const option = new Option(optionText, item.id, true, true);
            $select.empty().append(option).trigger('change');

            $row.find('.item-code-input').val(displayCode);
            const avail = parseFloat(item.available_qty !== undefined ? item.available_qty : (item.qty || 0));
            $row.find('.item-available').val(avail.toFixed(3));

            if (item.exp_date) {
                $row.find('input[type="date"]').val(item.exp_date);
            }

            const $qty = $row.find('.item-qty');
            if (!$qty.val() || parseFloat($qty.val()) <= 0) {
                $qty.val(1);
            }

            recalcTotals();
            $qty.focus().select();
        }

        function showHintState(msg) {
            $('#st-isl-loading').addClass('d-none');
            $('#st-isl-table-wrap').addClass('d-none');
            $('#st-isl-items-body').empty();
            let $nr = $('#st-isl-no-results');
            $nr.removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + msg + '</p>'
            );
            $('#st-isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = currentFromBranch();
            if (!branchId) {
                showHintState('Please select "From Branch" first.');
                return;
            }

            let srch   = $('#st-isl-filter-name').val().trim();
            let code   = $('#st-isl-filter-code').val().trim();
            let expiry = $('#st-isl-filter-expiry').val().trim();

            if (!srch && !code && !expiry) {
                showHintState('Start typing item name, code or barcode to search\u2026');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;
            if (stIslCache[cacheKey]) {
                if (stIslLastKey !== cacheKey) {
                    stIslLastKey = cacheKey;
                    renderItems(stIslCache[cacheKey]);
                }
                return;
            }

            stIslLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $('#st-isl-loading').removeClass('d-none');
            $('#st-isl-no-results').addClass('d-none');
            $('#st-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#st-isl-loading').addClass('d-none');
                stIslCache[cacheKey] = res.items || [];
                setTimeout(function() { delete stIslCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#st-isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        function renderItems(items) {
            let $tbody = $('#st-isl-items-body');
            $tbody.empty();

            if (!items || items.length === 0) {
                $('#st-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#st-isl-count-label').text('');
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = it.exp_date
                    ? `<span class="badge badge-danger px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                
                let qtyAvailable = parseFloat(it.available_qty !== undefined ? it.available_qty : (it.qty || 0));
                let isOutOfStock = qtyAvailable <= 0;
                let qtyClass = isOutOfStock ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                let rowClass = isOutOfStock ? 'st-isl-item-row st-isl-item-disabled text-muted bg-light' : 'st-isl-item-row';
                let rowStyle = isOutOfStock ? 'cursor: not-allowed; opacity: 0.65;' : 'cursor: pointer;';
                let actionBtn = isOutOfStock
                    ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock - Cannot select">
                        <i class="fas fa-ban mr-1"></i>Out of Stock
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 st-isl-btn-select">
                        <i class="fas fa-check mr-1"></i>Select
                       </button>`;

                html += `
                    <tr class="${rowClass}" style="${rowStyle}"
                        data-idx="${idx}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name} ${isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : ''}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right ${qtyClass}">${qtyAvailable.toFixed(3)}</td>
                        <td class="align-middle text-center">
                            ${actionBtn}
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            $('#st-isl-table-wrap').removeClass('d-none');
            $('#st-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');

            // Attach item data to rows
            $tbody.find('tr.st-isl-item-row').each(function () {
                let idx = $(this).data('idx');
                $(this).data('item', items[idx]);
            });
        }

        function openItemModal($row, initialQuery) {
            if (!currentFromBranch()) {
                $('#from-branch-warning').removeClass('d-none');
                $('#from_branch_id').focus();
                return;
            }
            $('#from-branch-warning').addClass('d-none');

            activeTargetRow = $row;
            initialQuery = (initialQuery || '').trim();
            $('#st-isl-filter-name').val(initialQuery);
            $('#st-isl-filter-code').val('');
            $('#st-isl-filter-expiry').val('');

            fetchItemList();
            stModalOpen = true;
            $('#st-item-search-modal').modal('show');
            $('#st-item-search-modal').one('shown.bs.modal', function () {
                $('#st-isl-filter-name').focus().select();
                if (initialQuery) fetchItemList();
            });
        }

        // Live filters debounce
        $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').on('input', function () {
            clearTimeout(stDebounce);
            stDebounce = setTimeout(fetchItemList, 350);
        });

        $('#st-isl-btn-clear').on('click', function () {
            $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').val('');
            fetchItemList();
        });

        // Clicking row or Select button picks item (unless out of stock)
        $(document).on('click', '.st-isl-item-row, .st-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('st-isl-item-row') ? $(this) : $(this).closest('tr');
            if ($row.hasClass('st-isl-item-disabled')) {
                return false;
            }
            let item = $row.data('item');
            let avail = parseFloat(item ? (item.available_qty !== undefined ? item.available_qty : (item.qty || 0)) : 0);
            if (avail <= 0) {
                return false;
            }
            if (item && activeTargetRow) {
                applyItemToRow(activeTargetRow, item);
                $('#st-item-search-modal').modal('hide');
                activeTargetRow = null;
            }
        });

        $('#st-item-search-modal').on('show.bs.modal', function() { stModalOpen = true; });
        $('#st-item-search-modal').on('hidden.bs.modal', function() {
            stModalOpen = false;
            setTimeout(function() { stModalOpen = false; }, 300);
        });

        // Trigger item search modal on focus of .item-code-input
        $(document).off('focus', '.item-code-input').on('focus', '.item-code-input', function () {
            if (stModalOpen) return;
            openItemModal($(this).closest('tr'), $(this).val());
        });

        function initRowSelect2($row) {
            const $select = $row.find('.item-select');
            if ($select.hasClass('select2-hidden-accessible')) return;

            $select.select2({
                placeholder: 'Search item name or code...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 1,
                ajax: {
                    url: searchItemsUrl,
                    dataType: 'json',
                    delay: 200,
                    data: function (params) {
                        return { q: params.term, branch_id: currentFromBranch() };
                    },
                    processResults: function (data) {
                        return { results: data.map(function (item) { return { id: item.id, text: item.text, itemData: item }; }) };
                    },
                    cache: true
                }
            });

            $select.on('select2:select', function (e) {
                const data = e.params.data.itemData;
                if (!data) return;
                applyItemToRow($row, data);
            });
            $select.on('select2:clear', function () {
                $row.find('.item-code-input').val('');
                $row.find('.item-available').val('0.000');
            });
        }

        function recalcTotals() {
            let totalQty = 0;
            $('#items-body tr.item-row').each(function () {
                totalQty += parseFloat($(this).find('.item-qty').val()) || 0;
            });
            $('#footer-total-qty').text(totalQty.toFixed(3));
        }

        function reindexSno() {
            $('#items-body tr.item-row').each(function (idx) {
                $(this).find('.row-sno').text(idx + 1);
            });
        }

        $('#items-body').on('click', '.open-item-modal', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            openItemModal($row, $row.find('.item-code-input').val());
        });

        $('#btn-quick-item-search').on('click', function () {
            let $targetRow = $('#items-body tr.item-row').last();
            openItemModal($targetRow, '');
        });

        $('#items-body').on('input change', '.item-qty', recalcTotals);

        $('#add-row').on('click', function () {
            let html = document.getElementById('row-template').innerHTML;
            html = html.replaceAll('__INDEX__', rowIndex);
            html = html.replaceAll('__SNO__', $('#items-body tr.item-row').length + 1);
            const $newRow = $(html);
            $('#items-body').append($newRow);
            initRowSelect2($newRow);
            reindexSno();
            rowIndex++;
            $newRow.find('.item-code-input').focus();
        });

        $('#items-body').on('click', '.row-remove', function () {
            if ($('#items-body tr.item-row').length <= 1) {
                alert('At least one item row is required.');
                return;
            }
            $(this).closest('tr').remove();
            reindexSno();
            recalcTotals();
        });

        $('#from_branch_id').on('change', function () {
            $('#from-branch-warning').addClass('d-none');
            stIslCache = {};
            $('#items-body tr.item-row').each(function () {
                $(this).find('.item-available').val('0.000');
            });
        });

        $('#items-body tr.item-row').each(function () {
            initRowSelect2($(this));
        });
        recalcTotals();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2') {
                e.preventDefault();
                const $focusedInput = $(':focus');
                let $targetRow = $focusedInput.closest('tr.item-row');
                if (!$targetRow.length) $targetRow = $('#items-body tr.item-row').last();
                openItemModal($targetRow, $targetRow.find('.item-code-input').val());
            } else if (e.key === 'F6') {
                e.preventDefault();
                const form = document.getElementById('transfer-form');
                if (form) form.submit();
            }
        });
    })();
</script>
@endpush
