@extends('adminlte::page')

@section('title', 'Custom Report Studio & Analytics Builder')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-magic text-info mr-2"></i> Custom Report Studio & Analytics Builder
            </h1>
            <p class="text-muted small mb-0">Dynamic multi-dimensional reports: Group By any metric, trace Item-Supplier sourcing, inspect single-item monthly sales, and save custom reports.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-arrow-left mr-1"></i> Reports Center
            </a>
            <a href="{{ route('reports.smart-analytics') }}" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-chart-line mr-1"></i> Smart 360° Studio
            </a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid px-0">

    {{-- 1. QUICK SMART PRESET TEMPLATES --}}
    <div class="card card-outline card-info shadow-sm mb-3">
        <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex flex-wrap align-items-center mb-1 mb-md-0">
                <span class="font-weight-bold text-dark mr-2 small"><i class="fas fa-bolt text-warning mr-1"></i> Smart Presets:</span>
                
                {{-- User exact requirement 1: Item Supplier Sourcing --}}
                <button type="button" class="btn btn-sm btn-info font-weight-bold mr-1 mb-1 preset-btn" data-preset="item_supplier">
                    <i class="fas fa-truck-loading mr-1"></i> 📦 Item ⇄ Supplier Sourcing
                </button>

                {{-- User exact requirement 2: Single Item Monthly Drilldown --}}
                <button type="button" class="btn btn-sm btn-primary font-weight-bold mr-1 mb-1 preset-btn" data-preset="single_item">
                    <i class="fas fa-search-dollar mr-1"></i> 🎯 Single Item Monthly Sales
                </button>

                <button type="button" class="btn btn-sm btn-outline-success font-weight-bold mr-1 mb-1 preset-btn" data-preset="top_selling">
                    <i class="fas fa-fire mr-1"></i> 🚀 Top Selling Items
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold mr-1 mb-1 preset-btn" data-preset="slow_moving">
                    <i class="fas fa-hourglass-end mr-1"></i> 🐢 Slow Moving / Dead Stock
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold mr-1 mb-1 preset-btn text-dark" data-preset="vip_customers">
                    <i class="fas fa-crown mr-1"></i> 👑 Top VIP Customers
                </button>
                <button type="button" class="btn btn-sm btn-outline-purple font-weight-bold mr-1 mb-1 preset-btn" data-preset="payment_mode" style="color: #6f42c1; border-color: #6f42c1;">
                    <i class="fas fa-wallet mr-1"></i> 💳 Cash vs UPI vs Card
                </button>
            </div>
            
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-toggle-filter" title="Toggle Filter Panel">
                    <i class="fas fa-sliders-h mr-1"></i> <span id="toggle-filter-text">Hide Controls</span>
                </button>
            </div>
        </div>

        {{-- USER SAVED REPORTS PILLS --}}
        <div class="card-footer bg-light p-2 border-top {{ count($savedReports) === 0 ? 'd-none' : '' }}" id="saved-reports-bar">
            <div class="d-flex flex-wrap align-items-center">
                <span class="small font-weight-bold text-secondary mr-2"><i class="fas fa-star text-warning mr-1"></i> My Saved Reports:</span>
                <div class="d-flex flex-wrap" id="saved-reports-container">
                    @foreach($savedReports as $sr)
                        <div class="badge badge-light border p-1 px-2 mr-2 mb-1 d-inline-flex align-items-center shadow-xs" id="saved-report-pill-{{ $sr->id }}">
                            <a href="javascript:void(0)" class="text-dark font-weight-bold mr-2 load-saved-report" 
                               data-id="{{ $sr->id }}" 
                               data-name="{{ $sr->name }}"
                               data-group="{{ $sr->group_by }}"
                               data-metrics='@json($sr->metrics)'
                               data-filters='@json($sr->filters)'>
                                <i class="fas fa-file-alt text-info mr-1"></i> {{ $sr->name }}
                            </a>
                            <button type="button" class="btn btn-link text-danger p-0 delete-saved-report" data-id="{{ $sr->id }}" title="Delete report">
                                <i class="fas fa-times fa-xs"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 2. REPORT BUILDER CONTROL PANEL --}}
    <div class="card shadow-sm mb-3 border-0" id="filter-panel">
        <div class="card-header bg-white py-2 border-bottom">
            <h6 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-cogs text-primary mr-1"></i> Report Configuration & Studio Controls
            </h6>
        </div>
        <div class="card-body p-3">
            <form id="builder-form">
                @csrf
                <div class="row">
                    {{-- 1. GROUP BY --}}
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold small text-dark"><i class="fas fa-layer-group text-info mr-1"></i> 1. Group By (Summarize Data):</label>
                        <select class="form-control form-control-sm font-weight-bold text-primary" id="group_by" name="group_by">
                            <option value="item">📦 By Item / Product</option>
                            <option value="item_supplier">🚚 By Item ⇄ Supplier Sourcing (Traceability)</option>
                            <option value="customer">👤 By Customer (Sales & Visits)</option>
                            <option value="category">📂 By Category / Sub-category</option>
                            <option value="brand">🏷️ By Brand / Manufacturer</option>
                            <option value="cashier">🧑‍💼 By Cashier / Billing Staff</option>
                            <option value="payment_mode">💳 By Payment Mode (Cash, UPI, Card)</option>
                            <option value="date">📅 By Day / Date</option>
                        </select>
                        <small class="form-text text-muted" id="group-desc-text">
                            Aggregates each item's sold quantity, total revenue, and profit.
                        </small>
                    </div>

                    {{-- 2. METRICS SELECTION --}}
                    <div class="col-md-5 mb-3">
                        <label class="font-weight-bold small text-dark"><i class="fas fa-check-square text-success mr-1"></i> 2. Choose Metrics & Calculations:</label>
                        <div class="d-flex flex-wrap bg-light p-2 rounded border" id="metrics-container">
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_qty" name="metrics[]" value="qty" checked>
                                <label class="custom-control-label small font-weight-bold" for="m_qty">Total Quantity</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_sales" name="metrics[]" value="sales_value" checked>
                                <label class="custom-control-label small font-weight-bold" for="m_sales">Total Value (₹)</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_disc" name="metrics[]" value="discount">
                                <label class="custom-control-label small font-weight-bold" for="m_disc">Discount (₹)</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_margin" name="metrics[]" value="margin" checked>
                                <label class="custom-control-label small font-weight-bold" for="m_margin">Gross Profit / Margin</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_bills" name="metrics[]" value="bill_count" checked>
                                <label class="custom-control-label small font-weight-bold" for="m_bills">Bill Count</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_aov" name="metrics[]" value="aov">
                                <label class="custom-control-label small font-weight-bold" for="m_aov">Avg Order Value (AOV)</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox" class="custom-control-input metric-check" id="m_last_date" name="metrics[]" value="last_date">
                                <label class="custom-control-label small font-weight-bold" for="m_last_date">Last Transaction</label>
                            </div>
                        </div>
                    </div>

                    {{-- 3. DATE PRESETS & FILTERS --}}
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold small text-dark"><i class="fas fa-calendar-alt text-warning mr-1"></i> 3. Date Presets:</label>
                        <div class="btn-group btn-group-sm btn-group-toggle d-flex mb-2" data-toggle="buttons">
                            <label class="btn btn-outline-secondary flex-fill">
                                <input type="radio" name="date_preset" value="today"> Today
                            </label>
                            <label class="btn btn-outline-secondary flex-fill">
                                <input type="radio" name="date_preset" value="this_week"> Week
                            </label>
                            <label class="btn btn-outline-secondary flex-fill active">
                                <input type="radio" name="date_preset" value="this_month" checked> This Month
                            </label>
                            <label class="btn btn-outline-secondary flex-fill">
                                <input type="radio" name="date_preset" value="last_month"> Last Mo.
                            </label>
                            <label class="btn btn-outline-secondary flex-fill">
                                <input type="radio" name="date_preset" value="all_time"> All Time
                            </label>
                            <label class="btn btn-outline-secondary flex-fill">
                                <input type="radio" name="date_preset" value="custom"> Custom
                            </label>
                        </div>
                        <div class="row d-none" id="custom-date-row">
                            <div class="col-6">
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ date('Y-m-01') }}">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECONDARY FILTERS ROW --}}
                <div class="row pt-2 border-top">
                    {{-- Branch Filter --}}
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted mb-1"><i class="fas fa-store-alt mr-1"></i> Branch:</label>
                        <select class="form-control form-control-sm" id="branch_id" name="branch_id">
                            <option value="">All Branches</option>
                            @foreach($branches as $bId => $bName)
                                <option value="{{ $bId }}">{{ $bName }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Specific Item Filter --}}
                    <div class="col-md-3 mb-2" id="filter-item-col">
                        <label class="small text-muted mb-1"><i class="fas fa-barcode mr-1"></i> Filter Specific Item:</label>
                        <select class="form-control form-control-sm select2-ajax" id="item_id" name="item_id">
                            @if($initialItem)
                                <option value="{{ $initialItem->id }}" selected>[{{ $initialItem->item_code }}] {{ $initialItem->name }} (₹{{ $initialItem->sell_price }})</option>
                            @else
                                <option value="">-- All Items --</option>
                            @endif
                        </select>
                    </div>

                    {{-- Specific Supplier Filter --}}
                    <div class="col-md-3 mb-2" id="filter-supplier-col">
                        <label class="small text-muted mb-1"><i class="fas fa-truck mr-1"></i> Filter Supplier:</label>
                        <select class="form-control form-control-sm select2-ajax" id="supplier_id" name="supplier_id">
                            @if($initialSupplier)
                                <option value="{{ $initialSupplier->id }}" selected>{{ $initialSupplier->name }} ({{ $initialSupplier->city }})</option>
                            @else
                                <option value="">-- All Suppliers --</option>
                            @endif
                        </select>
                    </div>

                    {{-- Limit & Sort --}}
                    <div class="col-md-3 mb-2">
                        <label class="small text-muted mb-1"><i class="fas fa-sort-amount-down mr-1"></i> Ranking & Limit:</label>
                        <div class="input-group input-group-sm">
                            <select class="form-control" id="limit" name="limit">
                                <option value="all">All Records</option>
                                <option value="10">Top 10</option>
                                <option value="20" selected>Top 20</option>
                                <option value="50">Top 50</option>
                                <option value="100">Top 100</option>
                            </select>
                            <select class="form-control" id="sort_dir" name="sort_dir">
                                <option value="desc">Highest First</option>
                                <option value="asc">Lowest First</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ACTION BUTTONS BAR --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 mt-2 border-top">
                    <div>
                        <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm" id="btn-run-report">
                            <i class="fas fa-bolt mr-1"></i> Run Live Report
                        </button>
                        <button type="button" class="btn btn-outline-info font-weight-bold ml-1" id="btn-open-save-modal">
                            <i class="fas fa-save mr-1"></i> Save as My Report
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btn-reset-filters">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                    </div>

                    <div class="mt-2 mt-md-0 d-flex align-items-center">
                        {{-- VIEW MODE TOGGLE --}}
                        <div class="btn-group btn-group-sm mr-2" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="view-table-btn">
                                <i class="fas fa-table mr-1"></i> Table View
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="view-chart-btn">
                                <i class="fas fa-chart-bar mr-1"></i> Visual Chart
                            </button>
                        </div>

                        {{-- EXPORTS --}}
                        <button type="button" class="btn btn-sm btn-outline-success font-weight-bold mr-1" id="btn-export-csv">
                            <i class="fas fa-file-excel mr-1"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 3. SUMMARY KPI CARDS --}}
    <div class="row mb-3" id="summary-cards-row">
        <div class="col-6 col-md-3 mb-2">
            <div class="info-box bg-white shadow-sm mb-0">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Total Quantity</span>
                    <span class="info-box-number text-dark h5 mb-0" id="stat-total-qty">0.00</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="info-box bg-white shadow-sm mb-0">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-rupee-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Total Value / Revenue</span>
                    <span class="info-box-number text-success h5 mb-0" id="stat-total-sales">₹ 0.00</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="info-box bg-white shadow-sm mb-0">
                <span class="info-box-icon bg-warning elevation-1 text-white"><i class="fas fa-tags"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Discount / Margin</span>
                    <span class="info-box-number text-dark h5 mb-0" id="stat-total-profit">₹ 0.00</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="info-box bg-white shadow-sm mb-0">
                <span class="info-box-icon bg-purple elevation-1 text-white" style="background-color: #6f42c1 !important;"><i class="fas fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Bills / Transactions</span>
                    <span class="info-box-number text-dark h5 mb-0" id="stat-total-bills">0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. RESULTS VIEWPORT (TABLE VIEW) --}}
    <div class="card shadow-sm border-0 mb-4" id="results-card">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h6 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-list-alt text-primary mr-1"></i> <span id="report-title-display">Generated Report Results</span>
            </h6>
            <span class="badge badge-light border" id="result-count-badge">0 records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-hover table-striped table-bordered mb-0" id="analytics-table">
                    <thead class="bg-light text-dark sticky-top" id="table-head">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>Name / Dimension</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Total (₹)</th>
                            <th class="text-right">Profit / Margin</th>
                            <th class="text-center">Bills</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-chart-pie fa-3x mb-3 text-secondary"></i>
                                <p class="mb-0">Select your Group By criteria and click <strong>"Run Live Report"</strong> or choose a Smart Preset above.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 5. RESULTS VIEWPORT (CHART VIEW) --}}
    <div class="card shadow-sm border-0 mb-4 d-none" id="chart-card">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h6 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-chart-bar text-success mr-1"></i> Visual Performance Analytics
            </h6>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary active" id="btn-chart-bar">Bar Chart</button>
                <button type="button" class="btn btn-outline-secondary" id="btn-chart-pie">Pie Chart</button>
            </div>
        </div>
        <div class="card-body p-3">
            <div style="height: 380px; position: relative;">
                <canvas id="analyticsChart"></canvas>
            </div>
        </div>
    </div>

</div>

{{-- MODAL: SAVE AS MY REPORT --}}
<div class="modal fade" id="saveReportModal" tabindex="-1" role="dialog" aria-labelledby="saveReportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="saveReportModalLabel">
                    <i class="fas fa-save mr-1"></i> Save as My Custom Report
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Save this configuration so you or your staff can re-run this exact report with fresh live data in just 1-click anytime.
                </p>
                <div class="form-group">
                    <label class="font-weight-bold small text-dark">Report Name *</label>
                    <input type="text" class="form-control" id="save-report-name" placeholder="e.g. My Item Supplier Sourcing Report" required>
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold small text-dark">Description (Optional)</label>
                    <textarea class="form-control" id="save-report-desc" rows="2" placeholder="e.g. Shows which suppliers supplied each item and purchase cost comparison."></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info btn-sm font-weight-bold px-3" id="btn-confirm-save-report">
                    <i class="fas fa-check mr-1"></i> Save Configuration
                </button>
            </div>
{{-- MODAL: GROUP DRILLDOWN TRANSACTION BREAKDOWN --}}
<div class="modal fade" id="drilldownModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold" id="drilldownModalTitle">
                    <i class="fas fa-list-ol mr-2 text-warning"></i> Transaction Breakdown
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center">
                    <div id="drilldown-summary-text" class="font-weight-bold text-dark h6 mb-0"></div>
                    <div class="small text-muted">
                        <i class="fas fa-external-link-alt text-primary mr-1"></i> All transaction links open in a new tab without closing this report.
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-hover table-striped table-bordered mb-0" id="drilldown-table">
                        <thead class="bg-light sticky-top" id="drilldown-thead"></thead>
                        <tbody id="drilldown-tbody">
                            <tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading transactions...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    let currentChart = null;
    let chartType = 'bar';
    let lastResponseData = null;

    // Initialize Select2 AJAX for Item search
    $('#item_id').select2({
        theme: 'bootstrap4',
        placeholder: '-- Search Item by Code or Name --',
        allowClear: true,
        ajax: {
            url: '{{ route("reports.analytics-builder.search-items") }}',
            dataType: 'json',
            delay: 250,
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    // Initialize Select2 AJAX for Supplier search
    $('#supplier_id').select2({
        theme: 'bootstrap4',
        placeholder: '-- Search Supplier by Name or City --',
        allowClear: true,
        ajax: {
            url: '{{ route("reports.analytics-builder.search-suppliers") }}',
            dataType: 'json',
            delay: 250,
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    // Date Preset Radio change
    $('input[name="date_preset"]').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom-date-row').removeClass('d-none');
        } else {
            $('#custom-date-row').addClass('d-none');
        }
    });

    // Toggle filter panel
    $('#btn-toggle-filter').on('click', function() {
        $('#filter-panel').slideToggle(200, function() {
            let isVisible = $(this).is(':visible');
            $('#toggle-filter-text').text(isVisible ? 'Hide Controls' : 'Show Controls');
        });
    });

    // Group By dropdown change: update help text & visibility
    $('#group_by').on('change', function() {
        let val = $(this).val();
        let desc = 'Aggregates data by selected dimension.';
        if (val === 'item_supplier') {
            desc = '📦 Item ⇄ Supplier Traceability: Shows which suppliers supplied each item, inward quantity, last purchase rate, and invoices.';
            $('#m_margin').prop('checked', false);
        } else if (val === 'item') {
            desc = 'Aggregates each item\'s sold quantity, total revenue, discount, and profit margin.';
        } else if (val === 'customer') {
            desc = 'Aggregates each customer\'s total purchase value, number of visits, and average order value.';
        } else if (val === 'payment_mode') {
            desc = 'Analyzes cash vs card vs UPI transactions breakdown.';
        }
        $('#group-desc-text').text(desc);
    });

    // Run Report Action
    $('#builder-form').on('submit', function(e) {
        e.preventDefault();
        runLiveReport();
    });

    function runLiveReport() {
        let $btn = $('#btn-run-report');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Running...');

        let formData = $('#builder-form').serialize();

        $.ajax({
            url: '{{ route("reports.analytics-builder.generate") }}',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $btn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Run Live Report');
                lastResponseData = resp;
                renderReportTable(resp);
                renderReportChart(resp);
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Run Live Report');
                alert('Error running analytics: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    }

    // Render Table Output
    function renderReportTable(resp) {
        let $head = $('#table-head').empty();
        let $body = $('#table-body').empty();

        // 1. Update KPI stats
        let totals = resp.totals || {};
        $('#stat-total-qty').text(totals.total_qty || '0.00');
        $('#stat-total-sales').text(totals.total_sales || totals.total_amount || '₹ 0.00');
        $('#stat-total-profit').text(totals.total_profit || (totals.total_invoices ? totals.total_invoices + ' Invoices' : '₹ 0.00'));
        $('#stat-total-bills').text(totals.total_bills ?? totals.total_invoices ?? totals.count ?? 0);
        $('#result-count-badge').text((totals.count || resp.rows.length) + ' records');

        let title = 'Report: Grouped By ' + resp.group_by.toUpperCase();
        if (resp.group_by === 'item_supplier') {
            title = 'Item ⇄ Supplier Sourcing Traceability Report';
        }
        $('#report-title-display').text(title);

        // 2. Build Header
        let headHtml = '<tr><th class="text-center" style="width: 45px;">#</th>';
        resp.columns.forEach(function(col) {
            let alignClass = (col.key.includes('qty') || col.key.includes('sales') || col.key.includes('amount') || col.key.includes('profit') || col.key.includes('cost') || col.key.includes('disc') || col.key.includes('aov')) ? 'text-right' : (col.key === 'bill_count' || col.key === 'invoice_count' || col.key.includes('date') || col.key === 'is_primary_supplier' ? 'text-center' : 'text-left');
            headHtml += `<th class="${alignClass}">${col.label}</th>`;
        });
        headHtml += '<th class="text-center" style="width: 90px;">Action</th></tr>';
        $head.html(headHtml);

        // 3. Build Body Rows
        if (!resp.rows || resp.rows.length === 0) {
            $body.html(`<tr><td colspan="${resp.columns.length + 2}" class="text-center py-4 text-muted">No records found for the chosen filters.</td></tr>`);
            return;
        }

        resp.rows.forEach(function(row, idx) {
            let rowHtml = `<tr><td class="text-center font-weight-bold text-muted">${idx + 1}</td>`;
            resp.columns.forEach(function(col) {
                let val = row[col.key] !== undefined ? row[col.key] : '';
                let alignClass = (col.key.includes('qty') || col.key.includes('sales') || col.key.includes('amount') || col.key.includes('profit') || col.key.includes('cost') || col.key.includes('disc') || col.key.includes('aov')) ? 'text-right' : (col.key === 'bill_count' || col.key === 'invoice_count' || col.key.includes('date') || col.key === 'is_primary_supplier' ? 'text-center' : 'text-left');

                if (col.key === 'is_primary_supplier') {
                    val = val ? '<span class="badge badge-success"><i class="fas fa-check mr-1"></i> Primary Master</span>' : '<span class="badge badge-light border text-muted">Inward Source</span>';
                } else if (col.key === 'item_code') {
                    val = `<span class="badge badge-light border">${val || '-'}</span>`;
                } else if (col.key === 'item_name') {
                    let itemId = row.item_id || row.group_id;
                    val = `<span class="font-weight-bold text-dark">${val}</span>
                           <a href="{{ route('reports.smart-analytics') }}?item_id=${itemId}" target="_blank" class="badge badge-light border text-primary ml-1" title="Open Single Item 360° Profile in New Tab"><i class="fas fa-external-link-alt mr-1"></i>360°</a>`;
                } else if (col.key === 'group_name') {
                    if (resp.group_by === 'item') {
                        val = `<span class="font-weight-bold text-dark">${val}</span>
                               <a href="{{ route('reports.smart-analytics') }}?item_id=${row.group_id}" target="_blank" class="badge badge-light border text-primary ml-1" title="Open Single Item 360° Profile in New Tab"><i class="fas fa-external-link-alt mr-1"></i>360°</a>`;
                    } else if (resp.group_by === 'customer') {
                        val = `<span class="font-weight-bold text-dark">${val}</span>
                               <a href="{{ route('reports.smart-analytics') }}?customer_id=${row.group_id}" target="_blank" class="badge badge-light border text-primary ml-1" title="Open Customer 360° Profile in New Tab"><i class="fas fa-external-link-alt mr-1"></i>360°</a>`;
                    } else {
                        val = `<span class="font-weight-bold text-dark">${val}</span>`;
                    }
                } else if (col.key === 'bill_count' || col.key === 'invoice_count') {
                    let countNum = parseInt(val) || 0;
                    if (countNum > 0) {
                        val = `<button type="button" class="btn btn-xs btn-outline-primary btn-drilldown py-0 px-2 font-weight-bold" 
                                       data-group="${resp.group_by}" 
                                       data-id="${row.group_id || row.item_id}" 
                                       data-subid="${row.supplier_id || ''}" 
                                       data-name="${row.group_name || row.item_name}" 
                                       title="Click to view underlying transactions in modal">
                                       <i class="fas fa-search-plus mr-1"></i>${val} ${col.key === 'invoice_count' ? 'Invoices' : 'Bills'}
                               </button>`;
                    }
                }

                rowHtml += `<td class="${alignClass}">${val}</td>`;
            });

            // Action column with Drilldown button
            let targetId = row.group_id || row.item_id;
            let targetSubId = row.supplier_id || '';
            let targetName = row.group_name || row.item_name;
            rowHtml += `
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-info btn-drilldown font-weight-bold px-2" 
                            data-group="${resp.group_by}" 
                            data-id="${targetId}" 
                            data-subid="${targetSubId}" 
                            data-name="${targetName}" 
                            title="View underlying transactions">
                        <i class="fas fa-eye mr-1"></i> View / Details
                    </button>
                </td>
            `;

            rowHtml += '</tr>';
            $body.append(rowHtml);
        });
    }

    // Render Chart.js
    function renderReportChart(resp) {
        if (!resp.chart || !resp.chart.labels || resp.chart.labels.length === 0) return;

        let ctx = document.getElementById('analyticsChart').getContext('2d');
        if (currentChart) {
            currentChart.destroy();
        }

        let bgColors = [
            '#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545',
            '#6610f2', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
        ];

        currentChart = new Chart(ctx, {
            type: chartType,
            data: {
                labels: resp.chart.labels,
                datasets: [{
                    label: resp.chart.label || 'Value',
                    data: resp.chart.values,
                    backgroundColor: chartType === 'pie' ? bgColors : '#17a2b8',
                    borderColor: chartType === 'pie' ? '#ffffff' : '#117a8b',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: (chartType === 'pie')
                    }
                },
                scales: (chartType === 'bar') ? {
                    y: {
                        beginAtZero: true
                    }
                } : {}
            }
        });
    }

    // View Mode Toggle (Table vs Chart)
    $('#view-table-btn').on('click', function() {
        $(this).addClass('active');
        $('#view-chart-btn').removeClass('active');
        $('#results-card').removeClass('d-none');
        $('#chart-card').addClass('d-none');
    });

    $('#view-chart-btn').on('click', function() {
        $(this).addClass('active');
        $('#view-table-btn').removeClass('active');
        $('#results-card').addClass('d-none');
        $('#chart-card').removeClass('d-none');
        if (lastResponseData) {
            renderReportChart(lastResponseData);
        }
    });

    // Chart Type Toggle
    $('#btn-chart-bar').on('click', function() {
        chartType = 'bar';
        $(this).addClass('active');
        $('#btn-chart-pie').removeClass('active');
        if (lastResponseData) renderReportChart(lastResponseData);
    });

    $('#btn-chart-pie').on('click', function() {
        chartType = 'pie';
        $(this).addClass('active');
        $('#btn-chart-bar').removeClass('active');
        if (lastResponseData) renderReportChart(lastResponseData);
    });

    // SMART PRESET BUTTON HANDLERS
    $('.preset-btn').on('click', function(e) {
        e.preventDefault();
        let preset = $(this).data('preset');
        applyPreset(preset);
        runLiveReport();
    });

    function applyPreset(preset) {
        if (preset === 'item_supplier') {
            $('#group_by').val('item_supplier').trigger('change');
            $('input[name="date_preset"][value="all_time"]').prop('checked', true).trigger('change');
            $('#limit').val('all');
            $('#sort_dir').val('desc');
        } else if (preset === 'single_item') {
            $('#group_by').val('item').trigger('change');
            $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
            $('#limit').val('all');
            $('#sort_dir').val('desc');
        } else if (preset === 'top_selling') {
            $('#group_by').val('item').trigger('change');
            $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
            $('#limit').val('20');
            $('#sort_dir').val('desc');
        } else if (preset === 'slow_moving') {
            $('#group_by').val('item').trigger('change');
            $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
            $('#limit').val('20');
            $('#sort_dir').val('asc');
        } else if (preset === 'vip_customers') {
            $('#group_by').val('customer').trigger('change');
            $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
            $('#limit').val('20');
            $('#sort_dir').val('desc');
        } else if (preset === 'payment_mode') {
            $('#group_by').val('payment_mode').trigger('change');
            $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
            $('#limit').val('all');
        }
    }

    // EXPORT CSV
    $('#btn-export-csv').on('click', function(e) {
        e.preventDefault();
        let params = $('#builder-form').serialize();
        window.location.href = '{{ route("reports.analytics-builder.export") }}?' + params;
    });

    // SAVE AS MY REPORT MODAL
    $('#btn-open-save-modal').on('click', function() {
        let groupText = $('#group_by option:selected').text();
        $('#save-report-name').val(groupText.replace(/[^a-zA-Z0-9 ]/g, '').trim() + ' Report');
        $('#saveReportModal').modal('show');
    });

    $('#btn-confirm-save-report').on('click', function() {
        let name = $('#save-report-name').val();
        if (!name) {
            alert('Please enter a report name.');
            return;
        }

        let groupBy = $('#group_by').val();
        let metrics = [];
        $('.metric-check:checked').each(function() {
            metrics.push($(this).val());
        });

        let filters = {
            date_preset: $('input[name="date_preset"]:checked').val(),
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val(),
            branch_id: $('#branch_id').val(),
            item_id: $('#item_id').val(),
            supplier_id: $('#supplier_id').val(),
            limit: $('#limit').val(),
            sort_dir: $('#sort_dir').val()
        };

        let $saveBtn = $(this);
        $saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: '{{ route("reports.analytics-builder.save") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                name: name,
                description: $('#save-report-desc').val(),
                group_by: groupBy,
                metrics: metrics,
                filters: filters
            },
            success: function(resp) {
                $saveBtn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Configuration');
                $('#saveReportModal').modal('hide');

                // Append pill to saved reports bar
                let r = resp.report;
                let pillHtml = `
                    <div class="badge badge-light border p-1 px-2 mr-2 mb-1 d-inline-flex align-items-center shadow-xs" id="saved-report-pill-${r.id}">
                        <a href="javascript:void(0)" class="text-dark font-weight-bold mr-2 load-saved-report" 
                           data-id="${r.id}" 
                           data-name="${r.name}"
                           data-group="${r.group_by}"
                           data-metrics='${JSON.stringify(r.metrics)}'
                           data-filters='${JSON.stringify(r.filters)}'>
                            <i class="fas fa-file-alt text-info mr-1"></i> ${r.name}
                        </a>
                        <button type="button" class="btn btn-link text-danger p-0 delete-saved-report" data-id="${r.id}" title="Delete report">
                            <i class="fas fa-times fa-xs"></i>
                        </button>
                    </div>
                `;
                $('#saved-reports-bar').removeClass('d-none');
                $('#saved-reports-container').append(pillHtml);
                alert('Report configuration saved! You can reload it anytime with 1-click.');
            },
            error: function(xhr) {
                $saveBtn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Save Configuration');
                alert('Failed to save report: ' + (xhr.responseJSON?.message || 'Validation error'));
            }
        });
    });

    // LOAD SAVED REPORT
    $(document).on('click', '.load-saved-report', function() {
        let group = $(this).data('group');
        let metrics = $(this).data('metrics');
        let filters = $(this).data('filters');

        if (group) {
            $('#group_by').val(group).trigger('change');
        }

        if (metrics && Array.isArray(metrics)) {
            $('.metric-check').prop('checked', false);
            metrics.forEach(function(m) {
                $(`.metric-check[value="${m}"]`).prop('checked', true);
            });
        }

        if (filters) {
            if (filters.date_preset) {
                $(`input[name="date_preset"][value="${filters.date_preset}"]`).prop('checked', true).trigger('change');
            }
            if (filters.date_from) $('#date_from').val(filters.date_from);
            if (filters.date_to) $('#date_to').val(filters.date_to);
            if (filters.branch_id) $('#branch_id').val(filters.branch_id);
            if (filters.limit) $('#limit').val(filters.limit);
            if (filters.sort_dir) $('#sort_dir').val(filters.sort_dir);
        }

        runLiveReport();
    });

    // DELETE SAVED REPORT
    $(document).on('click', '.delete-saved-report', function(e) {
        e.preventDefault();
        e.stopPropagation();
        let id = $(this).data('id');
        if (!confirm('Are you sure you want to delete this saved report?')) return;

        $.ajax({
            url: '{{ url("reports/analytics-builder/saved") }}/' + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function() {
                $('#saved-report-pill-' + id).fadeOut(200, function() { $(this).remove(); });
            }
        });
    });

    // Reset Filters
    $('#btn-reset-filters').on('click', function() {
        $('#builder-form')[0].reset();
        $('#group_by').val('item').trigger('change');
        $('#item_id').val(null).trigger('change');
        $('#supplier_id').val(null).trigger('change');
        $('input[name="date_preset"][value="this_month"]').prop('checked', true).trigger('change');
        $('#m_qty, #m_sales, #m_margin, #m_bills').prop('checked', true);
        runLiveReport();
    });

    // GROUP DRILLDOWN ACTION (CLICKING BILLS OR DETAILS BUTTON)
    $(document).on('click', '.btn-drilldown', function(e) {
        e.preventDefault();
        let group = $(this).data('group');
        let id = $(this).data('id');
        let subid = $(this).data('subid');
        let name = $(this).data('name') || '';

        $('#drilldownModalTitle').html(`<i class="fas fa-search-plus mr-2 text-warning"></i> Transaction Breakdown: ${name}`);
        $('#drilldown-summary-text').html('<span class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Fetching transactions...</span>');
        $('#drilldown-tbody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading transactions...</td></tr>');
        $('#drilldownModal').modal('show');

        let formData = $('#builder-form').serializeArray();
        let params = {};
        formData.forEach(function(item) {
            params[item.name] = item.value;
        });
        params['group_by'] = group;
        params['group_id'] = id;
        params['id'] = id;
        params['sub_id'] = subid;

        $.ajax({
            url: '{{ route("reports.analytics-builder.drilldown") }}',
            method: 'GET',
            data: params,
            dataType: 'json',
            success: function(resp) {
                renderDrilldownModal(resp);
            },
            error: function(xhr) {
                $('#drilldown-tbody').html('<tr><td colspan="9" class="text-center py-4 text-danger">Failed to load transaction details: ' + (xhr.responseJSON?.message || 'Server error') + '</td></tr>');
            }
        });
    });

    function renderDrilldownModal(resp) {
        let $thead = $('#drilldown-thead').empty();
        let $tbody = $('#drilldown-tbody').empty();
        let summary = resp.summary || {};

        let summaryHtml = `<strong>Total Records:</strong> <span class="badge badge-primary mr-3">${summary.count || resp.records.length}</span>`;
        if (summary.total_qty) {
            summaryHtml += `<strong>Total Quantity:</strong> <span class="badge badge-info mr-3">${summary.total_qty}</span>`;
        }
        if (summary.total_amount) {
            summaryHtml += `<strong>Total Value:</strong> <span class="badge badge-success mr-3">${summary.total_amount}</span>`;
        }
        $('#drilldown-summary-text').html(summaryHtml);

        if (resp.group_by === 'item_supplier') {
            $thead.html(`
                <tr>
                    <th class="text-center" style="width: 45px;">#</th>
                    <th>Invoice / GRN No</th>
                    <th>Invoice Date</th>
                    <th>Branch</th>
                    <th class="text-right">Inward Qty</th>
                    <th class="text-right">Cost Price (₹)</th>
                    <th class="text-right">Net Value (₹)</th>
                    <th class="text-center" style="width: 100px;">Action</th>
                </tr>
            `);

            if (resp.records.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No purchase transactions found for this item and supplier.</td></tr>');
                return;
            }

            resp.records.forEach(function(r, idx) {
                $tbody.append(`
                    <tr>
                        <td class="text-center text-muted font-weight-bold">${idx + 1}</td>
                        <td class="font-weight-bold">
                            <a href="${r.view_url}" target="_blank" class="text-primary" title="Open Purchase Bill in New Tab">
                                ${r.invoice_number} <i class="fas fa-external-link-alt fa-xs ml-1"></i>
                            </a>
                        </td>
                        <td>${r.formatted_date}</td>
                        <td>${r.branch_name}</td>
                        <td class="text-right font-weight-bold">${parseFloat(r.qty).toFixed(2)}</td>
                        <td class="text-right">${r.formatted_cost}</td>
                        <td class="text-right font-weight-bold text-success">${r.formatted_amount}</td>
                        <td class="text-center">
                            <a href="${r.view_url}" target="_blank" class="btn btn-xs btn-outline-primary font-weight-bold" title="Open in New Tab">
                                Open ↗
                            </a>
                        </td>
                    </tr>
                `);
            });

        } else if (resp.group_by === 'customer') {
            $thead.html(`
                <tr>
                    <th class="text-center" style="width: 45px;">#</th>
                    <th>Bill Number</th>
                    <th>Bill Date & Time</th>
                    <th>Branch</th>
                    <th class="text-center">Total Qty</th>
                    <th class="text-center">Payment Mode</th>
                    <th class="text-right">Bill Total (₹)</th>
                    <th class="text-center" style="width: 130px;">Action</th>
                </tr>
            `);

            if (resp.records.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No bills found for this customer.</td></tr>');
                return;
            }

            resp.records.forEach(function(r, idx) {
                $tbody.append(`
                    <tr>
                        <td class="text-center text-muted font-weight-bold">${idx + 1}</td>
                        <td class="font-weight-bold">
                            <a href="${r.view_url}" target="_blank" class="text-primary" title="Open Bill in New Tab">
                                ${r.bill_number} <i class="fas fa-external-link-alt fa-xs ml-1"></i>
                            </a>
                        </td>
                        <td>${r.formatted_date}</td>
                        <td>${r.branch_name}</td>
                        <td class="text-center">${parseFloat(r.total_qty || 0).toFixed(0)}</td>
                        <td class="text-center"><span class="badge badge-light border">${r.payment_type || 'Cash'}</span></td>
                        <td class="text-right font-weight-bold text-success">${r.formatted_amount}</td>
                        <td class="text-center">
                            <a href="${r.view_url}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Open Bill in New Tab">
                                <i class="fas fa-eye mr-1"></i> View Bill ↗
                            </a>
                            <a href="${r.receipt_url}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Open Receipt in New Tab">
                                <i class="fas fa-receipt mr-1"></i> Receipt ↗
                            </a>
                        </td>
                    </tr>
                `);
            });

        } else {
            // Standard item or general sales bills drilldown
            $thead.html(`
                <tr>
                    <th class="text-center" style="width: 45px;">#</th>
                    <th>Bill Number</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Branch</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Rate (₹)</th>
                    <th class="text-right">Total Amount (₹)</th>
                    <th class="text-center" style="width: 140px;">Action</th>
                </tr>
            `);

            if (resp.records.length === 0) {
                $tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No transactions found for this selection.</td></tr>');
                return;
            }

            resp.records.forEach(function(r, idx) {
                $tbody.append(`
                    <tr>
                        <td class="text-center text-muted font-weight-bold">${idx + 1}</td>
                        <td class="font-weight-bold">
                            <a href="${r.view_url}" target="_blank" class="text-primary" title="Open Bill in New Tab">
                                ${r.bill_number} <i class="fas fa-external-link-alt fa-xs ml-1"></i>
                            </a>
                        </td>
                        <td>${r.formatted_date}</td>
                        <td>${r.customer_name}</td>
                        <td>${r.branch_name || 'Main'}</td>
                        <td class="text-right font-weight-bold">${parseFloat(r.qty || 1).toFixed(2)}</td>
                        <td class="text-right">${r.formatted_rate || '-'}</td>
                        <td class="text-right font-weight-bold text-success">${r.formatted_amount}</td>
                        <td class="text-center">
                            <a href="${r.view_url}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Open Bill in New Tab">
                                <i class="fas fa-eye mr-1"></i> View Bill ↗
                            </a>
                            <a href="${r.receipt_url}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Open Receipt in New Tab">
                                <i class="fas fa-receipt mr-1"></i> Receipt ↗
                            </a>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Auto-run initial preset if passed in query string or load default
    @if($initialPreset)
        applyPreset('{{ $initialPreset }}');
    @endif

    // Initial Execution
    runLiveReport();
});
</script>
@endpush
