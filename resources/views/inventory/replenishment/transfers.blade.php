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

/* Nav Pills Styling */
.nav-pills .nav-link {
    border-radius: 8px;
    padding: 0.55rem 1.15rem;
    color: #475569;
    background: #f1f5f9;
    margin-right: 0.65rem;
    border: 1.5px solid #cbd5e1;
    font-weight: 700;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.nav-pills .nav-link:hover {
    color: #0f172a;
    border-color: #94a3b8;
}
.nav-pills .nav-link.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    box-shadow: 0 4px 8px -1px rgba(15, 23, 42, 0.25);
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

@php
    $currentUser = auth()->user();
    $isGlobalAdmin = ! $currentUser || $currentUser->branch_id === null || $currentUser->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']) || $currentUser->email === 'admin@urbanpos.com';
    $userBranchId = $currentUser?->branch_id ? (int)$currentUser->branch_id : null;
@endphp

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="page-heading m-0">
                <i class="fas fa-random text-success mr-2"></i> Smart Inter-Branch Matchmaker
            </h1>
            <div class="page-subheading mt-1">
                2-Way Verified Stock Transfers: Match Deficits &rarr; Dispatch with Staff Attribution &rarr; Receive & Accept
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

    {{-- Main Module Nav Pills --}}
    <ul class="nav nav-pills mt-2 mb-1" id="transferTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-recommendations-btn" data-toggle="pill" href="#tab-recommendations" role="tab" aria-controls="tab-recommendations" aria-selected="true">
                <i class="fas fa-arrows-split-up-and-left mr-1 text-success"></i> Transfer Recommendations
                <span class="badge badge-light border ml-1 font-weight-bold" id="tabRecommendationsCount">{{ $transferRecommendations->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-intransit-btn" data-toggle="pill" href="#tab-intransit" role="tab" aria-controls="tab-intransit" aria-selected="false">
                <i class="fas fa-truck mr-1 text-warning"></i> In-Transit Transfers (Awaiting Receipt)
                <span class="badge badge-warning text-dark ml-1 font-weight-bold" id="tabInTransitCount">{{ $inTransitTransfers->count() }}</span>
            </a>
        </li>
    </ul>
@stop

@section('content')
    <div id="transferAlert" class="alert alert-dark alert-dismissible fade show d-none border-dark shadow-sm mb-3 font-weight-bold" style="background:#0f172a; color:#ffffff; border-radius:10px;" role="alert">
        <i class="fas fa-check-circle mr-1 text-success"></i> <span id="transferAlertMessage"></span>
        <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="tab-content" id="transferTabContent">
        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: TRANSFER RECOMMENDATIONS                                --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="tab-recommendations" role="tabpanel" aria-labelledby="tab-recommendations-btn">
            {{-- Filter Toolbar Card --}}
            <div class="modern-card mb-3 p-3">
                <div class="row align-items-center">
                    <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
                        <label for="transferSearchInput" class="mb-1 font-weight-bold text-xs text-uppercase text-secondary">
                            <i class="fas fa-search text-primary mr-1"></i> Search SKU / Name / Barcode:
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="transferSearchInput" class="form-control font-weight-bold text-dark" placeholder="Search SKU, Barcode, or Name..." autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btnClearSearch" title="Clear Search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 mb-lg-0">
                        <label for="filterSourceBranch" class="mb-1 font-weight-bold text-xs text-uppercase text-secondary" title="Surplus Store (Sending)">
                            <i class="fas fa-warehouse text-warning mr-1"></i> Surplus (From):
                        </label>
                        <select id="filterSourceBranch" class="form-control form-control-sm font-weight-bold">
                            <option value="all">All Surplus Stores</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 mb-lg-0">
                        <label for="filterDestBranch" class="mb-1 font-weight-bold text-xs text-uppercase text-secondary" title="Deficit Store (Receiving)">
                            <i class="fas fa-store text-danger mr-1"></i> Deficit (To):
                        </label>
                        <select id="filterDestBranch" class="form-control form-control-sm font-weight-bold">
                            <option value="all">All Deficit Stores</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $activeBranchId == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 mb-lg-0">
                        <label for="filterDeficitStatus" class="mb-1 font-weight-bold text-xs text-uppercase text-secondary" title="Deficit Urgency Status">
                            <i class="fas fa-signal text-info mr-1"></i> Deficit Status:
                        </label>
                        <select id="filterDeficitStatus" class="form-control form-control-sm font-weight-bold">
                            <option value="all">All Statuses</option>
                            <option value="Critical">🔴 Critical Only</option>
                            <option value="Low">🟡 Low Only</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 text-lg-right text-left mt-2 mt-md-0 d-flex align-items-end justify-content-lg-end" style="padding-top: 1.35rem;">
                        <button type="button" class="btn btn-primary btn-sm font-weight-bold mr-1" id="btnApplyFilters" title="Apply selected filters">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold" id="btnResetFilters" title="Reset all filters">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Recommendations Table Card --}}
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
                                    data-item-name="{{ e($transfer->item_name) }}"
                                    data-item-code="{{ e($transfer->item_code) }}"
                                    data-ean-upc-code="{{ e($transfer->ean_upc_code ?? '') }}"
                                    data-dest-branch-id="{{ $transfer->dest_branch_id }}"
                                    data-dest-branch-name="{{ e($transfer->dest_branch_name) }}"
                                    data-dest-stock="{{ (int)$transfer->dest_stock }}"
                                    data-dest-status="{{ e($transfer->dest_status ?? '') }}"
                                    data-source-branch-id="{{ $transfer->source_branch_id }}"
                                    data-source-branch-name="{{ e($transfer->source_branch_name) }}"
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
                                            data-item-code="{{ $transfer->item_code }}"
                                            data-source-branch-name="{{ $transfer->source_branch_name }}"
                                            data-dest-branch-name="{{ $transfer->dest_branch_name }}"
                                            data-dest-stock="{{ (int)$transfer->dest_stock }}"
                                            data-source-stock="{{ (int)$transfer->source_stock }}"
                                            data-suggested-qty="{{ $transfer->suggested_transfer_qty }}">
                                            <i class="fas fa-dolly mr-1 text-success"></i> Dispatch Stock
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

                {{-- Table Pagination Footer --}}
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center flex-wrap">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <span class="font-weight-bold text-dark text-sm mr-3" id="transfersPaginationInfo">Showing 0 of 0</span>
                        <div class="d-flex align-items-center">
                            <label class="small font-weight-bold mr-1 mb-0 text-muted">Per Page:</label>
                            <select id="transfersPerPage" class="form-control form-control-sm" style="width: 75px; height: 31px; border-radius: 6px; font-weight: 700;">
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <ul class="pagination pagination-sm m-0" id="transfersPaginationControls"></ul>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 2: IN-TRANSIT TRANSFERS (AWAITING STORE RECEIPT)           --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-intransit" role="tabpanel" aria-labelledby="tab-intransit-btn">
            <div class="modern-card">
                <div class="modern-card-header">
                    <div>
                        <h3 class="modern-card-title">
                            <i class="fas fa-truck text-warning mr-2"></i> In-Transit Transfers (Dispatched & Awaiting Store Receipt)
                        </h3>
                        <div class="text-muted text-xs font-weight-bold mt-1">
                            Transfers already dispatched by source branch. Receiving store staff verifies physical quantity, records staff attribution, and confirms inward receipt.
                        </div>
                    </div>
                    <span class="badge badge-warning text-dark px-3 py-2 font-weight-bold" style="border-radius: 6px;">
                        <span id="inTransitTableCount">{{ $inTransitTransfers->count() }}</span> Transfers In-Transit
                    </span>
                </div>
                <div class="p-0 table-responsive">
                    <table class="modern-table" id="inTransitTable">
                        <thead>
                            <tr>
                                <th>Challan No</th>
                                <th>Date & Time</th>
                                <th>From (Source Store)</th>
                                <th>To (Destination Store)</th>
                                <th>Dispatched By</th>
                                <th>Items / Qty</th>
                                <th>Dispatch Remarks</th>
                                <th class="text-center" style="width: 170px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="inTransitTableBody">
                            @forelse($inTransitTransfers as $tr)
                                @php
                                    $canReceive = $isGlobalAdmin || ($userBranchId && $userBranchId === (int)$tr->to_branch_id);
                                    $itemsPayload = $tr->items->map(function($line) {
                                        return [
                                            'id' => $line->id,
                                            'item_id' => $line->item_id,
                                            'name' => $line->item?->name ?? 'Item #'.$line->item_id,
                                            'code' => $line->item?->item_code ?? ($line->item?->ean_upc_code ?? ''),
                                            'batch_no' => $line->batch_no ?? 'Default',
                                            'exp_date' => $line->exp_date ? \Carbon\Carbon::parse($line->exp_date)->format('d-m-Y') : '—',
                                            'qty' => (float)$line->qty,
                                            'requested_qty' => (float)($line->requested_qty ?: $line->qty),
                                            'mrp' => (float)($line->mrp ?: ($line->item?->mrp ?: 0)),
                                        ];
                                    });
                                @endphp
                                <tr class="intransit-row" id="intransit-row-{{ $tr->id }}">
                                    <td>
                                        <a href="{{ route('inventory.stock-transfers.show', $tr->id) }}" class="font-weight-bold text-primary" target="_blank" title="View Transfer Details">
                                            {{ $tr->transfer_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark">{{ $tr->transfer_date->format('d-m-Y') }}</div>
                                        @if($tr->dispatched_at)
                                            <div class="text-muted text-xs">{{ \Carbon\Carbon::parse($tr->dispatched_at)->format('h:i A') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light border border-dark text-success font-weight-bold px-2 py-1">
                                            <i class="fas fa-warehouse mr-1"></i> {{ $tr->fromBranch?->name }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light border border-dark text-danger font-weight-bold px-2 py-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i> {{ $tr->toBranch?->name }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info px-2 py-1 font-weight-bold">
                                            <i class="fas fa-user-check mr-1"></i> {{ $tr->creator?->name ?: 'Staff' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-dark">{{ $tr->items->count() }} item(s) &bull; {{ (float)$tr->total_qty == (int)$tr->total_qty ? number_format($tr->total_qty, 0) : number_format($tr->total_qty, 2) }} units</strong>
                                        <div class="text-muted text-xs mt-1">
                                            @foreach($tr->items->take(2) as $itLine)
                                                <span class="d-inline-block mr-1">&bull; {{ Str::limit($itLine->item?->name, 22) }} ({{ (int)$itLine->qty }}u)</span>
                                            @endforeach
                                            @if($tr->items->count() > 2)
                                                <span class="text-secondary">+{{ $tr->items->count() - 2 }} more</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted font-italic">{{ Str::limit($tr->remarks, 40) ?: 'No remarks' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            @if($canReceive)
                                                <button type="button" class="btn btn-success font-weight-bold shadow-sm btn-open-receive-modal"
                                                    data-transfer-id="{{ $tr->id }}"
                                                    data-transfer-number="{{ $tr->transfer_number }}"
                                                    data-from-branch="{{ $tr->fromBranch?->name }}"
                                                    data-to-branch="{{ $tr->toBranch?->name }}"
                                                    data-creator-name="{{ $tr->creator?->name ?: 'Staff' }}"
                                                    data-dispatched-at="{{ $tr->dispatched_at ? \Carbon\Carbon::parse($tr->dispatched_at)->format('d-m-Y h:i A') : '' }}"
                                                    data-remarks="{{ e($tr->remarks ?? '') }}"
                                                    data-items="{{ json_encode($itemsPayload) }}"
                                                    title="Verify quantities and receive into {{ $tr->toBranch?->name }}">
                                                    <i class="fas fa-check-circle mr-1"></i> Receive
                                                </button>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1 mr-1" title="Only destination store staff can receive this transfer">
                                                    <i class="fas fa-lock mr-1"></i> {{ $tr->toBranch?->name }}
                                                </span>
                                            @endif
                                            <a href="{{ route('inventory.stock-transfers.print', $tr->id) }}" target="_blank" class="btn btn-outline-secondary font-weight-bold" title="Print Transfer Challan">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr id="emptyInTransitRow">
                                    <td colspan="8" class="text-center py-5 text-dark font-weight-bold">
                                        <i class="fas fa-box-open text-muted mb-2" style="font-size: 2.2rem;"></i><br>
                                        <span>No In-Transit Stock Transfers Pending Receipt</span><br>
                                        <small class="text-muted">All dispatched transfers have been received and reconciled.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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

    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL 1: SINGLE DISPATCH CONFIRMATION MODAL                           --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalDispatchTransfer" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <h5 class="modal-title font-weight-bold" style="font-size: 1.05rem;">
                        <i class="fas fa-truck-loading text-success mr-2"></i> Confirm Stock Transfer Dispatch
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    {{-- Route & Staff Attribution Bar --}}
                    <div class="card border mb-3 shadow-sm" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <div class="row align-items-center">
                                <div class="col-md-7 mb-2 mb-md-0">
                                    <div class="d-flex align-items-center font-weight-bold">
                                        <span class="badge badge-light border border-dark text-success px-2 py-1 mr-2" style="font-size: 0.88rem;">
                                            <i class="fas fa-warehouse mr-1"></i> <span id="dispModalFromBranch"></span>
                                        </span>
                                        <i class="fas fa-arrow-right text-muted mx-2"></i>
                                        <span class="badge badge-light border border-dark text-danger px-2 py-1 ml-2" style="font-size: 0.88rem;">
                                            <i class="fas fa-map-marker-alt mr-1"></i> <span id="dispModalToBranch"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-5 text-md-right">
                                    <span class="text-xs font-weight-bold text-secondary text-uppercase d-block mb-1">Dispatching Staff:</span>
                                    <span class="badge badge-dark px-3 py-1 font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fas fa-user-check text-info mr-1"></i> {{ auth()->user()->name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Product Details Card --}}
                    <div class="card border mb-3 shadow-sm" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1" id="dispModalItemName" style="font-size: 1.05rem;"></h6>
                                    <span class="badge badge-secondary px-2 py-1 font-weight-bold" id="dispModalItemCode"></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-muted font-weight-bold d-block">Recommended Deficit:</span>
                                    <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" id="dispModalRecommendedBadge" style="font-size: 0.9rem;">
                                        0 units
                                    </span>
                                </div>
                            </div>

                            <div class="row mt-3 pt-2 border-top">
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block font-weight-bold">Destination Stock:</small>
                                    <span class="font-weight-bold text-danger" id="dispModalDestStock">0 units</span>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block font-weight-bold">Surplus Available:</small>
                                    <span class="font-weight-bold text-success" id="dispModalSourceStock">0 units</span>
                                </div>
                                <div class="col-6 col-md-3 mt-2 mt-md-0">
                                    <small class="text-muted d-block font-weight-bold">Product MRP:</small>
                                    <span class="font-weight-bold text-dark" id="dispModalItemMrp">₹0.00</span>
                                </div>
                                <div class="col-6 col-md-3 mt-2 mt-md-0">
                                    <small class="text-muted d-block font-weight-bold">Transfer Type:</small>
                                    <span class="badge badge-info px-2 py-1">Replenishment</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Dispatch Quantities & Batches Form --}}
                    <div class="card border mb-3 shadow-sm" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <div class="row">
                                <div class="col-md-5 mb-3 mb-md-0">
                                    <label for="dispModalQtyInput" class="font-weight-bold text-dark text-sm mb-1">
                                        <i class="fas fa-boxes text-primary mr-1"></i> Dispatch Quantity: <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" id="dispModalQtyInput" class="form-control form-control-lg font-weight-bold text-dark text-center" min="1" step="1">
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-light font-weight-bold text-dark text-sm">Units</span>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-1 font-weight-bold" id="dispModalQtyHelp">Max available: 0 units</small>
                                </div>

                                <div class="col-md-7">
                                    <label for="dispModalBatchSelect" class="font-weight-bold text-dark text-sm mb-1">
                                        <i class="fas fa-layer-group text-info mr-1"></i> Select Source Branch Batch / Expiry:
                                    </label>
                                    <select id="dispModalBatchSelect" class="form-control font-weight-bold text-dark">
                                        <option value="">Loading available batches...</option>
                                    </select>
                                    <small class="text-muted d-block mt-1" id="dispModalBatchHelp">Active inventory batch in source store.</small>
                                </div>
                            </div>

                            {{-- Discrepancy Alert Box (Dynamic) --}}
                            <div id="dispModalDiscrepancyAlert" class="alert alert-warning mt-3 mb-0 py-2 px-3 d-none border-warning font-weight-bold text-xs" style="border-radius: 8px;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <span id="dispModalDiscrepancyMessage"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Dispatch Remarks --}}
                    <div class="card border shadow-sm mb-0" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <label for="dispModalRemarks" class="font-weight-bold text-dark text-sm mb-1">
                                <i class="fas fa-comment-alt text-secondary mr-1"></i> Dispatch Remarks / Notes:
                            </label>
                            <input type="text" id="dispModalRemarks" class="form-control text-dark font-weight-bold" placeholder="e.g. Sent 25 units only as Motera needs remaining stock for upcoming weekend..." maxlength="250">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary font-weight-bold" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-success font-weight-bold shadow-sm px-4" id="btnConfirmSingleDispatch">
                        <i class="fas fa-dolly mr-1"></i> Confirm & Dispatch (Challan)
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL 2: RECEIVE TRANSFER CONFIRMATION MODAL                         --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalReceiveTransfer" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <h5 class="modal-title font-weight-bold" style="font-size: 1.05rem;">
                        <i class="fas fa-box-open text-success mr-2"></i> Receive Stock Transfer: <span id="recModalTransferNumber" class="text-warning"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    {{-- Route & Staff Verification Header --}}
                    <div class="card border mb-3 shadow-sm" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <div class="font-weight-bold text-dark" style="font-size: 0.95rem;">
                                        <span class="badge badge-light border border-dark text-success px-2 py-1 mr-1">
                                            <i class="fas fa-warehouse mr-1"></i> <span id="recModalFromBranch"></span>
                                        </span>
                                        <i class="fas fa-arrow-right text-muted mx-2"></i>
                                        <span class="badge badge-light border border-dark text-danger px-2 py-1 ml-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i> <span id="recModalToBranch"></span>
                                        </span>
                                    </div>
                                    <div class="text-muted text-xs mt-1" id="recModalDispatchMeta"></div>
                                </div>
                                <div class="col-md-6 text-md-right">
                                    <span class="text-xs font-weight-bold text-secondary text-uppercase d-block mb-1">Receiving Staff (Destination Store):</span>
                                    <span class="badge badge-success px-3 py-1 font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fas fa-user-check mr-1"></i> {{ auth()->user()->name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Items Verification Table --}}
                    <div class="card border mb-3 shadow-sm" style="border-radius: 10px;">
                        <div class="card-header bg-white py-2 px-3 border-bottom">
                            <div class="font-weight-bold text-dark text-sm">
                                <i class="fas fa-clipboard-check text-success mr-1"></i> Verify Received Quantities
                            </div>
                        </div>
                        <div class="p-0 table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="bg-light text-xs font-weight-bold text-uppercase text-secondary">
                                    <tr>
                                        <th>Item Description</th>
                                        <th class="text-center" style="width: 140px;">Batch / Expiry</th>
                                        <th class="text-center" style="width: 110px;">Dispatched Qty</th>
                                        <th class="text-center" style="width: 140px;">Received Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="recModalItemsTableBody">
                                    {{-- Injected dynamically --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Inward Remarks --}}
                    <div class="card border shadow-sm mb-0" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-white">
                            <label for="recModalRemarks" class="font-weight-bold text-dark text-sm mb-1">
                                <i class="fas fa-comment-dots text-secondary mr-1"></i> Receiving Remarks / Condition Notes:
                            </label>
                            <input type="text" id="recModalRemarks" class="form-control text-dark font-weight-bold" placeholder="e.g. Received in full and in good condition." maxlength="250">
                            <div class="alert alert-light border mt-2 mb-0 py-2 px-3 text-xs text-muted font-weight-bold" style="border-radius: 8px;">
                                <i class="fas fa-info-circle text-primary mr-1"></i> Upon confirmation, stock is instantly posted as <strong class="text-dark">TRANSFER_IN</strong> in destination store ledger and made live in POS Terminal.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary font-weight-bold" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-success font-weight-bold shadow-sm px-4" id="btnConfirmReceiveTransfer">
                        <i class="fas fa-check-circle mr-1"></i> Confirm & Accept Stock into Store
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL 3: BULK TRANSFERS REVIEW & ROUTE GROUPING MODAL                 --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="bulkTransferModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header bg-dark text-white py-3 px-4">
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
                <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center px-4">
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

    // Active single dispatch payload state
    let activeDispatchData = null;

    // Active receive transfer payload state
    let activeReceiveData = null;

    $(document).ready(function() {
        // Tab preservation: check URL hash
        if (window.location.hash === '#intransit') {
            $('#tab-intransit-btn').tab('show');
        }

        $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
            const target = $(e.target).attr('href');
            if (target === '#tab-intransit') {
                window.location.hash = 'intransit';
            } else {
                window.location.hash = '';
            }
        });

        // Search & Filters
        $('#btnApplyFilters').on('click', applyFilters);
        $('#transferSearchInput').on('input', applyFilters);
        $('#transferSearchInput').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                applyFilters();
            }
        });
        $('#filterSourceBranch, #filterDestBranch, #filterDeficitStatus').on('change', applyFilters);

        // Run filters immediately on load so preset activeBranchId or search is respected
        applyFilters();

        $('#btnClearSearch').on('click', function() {
            $('#transferSearchInput').val('');
            applyFilters();
        });

        $('#btnResetFilters').on('click', function() {
            $('#transferSearchInput').val('');
            $('#filterSourceBranch').val('all');
            $('#filterDestBranch').val('all');
            $('#filterDeficitStatus').val('all');
            applyFilters();
        });

        $('#transfersPerPage').on('change', function() {
            transferPerPage = $(this).val();
            transferCurrentPage = 1;
            renderTransferPagination(1);
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

        // ══════════════════════════════════════════════════════════════════
        // SINGLE DISPATCH FLOW (Interactive Modal with Discrepancy & Batches)
        // ══════════════════════════════════════════════════════════════════
        $(document).on('click', '.btn-single-transfer', function() {
            const $btn = $(this);
            const $row = $btn.closest('.transfer-data-row');
            const itemId = parseInt($btn.data('item-id'));
            const fromBranchId = parseInt($btn.data('source-branch-id'));
            const toBranchId = parseInt($btn.data('dest-branch-id'));
            const itemName = $btn.data('item-name');
            const itemCode = $btn.data('item-code');
            const fromBranchName = $btn.data('source-branch-name');
            const toBranchName = $btn.data('dest-branch-name');
            const destStock = parseInt($btn.data('dest-stock')) || 0;
            const sourceStock = parseInt($btn.data('source-stock')) || 0;
            const suggestedQty = parseInt($btn.data('suggested-qty')) || 1;

            const $qtyInput = $row.find('.row-transfer-qty');
            let initialQty = parseInt($qtyInput.val()) || suggestedQty;
            if (initialQty > sourceStock) initialQty = sourceStock;
            if (initialQty < 1) initialQty = 1;

            activeDispatchData = {
                itemId: itemId,
                fromBranchId: fromBranchId,
                toBranchId: toBranchId,
                itemName: itemName,
                itemCode: itemCode,
                fromBranchName: fromBranchName,
                toBranchName: toBranchName,
                destStock: destStock,
                sourceStock: sourceStock,
                suggestedQty: suggestedQty,
                rowKey: getRowKey($row)
            };

            // Populate Modal Fields
            $('#dispModalFromBranch').text(fromBranchName);
            $('#dispModalToBranch').text(toBranchName);
            $('#dispModalItemName').text(itemName);
            $('#dispModalItemCode').text('SKU: ' + itemCode);
            $('#dispModalDestStock').text(destStock + ' units');
            $('#dispModalSourceStock').text(sourceStock + ' units');
            $('#dispModalRecommendedBadge').text(suggestedQty + ' units');
            $('#dispModalQtyInput').val(initialQty).attr('max', sourceStock);
            $('#dispModalQtyHelp').text(`Available surplus in ${fromBranchName}: ${sourceStock} units`);
            $('#dispModalRemarks').val(`Smart inter-branch transfer: ${fromBranchName} -> ${toBranchName}`);

            // Fetch active batches for this item from source branch
            $('#dispModalBatchSelect').html('<option value="">Loading available batches...</option>').prop('disabled', true);
            $('#dispModalItemMrp').text('Loading...');

            $.ajax({
                url: "{{ route('inventory.replenishment.item-batches') }}",
                type: 'GET',
                data: {
                    item_id: itemId,
                    branch_id: fromBranchId
                },
                success: function(res) {
                    let batchOptions = '';
                    if (res.batches && res.batches.length > 0) {
                        res.batches.forEach(function(b, idx) {
                            const isSelected = idx === 0 ? 'selected' : '';
                            batchOptions += `<option value="${escapeHtml(b.batch_no)}" data-exp="${escapeHtml(b.exp_date || '')}" data-mrp="${b.mrp}" data-avail="${b.available_qty}" ${isSelected}>Batch: ${escapeHtml(b.batch_no)} | Exp: ${escapeHtml(b.exp_date_formatted)} | MRP: ₹${b.mrp.toFixed(2)} (${b.available_qty} u)</option>`;
                        });
                        $('#dispModalBatchSelect').html(batchOptions).prop('disabled', false);
                        const firstMrp = res.batches[0].mrp;
                        $('#dispModalItemMrp').text('₹' + Number(firstMrp).toFixed(2));
                    } else {
                        $('#dispModalBatchSelect').html('<option value="Default" data-mrp="0" data-avail="' + sourceStock + '">Default Batch (No Batch Tracking)</option>').prop('disabled', false);
                        $('#dispModalItemMrp').text('Standard');
                    }
                },
                error: function() {
                    $('#dispModalBatchSelect').html('<option value="Default" data-mrp="0" data-avail="' + sourceStock + '">Default Batch</option>').prop('disabled', false);
                    $('#dispModalItemMrp').text('Standard');
                }
            });

            checkDiscrepancyFeedback(initialQty, suggestedQty);
            $('#modalDispatchTransfer').modal('show');
        });

        // Watch dispatch quantity changes inside modal
        $('#dispModalQtyInput').on('input change', function() {
            if (!activeDispatchData) return;
            let val = parseInt($(this).val()) || 0;
            const max = activeDispatchData.sourceStock;
            if (val > max) {
                val = max;
                $(this).val(val);
            }
            if (val < 1 && $(this).val() !== '') {
                val = 1;
                $(this).val(val);
            }
            checkDiscrepancyFeedback(val, activeDispatchData.suggestedQty);
        });

        // Watch batch select change to update MRP display
        $('#dispModalBatchSelect').on('change', function() {
            const $opt = $(this).find('option:selected');
            const mrp = parseFloat($opt.data('mrp')) || 0;
            if (mrp > 0) {
                $('#dispModalItemMrp').text('₹' + mrp.toFixed(2));
            }
        });

        // Check and display discrepancy feedback
        function checkDiscrepancyFeedback(currentQty, recommendedDeficit) {
            const $alert = $('#dispModalDiscrepancyAlert');
            const $msg = $('#dispModalDiscrepancyMessage');

            if (currentQty < recommendedDeficit) {
                const deficitRemaining = recommendedDeficit - currentQty;
                $msg.html(`<strong>Partial Transfer:</strong> You are dispatching <span class="badge badge-dark">${currentQty} units</span> out of the recommended <span class="badge badge-dark">${recommendedDeficit} units</span>. The residual deficit of <strong class="text-danger">${deficitRemaining} units</strong> will remain in Smart Replenishment for purchase orders or future transfers.`);
                $alert.removeClass('d-none alert-info').addClass('alert-warning');
            } else if (currentQty > recommendedDeficit) {
                $msg.html(`<strong>Surplus Dispatch:</strong> You are transferring <span class="badge badge-dark">${currentQty} units</span>, exceeding destination deficit (${recommendedDeficit} units). Destination store will receive additional buffer inventory.`);
                $alert.removeClass('d-none alert-warning').addClass('alert-info');
            } else {
                $alert.addClass('d-none');
            }
        }

        // Confirm Single Dispatch Action
        $('#btnConfirmSingleDispatch').on('click', function() {
            if (!activeDispatchData) return;

            const dispatchQty = parseInt($('#dispModalQtyInput').val()) || 0;
            if (dispatchQty <= 0) {
                alert('Please enter a dispatch quantity of at least 1 unit.');
                $('#dispModalQtyInput').focus();
                return;
            }

            if (dispatchQty > activeDispatchData.sourceStock) {
                alert(`Dispatch quantity (${dispatchQty}) cannot exceed available surplus stock (${activeDispatchData.sourceStock} units).`);
                $('#dispModalQtyInput').val(activeDispatchData.sourceStock).focus();
                return;
            }

            const $selectedBatchOpt = $('#dispModalBatchSelect').find('option:selected');
            const batchNo = $selectedBatchOpt.val() || 'Default';
            const expDate = $selectedBatchOpt.data('exp') || null;
            const batchMrp = parseFloat($selectedBatchOpt.data('mrp')) || 0;
            const remarks = $.trim($('#dispModalRemarks').val()) || '';

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating Challan...');

            $.ajax({
                url: "{{ route('inventory.replenishment.create-transfer') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    from_branch_id: activeDispatchData.fromBranchId,
                    to_branch_id: activeDispatchData.toBranchId,
                    remarks: remarks,
                    items: [{
                        item_id: activeDispatchData.itemId,
                        qty: dispatchQty,
                        requested_qty: activeDispatchData.suggestedQty,
                        batch_no: batchNo,
                        exp_date: expDate,
                        mrp: batchMrp
                    }]
                },
                success: function(res) {
                    $('#modalDispatchTransfer').modal('hide');

                    let alertMsg = `<strong>${escapeHtml(res.message)}</strong> `;
                    alertMsg += `<div class="mt-2">`;
                    alertMsg += `<a href="${res.redirect_url}" class="badge badge-light border border-dark text-dark mr-2 p-2 font-weight-bold" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Challan #${escapeHtml(res.transfer_number)}</a> `;
                    alertMsg += `<a href="${res.print_url}" class="badge badge-primary mr-2 p-2 font-weight-bold" target="_blank"><i class="fas fa-print mr-1"></i> Print Challan</a> `;
                    alertMsg += `<button type="button" class="btn btn-outline-light btn-xs p-1 font-weight-bold" onclick="$('#tab-intransit-btn').tab('show');"><i class="fas fa-truck mr-1"></i> Go to In-Transit Tab</button>`;
                    alertMsg += `</div>`;

                    $('#transferAlertMessage').html(alertMsg);
                    $('#transferAlert').removeClass('d-none');

                    // Remove row from table
                    const $row = $(`#transfer-row-${activeDispatchData.rowKey}`);
                    delete selectedTransfers[activeDispatchData.rowKey];
                    updateSelectionState();

                    $row.fadeOut(300, function() {
                        $(this).remove();
                        refreshVisibleCount();
                    });

                    // Add to In-Transit table & update badge
                    prependInTransitRow({
                        id: res.transfer_id,
                        transfer_number: res.transfer_number,
                        date_formatted: "{{ now()->format('d-m-Y') }}",
                        time_formatted: "{{ now()->format('h:i A') }}",
                        from_branch: activeDispatchData.fromBranchName,
                        to_branch: activeDispatchData.toBranchName,
                        to_branch_id: activeDispatchData.toBranchId,
                        creator_name: "{{ auth()->user()->name }}",
                        item_name: activeDispatchData.itemName,
                        item_code: activeDispatchData.itemCode,
                        batch_no: batchNo,
                        exp_date: expDate || '—',
                        qty: dispatchQty,
                        requested_qty: activeDispatchData.suggestedQty,
                        mrp: batchMrp,
                        remarks: remarks,
                        show_url: res.redirect_url,
                        print_url: res.print_url
                    });
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-dolly mr-1"></i> Confirm & Dispatch (Challan)');
                    activeDispatchData = null;
                }
            });
        });

        // ══════════════════════════════════════════════════════════════════
        // RECEIVE TRANSFER FLOW (Interactive Verification Modal)
        // ══════════════════════════════════════════════════════════════════
        $(document).on('click', '.btn-open-receive-modal', function() {
            const $btn = $(this);
            const transferId = parseInt($btn.data('transfer-id'));
            const transferNumber = $btn.data('transfer-number');
            const fromBranch = $btn.data('from-branch');
            const toBranch = $btn.data('to-branch');
            const creatorName = $btn.data('creator-name');
            const dispatchedAt = $btn.data('dispatched-at');
            const remarks = $btn.data('remarks') || '';
            let items = $btn.data('items');

            if (typeof items === 'string') {
                try { items = JSON.parse(items); } catch(e) { items = []; }
            }

            activeReceiveData = {
                transferId: transferId,
                transferNumber: transferNumber,
                fromBranch: fromBranch,
                toBranch: toBranch,
                items: items
            };

            $('#recModalTransferNumber').text(transferNumber);
            $('#recModalFromBranch').text(fromBranch);
            $('#recModalToBranch').text(toBranch);
            $('#recModalDispatchMeta').html(`Dispatched by <strong>${escapeHtml(creatorName)}</strong> on ${dispatchedAt}`);
            $('#recModalRemarks').val(remarks ? `Received: ${remarks}` : 'All items received in good condition.');

            let itemsHtml = '';
            items.forEach(function(item, idx) {
                const requestedSubtext = (item.requested_qty && item.requested_qty !== item.qty)
                    ? `<br><small class="text-muted">Recommended: ${item.requested_qty} u</small>`
                    : '';

                itemsHtml += `
                    <tr class="rec-modal-item-row" data-line-id="${item.id}" data-dispatched-qty="${item.qty}">
                        <td class="align-middle">
                            <strong class="text-dark">${escapeHtml(item.name)}</strong><br>
                            <span class="badge badge-dark text-xs px-2 py-0">SKU: ${escapeHtml(item.code || '—')}</span>
                        </td>
                        <td class="text-center align-middle">
                            <span class="badge badge-info px-2 py-1">${escapeHtml(item.batch_no || 'Default')}</span><br>
                            <small class="text-muted">Exp: ${escapeHtml(item.exp_date || '—')}</small>
                        </td>
                        <td class="text-center align-middle font-weight-bold text-dark">
                            ${item.qty} u ${requestedSubtext}
                        </td>
                        <td class="text-center align-middle">
                            <div class="input-group input-group-sm mx-auto" style="max-width: 125px;">
                                <input type="number"
                                    class="form-control form-control-sm text-center font-weight-bold rec-item-qty-input"
                                    value="${item.qty}"
                                    min="0"
                                    max="${item.qty}"
                                    step="1"
                                    data-line-id="${item.id}">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-light text-xs font-weight-bold">Units</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#recModalItemsTableBody').html(itemsHtml);
            $('#modalReceiveTransfer').modal('show');
        });

        // Confirm Receive Transfer Action
        $('#btnConfirmReceiveTransfer').on('click', function() {
            if (!activeReceiveData) return;

            const itemsPayload = [];
            let hasValid = false;

            $('.rec-modal-item-row').each(function() {
                const lineId = parseInt($(this).data('line-id'));
                const maxQty = parseFloat($(this).data('dispatched-qty')) || 0;
                let recQty = parseFloat($(this).find('.rec-item-qty-input').val());
                if (isNaN(recQty) || recQty < 0) recQty = 0;
                if (recQty > maxQty) recQty = maxQty;

                if (recQty > 0) hasValid = true;

                itemsPayload.push({
                    id: lineId,
                    received_qty: recQty
                });
            });

            if (!hasValid && !confirm('All item received quantities are set to 0. Do you still want to complete this receipt?')) {
                return;
            }

            const receiveRemarks = $.trim($('#recModalRemarks').val());

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Receiving Stock...');

            $.ajax({
                url: `/inventory/stock-transfers/${activeReceiveData.transferId}/receive`,
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    remarks: receiveRemarks,
                    items: itemsPayload
                },
                success: function(res) {
                    $('#modalReceiveTransfer').modal('hide');

                    $('#transferAlertMessage').html(`<strong>${escapeHtml(res.message || 'Stock transfer received successfully.')}</strong>`);
                    $('#transferAlert').removeClass('d-none');

                    // Remove row from In-Transit table
                    $(`#intransit-row-${activeReceiveData.transferId}`).fadeOut(300, function() {
                        $(this).remove();
                        const remainingCount = $('.intransit-row').length;
                        $('#tabInTransitCount').text(remainingCount);
                        $('#inTransitTableCount').text(remainingCount);
                        if (remainingCount === 0) {
                            $('#inTransitTableBody').html(`
                                <tr id="emptyInTransitRow">
                                    <td colspan="8" class="text-center py-5 text-dark font-weight-bold">
                                        <i class="fas fa-box-open text-muted mb-2" style="font-size: 2.2rem;"></i><br>
                                        <span>No In-Transit Stock Transfers Pending Receipt</span><br>
                                        <small class="text-muted">All dispatched transfers have been received and reconciled.</small>
                                    </td>
                                </tr>
                            `);
                        }
                    });
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to receive stock transfer.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Confirm & Accept Stock into Store');
                    activeReceiveData = null;
                }
            });
        });

        // ══════════════════════════════════════════════════════════════════
        // BULK TRANSFER REVIEW MODAL & DISPATCH
        // ══════════════════════════════════════════════════════════════════
        $('.btn-bulk-transfer-trigger').on('click', function() {
            const keys = Object.keys(selectedTransfers);
            if (keys.length === 0) {
                alert('Please select at least one item from the table using the checkboxes to perform a bulk transfer.');
                return;
            }

            renderBulkTransferModal();
            $('#bulkTransferModal').modal('show');
        });

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

                    // Reload page or hash to show updated in-transit transfers
                    setTimeout(function() {
                        window.location.hash = 'intransit';
                        window.location.reload();
                    }, 1200);
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

    // Helper: Prepend newly created transfer into In-Transit table dynamically
    function prependInTransitRow(data) {
        $('#emptyInTransitRow').remove();

        const itemsArray = [{
            id: 0,
            name: data.item_name,
            code: data.item_code,
            batch_no: data.batch_no,
            exp_date: data.exp_date,
            qty: data.qty,
            requested_qty: data.requested_qty,
            mrp: data.mrp
        }];

        const currentUserId = {{ (int)($userBranchId ?? 0) }};
        const isGlobalAdmin = {{ $isGlobalAdmin ? 'true' : 'false' }};
        const canReceive = isGlobalAdmin || (currentUserId && currentUserId === parseInt(data.to_branch_id));

        const actionHtml = canReceive
            ? `<button type="button" class="btn btn-success font-weight-bold shadow-sm btn-open-receive-modal"
                    data-transfer-id="${data.id}"
                    data-transfer-number="${escapeHtml(data.transfer_number)}"
                    data-from-branch="${escapeHtml(data.from_branch)}"
                    data-to-branch="${escapeHtml(data.to_branch)}"
                    data-creator-name="${escapeHtml(data.creator_name)}"
                    data-dispatched-at="${data.date_formatted} ${data.time_formatted}"
                    data-remarks="${escapeHtml(data.remarks || '')}"
                    data-items='${JSON.stringify(itemsArray)}'
                    title="Verify quantities and receive">
                    <i class="fas fa-check-circle mr-1"></i> Receive
               </button>`
            : `<span class="badge badge-light border text-muted px-2 py-1 mr-1"><i class="fas fa-lock mr-1"></i> ${escapeHtml(data.to_branch)}</span>`;

        const newRow = `
            <tr class="intransit-row bg-light" id="intransit-row-${data.id}">
                <td>
                    <a href="${data.show_url}" class="font-weight-bold text-primary" target="_blank">
                        ${escapeHtml(data.transfer_number)}
                    </a>
                </td>
                <td>
                    <div class="font-weight-bold text-dark">${data.date_formatted}</div>
                    <div class="text-muted text-xs">${data.time_formatted}</div>
                </td>
                <td>
                    <span class="badge badge-light border border-dark text-success font-weight-bold px-2 py-1">
                        <i class="fas fa-warehouse mr-1"></i> ${escapeHtml(data.from_branch)}
                    </span>
                </td>
                <td>
                    <span class="badge badge-light border border-dark text-danger font-weight-bold px-2 py-1">
                        <i class="fas fa-map-marker-alt mr-1"></i> ${escapeHtml(data.to_branch)}
                    </span>
                </td>
                <td>
                    <span class="badge badge-info px-2 py-1 font-weight-bold">
                        <i class="fas fa-user-check mr-1"></i> ${escapeHtml(data.creator_name)}
                    </span>
                </td>
                <td>
                    <strong class="text-dark">1 item(s) &bull; ${data.qty} units</strong>
                    <div class="text-muted text-xs mt-1">&bull; ${escapeHtml(data.item_name)} (${data.qty}u)</div>
                </td>
                <td>
                    <small class="text-muted font-italic">${escapeHtml(data.remarks || 'No remarks')}</small>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        ${actionHtml}
                        <a href="${data.print_url}" target="_blank" class="btn btn-outline-secondary font-weight-bold">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `;

        $('#inTransitTableBody').prepend(newRow);
        const newCount = $('.intransit-row').length;
        $('#tabInTransitCount').text(newCount);
        $('#inTransitTableCount').text(newCount);
    }

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
            suggestedQty: parseInt($row.data('suggested-qty')) || 1,
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

    // Pagination state
    let transferCurrentPage = 1;
    let transferPerPage = 25;
    let matchingTransferRows = [];

    // Apply Live Table Filters (Search + Surplus Store + Deficit Store + Status)
    function applyFilters(resetPage = true) {
        if (resetPage) {
            transferCurrentPage = 1;
        }
        const query = $.trim($('#transferSearchInput').val()).toLowerCase();
        const fromBranch = String($('#filterSourceBranch').val() || 'all');
        const toBranch = String($('#filterDestBranch').val() || 'all');
        const status = String($('#filterDeficitStatus').val() || 'all');

        matchingTransferRows = [];

        $('.transfer-data-row').each(function() {
            const $row = $(this);
            const itemName = String($row.attr('data-item-name') || $row.data('item-name') || '').toLowerCase();
            const itemCode = String($row.attr('data-item-code') || $row.data('item-code') || '').toLowerCase();
            const barcode = String($row.attr('data-ean-upc-code') || $row.data('ean-upc-code') || '').toLowerCase();
            const rowDestBranch = String($row.attr('data-dest-branch-id') || $row.data('dest-branch-id') || '');
            const rowSourceBranch = String($row.attr('data-source-branch-id') || $row.data('source-branch-id') || '');
            const rowDestStatus = String($row.attr('data-dest-status') || $row.data('dest-status') || '');

            let matchesSearch = true;
            if (query.length > 0) {
                matchesSearch = itemName.includes(query) || itemCode.includes(query) || barcode.includes(query);
            }

            let matchesFrom = true;
            if (fromBranch !== 'all') {
                matchesFrom = (rowSourceBranch === fromBranch);
            }

            let matchesTo = true;
            if (toBranch !== 'all') {
                matchesTo = (rowDestBranch === toBranch);
            }

            let matchesStatus = true;
            if (status !== 'all') {
                matchesStatus = (rowDestStatus === status);
            }

            if (matchesSearch && matchesFrom && matchesTo && matchesStatus) {
                matchingTransferRows.push($row);
            } else {
                $row.hide();
            }
        });

        const visibleCount = matchingTransferRows.length;
        $('#visibleRowCount').text(visibleCount);

        if (visibleCount === 0 && $('.transfer-data-row').length > 0) {
            $('#noMatchingRows').removeClass('d-none');
            $('#transfersPaginationControls').empty();
            $('#transfersPaginationInfo').text('Showing 0 of 0');
        } else {
            $('#noMatchingRows').addClass('d-none');
            renderTransferPagination();
        }

        // Also filter in-transit rows if user is searching on Tab 2
        if ($('#inTransitTableBody tr').length > 0) {
            let inTransitVisible = 0;
            $('#inTransitTableBody tr').each(function() {
                const text = $(this).text().toLowerCase();
                if (query.length === 0 || text.includes(query)) {
                    $(this).show();
                    inTransitVisible++;
                } else {
                    $(this).hide();
                }
            });
            $('#inTransitTableCount').text(inTransitVisible);
        }
    }

    function renderTransferPagination(page = null) {
        if (page !== null) {
            transferCurrentPage = page;
        }
        const total = matchingTransferRows.length;
        let perPage = transferPerPage === 'all' ? (total > 0 ? total : 25) : parseInt(transferPerPage, 10);
        if (!perPage || perPage <= 0) perPage = 25;

        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if (transferCurrentPage > totalPages) {
            transferCurrentPage = totalPages;
        }
        if (transferCurrentPage < 1) {
            transferCurrentPage = 1;
        }

        const startIndex = (transferCurrentPage - 1) * perPage;
        const endIndex = transferPerPage === 'all' ? total : Math.min(startIndex + perPage, total);

        matchingTransferRows.forEach(function($row, idx) {
            if (idx >= startIndex && idx < endIndex) {
                $row.show();
            } else {
                $row.hide();
            }
        });

        if (total === 0) {
            $('#transfersPaginationInfo').text('Showing 0 of 0');
        } else {
            $('#transfersPaginationInfo').text(`Showing page ${transferCurrentPage} of ${totalPages} (${total} total items)`);
        }

        let html = '';
        if (totalPages > 1) {
            html += `<li class="page-item ${transferCurrentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToTransferPage(${transferCurrentPage - 1})">&laquo;</a>
                     </li>`;

            let start = Math.max(1, transferCurrentPage - 2);
            let end = Math.min(totalPages, transferCurrentPage + 2);

            if (start > 1) {
                html += `<li class="page-item"><a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToTransferPage(1)">1</a></li>`;
                if (start > 2) {
                    html += `<li class="page-item disabled"><span class="page-link font-weight-bold text-dark">...</span></li>`;
                }
            }

            for (let i = start; i <= end; i++) {
                html += `<li class="page-item ${i === transferCurrentPage ? 'active' : ''}">
                            <a class="page-link font-weight-bold ${i === transferCurrentPage ? 'bg-dark text-white border-dark' : 'text-dark'}" href="javascript:void(0)" onclick="goToTransferPage(${i})">${i}</a>
                         </li>`;
            }

            if (end < totalPages) {
                if (end < totalPages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link font-weight-bold text-dark">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToTransferPage(${totalPages})">${totalPages}</a></li>`;
            }

            html += `<li class="page-item ${transferCurrentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToTransferPage(${transferCurrentPage + 1})">&raquo;</a>
                     </li>`;
        }

        $('#transfersPaginationControls').html(html);

        // Update header checkbox for visible rows on current page
        const visibleRowsOnPage = $('.transfer-data-row:visible');
        const checkedVisibleCount = visibleRowsOnPage.find('.row-transfer-checkbox:checked').length;
        $('#checkAllRows').prop('checked', visibleRowsOnPage.length > 0 && checkedVisibleCount === visibleRowsOnPage.length);
    }

    window.goToTransferPage = function(page) {
        renderTransferPagination(page);
        const tableOffset = $('#transfersTable').offset();
        if (tableOffset && $(window).scrollTop() > tableOffset.top - 100) {
            $('html, body').animate({ scrollTop: tableOffset.top - 100 }, 200);
        }
    };

    function refreshVisibleCount() {
        const remaining = $('.transfer-data-row').length;
        $('#tabRecommendationsCount').text(remaining);
        if (remaining === 0) {
            $('#emptyTableRow').removeClass('d-none');
            $('#noMatchingRows').addClass('d-none');
            $('#transfersPaginationControls').empty();
            $('#transfersPaginationInfo').text('Showing 0 of 0');
            $('#visibleRowCount').text(0);
        } else {
            applyFilters(false);
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
                        data-to-branch-id="${it.toBranchId}"
                        data-suggested-qty="${it.suggestedQty || it.qty}">
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
                            <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeModalTransferItem('${escapeHtml(itemKey)}')" title="Remove from this transfer">
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
            const requestedQty = parseInt($(this).data('suggested-qty')) || qty;

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
                    qty: qty,
                    requested_qty: requestedQty
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
