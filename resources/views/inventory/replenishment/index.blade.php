@extends('adminlte::page')

@section('title', 'Smart Replenishment Studio (V1)')

@push('css')
<style>
/* Modern 2026 High-Contrast Dashboard Design System */
:root {
    --text-primary: #0f172a;
    --text-secondary: #1e293b;
    --border-subtle: #e2e8f0;
    --border-dark: #0f172a;
}

body, .content-wrapper {
    color: #0f172a !important;
}

/* Page Header */
.replenishment-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    font-size: 1.65rem;
}
.replenishment-subheading {
    color: #1e293b !important;
    font-weight: 600;
    font-size: 0.85rem;
}

/* Modern Metric Cards */
.stat-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
    padding: 1.15rem 1.25rem;
    position: relative;
    overflow: hidden;
    height: 100%;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    border-color: #0f172a;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}
.stat-card.critical::before { background: #dc2626; }
.stat-card.warning::before { background: #d97706; }
.stat-card.success::before { background: #16a34a; }
.stat-card.info::before { background: #2563eb; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
}
.stat-label {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #0f172a;
}
.stat-icon-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 800;
}
.stat-val {
    font-size: 1.95rem;
    font-weight: 800;
    line-height: 1.15;
    color: #0f172a !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin-bottom: 0.4rem;
}
.stat-indicator {
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
}

/* Modern Card Containers */
.modern-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.modern-card-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 0.95rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-card-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.01em;
}

/* Filter Toolbar */
.modern-filter-toolbar {
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
}
.filter-field-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #0f172a;
    margin-bottom: 0.35rem;
    display: block;
}
.modern-control {
    border-radius: 8px !important;
    border: 1.5px solid #cbd5e1 !important;
    color: #0f172a !important;
    font-weight: 700 !important;
    font-size: 0.83rem !important;
    background-color: #ffffff !important;
}
.modern-control:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15) !important;
}

/* Modern Table Architecture */
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
    padding: 0.85rem 0.95rem !important;
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
    padding: 0.8rem 0.95rem !important;
    vertical-align: middle !important;
    border-top: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    font-size: 0.84rem;
}
.item-title {
    font-weight: 800;
    color: #0f172a !important;
    font-size: 0.88rem;
}
.item-subtitle {
    font-weight: 700;
    color: #1e293b !important;
    font-size: 0.76rem;
    margin-top: 0.15rem;
}

/* Modern Status Chips */
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.65rem;
    font-size: 0.74rem;
    font-weight: 800;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.status-pill.critical { background: #fee2e2; color: #991b1b; border: 1.5px solid #f87171; }
.status-pill.low { background: #fef3c7; color: #92400e; border: 1.5px solid #fbbf24; }
.status-pill.healthy { background: #dcfce7; color: #166534; border: 1.5px solid #4ade80; }
.status-pill.overstock { background: #e0f2fe; color: #075985; border: 1.5px solid #38bdf8; }
.status-pill.dead { background: #f1f5f9; color: #0f172a; border: 1.5px solid #94a3b8; }

/* Reorder Pill */
.reorder-badge {
    background: #dc2626;
    color: #ffffff !important;
    font-weight: 800;
    font-size: 0.85rem;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.4);
    display: inline-block;
}

/* Modern Action Buttons */
.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}
.btn-action-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    color: #ffffff !important;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}
.btn-action-outline {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    color: #0f172a !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}
.btn-action-outline:hover {
    background: #0f172a;
    color: #ffffff !important;
}

/* Modal Styling */
.modal-content {
    border-radius: 14px;
    border: 1.5px solid #0f172a;
    overflow: hidden;
}
.modal-header {
    background: #0f172a !important;
    color: #ffffff !important;
    padding: 1rem 1.25rem;
}
.modal-header .modal-title {
    color: #ffffff !important;
    font-weight: 800;
}
.modal-header .close {
    color: #ffffff !important;
    opacity: 0.9;
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

/* Custom Checkbox Styling */
.row-select-checkbox {
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

/* Supplier Card in Bulk Modal */
.bulk-vendor-card {
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.bulk-vendor-header {
    background: #f8fafc;
    border-bottom: 1.5px solid #cbd5e1;
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Modern Select2 High-Contrast Styling */
.select2-container--bootstrap4 .select2-selection--single {
    height: 35px !important;
    border-radius: 8px !important;
    border: 1.5px solid #cbd5e1 !important;
    font-size: 0.83rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    background-color: #ffffff !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 32px !important;
    color: #0f172a !important;
    padding-left: 0.5rem !important;
    font-weight: 700 !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 32px !important;
}
.select2-container--bootstrap4.select2-container--focus .select2-selection--single {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15) !important;
}
.select2-dropdown {
    border: 1.5px solid #0f172a !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.15) !important;
}
.select2-results__option {
    font-weight: 600 !important;
    font-size: 0.83rem !important;
    color: #0f172a !important;
}
.select2-results__option--highlighted {
    background-color: #0f172a !important;
    color: #ffffff !important;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="replenishment-heading m-0">
                <i class="fas fa-brain text-primary mr-2"></i> Smart Replenishment Studio <span class="badge badge-dark font-weight-bold text-xs ml-1" style="letter-spacing: 0.05em; padding: 4px 8px;">V1</span>
            </h1>
            <div class="replenishment-subheading mt-1">
                Demand Velocity &bull; Stock Cover Days &bull; Inter-Branch Matchmaking &bull; 1-Click PO
            </div>
        </div>
        <div class="d-flex align-items-center mt-2 mt-md-0">
            <a href="{{ route('inventory.replenishment.settings') }}" class="btn btn-action-outline mr-2 shadow-sm">
                <i class="fas fa-sliders-h mr-1"></i> Rules & Settings
            </a>
            <button type="button" class="btn btn-action-dark shadow-sm" id="btnSyncLive">
                <i class="fas fa-sync-alt mr-1" id="syncIcon"></i> <span id="syncText">Sync Live Stock</span>
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- Alerts Bar --}}
    <div id="statusAlert" class="alert alert-dark alert-dismissible fade show d-none border-dark shadow-sm mb-3" style="background:#0f172a; color:#ffffff; border-radius:10px;" role="alert">
        <i class="fas fa-check-circle mr-1 text-success"></i> <span id="statusAlertMessage" class="font-weight-bold"></span>
        <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    {{-- Main Workspace: Full Screen Stock Analysis Table --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="fas fa-list-alt text-primary mr-2"></i> Stock Replenishment Analysis
                    </h3>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-dark font-weight-bold px-3 py-2 mr-2" id="totalFilteredRows" style="font-size: 0.8rem; border-radius: 6px;">Loading...</span>
                        <button type="button" class="btn btn-action-dark btn-sm shadow-sm" id="btnExportExcel" title="Export current filtered view to Excel spreadsheet">
                            <i class="fas fa-file-excel text-success mr-1"></i> Export Excel (.xlsx)
                        </button>
                    </div>
                </div>

                {{-- Filter Toolbar (Branch, Status, Movement, Department, Category, Brand, Supplier, Search) --}}
                <div class="modern-filter-toolbar">
                    <div class="row g-2 align-items-center">
                        {{-- Row 1: Core Dimensions --}}
                        <div class="col-md-4 col-12 mb-2">
                            <label class="filter-field-label"><i class="fas fa-store mr-1 text-primary"></i> Branch</label>
                            <select id="filterBranch" class="form-control form-control-sm modern-control">
                                <option value="all">All Branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ $activeBranchId == $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-signal mr-1 text-primary"></i> Stock Status</label>
                            <select id="filterStatus" class="form-control form-control-sm modern-control">
                                <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>All Statuses</option>
                                <option value="needs_action" {{ !request()->has('status') || request('status') === 'needs_action' ? 'selected' : '' }}>🚨 Needs Action (Order/Transfer)</option>
                                <option value="Critical" {{ request('status') === 'Critical' ? 'selected' : '' }}>🔴 Critical</option>
                                <option value="Low" {{ request('status') === 'Low' ? 'selected' : '' }}>🟡 Low</option>
                                <option value="Healthy" {{ request('status') === 'Healthy' ? 'selected' : '' }}>🟢 Healthy</option>
                                <option value="Overstock" {{ request('status') === 'Overstock' ? 'selected' : '' }}>🔵 Overstock</option>
                                <option value="Dead Stock" {{ request('status') === 'Dead Stock' ? 'selected' : '' }}>⚪ Dead Stock</option>
                            </select>
                        </div>

                        <div class="col-md-4 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-tachometer-alt mr-1 text-success"></i> Sales Movement</label>
                            <select id="filterSalesType" class="form-control form-control-sm modern-control">
                                <option value="all" {{ !request()->has('sales_type') || request('sales_type') === 'all' ? 'selected' : '' }}>All Velocities</option>
                                <option value="Highest Moving" {{ request('sales_type') === 'Highest Moving' ? 'selected' : '' }}>⚡ Highest Moving</option>
                                <option value="Fast Moving" {{ request('sales_type') === 'Fast Moving' ? 'selected' : '' }}>🚀 Fast Moving</option>
                                <option value="Normal Moving" {{ request('sales_type') === 'Normal Moving' ? 'selected' : '' }}>📦 Normal Moving</option>
                                <option value="Slow Moving" {{ request('sales_type') === 'Slow Moving' ? 'selected' : '' }}>⏳ Slow Moving</option>
                                <option value="Dead Stock" {{ request('sales_type') === 'Dead Stock' ? 'selected' : '' }}>💤 Dead Stock</option>
                            </select>
                        </div>

                        {{-- Row 2: Department, Category, Brand, Supplier from Item Master (with Select2) --}}
                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-layer-group mr-1 text-primary"></i> DEPARTMENT</label>
                            <select id="filterDepartment" class="form-control form-control-sm select2">
                                <option value="all" {{ !request()->has('department_id') || request('department_id') === 'all' ? 'selected' : '' }}>All Departments</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-tags mr-1 text-primary"></i> CATEGORY</label>
                            <select id="filterCategory" class="form-control form-control-sm select2">
                                <option value="all" {{ !request()->has('category_id') || request('category_id') === 'all' ? 'selected' : '' }}>All Categories</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-award mr-1 text-primary"></i> Brands</label>
                            <select id="filterBrand" class="form-control form-control-sm select2">
                                <option value="all" {{ !request()->has('brand_id') || request('brand_id') === 'all' ? 'selected' : '' }}>All Brands</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" {{ request('brand_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-building mr-1 text-primary"></i> Supplier</label>
                            <select id="filterSupplier" class="form-control form-control-sm select2">
                                <option value="all" {{ !request()->has('supplier_id') || request('supplier_id') === 'all' ? 'selected' : '' }}>All Suppliers</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Row 3: Search Item / SKU & Reset --}}
                        <div class="col-md-10 col-8 mb-2">
                            <label class="filter-field-label"><i class="fas fa-search mr-1 text-primary"></i> Search Item / SKU</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="filterSearch" class="form-control form-control-sm modern-control" placeholder="Search by SKU, Name or Barcode..." value="{{ request('search', '') }}" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-action-outline btn-sm {{ request('search') ? '' : 'd-none' }}" type="button" id="btnClearSearch" title="Clear search text">
                                        <i class="fas fa-times text-danger"></i>
                                    </button>
                                    <button class="btn btn-action-dark btn-sm" type="button" id="btnFilterSearch" title="Click to search">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 col-4 mb-2 d-flex align-items-end">
                            <button type="button" class="btn btn-action-outline btn-sm btn-block" id="btnResetFilters" title="Reset all filters">
                                <i class="fas fa-undo-alt mr-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Modern High-Contrast Table Container --}}
                <div class="p-0 table-responsive">
                    <table class="modern-table" id="replenishmentTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center align-middle">
                                    <input type="checkbox" id="checkAllRows" class="table-checkbox-header" title="Select / Deselect all items on this page">
                                </th>
                                <th class="align-middle">Item Details</th>
                                <th class="align-middle">Current Stock</th>
                                <th class="text-center align-middle">Daily Avg</th>
                                <th class="text-center align-middle">Cover Days</th>
                                <th class="text-center align-middle">Required Stock</th>
                                <th class="text-center align-middle" style="width: 140px;">Order / Transfer Qty</th>
                                <th class="text-center align-middle">Sales Type</th>
                                <th class="text-center align-middle">Stock Status</th>
                                <th class="text-center align-middle" style="width: 175px;">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="mb-1">ACTION</span>
                                        <select id="columnFilterAction" class="form-control form-control-sm modern-control py-0 font-weight-bold" style="height: 27px; font-size: 0.74rem; background: #1e293b; color: #ffffff; border: 1.5px solid #475569; border-radius: 6px; cursor: pointer;" title="Filter table by Action">
                                            <option value="all">View: All</option>
                                            <option value="order">🛒 Order Only</option>
                                            <option value="transfer">🔄 Transfer Only</option>
                                        </select>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" class="text-center py-4 font-weight-bold text-dark">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Loading replenishment metrics...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Table Pagination Footer --}}
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold text-dark text-sm" id="paginationInfo">Showing 0 of 0</span>
                    <ul class="pagination pagination-sm m-0" id="paginationControls"></ul>
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
                    Total Units: <strong id="selectedTotalUnits" class="text-warning ml-1">0</strong>
                </span>
                <span class="text-white font-weight-bold" style="font-size: 0.92rem;">
                    Est. Reorder Value: <strong id="selectedTotalAmount" class="text-success ml-1">₹ 0.00</strong>
                </span>
            </div>
            <div class="d-flex align-items-center py-1">
                <button type="button" class="btn btn-outline-light btn-sm mr-2 font-weight-bold" id="btnClearSelection" title="Clear all currently selected items">
                    <i class="fas fa-times mr-1"></i> Deselect All
                </button>
                <button type="button" class="btn btn-success btn-sm mr-2 font-weight-bold shadow-sm" id="btnBulkPoModalOpen">
                    <i class="fas fa-cart-plus mr-1"></i> Bulk Create PO(s) (<span class="selected-count-span">0</span>)
                </button>
                <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm text-white" id="btnBulkTransferModalOpen">
                    <i class="fas fa-random mr-1"></i> Bulk Create Transfer(s) (<span class="selected-count-span">0</span>)
                </button>
            </div>
        </div>
    </div>

    {{-- Single Item Quick PO Modal --}}
    <div class="modal fade" id="poModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-sm" id="poModalTitle">
                        <i class="fas fa-file-invoice text-primary mr-1"></i> Purchase Order Recommendation
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3 text-sm">
                    <div class="bg-light p-3 rounded mb-3 border border-dark d-flex justify-content-between align-items-center">
                        <div>
                            <span class="font-weight-bold text-dark">Vendor:</span> <strong id="modalVendorName" class="text-dark ml-1">-</strong><br>
                            <span class="font-weight-bold text-dark">Target Store:</span> <strong id="modalBranchName" class="text-dark ml-1">Current Branch</strong>
                        </div>
                        <div class="text-right">
                            <span class="font-weight-bold text-dark">Estimated Amount:</span><br>
                            <span class="font-weight-bold text-primary" id="modalEstimatedVal" style="font-size:1.25rem;">-</span>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-bordered text-xs mb-0">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th>Item SKU & Name</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th class="text-center" style="width: 120px;">Order Qty</th>
                                    <th class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="poModalItemsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-action-outline btn-sm font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-action-dark btn-sm font-weight-bold" id="btnConfirmGeneratePo">
                        <i class="fas fa-check mr-1 text-success"></i> Confirm & Generate 1-Click PO
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Purchase Orders Review & Grouping Modal --}}
    <div class="modal fade" id="bulkPoModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="bulkPoModalTitle">
                        <i class="fas fa-cart-plus text-primary mr-2"></i> Review & Create Bulk Purchase Orders
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                    <div class="alert alert-info py-2 px-3 mb-3 font-weight-bold text-xs" style="border-radius: 8px;">
                        <i class="fas fa-info-circle mr-1"></i> Items have been automatically grouped by primary vendor. You can adjust the <strong>Order Qty</strong> for any item or remove items before generating purchase orders.
                    </div>
                    <div id="bulkPoVendorsContainer">
                        {{-- Injected dynamically --}}
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="font-weight-bold text-dark" id="bulkPoOverallSummary">
                            Total: <strong>0</strong> POs &bull; <strong>0</strong> Units &bull; <strong>₹ 0.00</strong>
                        </span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-action-outline btn-sm font-weight-bold mr-2" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" id="btnConfirmBulkPo">
                            <i class="fas fa-check mr-1"></i> Confirm & Create All Purchase Orders
                        </button>
                    </div>
                </div>
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
                        <i class="fas fa-info-circle mr-1"></i> Select the <strong>Source Store</strong> and adjust the <strong>Transfer Qty</strong> for each item. Items originating from the same source store will be automatically combined into a single Stock Transfer Challan.
                    </div>
                    <div id="bulkTransferLoading" class="text-center py-5 d-none">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                        <div class="font-weight-bold text-dark">Checking other store stock availability...</div>
                    </div>
                    <div id="bulkTransferTableContainer" class="table-responsive">
                        <table class="table table-sm table-bordered text-sm mb-0">
                            <thead class="bg-dark text-white text-xs">
                                <tr>
                                    <th>Item SKU & Name</th>
                                    <th class="text-center" style="width: 110px;">Deficit Stock</th>
                                    <th style="width: 250px;">Source Store (Surplus Available)</th>
                                    <th class="text-center" style="width: 140px;">Transfer Qty</th>
                                    <th class="text-center" style="width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="bulkTransferItemsTableBody"></tbody>
                        </table>
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
    let currentPage = 1;
    let currentModalSupplierId = null;
    let currentModalItems = [];
    let activeAjaxRequest = null;
    let searchDebounceTimer = null;

    // Multi-Item Bulk Selection State
    // Key: itemId -> { itemId, name, code, cost, qty, supplierId, supplierName, othStock, curStock }
    let selectedItems = {};
    let currentTableRows = [];
    let bulkTransferSourcesMap = {}; // itemId -> Array of source branch options

    $(document).ready(function() {
        // Initialize Select2 for Department, Category, Brands, and Supplier dropdowns
        $('#filterDepartment, #filterCategory, #filterBrand, #filterSupplier').select2({
            theme: 'bootstrap4',
            width: '100%'
        });

        loadTableData(1);

        // Filter change triggers
        $('#filterBranch, #filterStatus, #filterSalesType, #columnFilterAction').on('change', function() {
            loadTableData(1);
        });

        $('#filterDepartment, #filterCategory, #filterBrand, #filterSupplier').on('change.select2 change', function() {
            loadTableData(1);
        });

        // Instant Live Debounced Search (250ms)
        $('#filterSearch').on('input', function() {
            clearTimeout(searchDebounceTimer);
            const val = $(this).val().trim();
            if (val.length > 0) {
                $('#btnClearSearch').removeClass('d-none');
            } else {
                $('#btnClearSearch').addClass('d-none');
            }
            searchDebounceTimer = setTimeout(function() {
                loadTableData(1);
            }, 250);
        });

        // Quick Clear Search Button
        $('#btnClearSearch').on('click', function() {
            $('#filterSearch').val('').focus();
            $(this).addClass('d-none');
            loadTableData(1);
        });

        // Search trigger on enter or button click
        $('#btnFilterSearch').on('click', function() {
            clearTimeout(searchDebounceTimer);
            loadTableData(1);
        });

        $('#filterSearch').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(searchDebounceTimer);
                loadTableData(1);
            }
        });

        // Reset Filters Button
        $('#btnResetFilters').on('click', function() {
            clearTimeout(searchDebounceTimer);
            $('#filterStatus').val('needs_action');
            $('#filterSalesType').val('all');
            $('#columnFilterAction').val('all');
            $('#filterDepartment').val('all').trigger('change.select2');
            $('#filterCategory').val('all').trigger('change.select2');
            $('#filterBrand').val('all').trigger('change.select2');
            $('#filterSupplier').val('all').trigger('change.select2');
            $('#filterSearch').val('');
            $('#btnClearSearch').addClass('d-none');
            loadTableData(1);
        });

        // 1-Click Excel Export Trigger
        $('#btnExportExcel').on('click', function() {
            const params = new URLSearchParams({
                branch_id: $('#filterBranch').val(),
                status: $('#filterStatus').val(),
                sales_type: $('#filterSalesType').val(),
                action: $('#columnFilterAction').val(),
                department_id: $('#filterDepartment').val(),
                category_id: $('#filterCategory').val(),
                brand_id: $('#filterBrand').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val()
            });
            window.location.href = "{{ route('inventory.replenishment.export') }}?" + params.toString();
        });

        // Master "Select All on Page" Checkbox
        $('#checkAllRows').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.row-select-checkbox').each(function() {
                $(this).prop('checked', isChecked);
                const itemId = $(this).data('item-id');
                const rowObj = currentTableRows.find(r => r.item_id === itemId);
                if (rowObj) {
                    if (isChecked) {
                        const customQty = parseInt($('#row-qty-' + itemId).val()) || 0;
                        selectedItems[itemId] = extractItemPayload(rowObj, customQty);
                    } else {
                        delete selectedItems[itemId];
                    }
                }
            });
            updateStickyBar();
        });

        // Row checkbox listener (delegated)
        $(document).on('change', '.row-select-checkbox', function() {
            const itemId = $(this).data('item-id');
            const rowObj = currentTableRows.find(r => r.item_id === itemId);
            if (!rowObj) return;

            if ($(this).is(':checked')) {
                const customQty = parseInt($('#row-qty-' + itemId).val()) || 0;
                selectedItems[itemId] = extractItemPayload(rowObj, customQty);
            } else {
                delete selectedItems[itemId];
                $('#checkAllRows').prop('checked', false);
            }
            updateStickyBar();
        });

        // Custom user quantity change in table row (delegated)
        $(document).on('input change', '.row-qty-input', function() {
            const itemId = $(this).data('item-id');
            const newQty = parseInt($(this).val()) || 0;
            if (selectedItems[itemId]) {
                selectedItems[itemId].qty = newQty;
                updateStickyBar();
            }
        });

        // Deselect All in Sticky Bar
        $('#btnClearSelection').on('click', function() {
            selectedItems = {};
            $('#checkAllRows').prop('checked', false);
            $('.row-select-checkbox').prop('checked', false);
            updateStickyBar();
        });

        // Sync Trigger
        $('#btnSyncLive').on('click', function() {
            const $btn = $(this);
            const branchId = $('#filterBranch').val();
            $('#syncIcon').addClass('fa-spin');
            $('#syncText').text('Calculating...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('inventory.replenishment.sync') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    branch_id: branchId
                },
                success: function(res) {
                    $('#statusAlertMessage').text(res.message);
                    $('#statusAlert').removeClass('d-none');
                    loadTableData(currentPage);
                },
                error: function(err) {
                    alert('Sync failed. Please check system logs.');
                },
                complete: function() {
                    $('#syncIcon').removeClass('fa-spin');
                    $('#syncText').text('Sync Live Stock');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Single PO Modal Confirmation
        $('#btnConfirmGeneratePo').on('click', function() {
            if (!currentModalSupplierId) {
                alert('Supplier ID is missing.');
                return;
            }

            const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating PO...');

            const itemsPayload = [];
            $('.modal-item-qty').each(function() {
                const itemId = $(this).data('item-id');
                const qty = parseInt($(this).val()) || 0;
                if (qty > 0) {
                    itemsPayload.push({ item_id: itemId, qty: qty });
                }
            });

            if (itemsPayload.length === 0) {
                alert('Please enter at least one quantity greater than zero.');
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1 text-success"></i> Confirm & Generate 1-Click PO');
                return;
            }

            $.ajax({
                url: "{{ route('inventory.replenishment.generate-po') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    supplier_id: currentModalSupplierId,
                    branch_id: branchId,
                    items: itemsPayload
                },
                success: function(res) {
                    $('#poModal').modal('hide');
                    $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                    $('#statusAlert').removeClass('d-none');
                    loadTableData(currentPage);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to generate Purchase Order.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1 text-success"></i> Confirm & Generate 1-Click PO');
                }
            });
        });

        // Open Bulk PO Review Modal
        $('#btnBulkPoModalOpen').on('click', function() {
            const itemIds = Object.keys(selectedItems);
            if (itemIds.length === 0) {
                alert('Please select at least one item from the table.');
                return;
            }

            // Strict Validation: Ensure all selected items have qty > 0
            let invalidItemId = null;
            for (let i = 0; i < itemIds.length; i++) {
                const id = itemIds[i];
                const q = parseInt(selectedItems[id].qty) || 0;
                if (q <= 0) {
                    invalidItemId = id;
                    break;
                }
            }
            if (invalidItemId) {
                alert('Please enter a valid Order Quantity (> 0) for all selected items before placing orders.');
                const $targetInput = $('#row-qty-' + invalidItemId);
                if ($targetInput.length) {
                    $targetInput.focus();
                }
                return;
            }

            renderBulkPoModal();
            $('#bulkPoModal').modal('show');
        });

        // Confirm Bulk PO Generation
        $('#btnConfirmBulkPo').on('click', function() {
            // Strict validation inside Bulk PO modal
            let hasInvalidQty = false;
            let firstInvalidInput = null;
            $('.bulk-po-qty-input').each(function() {
                const q = parseInt($(this).val()) || 0;
                if (q <= 0) {
                    hasInvalidQty = true;
                    if (!firstInvalidInput) firstInvalidInput = $(this);
                }
            });

            if (hasInvalidQty) {
                alert('Please enter a valid Order Quantity (> 0) for all items in the purchase orders.');
                if (firstInvalidInput) firstInvalidInput.focus();
                return;
            }

            const groupedOrders = collectBulkPoPayload();
            if (groupedOrders.length === 0) {
                alert('Please ensure at least one item has a quantity greater than zero.');
                return;
            }

            const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating Orders...');

            $.ajax({
                url: "{{ route('inventory.replenishment.bulk-generate-po') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    branch_id: branchId,
                    orders: groupedOrders
                },
                success: function(res) {
                    $('#bulkPoModal').modal('hide');
                    
                    // Build success message with clickable links for each PO
                    let linksHtml = '';
                    if (res.created_pos && res.created_pos.length > 0) {
                        linksHtml = '<div class="mt-1">';
                        res.created_pos.forEach(function(po) {
                            linksHtml += `<a href="${po.redirect_url}" class="badge badge-light border border-dark text-dark mr-2 p-1" target="_blank"><i class="fas fa-file-invoice text-primary mr-1"></i>${escapeHtml(po.po_number)} (${escapeHtml(po.supplier_name)}: ${po.total_qty} units)</a> `;
                        });
                        linksHtml += '</div>';
                    }

                    $('#statusAlertMessage').html(`<strong>${escapeHtml(res.message)}</strong> ${linksHtml}`);
                    $('#statusAlert').removeClass('d-none');

                    // Clear selection and reload
                    selectedItems = {};
                    updateStickyBar();
                    loadTableData(currentPage);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to generate bulk Purchase Orders.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Confirm & Create All Purchase Orders');
                }
            });
        });

        // Open Bulk Transfer Review Modal
        $('#btnBulkTransferModalOpen').on('click', function() {
            const itemIds = Object.keys(selectedItems).map(id => parseInt(id));
            if (itemIds.length === 0) {
                alert('Please select at least one item from the table.');
                return;
            }

            // Strict Validation: Ensure all selected items have qty > 0
            let invalidItemId = null;
            for (let i = 0; i < itemIds.length; i++) {
                const id = itemIds[i];
                const q = parseInt(selectedItems[id].qty) || 0;
                if (q <= 0) {
                    invalidItemId = id;
                    break;
                }
            }
            if (invalidItemId) {
                alert('Please enter a valid Transfer Quantity (> 0) for all selected items before transferring.');
                const $targetInput = $('#row-qty-' + invalidItemId);
                if ($targetInput.length) {
                    $targetInput.focus();
                }
                return;
            }

            const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
            $('#bulkTransferTableContainer').addClass('d-none');
            $('#bulkTransferLoading').removeClass('d-none');
            $('#bulkTransferModal').modal('show');

            $.ajax({
                url: "{{ route('inventory.replenishment.transfer-sources') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    to_branch_id: branchId,
                    item_ids: itemIds
                },
                success: function(res) {
                    bulkTransferSourcesMap = {};
                    (res.items || []).forEach(function(it) {
                        bulkTransferSourcesMap[it.item_id] = it;
                    });
                    renderBulkTransferModal();
                },
                error: function() {
                    alert('Failed to load other store stock availability.');
                    $('#bulkTransferModal').modal('hide');
                },
                complete: function() {
                    $('#bulkTransferLoading').addClass('d-none');
                    $('#bulkTransferTableContainer').removeClass('d-none');
                }
            });
        });

        // Confirm Bulk Transfer Generation
        $('#btnConfirmBulkTransfer').on('click', function() {
            // Strict Validation inside Bulk Transfer modal
            let hasInvalidQty = false;
            let firstInvalidInput = null;
            let missingSource = false;

            $('#bulkTransferItemsTableBody tr').each(function() {
                const fromBranch = $(this).find('.bulk-tr-source-select').val();
                const q = parseInt($(this).find('.bulk-tr-qty-input').val()) || 0;
                if (!fromBranch) {
                    missingSource = true;
                }
                if (q <= 0) {
                    hasInvalidQty = true;
                    if (!firstInvalidInput) firstInvalidInput = $(this).find('.bulk-tr-qty-input');
                }
            });

            if (missingSource) {
                alert('Please select a valid source store for all transfer items.');
                return;
            }

            if (hasInvalidQty) {
                alert('Please enter a valid Transfer Quantity (> 0) for all transfer items.');
                if (firstInvalidInput) firstInvalidInput.focus();
                return;
            }

            const groupedTransfers = collectBulkTransferPayload();
            if (groupedTransfers.length === 0) {
                alert('Please select a valid source store and enter a transfer quantity greater than zero for at least one item.');
                return;
            }

            const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Dispatching Transfers...');

            $.ajax({
                url: "{{ route('inventory.replenishment.bulk-create-transfer') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    to_branch_id: branchId,
                    transfers: groupedTransfers
                },
                success: function(res) {
                    $('#bulkTransferModal').modal('hide');

                    let linksHtml = '';
                    if (res.created_transfers && res.created_transfers.length > 0) {
                        linksHtml = '<div class="mt-1">';
                        res.created_transfers.forEach(function(tr) {
                            linksHtml += `<a href="${tr.redirect_url}" class="badge badge-light border border-dark text-dark mr-2 p-1" target="_blank"><i class="fas fa-random text-info mr-1"></i>${escapeHtml(tr.transfer_number)} (${escapeHtml(tr.from_branch_name)}: ${tr.total_qty} units)</a> `;
                        });
                        linksHtml += '</div>';
                    }

                    $('#statusAlertMessage').html(`<strong>${escapeHtml(res.message)}</strong> ${linksHtml}`);
                    $('#statusAlert').removeClass('d-none');

                    selectedItems = {};
                    updateStickyBar();
                    loadTableData(currentPage);
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

    // Extract item metadata for selected state
    function extractItemPayload(rowObj, customQty) {
        const item = rowObj.item || {};
        const qty = parseInt(customQty) > 0 ? parseInt(customQty) : 0;
        return {
            itemId: rowObj.item_id,
            name: item.name || 'SKU #' + rowObj.item_id,
            code: item.item_code || '-',
            cost: parseFloat(item.cost_price || 0),
            qty: qty,
            supplierId: rowObj.primary_supplier_id || (item.supplier_id || null),
            supplierName: (rowObj.supplier && rowObj.supplier.name) ? rowObj.supplier.name : 'Unassigned Vendor',
            othStock: parseFloat(rowObj.other_branch_stock || 0),
            curStock: parseFloat(rowObj.current_stock || 0),
            suggestedQty: parseInt(rowObj.suggested_qty || 0)
        };
    }

    function getDefaultRowQty(rowObj) {
        return 0;
    }

    // Refresh Sticky Bottom Bar
    function updateStickyBar() {
        const itemIds = Object.keys(selectedItems);
        const count = itemIds.length;

        if (count === 0) {
            $('#stickyBulkBar').addClass('hidden-bar');
            return;
        }

        let totalUnits = 0;
        let totalAmount = 0;

        itemIds.forEach(function(id) {
            const item = selectedItems[id];
            totalUnits += (parseInt(item.qty) || 0);
            totalAmount += ((parseInt(item.qty) || 0) * (parseFloat(item.cost) || 0));
        });

        $('#selectedCountBadge').text(count);
        $('.selected-count-span').text(count);
        $('#selectedTotalUnits').text(totalUnits.toLocaleString());
        $('#selectedTotalAmount').text('₹ ' + totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        $('#stickyBulkBar').removeClass('hidden-bar');
    }

    // Render Bulk PO Modal
    function renderBulkPoModal() {
        const itemIds = Object.keys(selectedItems);
        const vendors = {};

        itemIds.forEach(function(id) {
            const item = selectedItems[id];
            const supId = item.supplierId || 0;
            const supName = item.supplierName || 'Unassigned Vendor';

            if (!vendors[supId]) {
                vendors[supId] = {
                    id: supId,
                    name: supName,
                    items: []
                };
            }
            vendors[supId].items.push(item);
        });

        let html = '';
        let totalPoCount = 0;
        let grandUnits = 0;
        let grandAmount = 0;

        Object.keys(vendors).forEach(function(supId) {
            const vendor = vendors[supId];
            totalPoCount++;

            let vendorUnits = 0;
            let vendorAmount = 0;

            let rowsHtml = '';
            vendor.items.forEach(function(it) {
                const subtotal = it.qty * it.cost;
                vendorUnits += it.qty;
                vendorAmount += subtotal;

                rowsHtml += `
                    <tr id="bulk-po-row-${it.itemId}">
                        <td class="align-middle">
                            <strong class="text-dark">${escapeHtml(it.name)}</strong><br>
                            <span class="badge badge-dark px-2 py-0 text-xs">SKU: ${escapeHtml(it.code)}</span>
                        </td>
                        <td class="text-right align-middle font-weight-bold text-dark">
                            ₹ ${it.cost.toFixed(2)}
                        </td>
                        <td class="text-center align-middle" style="width: 130px;">
                            <input type="number" 
                                   min="1" 
                                   class="form-control form-control-sm text-center font-weight-bold bulk-po-qty-input modern-control" 
                                   data-item-id="${it.itemId}" 
                                   data-supplier-id="${vendor.id}"
                                   value="${it.qty}" 
                                   style="width: 80px; margin: 0 auto;">
                        </td>
                        <td class="text-right align-middle font-weight-bold text-dark" id="bulk-po-subtotal-${it.itemId}">
                            ₹ ${subtotal.toFixed(2)}
                        </td>
                        <td class="text-center align-middle" style="width: 50px;">
                            <button type="button" class="btn btn-outline-danger btn-xs" onclick="removeBulkPoItem(${it.itemId}, ${vendor.id})" title="Remove item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            grandUnits += vendorUnits;
            grandAmount += vendorAmount;

            html += `
                <div class="bulk-vendor-card shadow-sm" id="vendor-card-${vendor.id}">
                    <div class="bulk-vendor-header">
                        <div>
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-building text-primary mr-2"></i> ${escapeHtml(vendor.name)}
                            </h6>
                            <small class="text-muted font-weight-bold">
                                <span id="vendor-count-${vendor.id}">${vendor.items.length}</span> items &bull; 
                                Total: <strong id="vendor-units-${vendor.id}" class="text-dark">${vendorUnits}</strong> units &bull; 
                                Amount: <strong id="vendor-val-${vendor.id}" class="text-primary">₹ ${vendorAmount.toFixed(2)}</strong>
                            </small>
                        </div>
                        <div>
                            ${vendor.id > 0 
                                ? `<button type="button" class="btn btn-action-outline btn-xs font-weight-bold" onclick="createSingleVendorFromBulk(${vendor.id})">
                                     <i class="fas fa-file-invoice mr-1 text-primary"></i> Create PO For This Vendor
                                   </button>` 
                                : '<span class="badge badge-warning text-dark font-weight-bold">Needs Supplier</span>'
                            }
                        </div>
                    </div>
                    <div class="table-responsive p-0">
                        <table class="table table-sm table-bordered text-xs mb-0">
                            <thead class="bg-light text-dark">
                                <tr>
                                    <th>Item SKU & Name</th>
                                    <th class="text-right" style="width: 100px;">Unit Cost</th>
                                    <th class="text-center" style="width: 130px;">Order Qty</th>
                                    <th class="text-right" style="width: 120px;">Subtotal</th>
                                    <th class="text-center" style="width: 50px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        $('#bulkPoVendorsContainer').html(html);
        updateBulkPoModalSummary();

        // Listen for live quantity edits inside the bulk modal
        $('.bulk-po-qty-input').off('input change').on('input change', function() {
            const itemId = parseInt($(this).data('item-id'));
            const supId = parseInt($(this).data('supplier-id'));
            const qty = Math.max(1, parseInt($(this).val()) || 1);

            if (selectedItems[itemId]) {
                selectedItems[itemId].qty = qty;
                $('#row-qty-' + itemId).val(qty); // sync table row
            }

            const item = selectedItems[itemId];
            if (item) {
                const sub = qty * item.cost;
                $('#bulk-po-subtotal-' + itemId).text('₹ ' + sub.toFixed(2));
            }

            updateBulkPoVendorHeader(supId);
            updateBulkPoModalSummary();
            updateStickyBar();
        });
    }

    function removeBulkPoItem(itemId, supId) {
        delete selectedItems[itemId];
        $(`.row-select-checkbox[data-item-id="${itemId}"]`).prop('checked', false);
        $(`#bulk-po-row-${itemId}`).remove();

        const remainingRows = $(`#vendor-card-${supId} tbody tr`).length;
        if (remainingRows === 0) {
            $(`#vendor-card-${supId}`).remove();
        } else {
            updateBulkPoVendorHeader(supId);
        }

        updateBulkPoModalSummary();
        updateStickyBar();
    }

    function updateBulkPoVendorHeader(supId) {
        let u = 0;
        let v = 0;
        let count = 0;

        $(`#vendor-card-${supId} .bulk-po-qty-input`).each(function() {
            const id = $(this).data('item-id');
            const qty = parseInt($(this).val()) || 0;
            const item = selectedItems[id];
            if (item) {
                count++;
                u += qty;
                v += (qty * item.cost);
            }
        });

        $(`#vendor-count-${supId}`).text(count);
        $(`#vendor-units-${supId}`).text(u);
        $(`#vendor-val-${supId}`).text('₹ ' + v.toFixed(2));
    }

    function updateBulkPoModalSummary() {
        let totalPos = $('.bulk-vendor-card').length;
        let totalUnits = 0;
        let totalVal = 0;

        $('.bulk-po-qty-input').each(function() {
            const id = $(this).data('item-id');
            const qty = parseInt($(this).val()) || 0;
            const item = selectedItems[id];
            if (item) {
                totalUnits += qty;
                totalVal += (qty * item.cost);
            }
        });

        $('#bulkPoOverallSummary').html(`
            Will create: <strong class="text-primary">${totalPos}</strong> PO(s) &bull; 
            Total Units: <strong class="text-warning">${totalUnits}</strong> &bull; 
            Total Value: <strong class="text-success">₹ ${totalVal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
        `);
    }

    function collectBulkPoPayload() {
        const vendors = {};
        $('.bulk-po-qty-input').each(function() {
            const itemId = parseInt($(this).data('item-id'));
            const supId = parseInt($(this).data('supplier-id'));
            const qty = parseInt($(this).val()) || 0;

            if (qty > 0 && supId > 0) {
                if (!vendors[supId]) {
                    vendors[supId] = {
                        supplier_id: supId,
                        items: []
                    };
                }
                vendors[supId].items.push({
                    item_id: itemId,
                    qty: qty
                });
            }
        });

        return Object.values(vendors);
    }

    function createSingleVendorFromBulk(supplierId) {
        const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
        const itemsPayload = [];

        $(`#vendor-card-${supplierId} .bulk-po-qty-input`).each(function() {
            const itemId = parseInt($(this).data('item-id'));
            const qty = parseInt($(this).val()) || 0;
            if (qty > 0) {
                itemsPayload.push({ item_id: itemId, qty: qty });
            }
        });

        if (itemsPayload.length === 0) {
            alert('No valid items found for this supplier.');
            return;
        }

        if (!confirm(`Create Purchase Order for this supplier with ${itemsPayload.length} items?`)) {
            return;
        }

        $.ajax({
            url: "{{ route('inventory.replenishment.generate-po') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                supplier_id: supplierId,
                branch_id: branchId,
                items: itemsPayload
            },
            success: function(res) {
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                $('#statusAlert').removeClass('d-none');

                // Remove these items from selected state
                itemsPayload.forEach(it => {
                    delete selectedItems[it.item_id];
                });

                $(`#vendor-card-${supplierId}`).remove();
                updateBulkPoModalSummary();
                updateStickyBar();

                if ($('.bulk-vendor-card').length === 0) {
                    $('#bulkPoModal').modal('hide');
                }
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to create Purchase Order.');
            }
        });
    }

    // Render Bulk Stock Transfer Modal
    function renderBulkTransferModal() {
        const itemIds = Object.keys(selectedItems);
        let rowsHtml = '';

        itemIds.forEach(function(id) {
            const it = selectedItems[id];
            const sourceInfo = bulkTransferSourcesMap[id] || { sources: [] };
            const sources = sourceInfo.sources || [];

            let optionsHtml = '';
            if (sources.length === 0) {
                optionsHtml = '<option value="" disabled selected>No other store has stock</option>';
            } else {
                sources.forEach(function(src) {
                    const isSelected = (sourceInfo.recommended_branch_id === src.branch_id) ? 'selected' : '';
                    optionsHtml += `<option value="${src.branch_id}" data-max="${src.available_stock}" ${isSelected}>${escapeHtml(src.branch_name)} (Available: ${src.available_stock})</option>`;
                });
            }

            const initialMax = sources.length > 0 ? (sources[0].available_stock || 999) : 999;

            rowsHtml += `
                <tr id="bulk-tr-row-${it.itemId}">
                    <td class="align-middle">
                        <strong class="text-dark">${escapeHtml(it.name)}</strong><br>
                        <span class="badge badge-dark px-2 py-0 text-xs">SKU: ${escapeHtml(it.code)}</span>
                    </td>
                    <td class="text-center align-middle font-weight-bold text-danger">
                        ${it.curStock} units
                    </td>
                    <td class="align-middle">
                        <select class="form-control form-control-sm modern-control bulk-tr-source-select" data-item-id="${it.itemId}" ${sources.length === 0 ? 'disabled' : ''}>
                            ${optionsHtml}
                        </select>
                    </td>
                    <td class="text-center align-middle" style="width: 140px;">
                        <input type="number" 
                               min="1" 
                               max="${initialMax}" 
                               class="form-control form-control-sm text-center font-weight-bold bulk-tr-qty-input modern-control" 
                               data-item-id="${it.itemId}" 
                               value="${it.qty}" 
                               ${sources.length === 0 ? 'disabled' : ''}
                               style="width: 80px; margin: 0 auto;">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="removeBulkTransferItem(${it.itemId})" title="Remove item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#bulkTransferItemsTableBody').html(rowsHtml);
        updateBulkTransferModalSummary();

        // Listen for source changes to update max allowed transfer
        $('.bulk-tr-source-select').off('change').on('change', function() {
            const itemId = $(this).data('item-id');
            const max = $(this).find('option:selected').data('max') || 999;
            const $qtyInput = $(`.bulk-tr-qty-input[data-item-id="${itemId}"]`);
            $qtyInput.attr('max', max);
            if (parseInt($qtyInput.val()) > max) {
                $qtyInput.val(max);
            }
            updateBulkTransferModalSummary();
        });

        // Listen for quantity change in transfer modal
        $('.bulk-tr-qty-input').off('input change').on('input change', function() {
            const itemId = $(this).data('item-id');
            const max = parseInt($(this).attr('max')) || 999;
            let val = parseInt($(this).val()) || 1;
            if (val > max) val = max;
            if (val < 1) val = 1;
            $(this).val(val);

            if (selectedItems[itemId]) {
                selectedItems[itemId].qty = val;
                $('#row-qty-' + itemId).val(val);
            }

            updateBulkTransferModalSummary();
            updateStickyBar();
        });
    }

    function removeBulkTransferItem(itemId) {
        delete selectedItems[itemId];
        $(`.row-select-checkbox[data-item-id="${itemId}"]`).prop('checked', false);
        $(`#bulk-tr-row-${itemId}`).remove();
        updateBulkTransferModalSummary();
        updateStickyBar();

        if ($('#bulkTransferItemsTableBody tr').length === 0) {
            $('#bulkTransferModal').modal('hide');
        }
    }

    function updateBulkTransferModalSummary() {
        const routes = {};
        let totalUnits = 0;

        $('#bulkTransferItemsTableBody tr').each(function() {
            const fromBranch = $(this).find('.bulk-tr-source-select').val();
            const qty = parseInt($(this).find('.bulk-tr-qty-input').val()) || 0;

            if (fromBranch && qty > 0) {
                routes[fromBranch] = (routes[fromBranch] || 0) + 1;
                totalUnits += qty;
            }
        });

        const challanCount = Object.keys(routes).length;
        $('#bulkTransferOverallSummary').html(`
            Will create: <strong class="text-info">${challanCount}</strong> Stock Transfer Challan(s) &bull; 
            Total Transfer Units: <strong class="text-warning">${totalUnits}</strong>
        `);
    }

    function collectBulkTransferPayload() {
        const transfersMap = {};

        $('#bulkTransferItemsTableBody tr').each(function() {
            const fromBranchId = parseInt($(this).find('.bulk-tr-source-select').val());
            const itemId = parseInt($(this).find('.bulk-tr-qty-input').data('item-id'));
            const qty = parseInt($(this).find('.bulk-tr-qty-input').val()) || 0;

            if (fromBranchId && itemId && qty > 0) {
                if (!transfersMap[fromBranchId]) {
                    transfersMap[fromBranchId] = {
                        from_branch_id: fromBranchId,
                        items: []
                    };
                }
                transfersMap[fromBranchId].items.push({
                    item_id: itemId,
                    qty: qty
                });
            }
        });

        return Object.values(transfersMap);
    }

    function loadTableData(page = 1) {
        if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
            activeAjaxRequest.abort();
        }

        currentPage = page;
        $('#tableBody').html('<tr><td colspan="10" class="text-center py-4 font-weight-bold text-dark"><i class="fas fa-spinner fa-spin mr-1 text-primary"></i> Loading fast search results...</td></tr>');

        activeAjaxRequest = $.ajax({
            url: "{{ route('inventory.replenishment.data') }}",
            type: 'GET',
            data: {
                branch_id: $('#filterBranch').val(),
                status: $('#filterStatus').val(),
                sales_type: $('#filterSalesType').val(),
                action: $('#columnFilterAction').val(),
                department_id: $('#filterDepartment').val(),
                category_id: $('#filterCategory').val(),
                brand_id: $('#filterBrand').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val(),
                page: page
            },
            success: function(res) {
                currentTableRows = res.data || [];
                renderTable(res.data);
                renderPagination(res);
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    return;
                }
                $('#tableBody').html('<tr><td colspan="10" class="text-center py-3 text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Failed to load replenishment data.</td></tr>');
            }
        });
    }

    function renderTable(rows) {
        if (!rows || rows.length === 0) {
            $('#tableBody').html('<tr><td colspan="10" class="text-center py-4 font-weight-bold text-dark"><i class="fas fa-box-open mr-1"></i> No items found matching the selected filter criteria.</td></tr>');
            $('#checkAllRows').prop('checked', false);
            return;
        }

        let allCheckedOnPage = true;
        let html = '';

        rows.forEach(function(row) {
            const item = row.item || {};
            const itemName = item.name || 'Unnamed Item #' + row.item_id;
            const itemCode = item.item_code || '-';
            const brand = (item.brand && item.brand.name) ? item.brand.name : (item.brand_value && item.brand_value.name ? item.brand_value.name : '-');
            const category = (item.category_value && item.category_value.name) ? item.category_value.name : '-';
            const department = (item.department_value && item.department_value.name) ? item.department_value.name : '-';
            const curStock = Number(row.current_stock || 0).toFixed(0);
            const othStock = Number(row.other_branch_stock || 0).toFixed(0);
            const sales30d = Number(row.sales_30d || 0).toFixed(0);
            const avgDay = Number(row.daily_avg_sales || 0).toFixed(2);
            const coverDays = Number(row.cover_days || 0).toFixed(1);
            const targetDays = Number(row.target_cover_days || 0);
            const requiredStock = Math.ceil((row.daily_avg_sales || 0) * targetDays);
            const suggestedQty = Number(row.suggested_qty || 0);
            const status = row.stock_status || 'Healthy';
            const salesType = row.sales_type || 'Dead Stock';
            const supplierId = row.primary_supplier_id || (item.supplier_id || null);

            // Determine quantity: if user has already customized it in selectedItems, keep that, otherwise default to blank ""
            const isChecked = selectedItems.hasOwnProperty(row.item_id);
            if (!isChecked) {
                allCheckedOnPage = false;
            }
            const selectedQtyVal = (selectedItems[row.item_id] && selectedItems[row.item_id].qty > 0)
                ? selectedItems[row.item_id].qty
                : '';

            let statusChip = 'status-pill dead';
            if (status === 'Critical') statusChip = 'status-pill critical';
            else if (status === 'Low') statusChip = 'status-pill low';
            else if (status === 'Healthy') statusChip = 'status-pill healthy';
            else if (status === 'Overstock') statusChip = 'status-pill overstock';

            let salesTypeBadge = 'badge-dark';
            let salesTypeIcon = 'fa-bed';
            if (salesType === 'Highest Moving') { salesTypeBadge = 'badge-success'; salesTypeIcon = 'fa-fire'; }
            else if (salesType === 'Fast Moving') { salesTypeBadge = 'badge-primary'; salesTypeIcon = 'fa-tachometer-alt'; }
            else if (salesType === 'Normal Moving') { salesTypeBadge = 'badge-info'; salesTypeIcon = 'fa-boxes'; }
            else if (salesType === 'Slow Moving') { salesTypeBadge = 'badge-warning text-dark'; salesTypeIcon = 'fa-hourglass-half'; }

            let coverDaysColor = 'color: #0f172a;';
            if (status === 'Critical') coverDaysColor = 'color: #dc2626; font-weight: 800;';
            else if (status === 'Low') coverDaysColor = 'color: #d97706; font-weight: 800;';

            html += `
                <tr id="row-item-${row.item_id}">
                    <td class="text-center align-middle">
                        <input type="checkbox" class="row-select-checkbox" data-item-id="${row.item_id}" ${isChecked ? 'checked' : ''}>
                    </td>
                    <td>
                        <div class="item-title">${escapeHtml(itemName)}</div>
                        <div class="item-subtitle">
                            <span class="badge badge-dark px-2 py-1 font-weight-bold text-xs" style="border-radius:4px;">SKU: ${escapeHtml(itemCode)}</span> &bull; 
                            <span class="font-weight-bold text-dark"><i class="fas fa-award text-muted mr-1"></i>${escapeHtml(brand)}</span> &bull; 
                            <span class="badge badge-secondary px-2 py-0 font-weight-bold text-xs">Dept: ${escapeHtml(department)}</span> &bull; 
                            <span class="badge badge-light border border-dark text-dark px-2 py-0 font-weight-bold text-xs">Cat: ${escapeHtml(category)}</span>
                        </div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark" style="font-size:0.95rem;">${curStock} units</div>
                        ${othStock > 0 ? `<div class="item-subtitle text-dark mt-1"><i class="fas fa-warehouse mr-1 text-primary"></i> Other Store: <strong class="text-dark">${othStock}</strong></div>` : ''}
                    </td>
                    <td class="text-center font-weight-bold text-dark">
                        ${avgDay}<br>
                        <small class="font-weight-bold text-dark" style="font-size:0.7rem;">(${sales30d} in 30d)</small>
                    </td>
                    <td class="text-center" style="${coverDaysColor}">
                        ${coverDays} <span class="text-xs text-dark font-weight-bold">days</span>
                    </td>
                    <td class="text-center font-weight-bold text-dark">
                        <span class="badge badge-light border border-dark text-dark font-weight-bold px-2 py-1" style="font-size: 0.88rem;">${requiredStock} units</span>
                        <br><small class="text-dark font-weight-bold" style="font-size:0.7rem;">(${targetDays}d target)</small>
                    </td>
                    <td class="text-center align-middle">
                        <div class="d-flex flex-column align-items-center">
                            <input type="number" 
                                   min="1" 
                                   id="row-qty-${row.item_id}" 
                                   class="form-control form-control-sm text-center font-weight-bold row-qty-input modern-control shadow-sm" 
                                   data-item-id="${row.item_id}" 
                                   value="${selectedQtyVal}" 
                                   placeholder="Qty"
                                   title="Enter custom quantity to Order or Transfer"
                                   style="width: 80px; height: 32px; font-size: 0.9rem; border: 1.5px solid #0f172a; border-radius: 6px;">
                            ${suggestedQty > 0 
                                ? `<small class="text-muted font-weight-bold mt-1" style="font-size:0.7rem;">Sugg: ${suggestedQty}</small>` 
                                : (othStock > 0 ? `<small class="text-info font-weight-bold mt-1" style="font-size:0.7rem;">Surplus: ${othStock}</small>` : '')
                            }
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${salesTypeBadge} px-2 py-1 font-weight-bold text-xs" style="border-radius:6px;">
                            <i class="fas ${salesTypeIcon} mr-1"></i> ${salesType}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="${statusChip}">${status}</span>
                    </td>
                    <td class="text-center align-middle">
                        <div class="d-flex justify-content-center">
                            ${suggestedQty > 0 && supplierId
                                ? `<button type="button" class="btn btn-action-dark btn-xs mr-1 shadow-sm font-weight-bold" onclick="triggerRowPo(${row.item_id}, ${supplierId}, '${escapeJs(itemName)}')" title="Generate PO for this item">
                                     <i class="fas fa-cart-plus mr-1"></i> Order
                                   </button>` 
                                : ''
                            }
                            ${othStock > 0 && (status === 'Critical' || status === 'Low' || suggestedQty > 0)
                                ? `<button type="button" class="btn btn-action-outline btn-xs shadow-sm font-weight-bold" onclick="triggerRowTransfer(${row.item_id})" title="Transfer surplus stock from another store">
                                     <i class="fas fa-exchange-alt mr-1"></i> Transfer
                                   </button>` 
                                : ''
                            }
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
        $('#checkAllRows').prop('checked', allCheckedOnPage && rows.length > 0);
    }

    function renderPagination(res) {
        $('#totalFilteredRows').text(res.total + ' Items Found');
        $('#paginationInfo').text(`Showing page ${res.current_page} of ${res.last_page} (${res.total} total items)`);

        let html = '';
        if (res.last_page > 1) {
            html += `<li class="page-item ${res.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="loadTableData(${res.current_page - 1})">&laquo;</a>
                     </li>`;

            let start = Math.max(1, res.current_page - 2);
            let end = Math.min(res.last_page, res.current_page + 2);

            for (let i = start; i <= end; i++) {
                html += `<li class="page-item ${i === res.current_page ? 'active' : ''}">
                            <a class="page-link font-weight-bold ${i === res.current_page ? 'bg-dark text-white border-dark' : 'text-dark'}" href="javascript:void(0)" onclick="loadTableData(${i})">${i}</a>
                         </li>`;
            }

            html += `<li class="page-item ${res.current_page === res.last_page ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="loadTableData(${res.current_page + 1})">&raquo;</a>
                     </li>`;
        }

        $('#paginationControls').html(html);
    }

    // Trigger single row PO with user-decided custom quantity
    function triggerRowPo(itemId, supplierId, itemName) {
        const $qtyInput = $('#row-qty-' + itemId);
        const customQty = parseInt($qtyInput.val()) || 0;
        if (!customQty || customQty <= 0) {
            alert('Please enter a valid Order Quantity (> 0) before placing order.');
            $qtyInput.focus();
            return;
        }

        if (!confirm(`Generate Purchase Order for "${itemName}" with ${customQty} unit(s)?`)) {
            return;
        }

        const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};

        $.ajax({
            url: "{{ route('inventory.replenishment.generate-po') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                supplier_id: supplierId,
                branch_id: branchId,
                items: [{ item_id: itemId, qty: customQty }]
            },
            success: function(res) {
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                $('#statusAlert').removeClass('d-none');
                delete selectedItems[itemId];
                updateStickyBar();
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error generating Purchase Order.');
            }
        });
    }

    // Trigger single row Transfer with user-decided custom quantity
    function triggerRowTransfer(itemId) {
        const rowObj = currentTableRows.find(r => r.item_id === itemId);
        if (!rowObj) return;

        const $qtyInput = $('#row-qty-' + itemId);
        const customQty = parseInt($qtyInput.val()) || 0;
        if (!customQty || customQty <= 0) {
            alert('Please enter a valid Transfer Quantity (> 0) before transferring stock.');
            $qtyInput.focus();
            return;
        }

        selectedItems[itemId] = extractItemPayload(rowObj, customQty);
        $(`.row-select-checkbox[data-item-id="${itemId}"]`).prop('checked', true);
        updateStickyBar();

        $('#btnBulkTransferModalOpen').click();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeJs(text) {
        if (!text) return '';
        return String(text).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
@stop
