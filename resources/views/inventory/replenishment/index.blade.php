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

                {{-- Filter Toolbar (Branch, Status, Movement, Action, Department, Category, Brand, Supplier, Search) --}}
                <div class="modern-filter-toolbar">
                    <div class="row g-2 align-items-center">
                        {{-- Row 1: Core Dimensions & Action --}}
                        <div class="col-md-3 col-6 mb-2">
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

                        <div class="col-md-3 col-6 mb-2">
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

                        <div class="col-md-3 col-6 mb-2">
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

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-tasks mr-1 text-primary"></i> Action</label>
                            <select id="filterAction" class="form-control form-control-sm modern-control">
                                <option value="all" {{ !request()->has('action') || request('action') === 'all' ? 'selected' : '' }}>All Actions</option>
                                <option value="order" {{ request('action') === 'order' ? 'selected' : '' }}>🛒 Order (PO Needed)</option>
                                <option value="transfer" {{ request('action') === 'transfer' ? 'selected' : '' }}>🔄 Transfer (Other Store Stock)</option>
                            </select>
                        </div>

                        {{-- Row 2: Department, Category, Brand, Supplier from Item Master --}}
                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-layer-group mr-1 text-primary"></i> DEPARTMENT</label>
                            <select id="filterDepartment" class="form-control form-control-sm modern-control">
                                <option value="all" {{ !request()->has('department_id') || request('department_id') === 'all' ? 'selected' : '' }}>All Departments</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-tags mr-1 text-primary"></i> CATEGORY</label>
                            <select id="filterCategory" class="form-control form-control-sm modern-control">
                                <option value="all" {{ !request()->has('category_id') || request('category_id') === 'all' ? 'selected' : '' }}>All Categories</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-award mr-1 text-primary"></i> Brands</label>
                            <select id="filterBrand" class="form-control form-control-sm modern-control">
                                <option value="all" {{ !request()->has('brand_id') || request('brand_id') === 'all' ? 'selected' : '' }}>All Brands</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" {{ request('brand_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="filter-field-label"><i class="fas fa-building mr-1 text-primary"></i> Supplier</label>
                            <select id="filterSupplier" class="form-control form-control-sm modern-control">
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
                                <th>Item Details</th>
                                <th>Current Stock</th>
                                <th class="text-center">Daily Avg</th>
                                <th class="text-center">Cover Days</th>
                                <th class="text-center">Required Stock</th>
                                <th class="text-center">Suggested Qty</th>
                                <th class="text-center">Sales Type</th>
                                <th class="text-center">Stock Status</th>
                                <th class="text-center" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4 font-weight-bold text-dark">
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

    {{-- Supplier Review & 1-Click PO Modal --}}
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
@stop

@section('js')
<script>
    let currentPage = 1;
    let currentModalSupplierId = null;
    let currentModalItems = [];
    let activeAjaxRequest = null;
    let searchDebounceTimer = null;

    $(document).ready(function() {
        loadTableData(1);

        // Filter change triggers
        $('#filterBranch, #filterStatus, #filterSalesType, #filterAction, #filterDepartment, #filterCategory, #filterBrand, #filterSupplier').on('change', function() {
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
            $('#filterAction').val('all');
            $('#filterDepartment').val('all');
            $('#filterCategory').val('all');
            $('#filterBrand').val('all');
            $('#filterSupplier').val('all');
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
                action: $('#filterAction').val(),
                department_id: $('#filterDepartment').val(),
                category_id: $('#filterCategory').val(),
                brand_id: $('#filterBrand').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val()
            });
            window.location.href = "{{ route('inventory.replenishment.export') }}?" + params.toString();
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
                    if (res.summary) {
                        updateKpis(res.summary);
                    }
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

        // PO Confirmation inside modal
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
    });

    function updateKpis(summary) {
        $('#kpiCriticalCount').text(Number(summary.critical_count || 0).toLocaleString());
        $('#kpiLowCount').text(Number(summary.low_count || 0).toLocaleString());
        $('#kpiTransferCount').text(Number(summary.transfer_opportunities_count || 0).toLocaleString());
        $('#kpiPoValue').text('₹ ' + Number(summary.suggested_po_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2}));
    }

    function loadTableData(page = 1) {
        // Abort previous in-flight search request to prevent stale results and save server resources
        if (activeAjaxRequest && activeAjaxRequest.readyState !== 4) {
            activeAjaxRequest.abort();
        }

        currentPage = page;
        $('#tableBody').html('<tr><td colspan="9" class="text-center py-4 font-weight-bold text-dark"><i class="fas fa-spinner fa-spin mr-1 text-primary"></i> Loading fast search results...</td></tr>');

        activeAjaxRequest = $.ajax({
            url: "{{ route('inventory.replenishment.data') }}",
            type: 'GET',
            data: {
                branch_id: $('#filterBranch').val(),
                status: $('#filterStatus').val(),
                sales_type: $('#filterSalesType').val(),
                action: $('#filterAction').val(),
                department_id: $('#filterDepartment').val(),
                category_id: $('#filterCategory').val(),
                brand_id: $('#filterBrand').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val(),
                page: page
            },
            success: function(res) {
                renderTable(res.data);
                renderPagination(res);
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    return; // Ignore cancelled in-flight requests cleanly
                }
                $('#tableBody').html('<tr><td colspan="9" class="text-center py-3 text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Failed to load replenishment data.</td></tr>');
            }
        });
    }

    function renderTable(rows) {
        if (!rows || rows.length === 0) {
            $('#tableBody').html('<tr><td colspan="9" class="text-center py-4 font-weight-bold text-dark"><i class="fas fa-box-open mr-1"></i> No items found matching the selected filter criteria.</td></tr>');
            return;
        }

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
                <tr>
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
                    <td class="text-center">
                        ${suggestedQty > 0 
                            ? `<span class="reorder-badge">${suggestedQty} Qty</span>` 
                            : `<span class="text-dark font-weight-bold">&mdash;</span>`
                        }
                    </td>
                    <td class="text-center">
                        <span class="badge ${salesTypeBadge} px-2 py-1 font-weight-bold text-xs" style="border-radius:6px;">
                            <i class="fas ${salesTypeIcon} mr-1"></i> ${salesType}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="${statusChip}">${status}</span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center">
                            ${suggestedQty > 0 && supplierId
                                ? `<button type="button" class="btn btn-action-dark btn-xs mr-1 shadow-sm font-weight-bold" onclick="triggerSingleItemPo(${row.item_id}, ${supplierId}, ${suggestedQty}, '${escapeJs(itemName)}')" title="Generate PO for this item">
                                     <i class="fas fa-cart-plus mr-1"></i> Order
                                   </button>` 
                                : ''
                            }
                            ${othStock > 0 && (status === 'Critical' || status === 'Low')
                                ? `<a href="{{ route('inventory.replenishment.transfers') }}" class="btn btn-action-outline btn-xs shadow-sm font-weight-bold" title="View available transfer sources">
                                     <i class="fas fa-exchange-alt mr-1"></i> Transfer
                                   </a>` 
                                : ''
                            }
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
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

    function triggerSingleItemPo(itemId, supplierId, qty, itemName) {
        if (!confirm(`Generate 1-Click Purchase Order for ${itemName} (${qty} units)?`)) {
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
                items: [{ item_id: itemId, qty: qty }]
            },
            success: function(res) {
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                $('#statusAlert').removeClass('d-none');
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error generating Purchase Order.');
            }
        });
    }

    function triggerQuickTransfer(itemId, fromBranchId, toBranchId, qty, itemName) {
        if (!confirm(`Dispatch ${qty} units of ${itemName} from source store?`)) {
            return;
        }

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
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Transfer</a>`);
                $('#statusAlert').removeClass('d-none');
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.');
            }
        });
    }

    function openSupplierPoModal(supplierId, supplierName, count, val, items) {
        currentModalSupplierId = supplierId;
        currentModalItems = items || [];

        $('#poModalTitle').html(`<i class="fas fa-truck text-primary mr-2"></i> PO Recommendation for ${supplierName}`);
        $('#modalVendorName').text(supplierName);
        $('#modalEstimatedVal').text(val);

        let rowsHtml = '';
        currentModalItems.forEach(function(item) {
            rowsHtml += `
                <tr>
                    <td class="align-middle">
                        <strong class="text-dark">${escapeHtml(item.name)}</strong>
                    </td>
                    <td class="text-right align-middle font-weight-bold text-dark">₹ ${Number(item.cost || 0).toFixed(2)}</td>
                    <td class="text-center align-middle">
                        <input type="number" class="form-control form-control-sm text-center font-weight-bold modal-item-qty modern-control" data-item-id="${item.item_id}" value="${item.qty}" min="1">
                    </td>
                    <td class="text-right align-middle font-weight-bold text-dark">₹ ${(item.qty * (item.cost || 0)).toFixed(2)}</td>
                </tr>
            `;
        });

        $('#poModalItemsTableBody').html(rowsHtml);
        $('#poModal').modal('show');
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
