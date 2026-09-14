<div class="row mb-3">
    <div class="col-md-3">
        <label for="from_branch_id" class="font-weight-bold">From Branch <span class="text-danger">*</span></label>
        <select name="from_branch_id" id="from_branch_id" class="form-control" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('from_branch_id') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="to_branch_id" class="font-weight-bold">To Branch <span class="text-danger">*</span></label>
        <select name="to_branch_id" id="to_branch_id" class="form-control" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('to_branch_id') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="transfer_date" class="font-weight-bold">Transfer Date <span class="text-danger">*</span></label>
        <input type="date" name="transfer_date" id="transfer_date" class="form-control" value="{{ old('transfer_date', now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 d-flex align-items-end justify-content-end">
        <div class="text-muted small text-right">
            <span class="badge badge-primary p-1 mr-1">F2: Search Popup</span>
            <span class="badge badge-success p-1">F6: Dispatch</span>
        </div>
    </div>
</div>

<div id="from-branch-warning" class="alert alert-warning py-2 d-none">
    Select a "From Branch" first so item availability can be checked.
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
                    @include('inventory.stock-transfers._item-row', ['index' => 0])
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
        <textarea name="remarks" id="remarks" rows="2" class="form-control form-control-sm" placeholder="Optional remarks...">{{ old('remarks') }}</textarea>
    </div>
</div>

<!-- Item Search Modal -->
<div class="modal fade" id="item-search-modal" tabindex="-1" role="dialog" aria-labelledby="itemSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow border-primary">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold text-white" id="itemSearchModalLabel">
                    <i class="fas fa-search-plus mr-1"></i> Item Search
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-2">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-primary"></i></span>
                    </div>
                    <input type="text" id="modal-search-input" class="form-control form-control-lg font-weight-bold" placeholder="Type item name, description, or barcode to search..." autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="modal-search-clear">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small id="modal-search-status" class="text-muted">Type 1 or more characters to search...</small>
                </div>
                <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-striped mb-0" id="modal-items-table">
                        <thead class="thead-light sticky-top" style="z-index: 5;">
                            <tr class="text-center text-nowrap">
                                <th style="width: 140px;">Code</th>
                                <th class="text-left">Item Description</th>
                                <th style="width: 130px;">Brand</th>
                                <th style="width: 110px;">Available</th>
                                <th style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modal-items-body">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Type item name, description, or code above to search</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Press <kbd>Esc</kbd> to close</small>
                <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<template id="row-template">
    @include('inventory.stock-transfers._item-row', ['index' => '__INDEX__'])
</template>

@push('css')
<style>
    .select2-container .select2-selection--single { height: 31px !important; border-color: #ced4da !important; font-size: 0.85rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px !important; padding-left: 6px; padding-right: 18px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px !important; right: 3px; }
    .modal-item-result-row.table-active { background-color: #d1ecf1 !important; }
</style>
@endpush

@push('js')
<script>
    (function () {
        const searchItemsUrl = "{{ route('inventory.stock-transfers.search-items') }}";
        const itemByCodeUrl = "{{ route('inventory.stock-transfers.item-by-code') }}";
        let rowIndex = 1;
        let activeTargetRow = null;
        let searchDebounceTimer = null;

        function currentFromBranch() {
            return $('#from_branch_id').val() || '';
        }

        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return;
            const $select = $row.find('.item-select');
            const option = new Option(item.text, item.id, true, true);
            $select.empty().append(option).trigger('change');

            const displayCode = item.code || item.barcode || '';
            $row.find('.item-code-input').val(displayCode);
            $row.find('.item-available').val(parseFloat(item.available_qty || 0).toFixed(3));

            const $qty = $row.find('.item-qty');
            if (!$qty.val() || parseFloat($qty.val()) <= 0) {
                $qty.val(1);
            }

            recalcTotals();
            $qty.focus().select();
        }

        function openItemModal($row, initialQuery) {
            if (!currentFromBranch()) {
                $('#from-branch-warning').removeClass('d-none');
                $('#from_branch_id').focus();
                return;
            }
            $('#from-branch-warning').addClass('d-none');

            activeTargetRow = $row;
            const $modal = $('#item-search-modal');
            const $input = $('#modal-search-input');
            initialQuery = (initialQuery || '').trim();
            $input.val(initialQuery);
            $modal.modal('show');

            if (initialQuery.length >= 1) {
                performModalSearch(initialQuery);
            } else {
                $('#modal-items-body').html('<tr><td colspan="5" class="text-center py-4 text-muted">Type item name, description, or code above to search</td></tr>');
                $('#modal-search-status').text('Type 1 or more characters to search...');
            }
        }

        function escapeHtml(str) {
            return (str || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function performModalSearch(query) {
            query = (query || '').trim();
            if (!query) {
                $('#modal-items-body').html('<tr><td colspan="5" class="text-center py-4 text-muted">Type item name, description, or code above to search</td></tr>');
                return;
            }

            $('#modal-search-status').html('<i class="fas fa-spinner fa-spin mr-1 text-primary"></i> Searching...');
            $.ajax({
                url: searchItemsUrl,
                data: { q: query, branch_id: currentFromBranch() },
                dataType: 'json',
                success: function (results) {
                    if (!results || results.length === 0) {
                        $('#modal-search-status').html(`No items found for "<strong>${escapeHtml(query)}</strong>"`);
                        $('#modal-items-body').html('<tr><td colspan="5" class="text-center py-4 text-danger">No items found.</td></tr>');
                        return;
                    }
                    $('#modal-search-status').html(`Found <strong>${results.length}</strong> items`);
                    let rowsHtml = '';
                    results.forEach(function (item, idx) {
                        const codeDisplay = item.code || '-';
                        rowsHtml += `
                            <tr class="modal-item-result-row ${idx === 0 ? 'table-active' : ''}" style="cursor: pointer;">
                                <td class="text-nowrap font-weight-bold align-middle"><span class="badge badge-light border py-1 px-2 font-weight-normal">${escapeHtml(codeDisplay)}</span></td>
                                <td class="align-middle text-left"><span class="font-weight-bold text-dark">${escapeHtml(item.name)}</span></td>
                                <td class="text-muted small align-middle">${escapeHtml(item.brand || '-')}</td>
                                <td class="text-right font-weight-bold align-middle">${parseFloat(item.available_qty || 0).toFixed(3)}</td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-primary btn-xs px-2 btn-choose-modal-item"><i class="fas fa-check mr-1"></i> Select</button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#modal-items-body').html(rowsHtml);
                    $('#modal-items-body tr.modal-item-result-row').each(function (i) {
                        $(this).data('item', results[i]);
                    });
                },
                error: function () {
                    $('#modal-search-status').text('Error fetching items.');
                }
            });
        }

        $('#modal-search-input').on('input', function () {
            clearTimeout(searchDebounceTimer);
            const val = $(this).val();
            searchDebounceTimer = setTimeout(function () { performModalSearch(val); }, 250);
        });
        $('#modal-search-clear').on('click', function () { $('#modal-search-input').val('').focus(); performModalSearch(''); });
        $('#item-search-modal').on('shown.bs.modal', function () { $('#modal-search-input').focus().select(); });
        $('#modal-items-body').on('click', '.btn-choose-modal-item', function (e) {
            e.stopPropagation();
            const item = $(this).closest('tr').data('item');
            if (item && activeTargetRow) { applyItemToRow(activeTargetRow, item); $('#item-search-modal').modal('hide'); }
        });
        $('#modal-items-body').on('dblclick', 'tr.modal-item-result-row', function () {
            const item = $(this).data('item');
            if (item && activeTargetRow) { applyItemToRow(activeTargetRow, item); $('#item-search-modal').modal('hide'); }
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

        function lookupCode($row, code) {
            code = (code || '').trim();
            if (!code) return;
            if (!currentFromBranch()) {
                $('#from-branch-warning').removeClass('d-none');
                return;
            }
            $.ajax({
                url: itemByCodeUrl,
                data: { code: code, branch_id: currentFromBranch() },
                dataType: 'json',
                success: function (res) {
                    if (res && res.found && res.item) {
                        applyItemToRow($row, res.item);
                    } else {
                        openItemModal($row, code);
                    }
                },
                error: function () { openItemModal($row, code); }
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

        $('#items-body').on('change', '.item-code-input', function () {
            lookupCode($(this).closest('tr'), $(this).val());
        });
        $('#items-body').on('keypress', '.item-code-input', function (e) {
            if (e.which === 13) { e.preventDefault(); lookupCode($(this).closest('tr'), $(this).val()); }
        });
        $('#items-body').on('click', '.open-item-modal', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            openItemModal($row, $row.find('.item-code-input').val());
        });
        $('#btn-quick-item-search').on('click', function () {
            openItemModal($('#items-body tr.item-row').last(), '');
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
            if ($('#items-body tr.item-row').length <= 1) { alert('At least one item row is required.'); return; }
            $(this).closest('tr').remove();
            reindexSno();
            recalcTotals();
        });

        $('#from_branch_id').on('change', function () {
            $('#from-branch-warning').addClass('d-none');
            // Existing rows keep their selection but their "available" figure is now stale
            // for the newly chosen branch — clear it so the user re-checks via search/lookup.
            $('#items-body tr.item-row').each(function () {
                $(this).find('.item-available').val('0.000');
            });
        });

        $('#items-body tr.item-row').each(function () { initRowSelect2($(this)); });
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
