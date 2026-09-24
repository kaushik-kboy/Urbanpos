@php
    $transfer = $stockTransfer ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($transfer?->items ?? collect());
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
        <h6 class="font-weight-bold text-dark mb-0 mr-2"><i class="fas fa-dolly-flatbed text-primary mr-1"></i> Transfer Details</h6>
        <span class="badge badge-primary p-1 mr-1">F2: Search</span>
        <span class="badge badge-success p-1">F6: Dispatch</span>
    </div>
    <x-form-layout-customizer
        form-key="stock_transfers.header"
        container-id="st-header-fields-grid"
        title="Customize Stock Transfer Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="st-header-fields-grid">
    @php
        $selectedFromBranch = old('from_branch_id', $transfer->from_branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
    @endphp
    <div class="field-wrapper col-md-4" data-field="from_branch_id" data-label="From Branch" data-default-order="1" data-core="1">
        <label for="from_branch_id" class="font-weight-bold">From Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Top Navbar</span></label>
        <div class="input-group">
            <input type="text" class="form-control font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedFromBranch] ?? 'Active Branch' }}">
            <input type="hidden" name="from_branch_id" id="from_branch_id" value="{{ $selectedFromBranch }}">
            <div class="input-group-append">
                <span class="input-group-text bg-light text-primary" title="Branch is selected globally from top navbar"><i class="fas fa-lock"></i></span>
            </div>
        </div>
    </div>
    <div class="field-wrapper col-md-4" data-field="to_branch_id" data-label="To Branch" data-default-order="2" data-core="1">
        <label for="to_branch_id" class="font-weight-bold">To Branch <span class="text-danger">*</span></label>
        <select name="to_branch_id" id="to_branch_id" class="form-control select2" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('to_branch_id', $transfer->to_branch_id ?? '') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="field-wrapper col-md-4" data-field="transfer_date" data-label="Transfer Date" data-default-order="3" data-core="1">
        <label for="transfer_date" class="font-weight-bold">Transfer Date <span class="text-danger">*</span></label>
        <input type="text" name="transfer_date" id="transfer_date" class="form-control datepicker font-weight-bold" value="{{ old('transfer_date', optional($transfer->transfer_date ?? now())->format('d/m/Y')) }}" placeholder="DD/MM/YYYY (e.g. 10012026)" data-date-format="d/m/Y" required autocomplete="off">
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
        @php
            $stItemColumns = [
                'seq'       => ['label' => 'S.No', 'default' => true],
                'code'      => ['label' => 'Code / Barcode', 'default' => true],
                'item'      => ['label' => 'Item Description', 'default' => true],
                'expiry'    => ['label' => 'Exp Dt', 'default' => true],
                'available' => ['label' => 'Available', 'default' => true],
                'qty'       => ['label' => 'Qty', 'default' => true],
                'actions'   => ['label' => 'Actions', 'default' => true],
            ];
        @endphp
        <div class="d-flex align-items-center">
            <x-table-column-customizer
                table-key="inventory.stock-transfers.items"
                table-id="items-table"
                :columns="$stItemColumns"
            />
            <button type="button" id="btn-quick-item-search" class="btn btn-outline-info btn-xs px-2 mx-1" title="Open Item Search Modal (F2)">
                <i class="fas fa-search mr-1"></i> Search Item (F2)
            </button>
            <button type="button" id="add-row" class="btn btn-primary btn-xs px-2">
                <i class="fas fa-plus mr-1"></i> Add Row
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 520px; overflow-x: auto; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0 table-items-dense" id="items-table" style="min-width: 900px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;" data-col-key="seq">S.No</th>
                        <th style="width: 155px;" data-col-key="code">Code / Barcode</th>
                        <th style="width: 270px;" data-col-key="item">Item Description</th>
                        <th style="width: 135px;" data-col-key="expiry">Exp Dt</th>
                        <th style="width: 100px;" data-col-key="available">Available</th>
                        <th style="width: 90px;" data-col-key="qty">Qty</th>
                        <th style="width: 45px;" data-col-key="actions"></th>
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

<x-custom-fields-renderer :module="'StockTransfer'" :model="$transfer ?? null" :cardStyle="true" />

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
        let stModalClosing = false;

        function currentFromBranch() {
            return $('#from_branch_id').val() || '';
        }

        function formatToDisplayDate(val) {
            if (!val) return '';
            val = String(val).trim();
            if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(val)) return val;
            if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
                let parts = val.substring(0, 10).split('-');
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            if (typeof window.parseFastDate === 'function') {
                let p = window.parseFastDate(val, 'DD/MM/YYYY');
                if (p) return p;
            }
            return val;
        }

        function isExpiredDate(val) {
            if (!val) return false;
            let d = null;
            val = String(val).trim();
            if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
                let parts = val.substring(0, 10).split('-');
                d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            } else if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(val)) {
                let parts = val.split('/');
                d = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
            } else if (/^\d{4}-\d{2}$/.test(val)) {
                let parts = val.split('-');
                let y = parseInt(parts[0], 10);
                let m = parseInt(parts[1], 10);
                d = new Date(y, m, 0, 23, 59, 59);
            } else if (/^\d{1,2}\/\d{4}$/.test(val)) {
                let parts = val.split('/');
                let m = parseInt(parts[0], 10);
                let y = parseInt(parts[1], 10);
                d = new Date(y, m, 0, 23, 59, 59);
            } else if (/^\d{8}$/.test(val)) {
                let dNum = parseInt(val.substring(0, 2), 10);
                let mNum = parseInt(val.substring(2, 4), 10);
                let yNum = parseInt(val.substring(4, 8), 10);
                d = new Date(yNum, mNum - 1, dNum);
            }
            if (d && !isNaN(d.getTime())) {
                let today = new Date();
                today.setHours(0, 0, 0, 0);
                return d < today;
            }
            return false;
        }

        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return false;

            if (item.exp_date && isExpiredDate(item.exp_date)) {
                let formattedExp = formatToDisplayDate(item.exp_date);
                alert('Expiry Validation Error:\n\nProduct "' + (item.name || 'Selected Item') + '" has expired on ' + formattedExp + '!\nTransfer of expired products is not permitted.');
                return false;
            }

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
                let formattedExp = formatToDisplayDate(item.exp_date);
                $row.find('.item-exp-date, input[name*="[exp_date]"]').val(formattedExp);
            } else {
                $row.find('.item-exp-date, input[name*="[exp_date]"]').val('');
            }

            const $qty = $row.find('.item-qty');
            if (!$qty.val() || parseFloat($qty.val()) <= 0) {
                $qty.val(1);
            }

            recalcTotals();
            setTimeout(function() {
                $qty.focus().select();
            }, 80);
            return true;
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
                let isExpired = it.exp_date && isExpiredDate(it.exp_date);
                let expBadge = it.exp_date
                    ? (isExpired
                        ? `<span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>EXPIRED (${formatToDisplayDate(it.exp_date)})</span>`
                        : `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${formatToDisplayDate(it.exp_date)}</span>`)
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                
                let qtyAvailable = parseFloat(it.available_qty !== undefined ? it.available_qty : (it.qty || 0));
                let isOutOfStock = qtyAvailable <= 0;
                let isBlocked = isOutOfStock || isExpired;
                let qtyClass = isOutOfStock ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                let rowClass = isBlocked ? 'st-isl-item-row st-isl-item-disabled text-muted bg-light' : 'st-isl-item-row';
                let rowStyle = isBlocked ? 'cursor: not-allowed; opacity: 0.65;' : 'cursor: pointer;';
                let actionBtn = isExpired
                    ? `<button type="button" class="btn btn-danger btn-xs px-2" disabled title="Product is Expired - Cannot transfer">
                        <i class="fas fa-ban mr-1"></i>Expired
                       </button>`
                    : (isOutOfStock
                        ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock - Cannot select">
                        <i class="fas fa-ban mr-1"></i>Out of Stock
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 st-isl-btn-select">
                        <i class="fas fa-check mr-1"></i>Select
                       </button>`);

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

            stIslSelectedIdx = $tbody.find('tr.st-isl-item-row').not('.st-isl-item-disabled').length > 0 ? 0 : -1;
            updateStModalHighlight();
        }

        let stIslSelectedIdx = -1;
        function updateStModalHighlight() {
            let $rows = $('#st-isl-items-body tr.st-isl-item-row').not('.st-isl-item-disabled');
            $('#st-isl-items-body tr').removeClass('table-primary');
            if (stIslSelectedIdx >= 0 && stIslSelectedIdx < $rows.length) {
                let $target = $rows.eq(stIslSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#st-isl-table-wrap')[0];
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

        $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#st-isl-items-body tr.st-isl-item-row').not('.st-isl-item-disabled');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                stIslSelectedIdx = Math.min(stIslSelectedIdx + 1, $rows.length - 1);
                updateStModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                stIslSelectedIdx = Math.max(stIslSelectedIdx - 1, 0);
                updateStModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (stIslSelectedIdx >= 0 && stIslSelectedIdx < $rows.length) {
                    $rows.eq(stIslSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        // Last column (.item-qty): pressing Tab or Enter advances to next row or adds a new row and opens search modal
        $(document).on('keydown', '.item-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr.item-row');
                if (!$nextRow.length) {
                    e.preventDefault();
                    $('#add-row').trigger('click');
                    let $newRow = $('#items-table tbody tr.item-row').last();
                    setTimeout(function () {
                        $newRow.find('.item-code-input').focus().trigger('click');
                    }, 60);
                }
            }
        });

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

        let stCancellingRow = null;
        let stItemSelectedInModal = false;
        let stLastSelectedRow = null;

        // Clicking row or Select button picks item (unless out of stock or expired)
        $(document).on('click', '.st-isl-item-row, .st-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('st-isl-item-row') ? $(this) : $(this).closest('tr');
            let item = $row.data('item');
            if (!item) return false;

            if (item.exp_date && isExpiredDate(item.exp_date)) {
                alert('Expiry Validation Error:\n\nProduct "' + (item.name || 'Selected Item') + '" has expired on ' + formatToDisplayDate(item.exp_date) + '!\nTransfer of expired products is not permitted.');
                return false;
            }

            if ($row.hasClass('st-isl-item-disabled')) {
                return false;
            }
            let avail = parseFloat(item.available_qty !== undefined ? item.available_qty : (item.qty || 0));
            if (avail <= 0) {
                alert('Product "' + (item.name || 'Selected Item') + '" has 0 available stock in this branch.');
                return false;
            }
            if (item && activeTargetRow) {
                if (applyItemToRow(activeTargetRow, item) === false) {
                    return false;
                }
                stItemSelectedInModal = true;
                stCancellingRow = null;
                stLastSelectedRow = activeTargetRow;
                $('#st-item-search-modal').modal('hide');
            }
        });

        $('#st-item-search-modal').on('show.bs.modal', function() {
            stModalOpen = true;
            stModalClosing = false;
            stItemSelectedInModal = false;
            stCancellingRow = null;
        });

        $('#st-item-search-modal').on('hide.bs.modal', function() {
            stModalOpen = false;
            stModalClosing = true;
            if (!stItemSelectedInModal && activeTargetRow && activeTargetRow.length) {
                let selectedId = activeTargetRow.find('.item-id-input').val();
                if (!selectedId) {
                    stCancellingRow = activeTargetRow;
                }
            }
        });

        $('#st-item-search-modal').on('hidden.bs.modal', function() {
            stModalOpen = false;
            stModalClosing = true;
            setTimeout(function() { stModalClosing = false; }, 350);

            if (!stItemSelectedInModal && stCancellingRow && stCancellingRow.length) {
                let totalRows = $('#items-table tbody tr.item-row').length;
                if (totalRows > 1) {
                    stCancellingRow.remove();
                    renumberRows();
                    recalcTotals();
                } else {
                    stCancellingRow.find('.item-code-input').val('');
                    stCancellingRow.find('.item-desc-input').val('');
                }
                stCancellingRow = null;
                activeTargetRow = null;
                setTimeout(function () {
                    let $target = $('#remarks, button[type=submit], #add-row');
                    $target.first().focus();
                }, 60);
                return;
            }

            if (stItemSelectedInModal && stLastSelectedRow && stLastSelectedRow.length) {
                let $targetRow = stLastSelectedRow;
                stLastSelectedRow = null;
                setTimeout(function () {
                    $targetRow.find('.item-qty').focus().select();
                }, 60);
            }

            stItemSelectedInModal = false;
            stCancellingRow = null;
            activeTargetRow = null;
        });

        // Trigger item search modal on keydown (Enter / F2) or focus when blank; disabled on mouse click
        $(document).off('click focus keydown', '.item-code-input').on('click focus keydown', '.item-code-input', function (e) {
            if (e.type === 'click') return; // Do not open on mouse click!
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== 'F2') return;
            if (e.type === 'keydown') e.preventDefault();
            if (stModalOpen || stModalClosing) return;
            let $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.item-id-input').val()) return;
            openItemModal($row, $(this).val());
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
                $row.find('.item-exp-date').val('');
            });
        }

        // Initialize Select2 on any pre-rendered item rows
        $('#items-body tr.item-row').each(function () {
            initRowSelect2($(this));
        });

        // Add Row Handler
        function addNewRow() {
            let tpl = document.getElementById('row-template');
            if (!tpl) return null;
            let html = tpl.innerHTML.replace(/__INDEX__/g, rowIndex);
            let $newRow = $(html);
            $('#items-body').append($newRow);
            rowIndex++;
            reindexSno();
            initRowSelect2($newRow);
            return $newRow;
        }

        $('#add-row').on('click', function (e) {
            e.preventDefault();
            let $newRow = addNewRow();
            if ($newRow) {
                setTimeout(function () {
                    $newRow.find('.item-code-input').focus();
                }, 50);
            }
        });

        // Row Remove Handler
        $('#items-body').on('click', '.row-remove', function (e) {
            e.preventDefault();
            if ($('#items-body tr.item-row').length > 1) {
                $(this).closest('tr.item-row').remove();
                reindexSno();
                recalcTotals();
            } else {
                let $row = $(this).closest('tr.item-row');
                $row.find('.item-code-input').val('');
                $row.find('.item-select').val(null).trigger('change');
                $row.find('.item-exp-date').val('');
                $row.find('.item-available').val('0.000');
                $row.find('.item-qty').val('');
                recalcTotals();
            }
        });

        // Barcode / Code input: pressing Enter directly fetches item or opens modal
        $(document).on('keydown', '.item-code-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let code = $(this).val().trim();
                let $row = $(this).closest('tr');
                if (!code) {
                    openItemModal($row, '');
                    return;
                }
                $.getJSON(itemByCodeUrl, { code: code, branch_id: currentFromBranch() }, function (res) {
                    if (res && res.found && res.item) {
                        if (res.item.exp_date && isExpiredDate(res.item.exp_date)) {
                            alert('Expiry Validation Error:\n\nProduct "' + res.item.name + '" has expired on ' + formatToDisplayDate(res.item.exp_date) + '!\nTransfer of expired products is not permitted.');
                            return;
                        }
                        applyItemToRow($row, res.item);
                    } else if (res && res.error) {
                        alert('Stock Transfer Error:\n\n' + res.error);
                    } else {
                        openItemModal($row, code);
                    }
                }).fail(function () {
                    openItemModal($row, code);
                });
            }
        });

        // Expiry Date input: auto-format to DD/MM/YYYY on blur or Enter/Tab, and validate not expired
        $(document).on('change blur', '.item-exp-date', function () {
            let val = $(this).val();
            if (val) {
                let formatted = formatToDisplayDate(val);
                $(this).val(formatted);
                if (isExpiredDate(formatted)) {
                    $(this).addClass('is-invalid');
                    alert('Expiry Validation Error:\n\nExpired date (' + formatted + ') entered! Transfer of expired products is not allowed.');
                    $(this).val('').focus();
                } else {
                    $(this).removeClass('is-invalid');
                }
            }
        });
        $(document).on('keydown', '.item-exp-date', function (e) {
            if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
                let val = $(this).val();
                if (val) {
                    let formatted = formatToDisplayDate(val);
                    $(this).val(formatted);
                    if (isExpiredDate(formatted)) {
                        $(this).addClass('is-invalid');
                        alert('Expiry Validation Error:\n\nExpired date (' + formatted + ') entered! Transfer of expired products is not allowed.');
                        $(this).val('').focus();
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                }
            }
        });

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
                $(this).attr('data-row', idx);
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

        function validateBranchSelection() {
            let fromBranch = $('#from_branch_id').val();
            let toBranch = $('#to_branch_id').val();
            let $toContainer = $('#to_branch_id').next('.select2-container').find('.select2-selection');

            if (fromBranch && toBranch && fromBranch === toBranch) {
                $toContainer.addClass('border-danger');
                alert('Source (From) Branch and Destination (To) Branch cannot be the same!');
                return false;
            } else {
                $toContainer.removeClass('border-danger');
                return true;
            }
        }

        $('#to_branch_id').on('change', function () {
            validateBranchSelection();
        });

        function validateStQty($input, showAlert = true) {
            let $row = $input.closest('tr');
            let itemId = $row.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
            if (!itemId) return true;

            let q = parseFloat($input.val()) || 0;
            let avail = parseFloat($row.find('.item-available').val()) || 0;
            let itemName = $row.find('.item-desc-input, .item-select option:selected').text() || 'Selected Item';

            // Check total across rows for same item
            let totalForItem = 0;
            $('#items-body tr.item-row').each(function () {
                let rId = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                if (rId == itemId) {
                    totalForItem += parseFloat($(this).find('.item-qty').val()) || 0;
                }
            });

            if (avail >= 0 && totalForItem > avail) {
                $input.addClass('is-invalid border-danger text-danger');
                if (showAlert) {
                    alert('Stock is only ' + avail.toFixed(3) + ' for ' + itemName.trim() + '.\nTransfer quantity (' + totalForItem.toFixed(3) + ') cannot exceed available stock!');
                    setTimeout(function () { $input.focus().select(); }, 10);
                }
                return false;
            } else if (q <= 0) {
                $input.addClass('is-invalid border-danger text-danger');
                if (showAlert) {
                    alert('Quantity must be greater than 0.');
                    setTimeout(function () { $input.focus().select(); }, 10);
                }
                return false;
            } else {
                $input.removeClass('is-invalid border-danger text-danger');
                return true;
            }
        }

        $('#items-body').on('input', '.item-qty', function () {
            let q = parseFloat($(this).val()) || 0;
            let avail = parseFloat($(this).closest('tr').find('.item-available').val()) || 0;
            let itemId = $(this).closest('tr').find('.item-select, .item-id-input, select[name*="[item_id]"]').val();

            if (itemId) {
                if (q <= 0) {
                    $(this).addClass('is-invalid border-danger text-danger').attr('title', 'Quantity must be greater than 0');
                } else if (avail >= 0 && q > avail) {
                    $(this).addClass('is-invalid border-danger text-danger').attr('title', `Quantity (${q}) exceeds available stock (${avail})`);
                } else {
                    $(this).removeClass('is-invalid border-danger text-danger').attr('title', '');
                }
            }
            recalcTotals();
        });

        // Block Tab or Enter if quantity exceeds available stock
        $(document).on('keydown', '.item-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                if (!validateStQty($(this), true)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let $nextRow = $(this).closest('tr.item-row').next('tr.item-row');
                    if ($nextRow.length) {
                        $nextRow.find('.item-code-input').focus();
                    } else {
                        $('#add-row').trigger('click');
                    }
                }
            }
        });

        $(document).on('change', '.item-qty', function () {
            validateStQty($(this), true);
        });

        // Form Submit Handler
        $('form').on('submit', function (e) {
            let toBranch = $('#to_branch_id').val();
            if (!toBranch) {
                e.preventDefault();
                alert('Please select a destination (To) branch.');
                $('#to_branch_id').select2('open');
                return false;
            }

            if (!validateBranchSelection()) {
                e.preventDefault();
                return false;
            }

            // Remove any trailing completely empty rows if there is more than 1 row
            $('#items-body tr.item-row').each(function () {
                let id = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                if (!id && $('#items-body tr.item-row').length > 1) {
                    $(this).remove();
                }
            });
            reindexSno();

            let hasError = false;
            let validCount = 0;

            $('#items-body tr.item-row').each(function (idx) {
                let $row = $(this);
                let id = $row.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                let $q = $row.find('.item-qty');
                let q = parseFloat($q.val()) || 0;
                let avail = parseFloat($row.find('.item-available').val()) || 0;

                if (id) {
                    if (q <= 0) {
                        $q.addClass('is-invalid border-danger text-danger');
                        alert(`Row #${idx + 1}: Quantity must be greater than 0.`);
                        $q.focus();
                        hasError = true;
                        return false;
                    }
                    if (avail >= 0 && q > avail) {
                        $q.addClass('is-invalid border-danger text-danger');
                        alert(`Row #${idx + 1}: Transfer quantity (${q}) exceeds available stock (${avail}).`);
                        $q.focus();
                        hasError = true;
                        return false;
                    }

                    // Strict Expiry check on submit
                    let exp = $row.find('.item-exp-date').val();
                    if (exp && isExpiredDate(exp)) {
                        $row.find('.item-exp-date').addClass('is-invalid border-danger');
                        alert(`Row #${idx + 1}: Cannot transfer expired item (Expiry: ${exp})!`);
                        $row.find('.item-exp-date').focus();
                        hasError = true;
                        return false;
                    }

                    validCount++;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            if (validCount === 0) {
                e.preventDefault();
                alert('Please add at least one valid item with quantity > 0.');
                return false;
            }
        });

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
                if (form) $(form).trigger('submit');
            }
        });
    })();
</script>
@endpush
