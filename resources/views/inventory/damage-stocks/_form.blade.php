@php
    $entry = $damageStock ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($entry?->items ?? collect());
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
<style>
.item-exp-date[readonly] {
    pointer-events: none !important;
    user-select: none !important;
}
.item-exp-date[readonly]::-webkit-calendar-picker-indicator {
    display: none !important;
}
#ds-header-fields-grid .btn-open-datepicker,
#ds-header-fields-grid .btn-date-settings-modal,
#ds-header-fields-grid .urbanpos-date-group .input-group-append,
#items-table .btn-open-datepicker,
#items-table .btn-date-settings-modal,
#items-table .urbanpos-date-group .input-group-append {
    display: none !important;
}
#ds-header-fields-grid .urbanpos-date-group input,
#items-table .urbanpos-date-group input {
    border-top-right-radius: 0.25rem !important;
    border-bottom-right-radius: 0.25rem !important;
}
</style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
        <h6 class="font-weight-bold text-dark mb-0 mr-2"><i class="fas fa-boxes-alt text-danger mr-1"></i> Damage / Wastage Header</h6>
        <div class="text-muted small">
            <span class="badge badge-danger p-1 mr-1">F2: Search</span>
            <span class="badge badge-primary p-1">F5: Add Row</span>
        </div>
    </div>
    <x-form-layout-customizer
        form-key="damage_stocks.header"
        container-id="ds-header-fields-grid"
        title="Customize Damage Stock Header"
    />
</div>

<div class="row g-2 form-fields-grid tx-header-fields-grid mb-3" id="ds-header-fields-grid">
    @php
        $selectedBranch = old('branch_id', $entry->branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
    @endphp
    <input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">
    <div class="field-wrapper col-md-6" data-field="entry_date" data-label="Entry Date" data-default-order="2" data-core="1">
        <label for="entry_date" class="font-weight-bold">Entry Date <span class="text-danger">*</span></label>
        <input type="date" name="entry_date" id="entry_date" class="form-control" 
               value="{{ optional($entry->entry_date ?? now())->format('Y-m-d') }}" required>
    </div>
    <div class="field-wrapper col-md-6" data-field="wastage_type" data-label="Wastage Type" data-default-order="3" data-core="1">
        <label for="wastage_type" class="font-weight-bold">Wastage Type <span class="text-danger">*</span></label>
        <select name="wastage_type" id="wastage_type" class="form-control select2" required>
            <option value="Damage" @selected(($entry->wastage_type ?? old('wastage_type', 'Damage')) === 'Damage')>Damage</option>
            <option value="Wastage" @selected(($entry->wastage_type ?? old('wastage_type')) === 'Wastage')>Wastage</option>
            <option value="Theft" @selected(($entry->wastage_type ?? old('wastage_type')) === 'Theft')>Theft</option>
        </select>
    </div>
</div>

<div class="card card-outline card-danger mb-3 shadow-none border">
    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-boxes-alt mr-1 text-danger"></i> Damage Stock Items
        </h6>
        <div>
            <button type="button" id="btn-reset-table" class="btn btn-outline-danger btn-xs px-2 mr-1 font-weight-bold" title="Reset table rows">
                <i class="fas fa-undo mr-1"></i> Reset Table
            </button>
            <button type="button" id="btn-quick-item-search" class="btn btn-outline-danger btn-xs px-2 mr-1" title="Open Item Search Modal (F2)">
                <i class="fas fa-search mr-1"></i> Search Item (F2)
            </button>
            <button type="button" id="add-row" class="btn btn-danger btn-xs px-2">
                <i class="fas fa-plus mr-1"></i> Add Row
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive tx-items-scroll-container" style="overflow-x: auto; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0" id="items-table" style="min-width: 1400px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;">S.No</th>
                        <th style="width: 145px;">Code / Barcode</th>
                        <th style="min-width: 240px;" class="text-left">Item Description</th>
                        <th style="width: 215px; min-width: 205px;">Exp Dt</th>
                        <th style="width: 75px;" class="text-right">Qty</th>
                        <th style="width: 85px;" class="text-right">Cost Price</th>
                        <th style="width: 85px;" class="text-right">Sell Price</th>
                        <th style="width: 85px;" class="text-right">MRP</th>
                        <th style="width: 65px;" class="text-center">GST%</th>
                        <th style="width: 85px;" class="text-right">GST TaxAmt</th>
                        <th style="width: 95px;" class="text-right">Net Amount</th>
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
            <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Specific reason for damage, wastage or theft..." value="{{ $entry->remarks ?? old('remarks') }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="message" class="font-weight-bold">Message / Notes</label>
            <input type="text" name="message" id="message" class="form-control" placeholder="Internal remarks or approval notes..." value="{{ $entry->message ?? old('message') }}">
        </div>
    </div>
</div>

<x-custom-fields-renderer :module="'DamageStock'" :model="$entry ?? null" :cardStyle="true" />

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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="input-group mr-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-danger"></i></span>
                        </div>
                        <input type="text" id="modal-search-input" class="form-control form-control-lg" placeholder="Type item name or description (e.g. Pedigree, Royal Canin, Harness, Sheba)..." autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="modal-search-clear"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="text-nowrap">
                        <x-table-column-customizer table-key="modal.damage-stocks.item-search" table-id="modal-results-table" button-class="btn btn-outline-secondary" button-text="Columns" title="Customize Columns & Order" />
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
                                <th style="width: 140px;" data-col-key="code">Code</th>
                                <th data-col-key="name">Item Description</th>
                                <th style="width: 140px;" data-col-key="brand">Brand</th>
                                <th style="width: 100px;" class="text-right" data-col-key="cost_price">Cost Price</th>
                                <th style="width: 100px;" class="text-right" data-col-key="sell_price">Sell Price</th>
                                <th style="width: 100px;" class="text-right" data-col-key="mrp">MRP</th>
                                <th style="width: 75px;" class="text-center" data-col-key="gst">GST%</th>
                                <th style="width: 90px;" class="text-center" data-col-key="action">Action</th>
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

{{-- Batch Selection Modal for Damage Stock --}}
<div class="modal fade" id="ds-batch-modal" tabindex="-1" role="dialog" aria-labelledby="dsBatchModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title font-weight-bold" id="dsBatchModalLabel">
                    <i class="fas fa-layer-group mr-2"></i> Select Batch & Expiry Date
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <div>
                        <strong class="text-danger" id="ds-batch-modal-item-title">Item Name</strong>
                        <span class="text-muted ml-2 small" id="ds-batch-modal-item-code"></span>
                    </div>
                    <small class="text-muted font-weight-bold" id="ds-batch-modal-branch-info"></small>
                </div>
                <div class="table-responsive border rounded" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="ds-modal-batches-table">
                        <thead class="thead-light" style="position: sticky; top: 0; z-index: 5;">
                            <tr class="text-center">
                                <th style="width: 45px;">#</th>
                                <th>Batch No</th>
                                <th style="width: 170px;">Expiry Date</th>
                                <th style="width: 120px;" class="text-right">Available Qty</th>
                                <th style="width: 110px;" class="text-right">Cost Price</th>
                                <th style="width: 95px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ds-modal-batches-body">
                            {{-- Populated via JS --}}
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                    <small class="text-muted"><kbd>↑</kbd> <kbd>↓</kbd> to navigate, <kbd>Enter</kbd> to select batch</small>
                    <small class="text-muted">Selecting a batch auto-fills Expiry Date & Cost</small>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <span class="text-muted small">Choose the batch corresponding to the damaged or expired stock.</span>
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

        function formatToDisplayDate(dateStr) {
            if (!dateStr) return '';
            let s = String(dateStr).trim();
            if (s.indexOf('/') !== -1) return s;
            let parts = s.substring(0, 10).split('-');
            if (parts.length === 3) {
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            return s;
        }

        function isExpiredDate(dateStr) {
            if (!dateStr) return false;
            let s = String(dateStr).trim();
            let y, m, d;
            if (s.indexOf('/') !== -1) {
                let p = s.split('/');
                if (p.length === 3) { d = parseInt(p[0], 10); m = parseInt(p[1], 10) - 1; y = parseInt(p[2], 10); }
            } else if (s.indexOf('-') !== -1) {
                let p = s.split('-');
                if (p.length === 3) { y = parseInt(p[0], 10); m = parseInt(p[1], 10) - 1; d = parseInt(p[2], 10); }
            }
            if (y && m !== undefined && d) {
                let exp = new Date(y, m, d, 23, 59, 59);
                return exp < new Date();
            }
            return false;
        }

        let dsActiveBatchRow = null;
        let dsActiveBatchItem = null;
        let dsBatchSelectedIndex = 0;

        function showDsBatchModal($row, item, batches) {
            dsActiveBatchRow = $row;
            dsActiveBatchItem = item;
            $('#ds-batch-modal-item-title').text(item.name || 'Selected Item');
            $('#ds-batch-modal-item-code').text(item.item_code || item.code || '—');
            let branchName = $('#branch_id option:selected').text() || 'Branch';
            $('#ds-batch-modal-branch-info').text('Location: ' + branchName);

            let $tbody = $('#ds-modal-batches-body');
            $tbody.empty();

            if (!batches || batches.length === 0) {
                $tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">No batches recorded for this item. Existing stock can be damaged without batch.</td></tr>');
                $('#ds-batch-modal').modal('show');
                return;
            }

            let currentBatchNo = $row.find('.item-batch-no').val() || '';
            let html = '';
            batches.forEach(function (b, idx) {
                let isExpired = b.exp_date && isExpiredDate(b.exp_date);
                let expBadge = b.exp_date
                    ? (isExpired
                        ? `<span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i>EXPIRED (${formatToDisplayDate(b.exp_date)})</span>`
                        : `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${formatToDisplayDate(b.exp_date)}</span>`)
                    : `<span class="text-muted">—</span>`;
                let batchLabel = b.batch_no ? `<span class="badge badge-secondary px-2 py-1 font-weight-bold">${b.batch_no}</span>` : `<span class="badge badge-light border text-muted">No Batch</span>`;
                let qtyAvail = parseFloat(b.qty !== undefined ? b.qty : (b.available_qty || 0));
                let costVal = parseFloat(b.cost_price || item.cost_price || 0);
                let isSelected = (currentBatchNo && b.batch_no === currentBatchNo);
                let rowClass = 'ds-batch-row ' + (isSelected ? 'table-success ' : '');
                let rowStyle = 'cursor: pointer;';
                let selectBtn = `<button type="button" class="btn btn-xs btn-success ds-btn-pick-batch font-weight-bold px-2"><i class="fas fa-check mr-1"></i>Select</button>`;

                html += `
                    <tr class="${rowClass}" style="${rowStyle}" data-idx="${idx}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle text-center">${batchLabel}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${qtyAvail.toFixed(3)}</td>
                        <td class="align-middle text-right font-weight-bold text-dark">₹${costVal.toFixed(2)}</td>
                        <td class="align-middle text-center">${selectBtn}</td>
                    </tr>`;
            });

            $tbody.html(html);

            $tbody.find('tr.ds-batch-row').each(function () {
                let idx = $(this).data('idx');
                $(this).data('batch', batches[idx]);
            });

            dsBatchSelectedIndex = 0;
            highlightDsBatchRow();
            $('#ds-batch-modal').modal('show');
        }

        function highlightDsBatchRow() {
            let $rows = $('#ds-modal-batches-body tr.ds-batch-row');
            $('#ds-modal-batches-body tr').removeClass('table-primary');
            if (dsBatchSelectedIndex >= 0 && dsBatchSelectedIndex < $rows.length) {
                $rows.eq(dsBatchSelectedIndex).addClass('table-primary');
            }
        }

        function applyBatchToRow($row, batch) {
            let batchNo = batch.batch_no || '';
            $row.find('.item-batch-no').val(batchNo);

            let $batchWrap = $row.find('.ds-batch-btn-wrap');
            $batchWrap.removeClass('d-none');
            $row.find('.ds-batch-badge-text, .item-batch-text').text(batchNo || 'Batch');
            $row.find('.ds-btn-choose-batch').attr('title', batchNo ? ('Batch: ' + batchNo + ' (Click to change)') : 'Click to choose batch');
            $row.find('.item-batch-display').removeClass('d-none');

            if (batch.cost_price !== undefined && parseFloat(batch.cost_price) > 0) {
                $row.find('.item-cost').val(parseFloat(batch.cost_price).toFixed(2));
            }
            if (batch.sell_price !== undefined && parseFloat(batch.sell_price) > 0) {
                $row.find('.item-sell').val(parseFloat(batch.sell_price).toFixed(2));
            }
            if (batch.mrp !== undefined && parseFloat(batch.mrp) > 0) {
                $row.find('.item-mrp').val(parseFloat(batch.mrp).toFixed(2));
            }

            if (batch.exp_date) {
                let formatted = formatToDisplayDate(batch.exp_date);
                $row.find('.item-exp-date').val(formatted).attr('data-original-exp', formatted).data('original-exp', formatted);
            }

            let availQty = (batch.available_qty !== undefined && batch.available_qty !== null) ? parseFloat(batch.available_qty) : (parseFloat(batch.qty) || 0);
            $row.attr('data-available-qty', availQty).data('available-qty', availQty);
            const $qty = $row.find('.item-qty');
            $qty.attr('data-available-qty', availQty).data('available-qty', availQty);
            $qty.attr('title', 'Available Stock: ' + availQty.toFixed(3));
            $qty.attr('placeholder', 'Max ' + availQty.toFixed(3));
            $qty.removeClass('is-invalid border-danger text-danger');

            recalcRow($row);
        }

        $(document).on('click', '.ds-batch-row, .ds-btn-pick-batch', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('ds-batch-row') ? $(this) : $(this).closest('tr');
            let batch = $tr.data('batch');
            if (!batch) return;

            if (dsActiveBatchRow && dsActiveBatchRow.length) {
                applyBatchToRow(dsActiveBatchRow, batch);
                let $targetRow = dsActiveBatchRow;
                $('#ds-batch-modal').modal('hide');
                setTimeout(function () {
                    $targetRow.find('.item-qty').focus().select();
                }, 80);
            }
        });

        // Batch Modal Keyboard Navigation
        $(document).on('keydown', function (e) {
            if ($('#ds-batch-modal').is(':visible')) {
                let $rows = $('#ds-modal-batches-body tr.ds-batch-row');
                if ($rows.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    dsBatchSelectedIndex = Math.min(dsBatchSelectedIndex + 1, $rows.length - 1);
                    highlightDsBatchRow();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    dsBatchSelectedIndex = Math.max(dsBatchSelectedIndex - 1, 0);
                    highlightDsBatchRow();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (dsBatchSelectedIndex >= 0 && dsBatchSelectedIndex < $rows.length) {
                        $rows.eq(dsBatchSelectedIndex).trigger('click');
                    }
                }
            }
        });

        // Click on batch button in table row to choose/change batch
        $(document).on('click', '.ds-btn-choose-batch', function (e) {
            e.preventDefault();
            e.stopPropagation();
            let $row = $(this).closest('tr.item-row');
            let itemId = $row.find('.item-id-hidden').val();
            if (!itemId) {
                if (window.toastr) toastr.warning('Please select an item first.', 'Item Required');
                $row.find('.item-code-input').focus();
                return;
            }
            let itemData = $row.data('item-data');
            if (itemData && itemData.batches && itemData.batches.length > 0) {
                showDsBatchModal($row, itemData, itemData.batches);
            } else {
                const currentBranch = $('#branch_id').val() || 2;
                $.getJSON(itemByCodeUrl, { item_id: itemId, branch_id: currentBranch }, function (res) {
                    if (res && res.found && res.item) {
                        $row.data('item-data', res.item);
                        showDsBatchModal($row, res.item, res.item.batches || []);
                    } else {
                        if (window.toastr) toastr.info('No batches found for this item.', 'Batches');
                    }
                });
            }
        });

        function applyItemToRow($row, item) {
            if (!$row || !$row.length) return;

            $row.find('.item-id-hidden').val(item.id);
            const itemName = item.name || item.text || '';
            $row.find('.item-desc').val(itemName);
            $row.data('item-data', item);

            // Update Inputs
            const displayCode = item.code || item.barcode || item.item_code || '';
            $row.find('.item-code-input').val(displayCode).removeClass('is-invalid');
            $row.find('.item-cost').val(parseFloat(item.cost_price || 0).toFixed(2));
            $row.find('.item-sell').val(parseFloat(item.sell_price || 0).toFixed(2));
            $row.find('.item-mrp').val(parseFloat(item.mrp || 0).toFixed(2));
            $row.find('.item-gst-percent').val(parseFloat(item.gst_percent || 0).toFixed(2));

            let hasBatches = (item.batches && Array.isArray(item.batches) && item.batches.length > 0) || Boolean(item.batch_no);
            let batchNo = item.batch_no || '';
            $row.find('.item-batch-no').val(batchNo);

            let $batchWrap = $row.find('.ds-batch-btn-wrap');
            if (hasBatches) {
                $batchWrap.removeClass('d-none');
                $row.find('.ds-batch-badge-text, .item-batch-text').text(batchNo || 'Batch');
                $row.find('.ds-btn-choose-batch').attr('title', batchNo ? ('Batch: ' + batchNo + ' (Click to change)') : 'Multiple batches available! Click to choose batch');
                $row.find('.item-batch-display').removeClass('d-none');
            } else {
                $batchWrap.addClass('d-none');
                $row.find('.ds-batch-badge-text, .item-batch-text').text('');
                $row.find('.item-batch-display').addClass('d-none');
            }

            if (item.exp_date) {
                let formatted = formatToDisplayDate(item.exp_date);
                $row.find('.item-exp-date').val(formatted).attr('data-original-exp', formatted).data('original-exp', formatted);
            } else {
                $row.find('.item-exp-date').val('').attr('data-original-exp', '').data('original-exp', '');
            }

            let availQty = (item.available_qty !== undefined && item.available_qty !== null) ? parseFloat(item.available_qty) : (parseFloat(item.qty) || 0);
            $row.attr('data-available-qty', availQty).data('available-qty', availQty);

            const $qty = $row.find('.item-qty');
            $qty.attr('data-available-qty', availQty).data('available-qty', availQty);
            $qty.attr('title', 'Available Stock: ' + availQty.toFixed(3));
            $qty.attr('placeholder', 'Max ' + availQty.toFixed(3));
            $qty.removeClass('is-invalid border-danger text-danger');
            // Do not default qty to 1; keep blank as requested

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
                                <td class="text-nowrap font-weight-bold align-middle" data-col-key="code">
                                    <span class="badge badge-light border py-1 px-2 font-weight-normal">${escapeHtml(codeDisplay)}</span>
                                </td>
                                <td class="align-middle text-left" data-col-key="name">
                                    <span class="font-weight-bold text-dark">${escapeHtml(item.name)}</span>
                                </td>
                                <td class="text-muted small align-middle" data-col-key="brand">${escapeHtml(item.brand || '-')}</td>
                                <td class="text-right font-weight-bold text-dark align-middle" data-col-key="cost_price">₹${parseFloat(item.cost_price || 0).toFixed(2)}</td>
                                <td class="text-right text-muted align-middle" data-col-key="sell_price">₹${parseFloat(item.sell_price || 0).toFixed(2)}</td>
                                <td class="text-right font-weight-bold text-danger align-middle" data-col-key="mrp">₹${parseFloat(item.mrp || 0).toFixed(2)}</td>
                                <td class="text-center align-middle" data-col-key="gst"><span class="badge badge-info">${parseFloat(item.gst_percent || 0).toFixed(0)}%</span></td>
                                <td class="text-center align-middle" data-col-key="action">
                                    <button type="button" class="btn btn-danger btn-xs px-2 btn-choose-modal-item">
                                        <i class="fas fa-check mr-1"></i> Select
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    $('#modal-items-body').html(rowsHtml);
                    if (window.applyTablePreferences) {
                        window.applyTablePreferences('modal-results-table');
                    }

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

        function chooseItemFromModal(item) {
            if (item && activeTargetRow) {
                let $targetRow = activeTargetRow;
                damageItemSelectedInModal = true;
                damageCancellingRow = null;
                $('#item-search-modal').modal('hide');

                const currentBranch = $('#branch_id').val() || 2;
                $.getJSON(itemByCodeUrl, { item_id: item.id, branch_id: currentBranch }, function (res) {
                    if (res && res.found && res.item) {
                        applyItemToRow($targetRow, res.item);
                        let batches = (res.item.batches || []);
                        if (batches.length > 1) {
                            showDsBatchModal($targetRow, res.item, batches);
                        } else {
                            setTimeout(function () {
                                $targetRow.find('.item-qty').focus().select();
                            }, 80);
                        }
                    } else {
                        applyItemToRow($targetRow, item);
                    }
                }).fail(function () {
                    applyItemToRow($targetRow, item);
                });
            }
        }

        // Select item from modal row click or button
        $('#modal-items-body').on('click', '.btn-choose-modal-item', function (e) {
            e.stopPropagation();
            const item = $(this).closest('tr').data('item');
            chooseItemFromModal(item);
        });

        $('#modal-items-body').on('click', 'tr.modal-item-result-row', function () {
            $('#modal-items-body tr.modal-item-result-row').removeClass('table-active');
            $(this).addClass('table-active');
        });

        $('#modal-items-body').on('dblclick', 'tr.modal-item-result-row', function () {
            const item = $(this).data('item');
            chooseItemFromModal(item);
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
                    chooseItemFromModal(item);
                }
            }
        });

        // Initialize Select2 on a row (no-op since using standard description input)
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
                        let batches = (res.item.batches || []);
                        if (batches.length > 1) {
                            showDsBatchModal($row, res.item, batches);
                        } else {
                            setTimeout(function () {
                                $row.find('.item-qty').focus().select();
                            }, 60);
                        }
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
            $('#display-ds-final-total').text(grandNet.toFixed(2));
            let itemCount = $('#items-body tr.item-row').filter(function () {
                return !!$(this).find('.item-select').val();
            }).length;
            $('#ds-total-items-badge').text(itemCount + (itemCount === 1 ? ' Item' : ' Items'));
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

        // Reset Table (Leaves exactly 1 empty default row)
        $('#btn-reset-table').on('click', function () {
            const template = document.getElementById('row-template').innerHTML;
            const html = template.replace(/__INDEX__/g, 0);
            const $newRow = $(html);
            $('#items-body').empty().append($newRow);
            initRowSelect2($newRow);
            rowIndex = 1;
            reindexRows();
            recalcTotals();
            setTimeout(function () {
                $newRow.find('.item-code-input').focus();
            }, 50);
        });

        // Reset Form (Clears remarks and resets table)
        $(document).on('click', '#btn-reset-form, .btn-reset-form', function () {
            $('textarea[name="remarks"]').val('');
            $('#btn-reset-table').trigger('click');
            if (window.toastr) {
                toastr.info('Damage Stock form has been reset.');
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

        function validateSingleRow($row, isTriggeredByAddRow = false) {
            let itemId = $row.find('.item-id-hidden').val();
            let $code = $row.find('.item-code-input');
            let $qty = $row.find('.item-qty');
            let rowSno = $row.find('.row-sno').text().trim() || '1';

            // 1. Item must be selected
            if (!itemId) {
                if (isTriggeredByAddRow) {
                    if (window.toastr) {
                        toastr.clear();
                        toastr.warning(`Row #${rowSno} me pehle product select karein!`, 'Item Required');
                    }
                    $code.addClass('is-invalid border-danger').focus();
                }
                return false;
            }

            // 2. Qty must be entered and > 0
            let qtyVal = parseFloat($qty.val());
            if (isNaN(qtyVal) || qtyVal <= 0) {
                if (isTriggeredByAddRow) {
                    if (window.toastr) {
                        toastr.clear();
                        toastr.warning(`Row #${rowSno} me valid quantity (greater than 0) enter karein!`, 'Quantity Required');
                    }
                    $qty.addClass('is-invalid border-danger text-danger').focus().select();
                }
                return false;
            }

            // 3. Qty must not exceed available stock
            let availRaw = $qty.attr('data-available-qty') !== undefined ? $qty.attr('data-available-qty') : $row.attr('data-available-qty');
            if (availRaw !== undefined && availRaw !== '' && !isNaN(parseFloat(availRaw))) {
                let maxAvail = parseFloat(availRaw);
                if (qtyVal > maxAvail) {
                    $qty.addClass('is-invalid border-danger text-danger');
                    let errMsg = `Row #${rowSno}: Available stock (${maxAvail.toFixed(3)}) se jyada damage quantity (${qtyVal.toFixed(3)}) enter nahi kar sakte!`;
                    if (window.toastr) {
                        toastr.clear();
                        toastr.error(errMsg, 'Stock Limit Exceeded');
                    }
                    $qty.focus().select();
                    return false;
                }
            }

            $qty.removeClass('is-invalid border-danger text-danger');
            $code.removeClass('is-invalid border-danger');
            return true;
        }

        function validateAllRowsBeforeAdd() {
            let $rows = $('#items-body tr.item-row');
            if ($rows.length === 0) return true;

            for (let i = 0; i < $rows.length; i++) {
                let $r = $rows.eq(i);
                if (!validateSingleRow($r, true)) {
                    return false;
                }
            }
            return true;
        }

        // Add Row — block until current rows are completely validated
        $('#add-row').on('click', function () {
            if (!validateAllRowsBeforeAdd()) {
                return false;
            }
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

        // Safeguards to prevent Expiry Date from ever disappearing or getting wiped out on click/keydown/blur
        $(document).on('focus', '.item-exp-date[readonly]', function () {
            $(this).blur();
        });

        $(document).on('keydown', '.item-exp-date', function (e) {
            if ($(this).prop('readonly') || $(this).attr('readonly') || e.which === 8 || e.which === 46) {
                e.preventDefault();
                return false;
            }
        });

        $(document).on('input change blur', '.item-exp-date', function () {
            let $el = $(this);
            let orig = $el.attr('data-original-exp') || $el.data('original-exp');
            if (!$el.val() && orig) {
                $el.val(orig);
            }
        });

        // Open modal on item code: Tab/Enter/F2 ONLY — mouse click does NOT open modal
        let damMouseDown = false;
        $(document).on('mousedown', '.item-code-input', function () {
            damMouseDown = true;
        });

        function checkAndOpenDamageModal($input) {
            if (damageModalOpen || damageModalClosing) return;
            const $row = $input.closest('tr.item-row');
            if ($row.find('.item-id-hidden').val()) return;
            // Block if any PREVIOUS row has no item yet
            let $prevEmpty = null;
            $('#items-body tr.item-row').each(function () {
                if ($(this).is($row)) return false;
                if (!$(this).find('.item-id-hidden').val()) {
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
                        checkAndOpenDamageModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr.item-row');
                    if (val) {
                        e.preventDefault();
                        lookupCode($row, val);
                    } else {
                        e.preventDefault();
                        checkAndOpenDamageModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkAndOpenDamageModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr.item-row');
                    let itemId = $row.find('.item-id-hidden').val();
                    if (!itemId && $('#items-body tr.item-row').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr.item-row');
                        $row.remove();
                        reindexRows();
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
                    $row.find('.item-id-hidden').val('');
                    $row.find('.item-select').empty().append(new Option('-- Search Item / Description --', '')).trigger('change');
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
        $(document).on('click', '.item-select', function () {
            let $code = $(this).closest('tr.item-row').find('.item-code-input');
            checkAndOpenDamageModal($code);
        });

        $(document).off('keydown', '.item-gst-percent').on('keydown', '.item-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr.item-row');
                let $nextRow = $currentRow.next('tr.item-row');
                // Only allow moving to next row if current row has item
                if (!$currentRow.find('.item-id-hidden').val()) {
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



        // Real-time stock limit validation for Task 2
        function checkQtyStockLimit($qtyInput, showToast = true) {
            let $row = $qtyInput.closest('tr.item-row');
            let itemId = $row.find('.item-id-hidden').val();
            let availRaw = $qtyInput.attr('data-available-qty') !== undefined ? $qtyInput.attr('data-available-qty') : $row.attr('data-available-qty');
            let qtyVal = parseFloat($qtyInput.val());

            if (!itemId) {
                return true;
            }

            if (availRaw === undefined || availRaw === '' || isNaN(parseFloat(availRaw))) {
                return true;
            }

            let maxAvail = parseFloat(availRaw);
            if (!isNaN(qtyVal) && qtyVal > maxAvail) {
                $qtyInput.addClass('is-invalid border-danger text-danger');
                if (showToast) {
                    let lastWarned = $qtyInput.data('last-warned-qty');
                    if (lastWarned !== qtyVal) {
                        $qtyInput.data('last-warned-qty', qtyVal);
                        if (window.toastr) {
                            toastr.clear();
                            toastr.error(`Available stock (${maxAvail.toFixed(3)}) se jyada quantity enter nahi kar sakte! (Entered: ${qtyVal.toFixed(3)})`, 'Stock Limit Exceeded');
                        }
                    }
                }
                return false;
            } else {
                $qtyInput.data('last-warned-qty', null);
                if (!isNaN(qtyVal) && qtyVal > 0) {
                    $qtyInput.removeClass('is-invalid border-danger text-danger');
                }
                return true;
            }
        }

        // Real-time calculation triggers and stock checking
        $('#items-body').on('input change keyup', '.item-qty', function () {
            checkQtyStockLimit($(this), true);
            recalcRow($(this).closest('tr'));
        });

        $('#items-body').on('input change', '.item-cost, .item-gst-percent', function () {
            recalcRow($(this).closest('tr'));
        });

        // Keydown on .item-qty: block Tab & Enter if quantity > available or <= 0
        $('#items-body').on('keydown', '.item-qty', function (e) {
            let isMovingForward = (e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter';
            if (isMovingForward) {
                let $qty = $(this);
                let $row = $qty.closest('tr.item-row');
                let qtyVal = parseFloat($qty.val());
                let availRaw = $qty.attr('data-available-qty') !== undefined ? $qty.attr('data-available-qty') : $row.attr('data-available-qty');
                let maxAvail = (availRaw !== undefined && availRaw !== '' && !isNaN(parseFloat(availRaw))) ? parseFloat(availRaw) : null;

                if (isNaN(qtyVal) || qtyVal <= 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    $qty.addClass('is-invalid border-danger text-danger').focus().select();
                    if (window.toastr) {
                        toastr.clear();
                        toastr.warning('Valid quantity (greater than 0) enter karein tabhi aage ja sakte hain.', 'Invalid Quantity');
                    }
                    return false;
                }

                if (maxAvail !== null && qtyVal > maxAvail) {
                    e.preventDefault();
                    e.stopPropagation();
                    $qty.addClass('is-invalid border-danger text-danger').focus().select();
                    if (window.toastr) {
                        toastr.clear();
                        toastr.error(`Available stock (${maxAvail.toFixed(3)}) se jyada damage quantity enter nahi kar sakte! Aage badhne se pehle quantity sahi karein.`, 'Stock Limit Exceeded');
                    }
                    return false;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    let $cost = $row.find('.item-cost');
                    if ($cost.length) {
                        $cost.focus().select();
                    }
                }
            }
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

        // Autofocus first editable header field (entry_date) on page load
        setTimeout(function () {
            let $first = $('#entry_date');
            if ($first.length) $first.focus();
        }, 150);

        // Form submit validation and empty row pruning
        $('#damage-stock-form').on('submit', function (e) {
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
                    $row.find('.item-qty').addClass('is-invalid border-danger text-danger').focus().select();
                    if (window.toastr) {
                        toastr.warning('Please enter a valid quantity greater than 0 for: ' + desc);
                    }
                    return false;
                }

                let availRaw = $row.find('.item-qty').attr('data-available-qty') || $row.attr('data-available-qty');
                let maxAvail = (availRaw !== undefined && availRaw !== '' && !isNaN(parseFloat(availRaw))) ? parseFloat(availRaw) : null;
                if (maxAvail !== null && qty > maxAvail) {
                    e.preventDefault();
                    hasError = true;
                    let desc = $row.find('.item-desc').val() || 'selected item';
                    $row.find('.item-qty').addClass('is-invalid border-danger text-danger').focus().select();
                    if (window.toastr) {
                        toastr.error(`Available stock (${maxAvail.toFixed(3)}) se jyada damage quantity nahi daal sakte for: ${desc}`);
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
                        $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        recalcTotals();
    })();
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
<script>
    $(document).ready(function () {
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#items-table',
                minHeight: 180
            });
        }
    });
</script>
@endpush
