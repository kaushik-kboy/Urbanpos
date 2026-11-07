@extends('adminlte::page')

@section('title', 'Inter-Branch Stock Transfer Matchmaker')

@push('css')
<style>
body, .content-wrapper {
    color: #0f172a !important;
}

.page-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    font-size: 1.6rem;
}
.page-subheading {
    color: #1e293b !important;
    font-weight: 600;
    font-size: 0.85rem;
}

.modern-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.modern-card-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-card-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.modern-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.modern-table thead th {
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 0.72rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.06em !important;
    text-transform: uppercase !important;
    padding: 0.85rem 1rem !important;
    border: none !important;
    white-space: nowrap;
}
.modern-table tbody tr {
    transition: background-color 0.1s ease;
}
.modern-table tbody tr:nth-of-type(even) {
    background-color: #f8fafc;
}
.modern-table tbody tr:hover {
    background-color: #e2e8f0 !important;
}
.modern-table tbody td {
    padding: 0.85rem 1rem !important;
    vertical-align: middle !important;
    border-top: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    font-size: 0.85rem;
}

.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}
.btn-action-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}
.btn-action-outline {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    color: #0f172a !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}
.btn-action-outline:hover {
    background: #0f172a;
    color: #ffffff !important;
}

/* Floating Sticky Bulk Action Bar */
.sticky-bulk-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #0f172a !important;
    color: #ffffff !important;
    border-top: 3px solid #3b82f6;
    padding: 0.75rem 1.75rem;
    box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.35);
    z-index: 1045;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}
.sticky-bulk-bar.hidden-bar {
    transform: translateY(110%);
    opacity: 0;
    pointer-events: none;
}

/* Checkbox styling */
.row-transfer-checkbox {
    width: 17px;
    height: 17px;
    cursor: pointer;
    accent-color: #0f172a;
}
.table-checkbox-header {
    width: 17px;
    height: 17px;
    cursor: pointer;
    accent-color: #ffffff;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="page-heading m-0">
                <i class="fas fa-random text-success mr-2"></i> Smart Inter-Branch Matchmaker
            </h1>
            <div class="page-subheading mt-1">
                Pairs destination stores in shortage (Critical / Low) with surplus stores (Healthy / Overstock)
            </div>
        </div>
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-info font-weight-bold shadow-sm text-white mr-2 btn-bulk-transfer-trigger d-none" id="btnTopBulkTransfer">
                <i class="fas fa-random mr-1"></i> Bulk Create Transfer(s) (<span class="selected-count-span">0</span>)
            </button>
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-outline shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    <div id="transferAlert" class="alert alert-dark alert-dismissible fade show d-none border-dark shadow-sm mb-3 font-weight-bold" style="background:#0f172a; color:#ffffff; border-radius:10px;" role="alert">
        <i class="fas fa-check-circle mr-1 text-success"></i> <span id="transferAlertMessage"></span>
        <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    {{-- Filter Toolbar Card --}}
    <div class="modern-card mb-3 p-3">
        <div class="row align-items-center">
            <div class="col-md-4 mb-2 mb-md-0">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <input type="text" id="transferSearchInput" class="form-control border-left-0 font-weight-bold text-dark" placeholder="Filter by SKU or Item Name...">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="btnClearSearch" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                <div class="d-flex align-items-center">
                    <label for="filterDestBranch" class="mr-2 mb-0 font-weight-bold text-xs text-uppercase text-secondary text-nowrap">
                        <i class="fas fa-map-marker-alt text-danger mr-1"></i> Deficit:
                    </label>
                    <select id="filterDestBranch" class="form-control form-control-sm font-weight-bold">
                        <option value="all">All Destination Stores</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $activeBranchId == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                <div class="d-flex align-items-center">
                    <label for="filterSourceBranch" class="mr-2 mb-0 font-weight-bold text-xs text-uppercase text-secondary text-nowrap">
                        <i class="fas fa-warehouse text-success mr-1"></i> Surplus:
                    </label>
                    <select id="filterSourceBranch" class="form-control form-control-sm font-weight-bold">
                        <option value="all">All Surplus Stores</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2 text-right">
                <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold" id="btnResetFilters" title="Reset all filters">
                    <i class="fas fa-undo mr-1"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Main Recommendations Table Card --}}
    <div class="modern-card">
        <div class="modern-card-header flex-wrap">
            <div class="d-flex align-items-center mb-1 mb-md-0">
                <h3 class="modern-card-title mr-3">
                    <i class="fas fa-arrows-split-up-and-left mr-2 text-success"></i> Transfer Recommendations
                </h3>
                <span class="badge badge-dark px-3 py-2 font-weight-bold" id="opportunityCountBadge" style="border-radius: 6px;">
                    <span id="visibleRowCount">{{ $transferRecommendations->count() }}</span> Opportunities Found
                </span>
            </div>
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold mr-2" id="btnSelectAllVisible">
                    <i class="fas fa-check-double mr-1"></i> Select All
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2 d-none" id="btnDeselectAll">
                    <i class="fas fa-times mr-1"></i> Deselect
                </button>
                <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm text-white btn-bulk-transfer-trigger" id="btnCardHeaderBulkTransfer">
                    <i class="fas fa-random mr-1"></i> Bulk Create Transfer(s) (<span class="selected-count-span">0</span>)
                </button>
            </div>
        </div>
        <div class="p-0 table-responsive">
            <table class="modern-table" id="transfersTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 45px;">
                            <input type="checkbox" id="checkAllRows" class="table-checkbox-header" title="Select / Deselect All Visible">
                        </th>
                        <th>Item SKU & Name</th>
                        <th>Deficit Branch (Destination)</th>
                        <th>Current Deficit Stock</th>
                        <th>Surplus Branch (Source)</th>
                        <th>Available Surplus Stock</th>
                        <th class="text-center" style="width: 150px;">Transfer Qty</th>
                        <th class="text-center" style="width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody id="transfersTableBody">
                    @forelse($transferRecommendations as $transfer)
                        <tr class="transfer-data-row"
                            id="transfer-row-{{ $transfer->item_id }}-{{ $transfer->source_branch_id }}-{{ $transfer->dest_branch_id }}"
                            data-item-id="{{ $transfer->item_id }}"
                            data-item-name="{{ $transfer->item_name }}"
                            data-item-code="{{ $transfer->item_code }}"
                            data-dest-branch-id="{{ $transfer->dest_branch_id }}"
                            data-dest-branch-name="{{ $transfer->dest_branch_name }}"
                            data-dest-stock="{{ (int)$transfer->dest_stock }}"
                            data-source-branch-id="{{ $transfer->source_branch_id }}"
                            data-source-branch-name="{{ $transfer->source_branch_name }}"
                            data-source-stock="{{ (int)$transfer->source_stock }}"
                            data-suggested-qty="{{ $transfer->suggested_transfer_qty }}">
                            <td class="text-center">
                                <input type="checkbox"
                                    class="row-transfer-checkbox"
                                    data-item-id="{{ $transfer->item_id }}"
                                    data-source-branch-id="{{ $transfer->source_branch_id }}"
                                    data-dest-branch-id="{{ $transfer->dest_branch_id }}"
                                    title="Select for bulk transfer">
                            </td>
                            <td>
                                <strong class="text-dark item-search-target" style="font-size: 0.9rem;">{{ $transfer->item_name }}</strong><br>
                                <span class="badge badge-dark px-2 py-1 font-weight-bold text-xs mt-1 sku-search-target" style="border-radius: 4px;">SKU: {{ $transfer->item_code }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light border border-dark text-danger font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <i class="fas fa-map-marker-alt mr-1"></i> {{ $transfer->dest_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $transfer->dest_status === 'Critical' ? 'danger' : 'warning' }} px-2 py-1 font-weight-bold">
                                    {{ (int)$transfer->dest_stock }} units ({{ $transfer->dest_status }})
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-light border border-dark text-success font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <i class="fas fa-warehouse mr-1"></i> {{ $transfer->source_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success px-2 py-1 font-weight-bold">
                                    {{ (int)$transfer->source_stock }} units ({{ $transfer->source_status }})
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="input-group input-group-sm mx-auto" style="max-width: 125px;">
                                    <input type="number"
                                        class="form-control form-control-sm text-center font-weight-bold row-transfer-qty"
                                        value="{{ $transfer->suggested_transfer_qty }}"
                                        min="1"
                                        max="{{ (int)$transfer->source_stock }}"
                                        data-item-id="{{ $transfer->item_id }}"
                                        data-source-branch-id="{{ $transfer->source_branch_id }}"
                                        data-dest-branch-id="{{ $transfer->dest_branch_id }}"
                                        title="Enter transfer quantity (Max: {{ (int)$transfer->source_stock }})">
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-light text-muted text-xs px-2 font-weight-bold">Qty</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-action-dark btn-sm shadow-sm btn-single-transfer"
                                    data-item-id="{{ $transfer->item_id }}"
                                    data-source-branch-id="{{ $transfer->source_branch_id }}"
                                    data-dest-branch-id="{{ $transfer->dest_branch_id }}"
                                    data-item-name="{{ $transfer->item_name }}"
                                    data-source-branch-name="{{ $transfer->source_branch_name }}"
                                    data-dest-branch-name="{{ $transfer->dest_branch_name }}">
                                    <i class="fas fa-dolly mr-1 text-success"></i> Create Transfer
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyTableRow">
                            <td colspan="8" class="text-center py-5 text-dark font-weight-bold">
                                <i class="fas fa-check-circle text-success mb-2" style="font-size: 2.5rem;"></i><br>
                                <span style="font-size: 1rem;">No Inter-Branch Transfer Match Opportunities Needed</span><br>
                                <small class="text-dark">All stores have balanced inventory across active SKUs.</small>
                            </td>
                        </tr>
                    @endforelse
                    <tr id="noMatchingRows" class="d-none">
                        <td colspan="8" class="text-center py-5 text-dark font-weight-bold">
                            <i class="fas fa-search text-secondary mb-2" style="font-size: 2rem;"></i><br>
                            <span>No transfer recommendations match your search/filter criteria.</span><br>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="$('#btnResetFilters').click();">
                                Reset Filters
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Floating Sticky Bulk Action Bar --}}
    <div id="stickyBulkBar" class="sticky-bulk-bar hidden-bar">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center p-0">
            <div class="d-flex align-items-center py-1">
                <span class="badge badge-light border border-dark text-dark px-3 py-2 font-weight-bold mr-3 shadow-sm" style="font-size: 0.95rem; border-radius: 6px;">
                    <i class="fas fa-check-square mr-1 text-primary"></i> <span id="selectedCountBadge">0</span> Items Selected
                </span>
                <span class="text-white mr-4 font-weight-bold" style="font-size: 0.92rem;">
                    Total Transfer Units: <strong id="selectedTotalUnits" class="text-warning ml-1">0</strong>
                </span>
                <span class="text-white font-weight-bold" style="font-size: 0.92rem;">
                    Store Routes: <strong id="selectedRoutesCount" class="text-info ml-1">0 Challan(s)</strong>
                </span>
            </div>
            <div class="d-flex align-items-center py-1">
                <button type="button" class="btn btn-outline-light btn-sm mr-2 font-weight-bold" id="btnClearStickySelection" title="Clear all currently selected items">
                    <i class="fas fa-times mr-1"></i> Deselect All
                </button>
                <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm text-white btn-bulk-transfer-trigger" id="btnStickyBulkTransfer">
                    <i class="fas fa-random mr-1"></i> Bulk Create Transfer(s) (<span class="selected-count-span">0</span>)
                </button>
            </div>
        </div>
    </div>

    {{-- Bulk Stock Transfers Review & Route Grouping Modal --}}
    <div class="modal fade" id="bulkTransferModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="bulkTransferModalTitle">
                        <i class="fas fa-random text-info mr-2"></i> Review & Create Bulk Stock Transfers
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                    <div class="alert alert-info py-2 px-3 mb-3 font-weight-bold text-xs" style="border-radius: 8px;">
                        <i class="fas fa-info-circle mr-1"></i> Review the selected transfer items below. Items are automatically grouped into consolidated Stock Transfer Challans by source and destination store route. You can adjust transfer quantities or exclude items before confirming.
                    </div>
                    <div id="bulkTransferModalRoutesContainer">
                        {{-- Injected dynamically --}}
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="font-weight-bold text-dark" id="bulkTransferOverallSummary">
                            Challans: <strong>0</strong> &bull; Total Units: <strong>0</strong>
                        </span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-action-outline btn-sm font-weight-bold mr-2" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-info btn-sm font-weight-bold text-white shadow-sm" id="btnConfirmBulkTransfer">
                            <i class="fas fa-dolly mr-1"></i> Confirm & Dispatch All Stock Transfers
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    // Selected transfer items key: `${itemId}-${sourceBranchId}-${destBranchId}`
    let selectedTransfers = {};

    $(document).ready(function() {
        // Search & Filters
        $('#transferSearchInput').on('input', applyFilters);
        $('#filterDestBranch').on('change', applyFilters);
        $('#filterSourceBranch').on('change', applyFilters);

        $('#btnClearSearch').on('click', function() {
            $('#transferSearchInput').val('');
            applyFilters();
        });

        $('#btnResetFilters').on('click', function() {
            $('#transferSearchInput').val('');
            $('#filterDestBranch').val('all');
            $('#filterSourceBranch').val('all');
            applyFilters();
        });

        // Header Checkbox (Select all visible)
        $('#checkAllRows').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.transfer-data-row:visible').each(function() {
                const $row = $(this);
                const $cb = $row.find('.row-transfer-checkbox');
                $cb.prop('checked', isChecked);
                const key = getRowKey($row);
                if (isChecked) {
                    selectedTransfers[key] = extractRowData($row);
                } else {
                    delete selectedTransfers[key];
                }
            });
            updateSelectionState();
        });

        // Row Checkbox change
        $(document).on('change', '.row-transfer-checkbox', function() {
            const $row = $(this).closest('.transfer-data-row');
            const key = getRowKey($row);
            if ($(this).is(':checked')) {
                selectedTransfers[key] = extractRowData($row);
            } else {
                delete selectedTransfers[key];
            }
            updateSelectionState();
        });

        // Transfer Qty input in row change
        $(document).on('input change', '.row-transfer-qty', function() {
            const $row = $(this).closest('.transfer-data-row');
            const key = getRowKey($row);
            let val = parseInt($(this).val()) || 0;
            const max = parseInt($row.data('source-stock')) || 999999;

            if (val > max) {
                val = max;
                $(this).val(val);
            }
            if (val < 1 && $(this).val() !== '') {
                val = 1;
                $(this).val(val);
            }

            if (selectedTransfers[key]) {
                selectedTransfers[key].qty = val;
                updateSelectionState();
            }
        });

        // Select All Visible Button
        $('#btnSelectAllVisible').on('click', function() {
            $('#checkAllRows').prop('checked', true).trigger('change');
        });

        // Deselect All Buttons
        $('#btnDeselectAll, #btnClearStickySelection').on('click', function() {
            selectedTransfers = {};
            $('#checkAllRows').prop('checked', false);
            $('.row-transfer-checkbox').prop('checked', false);
            updateSelectionState();
        });

        // Single Row Transfer Button
        $(document).on('click', '.btn-single-transfer', function() {
            const $btn = $(this);
            const $row = $btn.closest('.transfer-data-row');
            const itemId = parseInt($btn.data('item-id'));
            const fromBranchId = parseInt($btn.data('source-branch-id'));
            const toBranchId = parseInt($btn.data('dest-branch-id'));
            const itemName = $btn.data('item-name');
            const fromBranchName = $btn.data('source-branch-name');
            const toBranchName = $btn.data('dest-branch-name');

            const $qtyInput = $row.find('.row-transfer-qty');
            let qty = parseInt($qtyInput.val()) || 0;
            const maxStock = parseInt($row.data('source-stock')) || 0;

            if (qty <= 0) {
                alert('Please enter a transfer quantity of at least 1 unit.');
                $qtyInput.focus();
                return;
            }

            if (qty > maxStock) {
                alert(`Transfer quantity (${qty}) cannot exceed available surplus stock (${maxStock} units).`);
                $qtyInput.val(maxStock).focus();
                return;
            }

            if (!confirm(`Dispatch ${qty} unit(s) of "${itemName}" from ${fromBranchName} to ${toBranchName}?`)) {
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating...');

            $.ajax({
                url: "{{ route('inventory.replenishment.create-transfer') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    from_branch_id: fromBranchId,
                    to_branch_id: toBranchId,
                    items: [{ item_id: itemId, qty: qty }]
                },
                success: function(res) {
                    $('#transferAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Transfer #${res.transfer_number}</a>`);
                    $('#transferAlert').removeClass('d-none');

                    const key = getRowKey($row);
                    delete selectedTransfers[key];
                    updateSelectionState();

                    $row.fadeOut(300, function() {
                        $(this).remove();
                        refreshVisibleCount();
                    });
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.';
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="fas fa-dolly mr-1 text-success"></i> Create Transfer');
                }
            });
        });

        // Open Bulk Transfer Review Modal
        $('.btn-bulk-transfer-trigger').on('click', function() {
            const keys = Object.keys(selectedTransfers);
            if (keys.length === 0) {
                alert('Please select at least one item from the table using the checkboxes to perform a bulk transfer.');
                return;
            }

            renderBulkTransferModal();
            $('#bulkTransferModal').modal('show');
        });

        // Confirm & Dispatch Bulk Transfers from Modal
        $('#btnConfirmBulkTransfer').on('click', function() {
            const payload = collectBulkTransferPayload();
            if (payload.length === 0) {
                alert('No valid transfer items found. Please make sure transfer quantities are greater than zero.');
                return;
            }

            let totalChallans = payload.length;
            let totalUnits = 0;
            payload.forEach(route => {
                route.items.forEach(it => { totalUnits += it.qty; });
            });

            if (!confirm(`Are you sure you want to dispatch ${totalChallans} Stock Transfer Challan(s) totaling ${totalUnits} units?`)) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Dispatching Transfers...');

            $.ajax({
                url: "{{ route('inventory.replenishment.bulk-create-transfer') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    transfers: payload
                },
                success: function(res) {
                    $('#bulkTransferModal').modal('hide');

                    let linksHtml = '';
                    if (res.created_transfers && res.created_transfers.length > 0) {
                        linksHtml = '<div class="mt-2">';
                        res.created_transfers.forEach(function(tr) {
                            linksHtml += `<a href="${tr.redirect_url}" class="badge badge-light border border-dark text-dark mr-2 mb-1 p-2 font-weight-bold" target="_blank" style="font-size:0.85rem;"><i class="fas fa-random text-info mr-1"></i>${escapeHtml(tr.transfer_number)} (${escapeHtml(tr.from_branch_name)} &rarr; ${escapeHtml(tr.to_branch_name)}: ${tr.total_qty} units)</a> `;
                        });
                        linksHtml += '</div>';
                    }

                    $('#transferAlertMessage').html(`<strong>${escapeHtml(res.message)}</strong> ${linksHtml}`);
                    $('#transferAlert').removeClass('d-none');

                    // Remove transferred rows from table
                    Object.keys(selectedTransfers).forEach(key => {
                        $(`#transfer-row-${key}`).fadeOut(300, function() {
                            $(this).remove();
                            refreshVisibleCount();
                        });
                    });

                    selectedTransfers = {};
                    $('#checkAllRows').prop('checked', false);
                    updateSelectionState();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to create bulk Stock Transfers.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-dolly mr-1"></i> Confirm & Dispatch All Stock Transfers');
                }
            });
        });
    });

    // Helper: Row unique key
    function getRowKey($row) {
        return `${$row.data('item-id')}-${$row.data('source-branch-id')}-${$row.data('dest-branch-id')}`;
    }

    // Helper: Extract data from row
    function extractRowData($row) {
        return {
            itemId: parseInt($row.data('item-id')),
            itemName: $row.data('item-name'),
            itemCode: $row.data('item-code'),
            fromBranchId: parseInt($row.data('source-branch-id')),
            fromBranchName: $row.data('source-branch-name'),
            toBranchId: parseInt($row.data('dest-branch-id')),
            toBranchName: $row.data('dest-branch-name'),
            destStock: parseInt($row.data('dest-stock')) || 0,
            sourceStock: parseInt($row.data('source-stock')) || 0,
            qty: parseInt($row.find('.row-transfer-qty').val()) || 1
        };
    }

    // Update selection count badges and sticky bar
    function updateSelectionState() {
        const keys = Object.keys(selectedTransfers);
        const count = keys.length;
        let totalUnits = 0;
        const routes = {};

        keys.forEach(key => {
            const item = selectedTransfers[key];
            totalUnits += item.qty;
            const routeKey = `${item.fromBranchId}_to_${item.toBranchId}`;
            routes[routeKey] = true;
        });

        const routesCount = Object.keys(routes).length;

        $('.selected-count-span').text(count);
        $('#selectedCountBadge').text(count);
        $('#selectedTotalUnits').text(totalUnits);
        $('#selectedRoutesCount').text(`${routesCount} Challan(s)`);

        if (count > 0) {
            $('#stickyBulkBar').removeClass('hidden-bar');
            $('#btnTopBulkTransfer').removeClass('d-none');
            $('#btnDeselectAll').removeClass('d-none');
        } else {
            $('#stickyBulkBar').addClass('hidden-bar');
            $('#btnTopBulkTransfer').addClass('d-none');
            $('#btnDeselectAll').addClass('d-none');
            $('#checkAllRows').prop('checked', false);
        }
    }

    // Apply Live Table Filters (Search + Branches)
    function applyFilters() {
        const query = $.trim($('#transferSearchInput').val()).toLowerCase();
        const destBranch = $('#filterDestBranch').val();
        const sourceBranch = $('#filterSourceBranch').val();

        let visibleCount = 0;

        $('.transfer-data-row').each(function() {
            const $row = $(this);
            const itemName = ($row.data('item-name') || '').toLowerCase();
            const itemCode = ($row.data('item-code') || '').toLowerCase();
            const rowDestBranch = String($row.data('dest-branch-id'));
            const rowSourceBranch = String($row.data('source-branch-id'));

            let matchesSearch = true;
            if (query.length > 0) {
                matchesSearch = itemName.includes(query) || itemCode.includes(query);
            }

            let matchesDest = (destBranch === 'all' || rowDestBranch === destBranch);
            let matchesSource = (sourceBranch === 'all' || rowSourceBranch === sourceBranch);

            if (matchesSearch && matchesDest && matchesSource) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        $('#visibleRowCount').text(visibleCount);

        if (visibleCount === 0 && $('.transfer-data-row').length > 0) {
            $('#noMatchingRows').removeClass('d-none');
        } else {
            $('#noMatchingRows').addClass('d-none');
        }
    }

    function refreshVisibleCount() {
        const remaining = $('.transfer-data-row').length;
        $('#visibleRowCount').text(remaining);
        if (remaining === 0) {
            $('#emptyTableRow').removeClass('d-none');
        }
    }

    // Render Bulk Transfer Modal Grouped by Store Route
    function renderBulkTransferModal() {
        const keys = Object.keys(selectedTransfers);
        const routesMap = {};

        keys.forEach(key => {
            const item = selectedTransfers[key];
            const routeKey = `${item.fromBranchId}_to_${item.toBranchId}`;
            if (!routesMap[routeKey]) {
                routesMap[routeKey] = {
                    fromBranchId: item.fromBranchId,
                    fromBranchName: item.fromBranchName,
                    toBranchId: item.toBranchId,
                    toBranchName: item.toBranchName,
                    items: []
                };
            }
            routesMap[routeKey].items.push(item);
        });

        let html = '';
        Object.keys(routesMap).forEach(routeKey => {
            const route = routesMap[routeKey];
            html += `
                <div class="card border border-dark shadow-sm mb-3 bulk-route-card" data-route-key="${escapeHtml(routeKey)}" style="border-radius: 10px; overflow: hidden;">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="font-weight-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-warehouse text-success mr-1"></i> <span class="text-white">${escapeHtml(route.fromBranchName)}</span>
                            <i class="fas fa-long-arrow-alt-right mx-2 text-warning"></i>
                            <i class="fas fa-map-marker-alt text-danger mr-1"></i> <span class="text-white">${escapeHtml(route.toBranchName)}</span>
                        </div>
                        <span class="badge badge-info px-2 py-1 font-weight-bold">
                            Consolidated Stock Transfer Challan
                        </span>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-striped text-sm mb-0">
                            <thead class="bg-light text-xs font-weight-bold text-uppercase text-secondary">
                                <tr>
                                    <th>Item SKU & Name</th>
                                    <th class="text-center" style="width: 130px;">Deficit Stock</th>
                                    <th class="text-center" style="width: 140px;">Surplus Stock</th>
                                    <th class="text-center" style="width: 160px;">Transfer Qty</th>
                                    <th class="text-center" style="width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            route.items.forEach(it => {
                const itemKey = `${it.itemId}-${it.fromBranchId}-${it.toBranchId}`;
                html += `
                    <tr id="modal-item-row-${escapeHtml(itemKey)}" class="modal-transfer-row"
                        data-item-key="${escapeHtml(itemKey)}"
                        data-item-id="${it.itemId}"
                        data-from-branch-id="${it.fromBranchId}"
                        data-to-branch-id="${it.toBranchId}">
                        <td>
                            <strong class="text-dark">${escapeHtml(it.itemName)}</strong><br>
                            <span class="badge badge-dark text-xs px-2 py-0">SKU: ${escapeHtml(it.itemCode)}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light border border-dark text-danger font-weight-bold px-2 py-1">
                                ${it.destStock} units
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light border border-dark text-success font-weight-bold px-2 py-1">
                                ${it.sourceStock} units
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="input-group input-group-sm mx-auto" style="max-width: 130px;">
                                <input type="number"
                                    class="form-control form-control-sm text-center font-weight-bold modal-transfer-qty-input"
                                    value="${it.qty}"
                                    min="1"
                                    max="${it.sourceStock}"
                                    data-item-key="${escapeHtml(itemKey)}">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-light text-muted text-xs">Units</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeModalTransferItem('${escapeHtml(itemKey)}') title="Remove from this transfer">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        $('#bulkTransferModalRoutesContainer').html(html);
        updateModalSummary();

        // Listen for input changes in modal
        $('.modal-transfer-qty-input').on('input change', function() {
            const itemKey = $(this).data('item-key');
            let val = parseInt($(this).val()) || 0;
            const max = parseInt($(this).attr('max')) || 999999;
            if (val > max) { val = max; $(this).val(val); }
            if (val < 1 && $(this).val() !== '') { val = 1; $(this).val(val); }

            if (selectedTransfers[itemKey]) {
                selectedTransfers[itemKey].qty = val;
                // Sync with table row input
                $(`#transfer-row-${itemKey}`).find('.row-transfer-qty').val(val);
                updateSelectionState();
            }
            updateModalSummary();
        });
    }

    // Remove item row from modal
    function removeModalTransferItem(itemKey) {
        delete selectedTransfers[itemKey];
        $(`#modal-item-row-${itemKey}`).remove();

        // Uncheck row in table
        $(`#transfer-row-${itemKey}`).find('.row-transfer-checkbox').prop('checked', false);

        // Remove empty route cards
        $('.bulk-route-card').each(function() {
            if ($(this).find('.modal-transfer-row').length === 0) {
                $(this).remove();
            }
        });

        updateSelectionState();
        updateModalSummary();

        if (Object.keys(selectedTransfers).length === 0) {
            $('#bulkTransferModal').modal('hide');
        }
    }

    // Modal summary update
    function updateModalSummary() {
        const routes = {};
        let totalUnits = 0;

        $('.modal-transfer-row').each(function() {
            const fromId = $(this).data('from-branch-id');
            const toId = $(this).data('to-branch-id');
            const qty = parseInt($(this).find('.modal-transfer-qty-input').val()) || 0;
            const routeKey = `${fromId}_to_${toId}`;
            routes[routeKey] = true;
            totalUnits += qty;
        });

        const challanCount = Object.keys(routes).length;
        $('#bulkTransferOverallSummary').html(`
            Will create: <strong class="text-info">${challanCount}</strong> Stock Transfer Challan(s) &bull;
            Total Transfer Units: <strong class="text-warning">${totalUnits}</strong>
        `);
    }

    // Collect payload grouped by route for bulkCreateTransfer
    function collectBulkTransferPayload() {
        const grouped = {};

        $('.modal-transfer-row').each(function() {
            const fromBranchId = parseInt($(this).data('from-branch-id'));
            const toBranchId = parseInt($(this).data('to-branch-id'));
            const itemId = parseInt($(this).data('item-id'));
            const qty = parseInt($(this).find('.modal-transfer-qty-input').val()) || 0;

            if (fromBranchId && toBranchId && itemId && qty > 0) {
                const routeKey = `${fromBranchId}_${toBranchId}`;
                if (!grouped[routeKey]) {
                    grouped[routeKey] = {
                        from_branch_id: fromBranchId,
                        to_branch_id: toBranchId,
                        items: []
                    };
                }
                grouped[routeKey].items.push({
                    item_id: itemId,
                    qty: qty
                });
            }
        });

        return Object.values(grouped);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
@stop
