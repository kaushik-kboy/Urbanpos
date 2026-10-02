@php
    $entry = $openingStock ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($entry?->items ?? collect());
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
        <h6 class="font-weight-bold text-dark mb-0 mr-2"><i class="fas fa-boxes text-primary mr-1"></i> Opening Stock Header</h6>
        <div class="text-muted small">
            <span class="badge badge-primary p-1 mr-1">F2: Search</span>
            <span class="badge badge-success p-1 mr-1">F6: Save</span>
        </div>
    </div>
    <x-form-layout-customizer
        form-key="opening_stocks.header"
        container-id="os-header-fields-grid"
        title="Customize Opening Stock Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="os-header-fields-grid">
    @php
        $selectedBranch = old('branch_id', $entry->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: ($branches->keys()->first() ?: 1))));
    @endphp
    <input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">
    <div class="field-wrapper col-md-4" data-field="entry_date" data-label="Entry Date" data-default-order="1" data-core="1">
        <label for="entry_date" class="font-weight-bold">Entry Date <span class="text-danger">*</span></label>
        <input type="date" name="entry_date" id="entry_date" class="form-control" value="{{ optional($entry->entry_date ?? now())->format('Y-m-d') }}" required>
    </div>
</div>

<div class="card card-outline card-secondary mb-3 shadow-none border">
    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-boxes mr-1"></i> Opening Stock Items
        </h6>
        <div>
            <button type="button" id="btn-reset-table" class="btn btn-outline-danger btn-xs px-2 mr-1 font-weight-bold" title="Reset table rows">
                <i class="fas fa-undo mr-1"></i> Reset Table
            </button>
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
            <table class="table table-sm table-bordered table-hover mb-0" id="items-table" style="min-width: 1750px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;">S.No</th>
                        <th style="width: 145px;">Code / Barcode</th>
                        <th style="min-width: 240px;">Item Description</th>
                        <th style="width: 155px; min-width: 150px;">Exp Dt</th>
                        <th style="width: 75px;">Qty</th>
                        <th style="width: 85px;">Cost Price</th>
                        <th style="width: 85px;">Sell Price</th>
                        <th style="width: 80px;">MRP</th>
                        <th style="width: 65px;">Disc %</th>
                        <th style="width: 80px;">Disc Amount</th>
                        <th style="width: 60px;">GST%</th>
                        <th style="width: 80px;">GST TaxAmt</th>
                        <th style="width: 150px;">Supplier</th>
                        <th style="width: 70px;">Scheme Disc%</th>
                        <th style="width: 80px;">Scheme Amt</th>
                        <th style="width: 80px;">Scheme Others</th>
                        <th style="width: 95px;">Net Amount</th>
                        <th style="width: 45px;"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @forelse ($existingItems as $index => $line)
                        @include('inventory.opening-stocks._item-row', ['suppliers' => $suppliers, 'index' => $index, 'line' => $line])
                    @empty
                        @include('inventory.opening-stocks._item-row', ['suppliers' => $suppliers, 'index' => 0, 'line' => null])
                    @endforelse
                </tbody>
                <tfoot class="bg-light font-weight-bold" style="position: sticky; bottom: 0; z-index: 10; border-top: 2px solid #dee2e6;">
                    <tr>
                        <td colspan="4" class="text-right align-middle">Totals:</td>
                        <td class="text-right align-middle text-primary" id="footer-total-qty">0.000</td>
                        <td class="text-right align-middle" id="footer-total-cost">0.00</td>
                        <td colspan="3" class="text-right align-middle">Total Disc:</td>
                        <td class="text-right align-middle text-info" id="footer-total-disc">0.00</td>
                        <td class="text-right align-middle">Tax:</td>
                        <td class="text-right align-middle text-secondary" id="footer-total-tax">0.00</td>
                        <td class="text-right align-middle">Scheme:</td>
                        <td colspan="2" class="text-right align-middle text-info" id="footer-total-scheme">0.00</td>
                        <td class="text-right align-middle">Grand Net:</td>
                        <td class="text-right align-middle text-success font-weight-bold" id="footer-grand-net">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <label for="remarks" class="font-weight-bold text-muted small">Remarks</label>
        <textarea name="remarks" id="remarks" rows="2" class="form-control form-control-sm" placeholder="Optional remarks...">{{ $entry->remarks ?? '' }}</textarea>
    </div>
    <div class="col-md-6">
        <label for="message" class="font-weight-bold text-muted small">Message / Note</label>
        <textarea name="message" id="message" rows="2" class="form-control form-control-sm" placeholder="Optional message...">{{ $entry->message ?? '' }}</textarea>
    </div>
</div>

<x-custom-fields-renderer :module="'OpeningStock'" :model="$entry ?? null" :cardStyle="true" />

<!-- Item Search & Description Lookup Modal -->
<div class="modal fade" id="item-search-modal" tabindex="-1" role="dialog" aria-labelledby="itemSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow border-primary">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold text-white" id="itemSearchModalLabel">
                    <i class="fas fa-search-plus mr-1"></i> Item Search & Description Lookup
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
                    <input type="text" id="modal-search-input" class="form-control form-control-lg font-weight-bold" placeholder="Type item name, description, barcode, or code to search..." autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="modal-search-clear">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small id="modal-search-status" class="text-muted">Type 1 or more characters to search...</small>
                    <small class="badge badge-light border text-muted">Use <kbd>&uarr;</kbd> <kbd>&darr;</kbd> arrow keys to navigate and <kbd>Enter</kbd> to select</small>
                </div>

                <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-striped mb-0" id="modal-items-table">
                        <thead class="thead-light sticky-top" style="z-index: 5;">
                            <tr class="text-center text-nowrap">
                                <th style="width: 140px;">Code / Barcode</th>
                                <th class="text-left">Item Description</th>
                                <th style="width: 130px;">Brand</th>
                                <th style="width: 110px;">Cost Price</th>
                                <th style="width: 110px;">Sell Price</th>
                                <th style="width: 110px;">MRP</th>
                                <th style="width: 75px;">GST%</th>
                                <th style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modal-items-body">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                                    Type item name, description, or code above to search
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Press <kbd>Esc</kbd> to close without selecting</small>
                <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<template id="row-template">
    @include('inventory.opening-stocks._item-row', ['suppliers' => $suppliers, 'index' => '__INDEX__', 'line' => null])
</template>

@push('css')
<style>
    #items-table th {
        font-weight: 600;
        vertical-align: middle;
        font-size: 0.84rem;
        padding: 6px 4px;
    }
    #items-table td {
        padding: 4px;
        vertical-align: middle;
    }
    .select2-container .select2-selection--single {
        height: 31px !important;
        border-color: #ced4da !important;
        font-size: 0.85rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 29px !important;
        padding-left: 6px;
        padding-right: 18px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 29px !important;
        right: 3px;
    }
    .select2-dropdown {
        font-size: 0.85rem;
    }
    .modal-item-result-row.table-active {
        background-color: #d1ecf1 !important;
    }
</style>
@endpush

@push('js')
<script>
    (function () {
        const searchItemsUrl = "{{ route('inventory.opening-stocks.search-items') }}";
        const itemByCodeUrl = "{{ route('inventory.opening-stocks.item-by-code') }}";
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        let activeTargetRow = null;
        let searchDebounceTimer = null;

        // Apply item details to a specific row
        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return;

            $row.find('.item-id-hidden').val(item.id);
            $row.find('.item-select').val(item.id);
            const itemName = item.name || item.text || '';
            $row.find('.item-desc').val(itemName);

            // Update Inputs
            const displayCode = item.code || item.barcode || item.item_code || '';
            $row.find('.item-code-input').val(displayCode).removeClass('is-invalid');
            $row.find('.item-cost').val(parseFloat(item.cost_price || 0).toFixed(2));
            $row.find('.item-sell').val(parseFloat(item.sell_price || 0).toFixed(2));
            $row.find('.item-mrp').val(parseFloat(item.mrp || 0).toFixed(2));
            $row.find('.item-gst-percent').val(parseFloat(item.gst_percent || 0).toFixed(2));

            if (item.supplier_id) {
                $row.find('.item-supplier').val(item.supplier_id);
            }

            const $qty = $row.find('.item-qty');
            // Do not default qty to 1; keep blank as requested


            recalcRow($row);
            $qty.focus().select();
        }

        let osModalOpen = false;
        let osModalClosing = false;
        let osCancellingRow = null;
        let osItemSelectedInModal = false;

        $('#item-search-modal').on('show.bs.modal', function () {
            osModalOpen = true;
            osModalClosing = false;
            osItemSelectedInModal = false;
            osCancellingRow = null;
        });

        $('#item-search-modal').on('hide.bs.modal', function () {
            osModalOpen = false;
            osModalClosing = true;
            if (!osItemSelectedInModal && activeTargetRow && activeTargetRow.length) {
                let selectedId = activeTargetRow.find('.item-select').val();
                if (!selectedId) {
                    osCancellingRow = activeTargetRow;
                }
            }
        });

        $('#item-search-modal').on('hidden.bs.modal', function () {
            osModalOpen = false;
            osModalClosing = true;
            setTimeout(function () { osModalClosing = false; }, 350);

            if (!osItemSelectedInModal && osCancellingRow && osCancellingRow.length) {
                let totalRows = $('#items-body tr.item-row').length;
                if (totalRows > 1) {
                    osCancellingRow.remove();
                    reindexSno();
                    recalcTotals();
                } else {
                    osCancellingRow.find('.item-code-input').val('');
                    osCancellingRow.find('.item-desc').val('');
                    osCancellingRow.find('.item-id-hidden').val('');
                    osCancellingRow.find('.item-select').val('');
                }
                osCancellingRow = null;
                activeTargetRow = null;
                setTimeout(function () {
                    let $target = $('#add-row, #remarks, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }

            osItemSelectedInModal = false;
            osCancellingRow = null;
            activeTargetRow = null;
        });

        // Open item search modal popup
        function openItemModal($row, initialQuery) {
            activeTargetRow = $row;
            const $modal = $('#item-search-modal');
            const $input = $('#modal-search-input');

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
                            Type item name, description, or code above to search
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
                            Type item name, description, or code above to search
                        </td>
                    </tr>
                `);
                $('#modal-search-status').text('Type 1 or more characters to search...');
                return;
            }

            $('#modal-search-status').html('<i class="fas fa-spinner fa-spin mr-1 text-primary"></i> Searching items...');
            $('#modal-items-body').html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary d-block"></i>
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
                                <td class="text-right font-weight-bold text-primary align-middle">₹${parseFloat(item.mrp || 0).toFixed(2)}</td>
                                <td class="text-center align-middle"><span class="badge badge-info">${parseFloat(item.gst_percent || 0).toFixed(0)}%</span></td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-primary btn-xs px-2 btn-choose-modal-item">
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

        // Focus input when modal shown
        $('#item-search-modal').on('shown.bs.modal', function () {
            $('#modal-search-input').focus().select();
        });

        // Select item from modal row click or button
        $('#modal-items-body').on('click', '.btn-choose-modal-item', function (e) {
            e.stopPropagation();
            const item = $(this).closest('tr').data('item');
            if (item && activeTargetRow) {
                osItemSelectedInModal = true;
                osCancellingRow = null;
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
                osItemSelectedInModal = true;
                osCancellingRow = null;
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
                        osItemSelectedInModal = true;
                        osCancellingRow = null;
                        applyItemToRow(activeTargetRow, item);
                        $('#item-search-modal').modal('hide');
                    }
                }
            }
        });

        // Initialize Select2 on a row (no-op since standard readonly description input is used)
        function initRowSelect2($row) {
            // Standard read-only description input is now used.
        }

        // Lookup item by code (barcode scanner or typing)
        function lookupCode($row, code) {
            code = (code || '').trim();
            if (!code) return;

            if (window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(code);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

            const currentBranch = $('#branch_id').val() || 2;
            $.ajax({
                url: itemByCodeUrl,
                data: { code: code, branch_id: currentBranch },
                dataType: 'json',
                success: function (res) {
                    if (res && res.found && res.item) {
                        $row.data('last-processed-code', code || res.item.item_code || res.item.id);
                        $row.find('.item-code-input').removeClass('is-invalid border-danger');
                        applyItemToRow($row, res.item);
                        setTimeout(function () {
                            $row.find('.item-qty').focus().select();
                        }, 60);
                    } else {
                        $row.data('last-processed-code', null);
                        $row.find('.item-code-input').addClass('is-invalid border-danger');
                        const errMsg = "Product not found for this Item Code/Barcode.";
                        if (window.toastr && typeof window.toastr.warning === 'function') {
                            toastr.clear();
                            toastr.warning(errMsg, 'Item Not Found');
                        } else {
                            alert(errMsg);
                        }
                        setTimeout(function () {
                            $row.find('.item-code-input').focus().select();
                        }, 50);
                    }
                },
                error: function () {
                    $row.find('.item-code-input').addClass('is-invalid border-danger');
                }
            });
        }

        // Listen to Branch change to update existing row prices according to selected location
        $('#branch_id').on('change', function () {
            const branchId = $(this).val();
            if (!branchId) return;

            $('#items-body tr.item-row').each(function () {
                const $row = $(this);
                const code = $row.find('.item-code-input').val();
                const itemId = $row.find('.item-select').val();
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
                                if (res.item.supplier_id) {
                                    $row.find('.item-supplier').val(res.item.supplier_id);
                                }
                                recalcRow($row);
                            }
                        }
                    });
                }
            });
        });

        // Recalculate single row
        function recalcRow($row) {
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;

            let discPct = parseFloat($row.find('.item-disc-percent').val()) || 0;
            let discAmt = parseFloat($row.find('.item-disc-amount').val()) || 0;

            let schemePct = parseFloat($row.find('.item-scheme-percent').val()) || 0;
            let schemeAmt = parseFloat($row.find('.item-scheme-amount').val()) || 0;
            let schemeOthers = parseFloat($row.find('.item-scheme-others').val()) || 0;

            const gstPct = parseFloat($row.find('.item-gst-percent').val()) || 0;

            // Taxable base after discounts and schemes
            const taxable = Math.max(0, base - discAmt - schemeAmt - schemeOthers);
            const gstTaxAmt = (taxable * (gstPct / 100));
            const netAmt = taxable + gstTaxAmt;

            $row.find('.item-gst-amount').val(gstTaxAmt.toFixed(2));
            $row.find('.item-net').val(netAmt.toFixed(2));

            recalcTotals();
        }

        // Recalculate table totals
        function recalcTotals() {
            let totalQty = 0;
            let totalCost = 0;
            let totalDisc = 0;
            let totalScheme = 0;
            let totalTax = 0;
            let grandNet = 0;

            $('#items-body tr.item-row').each(function () {
                const $r = $(this);
                const qty = parseFloat($r.find('.item-qty').val()) || 0;
                const cost = parseFloat($r.find('.item-cost').val()) || 0;
                const discAmt = parseFloat($r.find('.item-disc-amount').val()) || 0;
                const schemeAmt = parseFloat($r.find('.item-scheme-amount').val()) || 0;
                const schemeOthers = parseFloat($r.find('.item-scheme-others').val()) || 0;
                const taxAmt = parseFloat($r.find('.item-gst-amount').val()) || 0;
                const netAmt = parseFloat($r.find('.item-net').val()) || 0;

                totalQty += qty;
                totalCost += (qty * cost);
                totalDisc += discAmt;
                totalScheme += (schemeAmt + schemeOthers);
                totalTax += taxAmt;
                grandNet += netAmt;
            });

            $('#footer-total-qty').text(totalQty.toFixed(3));
            $('#footer-total-cost').text(totalCost.toFixed(2));
            $('#footer-total-disc').text(totalDisc.toFixed(2));
            $('#footer-total-scheme').text(totalScheme.toFixed(2));
            $('#footer-total-tax').text(totalTax.toFixed(2));
            $('#footer-grand-net').text(grandNet.toFixed(2));
        }

        // Re-index S.No numbers
        function reindexSno() {
            $('#items-body tr.item-row').each(function (idx) {
                $(this).find('.row-sno').text(idx + 1);
            });
        }

        // Trigger item search modal on Tab/Enter/F2 ONLY — Click & Focus (mouse) disabled
        let osMouseDown = false;
        $(document).on('mousedown', '.item-code-input', function () {
            osMouseDown = true;
        });

        function checkAndOpenOsModal($input) {
            if (osModalOpen || osModalClosing) return;
            const $row = $input.closest('tr.item-row');
            if ($row.find('.item-select').val()) return;
            // Block if any PREVIOUS row has no item yet
            let $prevEmpty = null;
            $('#items-body tr.item-row').each(function () {
                if ($(this).is($row)) return false; // reached current row
                if (!$(this).find('.item-select').val()) {
                    $prevEmpty = $(this);
                    return false;
                }
            });
            if ($prevEmpty) {
                $prevEmpty.find('.item-code-input').focus();
                return;
            }
            openItemModal($row, $input.val());
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
        $('#items-body').off('keydown change input', '.item-code-input')
            .on('keydown', '.item-code-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr.item-row');
                    if (val) {
                        lookupCode($row, val);
                    } else {
                        checkAndOpenOsModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr.item-row');
                    if (val) {
                        e.preventDefault();
                        lookupCode($row, val);
                    } else {
                        e.preventDefault();
                        checkAndOpenOsModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkAndOpenOsModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr.item-row');
                    let itemId = $row.find('.item-select').val();
                    if (!itemId && $('#items-body tr.item-row').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr.item-row');
                        $row.remove();
                        reindexSno();
                        recalcTotals();
                        if ($prevRow.length) {
                            $prevRow.find('.item-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.item-code-input', function () {
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr.item-row');
                if (!val) {
                    $row.find('.item-select').val('');
                    $row.find('.item-desc-input').val('');
                    recalcRow($row);
                    $row.data('last-processed-code', '');
                    return;
                }
                if ($row.data('last-processed-code') === val) return;
                lookupCode($row, val);
            })
            .on('input', '.item-code-input', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Clicking on description also opens item search modal
        $(document).on('click', '.item-desc-input', function () {
            let $code = $(this).closest('tr.item-row').find('.item-code-input');
            checkAndOpenOsModal($code);
        });

        $(document).off('keydown', '.item-gst-percent, .item-scheme-others, .item-scheme-amount').on('keydown', '.item-gst-percent, .item-scheme-others, .item-scheme-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr.item-row');
                let $nextRow = $currentRow.next('tr.item-row');
                // Only allow moving to next row's item-code if current row has an item
                if (!$currentRow.find('.item-select').val()) {
                    e.preventDefault();
                    $currentRow.find('.item-code-input').focus();
                    return;
                }
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



        // Click search icon button in row
        $('#items-body').on('click', '.open-item-modal', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const currentCode = $row.find('.item-code-input').val();
            openItemModal($row, currentCode);
        });

        // Quick header search button
        $('#btn-quick-item-search').on('click', function () {
            const $lastRow = $('#items-body tr.item-row').last();
            openItemModal($lastRow, '');
        });

        // Disc % changed -> update Disc Amount
        $('#items-body').on('input change', '.item-disc-percent', function () {
            const $row = $(this).closest('tr');
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;
            const pct = parseFloat($(this).val()) || 0;
            $row.find('.item-disc-amount').val((base * (pct / 100)).toFixed(2));
            recalcRow($row);
        });

        // Disc Amount changed -> update Disc %
        $('#items-body').on('input change', '.item-disc-amount', function () {
            const $row = $(this).closest('tr');
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;
            const amt = parseFloat($(this).val()) || 0;
            $row.find('.item-disc-percent').val(base > 0 ? ((amt / base) * 100).toFixed(2) : 0);
            recalcRow($row);
        });

        // Scheme Disc % changed -> update Scheme Amt
        $('#items-body').on('input change', '.item-scheme-percent', function () {
            const $row = $(this).closest('tr');
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;
            const pct = parseFloat($(this).val()) || 0;
            $row.find('.item-scheme-amount').val((base * (pct / 100)).toFixed(2));
            recalcRow($row);
        });

        // Scheme Amt changed -> update Scheme Disc %
        $('#items-body').on('input change', '.item-scheme-amount', function () {
            const $row = $(this).closest('tr');
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;
            const amt = parseFloat($(this).val()) || 0;
            $row.find('.item-scheme-percent').val(base > 0 ? ((amt / base) * 100).toFixed(2) : 0);
            recalcRow($row);
        });

        // General calculation triggers
        $('#items-body').on('input change', '.item-qty, .item-cost, .item-gst-percent, .item-scheme-others', function () {
            const $row = $(this).closest('tr');
            const qty = parseFloat($row.find('.item-qty').val()) || 0;
            const cost = parseFloat($row.find('.item-cost').val()) || 0;
            const base = qty * cost;

            const discPct = parseFloat($row.find('.item-disc-percent').val()) || 0;
            if (discPct > 0) {
                $row.find('.item-disc-amount').val((base * (discPct / 100)).toFixed(2));
            }

            const schemePct = parseFloat($row.find('.item-scheme-percent').val()) || 0;
            if (schemePct > 0) {
                $row.find('.item-scheme-amount').val((base * (schemePct / 100)).toFixed(2));
            }

            recalcRow($row);
        });

        // Reset Table (Leaves exactly 1 empty default row)
        $('#btn-reset-table').on('click', function () {
            let html = document.getElementById('row-template').innerHTML;
            html = html.replaceAll('__INDEX__', 0);
            html = html.replaceAll('__SNO__', 1);
            const $newRow = $(html);
            $('#items-body').empty().append($newRow);
            initRowSelect2($newRow);
            reindexSno();
            rowIndex = 1;
            recalcTotals();
            setTimeout(function () {
                $newRow.find('.item-code-input').focus();
            }, 50);
        });

        // Reset Form (Clears form and resets table)
        $(document).on('click', '#btn-reset-form, .btn-reset-form', function () {
            $('#btn-reset-table').trigger('click');
            if (window.toastr) {
                toastr.info('Opening Stock form has been reset.');
            }
            setTimeout(function () {
                let $b = $('#branch_id');
                if ($b.data('select2')) {
                    $b.data('select2').$container.find('.select2-selection').focus();
                } else if ($b.length) {
                    $b.focus();
                }
            }, 100);
        });

        // Add Row — focus item-code (Tab will open modal)
        $('#add-row').on('click', function () {
            // Block add-row if first row has no item yet
            if (!$('#items-body tr.item-row:first .item-select').val()) {
                $('#items-body tr.item-row:first .item-code-input').focus();
                return;
            }
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

        // Remove Row
        $('#items-body').on('click', '.row-remove', function () {
            if ($('#items-body tr.item-row').length <= 1) {
                if (window.toastr) {
                    toastr.info('At least one item row is required.');
                }
                return;
            }
            $(this).closest('tr').remove();
            reindexSno();
            recalcTotals();
        });

        // Initialize existing rows
        $('#items-body tr.item-row').each(function () {
            initRowSelect2($(this));
            recalcRow($(this));
        });

        recalcTotals();

        // Autofocus first header field (branch_id) on page load
        setTimeout(function () {
            let $branch = $('#branch_id');
            if ($branch.data && $branch.data('select2')) {
                $branch.data('select2').$container.find('.select2-selection').focus();
            } else if ($branch.length) {
                $branch.focus();
            }
        }, 150);

        // Form submit validation and empty row pruning
        $('#opening-stock-form').on('submit', function (e) {
            let branchId = $('#branch_id').val();
            if (!branchId) {
                e.preventDefault();
                $('#branch_id').addClass('is-invalid').focus();
                if (window.toastr) {
                    toastr.warning('Please select a branch.');
                }
                return false;
            }

            let validRows = 0;
            let hasError = false;

            $('#items-body tr.item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.item-id-hidden').val();
                let itemCode = ($row.find('.item-code-input').val() || '').trim();
                let qty = parseFloat($row.find('.item-qty').val()) || 0;

                if (!itemId && !itemCode) {
                    return; // skip blank row
                }

                if (!itemId && itemCode) {
                    e.preventDefault();
                    hasError = true;
                    $row.find('.item-code-input').addClass('is-invalid').focus();
                    if (window.toastr) {
                        toastr.error('Please select a valid item for code: ' + itemCode);
                    }
                    return false;
                }

                if (qty <= 0) {
                    e.preventDefault();
                    hasError = true;
                    let desc = $row.find('.item-desc').val() || 'selected item';
                    $row.find('.item-qty').addClass('is-invalid').focus();
                    if (window.toastr) {
                        toastr.warning('Please enter a valid quantity greater than 0 for: ' + desc);
                    }
                    return false;
                }

                validRows++;
            });

            if (hasError) return false;

            if (validRows === 0) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Pehle item add karein. Please add at least one item before saving.');
                }
                $('#items-body tr.item-row:first .item-code-input').focus();
                return false;
            }

            // Prune empty rows before submit
            $('#items-body tr.item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.item-id-hidden').val();
                if (!itemId) {
                    $row.remove();
                }
            });

            // Re-index remaining rows contiguously
            $('#items-body tr.item-row').each(function (idx) {
                let $row = $(this);
                $row.find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.startsWith('items[')) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        // Keyboard shortcuts: F2 Search Modal, F5 New, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2') {
                e.preventDefault();
                // Find currently focused row or active row or last row
                const $focusedInput = $(':focus');
                let $targetRow = $focusedInput.closest('tr.item-row');
                if (!$targetRow.length) {
                    $targetRow = $('#items-body tr.item-row').last();
                }
                const currentCode = $targetRow.find('.item-code-input').val();
                openItemModal($targetRow, currentCode);
            } else if (e.key === 'F5') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.opening-stocks.create') }}";
            } else if (e.key === 'F6') {
                e.preventDefault();
                $('#opening-stock-form').trigger('submit');
            } else if (e.key === 'F7') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.opening-stocks.index') }}";
            } else if (e.key === 'F8') {
                e.preventDefault();
                window.print();
            } else if (e.key === 'F9') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) form.reset();
            } else if (e.key === 'F10') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.opening-stocks.index') }}";
            }
        });
    })();
</script>
@endpush
