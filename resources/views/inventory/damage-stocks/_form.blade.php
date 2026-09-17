@php
    $entry = $damageStock ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($entry?->items ?? collect());
@endphp

<div class="row mb-3">
    <div class="col-md-4">
        <label for="branch_id" class="font-weight-bold">Location / Branch <span class="text-danger">*</span></label>
        <select name="branch_id" id="branch_id" class="form-control select2" required>
            <option value="">-- Select Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(($entry->branch_id ?? old('branch_id', 2)) == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="entry_date" class="font-weight-bold">Entry Date <span class="text-danger">*</span></label>
        <input type="date" name="entry_date" id="entry_date" class="form-control" 
               value="{{ optional($entry->entry_date ?? now())->format('Y-m-d') }}" required>
    </div>
    <div class="col-md-3">
        <label for="wastage_type" class="font-weight-bold">Wastage Type <span class="text-danger">*</span></label>
        <select name="wastage_type" id="wastage_type" class="form-control select2" required>
            <option value="Damage" @selected(($entry->wastage_type ?? old('wastage_type', 'Damage')) === 'Damage')>Damage</option>
            <option value="Wastage" @selected(($entry->wastage_type ?? old('wastage_type')) === 'Wastage')>Wastage</option>
            <option value="Theft" @selected(($entry->wastage_type ?? old('wastage_type')) === 'Theft')>Theft</option>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end justify-content-end">
        <div class="text-muted small text-right">
            <span class="badge badge-danger p-1 mr-1">F2: Search Popup</span>
            <span class="badge badge-primary p-1">F5: Add Row</span>
        </div>
    </div>
</div>

<div class="card card-outline card-danger mb-3 shadow-none border">
    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-boxes-alt mr-1 text-danger"></i> Damage Stock Items
        </h6>
        <div>
            <button type="button" id="btn-quick-item-search" class="btn btn-outline-danger btn-xs px-2 mr-1" title="Open Item Search Modal (F2)">
                <i class="fas fa-search mr-1"></i> Search Item (F2)
            </button>
            <button type="button" id="add-row" class="btn btn-danger btn-xs px-2">
                <i class="fas fa-plus mr-1"></i> Add Row
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 480px; overflow-x: auto; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0" id="items-table" style="min-width: 1400px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;">S.No</th>
                        <th style="width: 155px;">Code / Barcode</th>
                        <th style="min-width: 280px;" class="text-left">Item Description</th>
                        <th style="width: 130px;">Exp Dt</th>
                        <th style="width: 95px;" class="text-right">Qty</th>
                        <th style="width: 110px;" class="text-right">Cost Price</th>
                        <th style="width: 110px;" class="text-right">Sell Price</th>
                        <th style="width: 110px;" class="text-right">MRP</th>
                        <th style="width: 80px;" class="text-center">GST%</th>
                        <th style="width: 110px;" class="text-right">GST TaxAmt</th>
                        <th style="width: 125px;" class="text-right">Net Amount</th>
                        <th style="width: 45px;"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @forelse ($existingItems as $index => $line)
                        @include('inventory.damage-stocks._item-row', ['index' => $index, 'line' => $line])
                    @empty
                        @include('inventory.damage-stocks._item-row', ['index' => 0, 'line' => null])
                    @endforelse
                </tbody>
                <tfoot class="bg-light font-weight-bold" style="position: sticky; bottom: 0; z-index: 10; border-top: 2px solid #dee2e6;">
                    <tr>
                        <td colspan="4" class="text-right align-middle">Totals:</td>
                        <td class="text-right align-middle text-primary" id="footer-total-qty">0.000</td>
                        <td class="text-right align-middle" id="footer-total-cost">0.00</td>
                        <td colspan="3" class="text-right align-middle">Total Tax:</td>
                        <td class="text-right align-middle text-secondary" id="footer-total-tax">0.00</td>
                        <td class="text-right align-middle text-danger font-weight-bold" id="footer-grand-net" style="font-size: 1.05rem;">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="remarks" class="font-weight-bold">Remarks / Reason</label>
            <textarea name="remarks" id="remarks" rows="2" class="form-control" placeholder="Specific reason for damage, wastage or theft...">{{ $entry->remarks ?? old('remarks') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="message" class="font-weight-bold">Message / Notes</label>
            <textarea name="message" id="message" rows="2" class="form-control" placeholder="Internal remarks or approval notes...">{{ $entry->message ?? old('message') }}</textarea>
        </div>
    </div>
</div>

{{-- Hidden Row Template for Add Row --}}
<template id="row-template">
    @include('inventory.damage-stocks._item-row', ['index' => '__INDEX__', 'line' => null])
</template>

{{-- Item Search Modal Popup (F2) --}}
<div class="modal fade" id="item-search-modal" tabindex="-1" role="dialog" aria-labelledby="itemSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title font-weight-bold" id="itemSearchModalLabel">
                    <i class="fas fa-search mr-2"></i> Search Items by Description
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-danger"></i></span>
                    </div>
                    <input type="text" id="modal-search-input" class="form-control form-control-lg" placeholder="Type item name or description (e.g. Pedigree, Royal Canin, Harness, Sheba)..." autocomplete="off">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="modal-search-clear"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted" id="modal-search-status">Type 1 or more characters to search...</small>
                    <small class="text-muted"><kbd>↑</kbd> <kbd>↓</kbd> to navigate, <kbd>Enter</kbd> to select, <kbd>Esc</kbd> to close</small>
                </div>

                <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-striped mb-0" id="modal-results-table">
                        <thead class="thead-light" style="position: sticky; top: 0; z-index: 5;">
                            <tr>
                                <th style="width: 140px;">Code</th>
                                <th>Item Description</th>
                                <th style="width: 140px;">Brand</th>
                                <th style="width: 100px;" class="text-right">Cost Price</th>
                                <th style="width: 100px;" class="text-right">Sell Price</th>
                                <th style="width: 100px;" class="text-right">MRP</th>
                                <th style="width: 75px;" class="text-center">GST%</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modal-items-body">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                                    Type item name or description above to search
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <span class="text-muted small">Location: <strong id="modal-branch-display">Branch</strong></span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('css')
<style>
    #items-table th, #items-table td {
        vertical-align: middle;
        padding: 0.35rem 0.45rem;
    }
    #items-table input.form-control-sm {
        height: calc(1.75rem + 2px);
        padding: 0.2rem 0.4rem;
        font-size: 0.85rem;
    }
    .select2-container--default .select2-selection--single {
        height: calc(1.75rem + 2px) !important;
        padding: 0.15rem 0.4rem !important;
        font-size: 0.85rem !important;
        border-color: #ced4da;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.45 !important;
        padding-left: 0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 1.65rem !important;
    }
    #modal-items-body tr.table-active {
        background-color: #ffebee !important;
    }
</style>
@endpush

@push('js')
<script>
    (function () {
        const searchItemsUrl = "{{ route('inventory.damage-stocks.search-items') }}";
        const itemByCodeUrl = "{{ route('inventory.damage-stocks.item-by-code') }}";
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        let activeTargetRow = null;
        let searchDebounceTimer = null;

        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return;

            const $select = $row.find('.item-select');
            $row.find('.item-id-hidden').val(item.id);

            // Update Select2
            const option = new Option(item.text, item.id, true, true);
            $select.empty().append(option).trigger('change');

            // Update Inputs
            const displayCode = item.code || item.barcode || item.item_code || '';
            $row.find('.item-code-input').val(displayCode).removeClass('is-invalid');
            $row.find('.item-cost').val(parseFloat(item.cost_price || 0).toFixed(2));
            $row.find('.item-sell').val(parseFloat(item.sell_price || 0).toFixed(2));
            $row.find('.item-mrp').val(parseFloat(item.mrp || 0).toFixed(2));
            $row.find('.item-gst-percent').val(parseFloat(item.gst_percent || 0).toFixed(2));

            const $qty = $row.find('.item-qty');
            if (!$qty.val() || parseFloat($qty.val()) <= 0) {
                $qty.val(1);
            }

            recalcRow($row);
            $qty.focus().select();
        }

        let damageModalOpen = false;
        let damageModalClosing = false;
        let damageCancellingRow = null;
        let damageItemSelectedInModal = false;

        $('#item-search-modal').on('show.bs.modal', function () {
            damageModalOpen = true;
            damageModalClosing = false;
            damageItemSelectedInModal = false;
            damageCancellingRow = null;
        });

        $('#item-search-modal').on('hide.bs.modal', function () {
            damageModalOpen = false;
            damageModalClosing = true;
            if (!damageItemSelectedInModal && activeTargetRow && activeTargetRow.length) {
                let selectedId = activeTargetRow.find('.item-id-hidden').val();
                if (!selectedId) {
                    damageCancellingRow = activeTargetRow;
                }
            }
        });

        $('#item-search-modal').on('hidden.bs.modal', function () {
            damageModalOpen = false;
            damageModalClosing = true;
            setTimeout(function () { damageModalClosing = false; }, 350);

            if (!damageItemSelectedInModal && damageCancellingRow && damageCancellingRow.length) {
                let totalRows = $('#items-body tr.item-row').length;
                if (totalRows > 1) {
                    damageCancellingRow.remove();
                    reindexRows();
                    recalcTotals();
                } else {
                    damageCancellingRow.find('.item-code-input').val('');
                    damageCancellingRow.find('.item-id-hidden').val('');
                }
                damageCancellingRow = null;
                activeTargetRow = null;
                setTimeout(function () {
                    let $target = $('#add-row, #remarks, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }

            damageItemSelectedInModal = false;
            damageCancellingRow = null;
            activeTargetRow = null;
        });

        // Open item search modal popup
        function openItemModal($row, initialQuery) {
            activeTargetRow = $row;
            const $modal = $('#item-search-modal');
            const $input = $('#modal-search-input');

            const branchName = $('#branch_id option:selected').text() || 'HO';
            $('#modal-branch-display').text(branchName);

            initialQuery = (initialQuery || '').trim();
            $input.val(initialQuery);
            $modal.modal('show');

            if (initialQuery.length >= 1) {
                performModalSearch(initialQuery);
            } else {
                $('#modal-items-body').html(`
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Type item name or description above to search
                        </td>
                    </tr>
                `);
                $('#modal-search-status').text('Type 1 or more characters to search...');
            }
        }

        // Perform modal AJAX search
        function performModalSearch(query) {
            query = (query || '').trim();
            if (!query) {
                $('#modal-items-body').html(`
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Type item name or description above to search
                        </td>
                    </tr>
                `);
                $('#modal-search-status').text('Type 1 or more characters to search...');
                return;
            }

            $('#modal-search-status').html('<i class="fas fa-spinner fa-spin mr-1 text-danger"></i> Searching items...');
            $('#modal-items-body').html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 text-danger d-block"></i>
                        Searching items matching "<strong>${escapeHtml(query)}</strong>"...
                    </td>
                </tr>
            `);

            const currentBranch = $('#branch_id').val() || 2;
            $.ajax({
                url: searchItemsUrl,
                data: { q: query, branch_id: currentBranch },
                dataType: 'json',
                success: function (results) {
                    if (!results || results.length === 0) {
                        $('#modal-search-status').html(`No items found for "<strong>${escapeHtml(query)}</strong>"`);
                        $('#modal-items-body').html(`
                            <tr>
                                <td colspan="8" class="text-center py-4 text-danger">
                                    <i class="fas fa-exclamation-circle fa-2x mb-2 d-block"></i>
                                    No items found matching "<strong>${escapeHtml(query)}</strong>". Try another keyword or description.
                                </td>
                            </tr>
                        `);
                        return;
                    }

                    $('#modal-search-status').html(`Found <strong>${results.length}</strong> items matching "<strong>${escapeHtml(query)}</strong>"`);
                    let rowsHtml = '';
                    results.forEach(function (item, idx) {
                        const codeDisplay = item.code || item.barcode || item.item_code || '-';
                        rowsHtml += `
                            <tr class="modal-item-result-row ${idx === 0 ? 'table-active' : ''}" style="cursor: pointer;">
                                <td class="text-nowrap font-weight-bold align-middle">
                                    <span class="badge badge-light border py-1 px-2 font-weight-normal">${escapeHtml(codeDisplay)}</span>
                                </td>
                                <td class="align-middle text-left">
                                    <span class="font-weight-bold text-dark">${escapeHtml(item.name)}</span>
                                </td>
                                <td class="text-muted small align-middle">${escapeHtml(item.brand || '-')}</td>
                                <td class="text-right font-weight-bold text-dark align-middle">₹${parseFloat(item.cost_price || 0).toFixed(2)}</td>
                                <td class="text-right text-muted align-middle">₹${parseFloat(item.sell_price || 0).toFixed(2)}</td>
                                <td class="text-right font-weight-bold text-danger align-middle">₹${parseFloat(item.mrp || 0).toFixed(2)}</td>
                                <td class="text-center align-middle"><span class="badge badge-info">${parseFloat(item.gst_percent || 0).toFixed(0)}%</span></td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-danger btn-xs px-2 btn-choose-modal-item">
                                        <i class="fas fa-check mr-1"></i> Select
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    $('#modal-items-body').html(rowsHtml);

                    // Attach item data
                    $('#modal-items-body tr.modal-item-result-row').each(function (i) {
                        $(this).data('item', results[i]);
                    });
                },
                error: function () {
                    $('#modal-search-status').text('Error fetching items.');
                    $('#modal-items-body').html(`
                        <tr>
                            <td colspan="8" class="text-center py-4 text-danger">
                                Failed to fetch items. Please try again.
                            </td>
                        </tr>
                    `);
                }
            });
        }

        function escapeHtml(str) {
            return (str || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Modal input listeners
        $('#modal-search-input').on('input', function () {
            clearTimeout(searchDebounceTimer);
            const val = $(this).val();
            searchDebounceTimer = setTimeout(function () {
                performModalSearch(val);
            }, 250);
        });

        $('#modal-search-clear').on('click', function () {
            $('#modal-search-input').val('').focus();
            performModalSearch('');
        });

        $('#item-search-modal').on('shown.bs.modal', function () {
            $('#modal-search-input').focus().select();
        });

        // Select item from modal row click or button
        $('#modal-items-body').on('click', '.btn-choose-modal-item', function (e) {
            e.stopPropagation();
            const item = $(this).closest('tr').data('item');
            if (item && activeTargetRow) {
                damageItemSelectedInModal = true;
                damageCancellingRow = null;
                applyItemToRow(activeTargetRow, item);
                $('#item-search-modal').modal('hide');
            }
        });

        $('#modal-items-body').on('click', 'tr.modal-item-result-row', function () {
            $('#modal-items-body tr.modal-item-result-row').removeClass('table-active');
            $(this).addClass('table-active');
        });

        $('#modal-items-body').on('dblclick', 'tr.modal-item-result-row', function () {
            const item = $(this).data('item');
            if (item && activeTargetRow) {
                damageItemSelectedInModal = true;
                damageCancellingRow = null;
                applyItemToRow(activeTargetRow, item);
                $('#item-search-modal').modal('hide');
            }
        });

        // Arrow navigation inside modal input
        $('#modal-search-input').on('keydown', function (e) {
            const $active = $('#modal-items-body tr.modal-item-result-row.table-active');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const $next = $active.next('tr.modal-item-result-row');
                if ($next.length) {
                    $active.removeClass('table-active');
                    $next.addClass('table-active');
                    $next[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const $prev = $active.prev('tr.modal-item-result-row');
                if ($prev.length) {
                    $active.removeClass('table-active');
                    $prev.addClass('table-active');
                    $prev[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if ($active.length) {
                    const item = $active.data('item');
                    if (item && activeTargetRow) {
                        damageItemSelectedInModal = true;
                        damageCancellingRow = null;
                        applyItemToRow(activeTargetRow, item);
                        $('#item-search-modal').modal('hide');
                    }
                }
            }
        });

        // Initialize Select2 on a row
        function initRowSelect2($row) {
            const $select = $row.find('.item-select');
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                placeholder: 'Search item name or code...',
                allowClear: true,
                dropdownAutoWidth: true,
                width: '100%',
                minimumInputLength: 1,
                ajax: {
                    url: searchItemsUrl,
                    dataType: 'json',
                    delay: 200,
                    data: function (params) {
                        return { 
                            q: params.term,
                            branch_id: $('#branch_id').val() || 2
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.text,
                                    itemData: item
                                };
                            })
                        };
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
                $row.find('.item-id-hidden').val('');
                $row.find('.item-code-input').val('');
                $row.find('.item-cost').val('');
                $row.find('.item-sell').val('');
                $row.find('.item-mrp').val('');
                $row.find('.item-gst-percent').val(0);
                recalcRow($row);
            });
        }

        // Lookup item by code (barcode scanner or typing)
        function lookupCode($row, code) {
            code = (code || '').trim();
            if (!code) return;

            const currentBranch = $('#branch_id').val() || 2;
            $.ajax({
                url: itemByCodeUrl,
                data: { code: code, branch_id: currentBranch },
                dataType: 'json',
                success: function (res) {
                    if (res && res.found && res.item) {
                        applyItemToRow($row, res.item);
                    } else {
                        // Not found by exact match -> Open Item Search Modal
                        openItemModal($row, code);
                    }
                },
                error: function () {
                    openItemModal($row, code);
                }
            });
        }

        // Recalculate single row
        function recalcRow($row) {
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const gstPct = parseFloat($row.find('.item-gst-percent').val()) || 0;

            const base = qty * cost;
            const gstTaxAmt = (base * (gstPct / 100));
            const netAmount = base + gstTaxAmt;

            $row.find('.item-gst-amount').val(gstTaxAmt.toFixed(2));
            $row.find('.item-net-amount').val(netAmount.toFixed(2));

            recalcTotals();
        }

        // Recalculate totals footer
        function recalcTotals() {
            let totalQty = 0;
            let totalCost = 0;
            let totalTax = 0;
            let grandNet = 0;

            $('#items-body tr.item-row').each(function () {
                const $row = $(this);
                const qty = parseFloat($row.find('.item-qty').val()) || 0;
                const cost = parseFloat($row.find('.item-cost').val()) || 0;
                const tax = parseFloat($row.find('.item-gst-amount').val()) || 0;
                const net = parseFloat($row.find('.item-net-amount').val()) || 0;

                totalQty += qty;
                totalCost += (qty * cost);
                totalTax += tax;
                grandNet += net;
            });

            $('#footer-total-qty').text(totalQty.toFixed(3));
            $('#footer-total-cost').text(totalCost.toFixed(2));
            $('#footer-total-tax').text(totalTax.toFixed(2));
            $('#footer-grand-net').text(grandNet.toFixed(2));
        }

        function reindexRows() {
            $('#items-body tr.item-row').each(function (idx) {
                const $row = $(this);
                $row.find('.row-sno').text(idx + 1);

                $row.find('input, select').each(function () {
                    const name = $(this).attr('name');
                    if (name) {
                        const newName = name.replace(/items\[\d+|__INDEX__\]/, `items[${idx}]`);
                        $(this).attr('name', newName);
                    }
                });
            });
            recalcTotals();
        }

        // Add Row
        $('#add-row').on('click', function () {
            const template = document.getElementById('row-template').innerHTML;
            const html = template.replace(/__INDEX__/g, rowIndex);
            const $newRow = $(html);
            $('#items-body').append($newRow);
            initRowSelect2($newRow);
            rowIndex++;
            reindexRows();
            $newRow.find('.item-code-input').focus();
        });

        // Quick search button
        $('#btn-quick-item-search').on('click', function () {
            let $targetRow = $('#items-body tr.item-row').last();
            if (!$targetRow.length) {
                $('#add-row').trigger('click');
                $targetRow = $('#items-body tr.item-row').last();
            }
            openItemModal($targetRow, '');
        });

        // Remove Row
        $('#items-body').on('click', '.row-remove', function () {
            if ($('#items-body tr.item-row').length <= 1) {
                const $row = $(this).closest('tr');
                $row.find('input:not(.item-gst-percent)').val('');
                $row.find('.item-id-hidden').val('');
                $row.find('.item-select').empty().append(new Option('-- Search Item / Description --', '')).trigger('change');
                $row.find('.item-gst-percent').val('0.00');
                recalcRow($row);
                return;
            }
            $(this).closest('tr').remove();
            reindexRows();
        });

        // Open modal on item code click or focus
        $('#items-body').off('click focus', '.item-code-input').on('click focus', '.item-code-input', function (e) {
            if (damageModalOpen || damageModalClosing) return;
            const $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.item-id-hidden').val()) return;
            openItemModal($row, $(this).val());
        });

        $(document).off('keydown', '.item-gst-percent').on('keydown', '.item-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr.item-row');
                let $nextRow = $currentRow.next('tr.item-row');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.item-code-input').focus();
                } else {
                    e.preventDefault();
                    $('#add-row').trigger('click');
                    let $newRow = $('#items-body tr.item-row').last();
                    setTimeout(function () {
                        $newRow.find('.item-code-input').focus();
                    }, 60);
                }
            }
        });

        // Code input blur / enter
        $('#items-body').on('keydown', '.item-code-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const $row = $(this).closest('tr');
                lookupCode($row, $(this).val());
            }
        });

        $('#items-body').on('blur', '.item-code-input', function () {
            const val = $(this).val().trim();
            const $row = $(this).closest('tr');
            const currentItem = $row.find('.item-id-hidden').val();
            if (val && !currentItem) {
                lookupCode($row, val);
            }
        });

        // Real-time calculation triggers
        $('#items-body').on('input change', '.item-qty, .item-cost, .item-gst-percent', function () {
            recalcRow($(this).closest('tr'));
        });

        // Global shortcuts (F2: Modal Search, F5: Add Row)
        $(document).on('keydown', function (e) {
            if (e.key === 'F2') {
                e.preventDefault();
                let $target = $(document.activeElement).closest('tr.item-row');
                if (!$target.length) {
                    $target = $('#items-body tr.item-row').last();
                }
                openItemModal($target, '');
            } else if (e.key === 'F5') {
                e.preventDefault();
                $('#add-row').trigger('click');
            }
        });

        // Location / Branch change -> refresh prices for existing items
        $('#branch_id').on('change', function () {
            const branchId = $(this).val();
            if (!branchId) return;

            $('#items-body tr.item-row').each(function () {
                const $row = $(this);
                const code = $row.find('.item-code-input').val();
                const itemId = $row.find('.item-id-hidden').val();
                const lookupVal = code || itemId;
                if (lookupVal) {
                    $.ajax({
                        url: itemByCodeUrl,
                        data: { code: lookupVal, branch_id: branchId },
                        dataType: 'json',
                        success: function (res) {
                            if (res && res.found && res.item) {
                                $row.find('.item-cost').val(parseFloat(res.item.cost_price || 0).toFixed(2));
                                $row.find('.item-sell').val(parseFloat(res.item.sell_price || 0).toFixed(2));
                                $row.find('.item-mrp').val(parseFloat(res.item.mrp || 0).toFixed(2));
                                $row.find('.item-gst-percent').val(parseFloat(res.item.gst_percent || 0).toFixed(2));
                                recalcRow($row);
                            }
                        }
                    });
                }
            });
        });

        // Initial setup
        $('#items-body tr.item-row').each(function () {
            initRowSelect2($(this));
            recalcRow($(this));
        });

        recalcTotals();
    })();
</script>
@endpush
