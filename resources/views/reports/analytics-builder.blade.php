@extends('adminlte::page')

@section('title', 'Custom Report Studio & Analytics Builder')

@push('css')
<style>
.preset-pill, .groupby-pill {
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s ease-in-out;
}
.preset-pill:hover, .groupby-pill:hover {
    transform: translateY(-1px);
}
.preset-pill.active, .groupby-pill.active {
    box-shadow: 0 2px 4px rgba(0,123,255,0.3);
}
.quick-switch-date {
    border-radius: 12px;
}
.metric-chip {
    cursor: pointer;
    user-select: none;
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid #ced4da;
    background: #ffffff;
    color: #495057;
    margin-right: 4px;
    margin-bottom: 4px;
    transition: all 0.15s ease-in-out;
}
.metric-chip:hover {
    border-color: #007bff;
    background: #f8f9fa;
    transform: translateY(-1px);
}
.metric-chip.active {
    background: #e7f1ff;
    border-color: #007bff;
    color: #0056b3;
    box-shadow: 0 1px 3px rgba(0,123,255,0.2);
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-magic text-info mr-2"></i> Custom Report Studio & Analytics Builder
            </h1>
            <p class="text-muted small mb-0">Dynamic multi-dimensional reports: Group By any metric, trace Item-Supplier sourcing, inspect single-item monthly sales, and save custom reports.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm mr-1" data-toggle="modal" data-target="#guideHindiModal">
                <i class="fas fa-book-reader mr-1"></i> 📖 Kaise Use Karein? (Guide)
            </button>
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
                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold mr-2 mb-1 px-2" data-toggle="modal" data-target="#guideHindiModal" title="Click for step-by-step Hindi guide">
                    <i class="fas fa-question-circle mr-1"></i> गाइड / उदाहरण
                </button>
                
                {{-- User exact requirement 1: Item Supplier Sourcing --}}
                <button type="button" class="btn btn-sm btn-info font-weight-bold mr-1 mb-1 preset-btn" data-preset="item_supplier">
                    <i class="fas fa-truck-loading mr-1"></i> 📦 Item ⇄ Supplier Sourcing
                </button>

                {{-- User exact requirement 2: Single Item Monthly Drilldown --}}
                <button type="button" class="btn btn-sm btn-primary font-weight-bold mr-1 mb-1 preset-btn" data-preset="single_item">
                    <i class="fas fa-search-dollar mr-1"></i> 🎯 Single Item Monthly Sales
                </button>

                {{-- User exact requirement: Supplier Invoices Count & Purchases --}}
                <button type="button" class="btn btn-sm btn-outline-dark font-weight-bold mr-1 mb-1 preset-btn" data-preset="supplier_invoices">
                    <i class="fas fa-file-invoice-dollar text-primary mr-1"></i> 🏭 Supplier Invoice Summary
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
                    <div class="col-md-4 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="font-weight-bold small text-dark mb-0">
                                <i class="fas fa-layer-group text-info mr-1"></i> 1. Group By Dimension:
                            </label>
                            <span class="badge badge-primary px-2 py-0 small font-weight-bold" id="current-group-badge">By Item</span>
                        </div>

                        <!-- Dimension Dropdown with Categories -->
                        <div class="input-group input-group-sm mb-2 shadow-xs">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white text-info font-weight-bold px-2"><i class="fas fa-sitemap"></i></span>
                            </div>
                            <select class="form-control form-control-sm font-weight-bold text-dark" id="group_by" name="group_by" style="font-size: 0.82rem;">
                                <optgroup label="🛍️ Sales Dimensions">
                                    <option value="item" selected>📦 By Item / Product</option>
                                    <option value="category">📂 By Category / Sub-category</option>
                                    <option value="brand">🏷️ By Brand / Manufacturer</option>
                                    <option value="customer">👤 By Customer (Sales & Visits)</option>
                                </optgroup>
                                <optgroup label="🏭 Purchase & Sourcing Dimensions">
                                    <option value="item_supplier">🚚 By Item ⇄ Supplier Sourcing</option>
                                    <option value="supplier">🏭 By Supplier (Invoices & Purchases)</option>
                                </optgroup>
                                <optgroup label="💼 Operations & Billing">
                                    <option value="payment_mode">💳 By Payment Mode (Cash, UPI, Card)</option>
                                    <option value="cashier">🧑‍💼 By Cashier / Billing Staff</option>
                                    <option value="date">📅 By Day / Transaction Date</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- 1-Click Dimension Quick Pills -->
                        <div class="d-flex flex-wrap mb-1" id="quick-groupby-pills">
                            <button type="button" class="btn btn-xs btn-primary font-weight-bold mr-1 mb-1 groupby-pill active" data-group="item">📦 Item</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 groupby-pill" data-group="customer">👤 Customer</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 groupby-pill" data-group="category">📂 Category</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 groupby-pill" data-group="item_supplier">🚚 Sourcing</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 groupby-pill" data-group="supplier">🏭 Supplier</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 groupby-pill" data-group="payment_mode">💳 Pay Mode</button>
                        </div>

                        <small class="form-text text-muted small mt-1 mb-0" id="group-desc-text" style="line-height: 1.25; font-size: 0.74rem;">
                            Aggregates each item's sold quantity, total revenue, and profit.
                        </small>
                    </div>

                    {{-- 2. METRICS SELECTION --}}
                    <div class="col-md-4 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="font-weight-bold small text-dark mb-0">
                                <i class="fas fa-calculator text-success mr-1"></i> 2. Choose Metrics:
                            </label>
                            <div class="d-inline-flex" id="metric-bundles">
                                <button type="button" class="btn btn-xs btn-outline-secondary px-1 py-0 mr-1 metric-bundle-btn" data-bundle="standard" title="Select standard metrics (Qty, Value, Margin, Bills)">Standard</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary px-1 py-0 mr-1 metric-bundle-btn" data-bundle="profit" title="Focus on Profitability (Sales, Margin, Discount)">Profit</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary px-1 py-0 metric-bundle-btn" data-bundle="all" title="Select All Metrics">All</button>
                            </div>
                        </div>

                        <!-- Interactive Metric Toggle Chips -->
                        <div class="p-2 bg-light rounded border d-flex flex-wrap align-items-center" id="metrics-chips-container" style="min-height: 72px;">
                            <label class="metric-chip active" data-val="qty" title="Toggle Quantity">
                                <input type="checkbox" class="d-none metric-check" id="m_qty" name="metrics[]" value="qty" checked>
                                <span><i class="fas fa-boxes text-info mr-1"></i> Qty</span>
                            </label>
                            <label class="metric-chip active" data-val="sales_value" title="Toggle Total Value / Revenue">
                                <input type="checkbox" class="d-none metric-check" id="m_sales" name="metrics[]" value="sales_value" checked>
                                <span><i class="fas fa-rupee-sign text-success mr-1"></i> Total ₹</span>
                            </label>
                            <label class="metric-chip active" data-val="margin" title="Toggle Gross Profit / Margin">
                                <input type="checkbox" class="d-none metric-check" id="m_margin" name="metrics[]" value="margin" checked>
                                <span><i class="fas fa-chart-line text-primary mr-1"></i> Margin / Profit</span>
                            </label>
                            <label class="metric-chip active" data-val="bill_count" title="Toggle Bill / Invoice Count">
                                <input type="checkbox" class="d-none metric-check" id="m_bills" name="metrics[]" value="bill_count" checked>
                                <span><i class="fas fa-receipt text-warning mr-1"></i> Bills</span>
                            </label>
                            <label class="metric-chip" data-val="discount" title="Toggle Total Discount">
                                <input type="checkbox" class="d-none metric-check" id="m_disc" name="metrics[]" value="discount">
                                <span><i class="fas fa-tag text-danger mr-1"></i> Discount</span>
                            </label>
                            <label class="metric-chip" data-val="aov" title="Toggle Average Order Value">
                                <input type="checkbox" class="d-none metric-check" id="m_aov" name="metrics[]" value="aov">
                                <span><i class="fas fa-balance-scale mr-1" style="color: #6f42c1;"></i> AOV</span>
                            </label>
                            <label class="metric-chip" data-val="last_date" title="Toggle Last Transaction Date">
                                <input type="checkbox" class="d-none metric-check" id="m_last_date" name="metrics[]" value="last_date">
                                <span><i class="fas fa-clock text-secondary mr-1"></i> Last Date</span>
                            </label>
                        </div>
                        <small class="text-muted d-block small mt-1 mb-0" style="font-size: 0.74rem;">
                            <i class="fas fa-mouse-pointer text-muted mr-1"></i> Click chips to add/remove columns • Auto-updates table.
                        </small>
                    </div>

                    {{-- 3. DATE PRESETS & FILTERS --}}
                    <div class="col-md-4 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="font-weight-bold small text-dark mb-0">
                                <i class="fas fa-calendar-alt text-warning mr-1"></i> 3. Date Filter:
                            </label>
                            <span class="badge badge-light border text-primary small font-weight-bold px-2 py-0" id="active-date-badge">
                                <i class="fas fa-clock mr-1"></i> <span id="active-date-text">This Month</span>
                            </span>
                        </div>

                        <!-- Hidden input storing current preset name -->
                        <input type="hidden" name="date_preset" id="date_preset" value="this_month">

                        <!-- Quick 1-Click Preset Pills -->
                        <div class="d-flex flex-wrap mb-2" id="date-preset-pills">
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 preset-pill" data-preset="today">Today</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 preset-pill" data-preset="yesterday">Yesterday</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 preset-pill" data-preset="this_week">This Week</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 preset-pill" data-preset="last_7_days">Last 7D</button>
                            <button type="button" class="btn btn-xs btn-primary font-weight-bold mr-1 mb-1 preset-pill active" data-preset="this_month">This Month</button>
                            <button type="button" class="btn btn-xs btn-outline-success font-weight-bold mr-1 mb-1 preset-pill" data-preset="last_month">Last Month</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 preset-pill" data-preset="last_30_days">Last 30D</button>
                            <button type="button" class="btn btn-xs btn-outline-info font-weight-bold mr-1 mb-1 preset-pill" data-preset="this_fy">This FY</button>
                            <button type="button" class="btn btn-xs btn-outline-dark font-weight-bold mr-1 mb-1 preset-pill" data-preset="all_time">All Time</button>
                        </div>

                        <!-- Connected From & To Date Pickers with 1-click Apply -->
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light text-muted px-2 py-0 font-weight-bold small">From</span>
                            </div>
                            <input type="date" class="form-control form-control-sm font-weight-bold text-dark px-1" id="date_from" name="date_from" value="{{ date('Y-m-01') }}" title="Start Date">
                            <div class="input-group-prepend input-group-append">
                                <span class="input-group-text bg-light text-muted px-2 py-0 font-weight-bold small">To</span>
                            </div>
                            <input type="date" class="form-control form-control-sm font-weight-bold text-dark px-1" id="date_to" name="date_to" value="{{ date('Y-m-d') }}" title="End Date">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-primary btn-sm px-2 font-weight-bold" id="btn-apply-dates" title="Apply Custom Date Range">
                                    <i class="fas fa-check mr-1"></i> Apply
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECONDARY FILTERS ROW --}}
                <div class="row pt-2 border-top">
                    {{-- Branch Filter --}}
                    <div class="col-md-2 mb-2">
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
                    <div class="col-md-2 mb-2" id="filter-supplier-col">
                        <label class="small text-muted mb-1"><i class="fas fa-truck mr-1"></i> Filter Supplier:</label>
                        <select class="form-control form-control-sm select2-ajax" id="supplier_id" name="supplier_id">
                            @if($initialSupplier)
                                <option value="{{ $initialSupplier->id }}" selected>{{ $initialSupplier->name }} ({{ $initialSupplier->city }})</option>
                            @else
                                <option value="">-- All Suppliers --</option>
                            @endif
                        </select>
                    </div>

                    {{-- Specific Customer Filter --}}
                    <div class="col-md-3 mb-2" id="filter-customer-col">
                        <label class="small text-muted mb-1"><i class="fas fa-user mr-1"></i> Filter Customer:</label>
                        <select class="form-control form-control-sm select2-ajax" id="customer_id" name="customer_id">
                            @if($initialCustomer)
                                <option value="{{ $initialCustomer->id }}" selected>{{ $initialCustomer->name }} {{ ($initialCustomer->mobile ?: $initialCustomer->phone) ? '(' . ($initialCustomer->mobile ?: $initialCustomer->phone) . ')' : '' }}</option>
                            @else
                                <option value="">-- All Customers --</option>
                            @endif
                        </select>
                    </div>

                    {{-- Limit & Sort --}}
                    <div class="col-md-2 mb-2">
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
                            <i class="fas fa-file-excel mr-1"></i> Export XLS
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
        <div class="card-header bg-white py-2 d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex align-items-center mb-1 mb-md-0">
                <h6 class="card-title font-weight-bold text-dark mb-0 mr-2">
                    <i class="fas fa-list-alt text-primary mr-1"></i> <span id="report-title-display">Generated Report Results</span>
                </h6>
                <span class="badge badge-info px-2 py-1 font-weight-bold shadow-xs" id="display-date-range-badge" title="Active Date Filter Period">
                    <i class="fas fa-calendar-alt mr-1"></i> <span id="display-date-range-text">This Month</span>
                </span>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-light border mr-2 font-weight-bold" id="result-count-badge">0 records</span>
                <div class="d-inline-flex" id="quick-date-helpers">
                    <button type="button" class="btn btn-xs btn-outline-success font-weight-bold quick-switch-date mr-1" data-preset="last_month" title="Switch to Last Month (Sep 2026)">
                        <i class="fas fa-history mr-1"></i> Last Month
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-info font-weight-bold quick-switch-date mr-1" data-preset="this_fy" title="Switch to Current Financial Year">
                        <i class="fas fa-calendar mr-1"></i> This FY
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-dark font-weight-bold quick-switch-date" data-preset="all_time" title="Switch to All Time (Full History)">
                        <i class="fas fa-globe mr-1"></i> All Time
                    </button>
                </div>
            </div>
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
        </div>
    </div>
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

{{-- MODAL: USER GUIDE & BUSINESS EXAMPLES IN HINDI --}}
<div class="modal fade" id="guideHindiModal" tabindex="-1" role="dialog" aria-labelledby="guideHindiModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="guideHindiModalTitle">
                    <i class="fas fa-book-reader mr-2"></i> Custom Report Studio — आसान यूज़र गाइड (Hindi Guide & Examples)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 p-md-4" style="background-color: #f8fafc; max-height: 75vh; overflow-y: auto;">
                
                {{-- TOP ALERT BANNER --}}
                <div class="alert alert-primary bg-white border-primary shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-lightbulb fa-2x text-warning mr-3"></i>
                        <div>
                            <h6 class="font-weight-bold mb-1 text-primary">यह टूल क्या है और इसका क्या फायदा है?</h6>
                            <p class="mb-0 text-dark small" style="line-height: 1.6;">
                                यह आपकी दुकान/बिज़नेस का <strong>"जादुई रिपोर्ट मेकर"</strong> है। इसमें आपको फिक्स रिपोर्ट देखने की कोई मजबूरी नहीं है। आप अपनी मर्जी से तय कर सकते हैं कि आपको <strong>किस चीज़ का हिसाब</strong> (जैसे आइटम, कस्टमर, सप्लायर, पेमेंट मोड) और <strong>क्या-क्या जानकारी</strong> (जैसे कुल बिक्री, क्वांटिटी, मुनाफा/प्रॉफ़िट) देखनी है।
                            </p>
                        </div>
                    </div>
                </div>

                {{-- TABS FOR NAVIGATION --}}
                <ul class="nav nav-pills nav-justified mb-3 shadow-sm bg-white p-1 rounded" id="guideTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="guide-concept-tab" data-toggle="pill" href="#guide-concept" role="tab">
                            <i class="fas fa-magic mr-1 text-primary"></i> 1. सिर्फ 3-स्टेप फॉर्मूला
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="guide-examples-tab" data-toggle="pill" href="#guide-examples" role="tab">
                            <i class="fas fa-store mr-1 text-success"></i> 2. रोज़ाना काम आने वाले 5 उदाहरण
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="guide-tips-tab" data-toggle="pill" href="#guide-tips" role="tab">
                            <i class="fas fa-star mr-1 text-warning"></i> 3. ख़ास फीचर्स और टिप्स
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="guideTabContent">

                    {{-- TAB 1: 3-STEP FORMULA --}}
                    <div class="tab-pane fade show active" id="guide-concept" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-primary shadow-sm">
                                    <div class="card-header bg-primary text-white py-2 font-weight-bold">
                                        <i class="fas fa-database mr-1"></i> स्टेप 1: Data Source (किसकी रिपोर्ट?)
                                    </div>
                                    <div class="card-body p-3">
                                        <p class="small text-muted mb-2">पहले तय करें कि डेटा कहाँ से उठाना है:</p>
                                        <ul class="small pl-3 mb-0" style="line-height: 1.8;">
                                            <li><strong>Sales (Items):</strong> हर बिके हुए सामान की डिटेल, मुनाफा और मार्जिन।</li>
                                            <li><strong>Sales (Bills):</strong> पूरे बिल की समरी, जैसे Cash या UPI और कस्टमर हिसाब।</li>
                                            <li><strong>Purchases:</strong> सप्लायर से खरीदे गए माल और बिलों का ब्यौरा।</li>
                                            <li><strong>Item ⇄ Supplier Sourcing:</strong> कौन सा आइटम किस सप्लायर से किस रेट पर आया।</li>
                                            <li><strong>Item Monthly:</strong> किसी 1 खास आइटम की महीने-दर-महीने बिक्री।</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-info shadow-sm">
                                    <div class="card-header bg-info text-white py-2 font-weight-bold">
                                        <i class="fas fa-layer-group mr-1"></i> स्टेप 2: Group By (किसके हिसाब से जोड़ें?)
                                    </div>
                                    <div class="card-body p-3">
                                        <p class="small text-muted mb-2">डेटा को किस आधार पर इकट्ठा (Group) करना है:</p>
                                        <ul class="small pl-3 mb-0" style="line-height: 1.8;">
                                            <li><strong>Item / Product:</strong> हर प्रोडक्ट की एक लाइन बनेगी (जैसे साबुन की कुल बिक्री)।</li>
                                            <li><strong>Customer:</strong> हर ग्राहक की एक लाइन बनेगी (किस ग्राहक ने कितना खरीदा)।</li>
                                            <li><strong>Category:</strong> कैटेगरी-वाइज़ जोड़ (जैसे Grocery, Electronics)।</li>
                                            <li><strong>Payment Mode:</strong> Cash, UPI, Card के हिसाब से कुल कलेक्शन।</li>
                                            <li><strong>Supplier:</strong> किस सप्लायर से कितना माल खरीदा।</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-success shadow-sm">
                                    <div class="card-header bg-success text-white py-2 font-weight-bold">
                                        <i class="fas fa-calculator mr-1"></i> स्टेप 3: Metrics (क्या कैलकुलेट करना है?)
                                    </div>
                                    <div class="card-body p-3">
                                        <p class="small text-muted mb-2">रिपोर्ट में कौन-से कॉलम देखने हैं:</p>
                                        <ul class="small pl-3 mb-2" style="line-height: 1.8;">
                                            <li><span class="badge badge-light border">Qty</span> = कुल कितनी मात्रा बिकी।</li>
                                            <li><span class="badge badge-light border">Total Amount</span> = कुल कितने रुपये बने।</li>
                                            <li><span class="badge badge-light border">Gross Profit</span> = कुल कितने रुपये का मुनाफा हुआ।</li>
                                            <li><span class="badge badge-light border">Margin %</span> = कितने प्रतिशत मार्जिन मिला।</li>
                                        </ul>
                                        <div class="alert alert-light border p-2 small mb-0">
                                            <strong>⚡ शॉर्टकट:</strong> आपको एक-एक टिक करने की ज़रूरत नहीं है! ऊपर बने <strong>"Standard"</strong> या <strong>"Profit"</strong> बटन पर 1 क्लिक करें।
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-white border shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar-alt fa-2x text-info mr-3"></i>
                                <div>
                                    <h6 class="font-weight-bold mb-1 text-dark">📅 तारीख चुनना (Date Filter) और भी आसान:</h6>
                                    <p class="small text-muted mb-0">
                                        मैनुअल तारीख डालने के अलावा आप 1-क्लिक बटन दबा सकते हैं: 
                                        <span class="badge badge-primary mr-1">आज (Today)</span>
                                        <span class="badge badge-secondary mr-1">इस हफ्ते (This Week)</span>
                                        <span class="badge badge-info mr-1">इस महीने (This Month)</span>
                                        <span class="badge badge-dark mr-1">पिछले महीने (Last Month)</span>
                                        <span class="badge badge-warning text-dark mr-1">इस वित्तीय वर्ष (FY)</span>
                                        <span class="badge badge-success mr-1">All Time</span>
                                        बटन दबाते ही रिपोर्ट तुरंत ताज़ा डेटा के साथ लोड हो जाएगी।
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: 5 REAL BUSINESS EXAMPLES --}}
                    <div class="tab-pane fade" id="guide-examples" role="tabpanel">
                        <p class="text-muted small mb-3">यहाँ 5 सबसे ज़्यादा इस्तेमाल होने वाले बिज़नेस उदाहरण दिए गए हैं। इन्हें देखने के लिए आप ऊपर दिए गए <strong>Smart Presets</strong> बटन भी दबा सकते हैं:</p>

                        <div class="accordion" id="examplesAccordion">
                            
                            {{-- EXAMPLE 1 --}}
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header bg-white py-2" id="headingEx1">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link btn-block text-left font-weight-bold text-dark text-decoration-none d-flex justify-content-between align-items-center" type="button" data-toggle="collapse" data-target="#collapseEx1">
                                            <span><i class="fas fa-fire text-danger mr-2"></i> <strong>उदाहरण 1:</strong> सबसे ज्यादा बिकने वाले सामान और किसमें कितना मुनाफ़ा (Profit & Margin) हुआ?</span>
                                            <span class="badge badge-danger">Top Selling</span>
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapseEx1" class="collapse show" data-parent="#examplesAccordion">
                                    <div class="card-body bg-light p-3 small">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <strong>🎯 क्यों देखना है:</strong> यह जानने के लिए कि कौन सा आइटम सबसे ज्यादा डिमांड में है और दुकान को असली कमाई किस आइटम से हो रही है।<br>
                                                <strong>⚡ 1-क्लिक तरीका:</strong> ऊपर दिए <strong><i class="fas fa-fire text-danger"></i> Top Selling Items</strong> बटन पर क्लिक करें।<br>
                                                <strong>⚙️ अगर खुद सेट करना हो:</strong>
                                                <ul class="mb-0 mt-1 pl-3">
                                                    <li><strong>Data Source:</strong> Sales (Bill Items)</li>
                                                    <li><strong>Group By:</strong> Item / Product</li>
                                                    <li><strong>Metrics:</strong> Qty, Total Amount, Gross Profit, Margin % (या "Profit" बटन दबाएं)</li>
                                                    <li><strong>Date:</strong> This Month (इस महीने) या All Time</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-5 border-left">
                                                <strong>📊 आपको स्क्रीन पर क्या दिखेगा:</strong>
                                                <div class="table-responsive mt-1">
                                                    <table class="table table-xs table-bordered bg-white mb-0">
                                                        <thead class="bg-secondary text-white">
                                                            <tr><th>Item Name</th><th>Qty</th><th>Sales (₹)</th><th>Profit (₹)</th><th>Margin</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr><td>Tata Tea Gold 500g</td><td>45 Pcs</td><td>₹13,500</td><td>₹2,700</td><td>20%</td></tr>
                                                            <tr><td>Fortune Oil 1L</td><td>30 Pcs</td><td>₹4,500</td><td>₹450</td><td>10%</td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- EXAMPLE 2 --}}
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header bg-white py-2" id="headingEx2">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link btn-block text-left font-weight-bold text-dark text-decoration-none d-flex justify-content-between align-items-center collapsed" type="button" data-toggle="collapse" data-target="#collapseEx2">
                                            <span><i class="fas fa-crown text-warning mr-2"></i> <strong>उदाहरण 2:</strong> दुकान के सबसे बड़े VIP ग्राहक कौन-से हैं? (Top Customers)</span>
                                            <span class="badge badge-warning text-dark">VIP Customers</span>
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapseEx2" class="collapse" data-parent="#examplesAccordion">
                                    <div class="card-body bg-light p-3 small">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <strong>🎯 क्यों देखना है:</strong> किन ग्राहकों ने आपकी दुकान से सबसे ज्यादा खरीदारी की है, ताकि उन्हें फेस्टिव डिस्काउंट या लॉयल्टी ऑफर दे सकें।<br>
                                                <strong>⚡ 1-क्लिक तरीका:</strong> ऊपर दिए <strong><i class="fas fa-crown text-warning"></i> Top VIP Customers</strong> बटन पर क्लिक करें।<br>
                                                <strong>⚙️ अगर खुद सेट करना हो:</strong>
                                                <ul class="mb-0 mt-1 pl-3">
                                                    <li><strong>Data Source:</strong> Sales (Bill Level)</li>
                                                    <li><strong>Group By:</strong> Customer</li>
                                                    <li><strong>Metrics:</strong> Records/Count, Total Amount, Total Paid</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-5 border-left">
                                                <strong>📊 आपको क्या दिखेगा:</strong>
                                                <div class="table-responsive mt-1">
                                                    <table class="table table-xs table-bordered bg-white mb-0">
                                                        <thead class="bg-secondary text-white">
                                                            <tr><th>Customer</th><th>Total Bills</th><th>Total Shopping</th><th>Action</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr><td>Ramesh Sharma</td><td>12 Bills</td><td>₹48,200</td><td><span class="badge badge-info">View Details</span></td></tr>
                                                            <tr><td>Priya Patel</td><td>8 Bills</td><td>₹31,500</td><td><span class="badge badge-info">View Details</span></td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- EXAMPLE 3 --}}
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header bg-white py-2" id="headingEx3">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link btn-block text-left font-weight-bold text-dark text-decoration-none d-flex justify-content-between align-items-center collapsed" type="button" data-toggle="collapse" data-target="#collapseEx3">
                                            <span><i class="fas fa-wallet text-primary mr-2"></i> <strong>उदाहरण 3:</strong> कैश कितना आया और ऑनलाइन/UPI में कितना पेमेंट हुआ?</span>
                                            <span class="badge badge-primary">Cash vs UPI</span>
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapseEx3" class="collapse" data-parent="#examplesAccordion">
                                    <div class="card-body bg-light p-3 small">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <strong>🎯 क्यों देखना है:</strong> शाम को गल्ला (Cash Counter) गिनने और बैंक खाते में आए ऑनलाइन पेमेंट को मिलाने के लिए।<br>
                                                <strong>⚡ 1-क्लिक तरीका:</strong> ऊपर दिए <strong><i class="fas fa-wallet"></i> Cash vs UPI vs Card</strong> बटन पर क्लिक करें।<br>
                                                <strong>⚙️ अगर खुद सेट करना हो:</strong>
                                                <ul class="mb-0 mt-1 pl-3">
                                                    <li><strong>Data Source:</strong> Sales (Bill Level)</li>
                                                    <li><strong>Group By:</strong> Payment Mode</li>
                                                    <li><strong>Metrics:</strong> Records/Count, Total Amount</li>
                                                    <li><strong>Date:</strong> Today (आज) या This Month</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-5 border-left">
                                                <strong>📊 आपको क्या दिखेगा:</strong>
                                                <div class="table-responsive mt-1">
                                                    <table class="table table-xs table-bordered bg-white mb-0">
                                                        <thead class="bg-secondary text-white">
                                                            <tr><th>Payment Mode</th><th>Transactions</th><th>Total Collected</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr><td><span class="badge badge-success">Cash</span></td><td>64 Bills</td><td>₹34,800</td></tr>
                                                            <tr><td><span class="badge badge-primary">UPI / QR</span></td><td>52 Bills</td><td>₹41,200</td></tr>
                                                            <tr><td><span class="badge badge-info">Card</span></td><td>14 Bills</td><td>₹12,500</td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- EXAMPLE 4 --}}
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header bg-white py-2" id="headingEx4">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link btn-block text-left font-weight-bold text-dark text-decoration-none d-flex justify-content-between align-items-center collapsed" type="button" data-toggle="collapse" data-target="#collapseEx4">
                                            <span><i class="fas fa-file-invoice-dollar text-success mr-2"></i> <strong>उदाहरण 4:</strong> किस सप्लायर से कुल कितने का माल खरीदा और कितने इनवॉइस आए?</span>
                                            <span class="badge badge-success">Supplier Purchases</span>
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapseEx4" class="collapse" data-parent="#examplesAccordion">
                                    <div class="card-body bg-light p-3 small">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <strong>🎯 क्यों देखना है:</strong> सप्लायर का हिसाब चुकता करने और GST इनपुट टैक्स क्रेडिट (ITC) का मिलान करने के लिए।<br>
                                                <strong>⚡ 1-क्लिक तरीका:</strong> ऊपर दिए <strong><i class="fas fa-file-invoice-dollar"></i> Supplier Invoice Summary</strong> बटन पर क्लिक करें।<br>
                                                <strong>⚙️ अगर खुद सेट करना हो:</strong>
                                                <ul class="mb-0 mt-1 pl-3">
                                                    <li><strong>Data Source:</strong> Purchases</li>
                                                    <li><strong>Group By:</strong> Supplier</li>
                                                    <li><strong>Metrics:</strong> Records/Count, Total Amount, Total Tax</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-5 border-left">
                                                <strong>📊 आपको क्या दिखेगा:</strong>
                                                <div class="table-responsive mt-1">
                                                    <table class="table table-xs table-bordered bg-white mb-0">
                                                        <thead class="bg-secondary text-white">
                                                            <tr><th>Supplier</th><th>Invoices</th><th>Total Purchases</th><th>Tax</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr><td>M/s Balaji Traders</td><td>4 Invoices</td><td>₹1,24,000</td><td>₹6,200</td></tr>
                                                            <tr><td>City Wholesale Mart</td><td>2 Invoices</td><td>₹65,000</td><td>₹3,250</td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- EXAMPLE 5 --}}
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header bg-white py-2" id="headingEx5">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link btn-block text-left font-weight-bold text-dark text-decoration-none d-flex justify-content-between align-items-center collapsed" type="button" data-toggle="collapse" data-target="#collapseEx5">
                                            <span><i class="fas fa-truck-loading text-info mr-2"></i> <strong>उदाहरण 5:</strong> कौन सा आइटम किस-किस सप्लायर से किस रेट पर आया? (Item ⇄ Supplier Sourcing)</span>
                                            <span class="badge badge-info">Sourcing Trace</span>
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapseEx5" class="collapse" data-parent="#examplesAccordion">
                                    <div class="card-body bg-light p-3 small">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <strong>🎯 क्यों देखना है:</strong> अलग-अलग सप्लायर के रेट की तुलना करने के लिए कि कौन सा डिस्ट्रीब्यूटर सस्ता माल देता है और अभी दुकान में कितना स्टॉक बाकी है।<br>
                                                <strong>⚡ 1-क्लिक तरीका:</strong> ऊपर दिए <strong><i class="fas fa-truck-loading"></i> Item ⇄ Supplier Sourcing</strong> बटन पर क्लिक करें।<br>
                                                <strong>⚙️ अगर खुद सेट करना हो:</strong>
                                                <ul class="mb-0 mt-1 pl-3">
                                                    <li><strong>Data Source:</strong> Item-Supplier Sourcing Trace</li>
                                                    <li><strong>Group By:</strong> Item + Supplier (Combined)</li>
                                                    <li><strong>Metrics:</strong> Purchase Qty, Total Purchase Cost, Current Stock</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-5 border-left">
                                                <strong>📊 आपको क्या दिखेगा:</strong>
                                                <div class="table-responsive mt-1">
                                                    <table class="table table-xs table-bordered bg-white mb-0">
                                                        <thead class="bg-secondary text-white">
                                                            <tr><th>Item Name</th><th>Supplier</th><th>Purchased</th><th>Current Stock</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr><td>Aashirvaad Atta 10kg</td><td>Balaji Traders</td><td>50 Bags</td><td>12 Bags</td></tr>
                                                            <tr><td>Aashirvaad Atta 10kg</td><td>National Foods</td><td>30 Bags</td><td>12 Bags</td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- TAB 3: PRO TIPS --}}
                    <div class="tab-pane fade" id="guide-tips" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 bg-white border shadow-sm">
                                    <div class="card-body p-3">
                                        <h6 class="font-weight-bold text-primary mb-2">
                                            <i class="fas fa-eye text-info mr-1"></i> 1. "View / Details" बटन (अंदर के सारे बिल देखना)
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            टेबल में जब भी कोई रिपोर्ट दिखे, हर लाइन के दाईं तरफ एक नीला <strong>"View / Details"</strong> बटन होता है। उस पर क्लिक करते ही एक पॉपअप खुलेगा जिसमें उस आइटम या कस्टमर से जुड़े सभी असली बिल, इनवॉइस नंबर, तारीख और रेट दिख जाएंगे। आप सीधे <em>"View Bill ↗"</em> दबाकर पूरा बिल भी खोल सकते हैं।
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="card h-100 bg-white border shadow-sm">
                                    <div class="card-body p-3">
                                        <h6 class="font-weight-bold text-success mb-2">
                                            <i class="fas fa-chart-pie text-success mr-1"></i> 2. Chart View (ग्राफ में रिपोर्ट देखना)
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            अगर आपको नंबर और टेबल देखने के बजाय चार्ट देखना पसंद है, तो ऊपर दाईं तरफ <strong>"Chart View"</strong> बटन पर क्लिक करें। आपकी रिपोर्ट तुरंत बार चार्ट (Bar Chart) या पाई चार्ट (Pie Chart) में बदल जाएगी, जिससे आँखों को तुरंत समझ आ जाता है कि कौन सा आइटम आगे है।
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="card h-100 bg-white border shadow-sm">
                                    <div class="card-body p-3">
                                        <h6 class="font-weight-bold text-warning mb-2">
                                            <i class="fas fa-file-excel text-success mr-1"></i> 3. Excel और PDF डाउनलोड करना
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            रिपोर्ट जनरेट होने के बाद टेबल के ठीक ऊपर <strong>"Export Excel"</strong> और <strong>"Export PDF"</strong> बटन एक्टिव हो जाते हैं। 1 क्लिक करते ही पूरी रिपोर्ट आपकी एक्सेल शीट में डाउनलोड हो जाएगी, जिसे आप सीधे WhatsApp पर या अपने अकाउंटेंट/CA को भेज सकते हैं।
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="card h-100 bg-white border shadow-sm">
                                    <div class="card-body p-3">
                                        <h6 class="font-weight-bold text-dark mb-2">
                                            <i class="fas fa-bookmark text-primary mr-1"></i> 4. Save Report (अपनी मनपसंद रिपोर्ट सेव करना)
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            अगर कोई खास रिपोर्ट आपको हर हफ्ते देखनी पड़ती है, तो बार-बार सेटिंग बदलने की ज़रूरत नहीं है। ऊपर <strong>"Save Report"</strong> पर क्लिक करें और कोई भी नाम दे दें (जैसे: <em>"मेरी वीकली ग्रोसरी रिपोर्ट"</em>)। अगली बार वह रिपोर्ट आपके <strong>"My Saved Reports"</strong> में 1-क्लिक में मिल जाएगी!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <span class="small text-muted font-italic">
                    <i class="fas fa-check-circle text-success mr-1"></i> अगर कोई सवाल या परेशानी हो, तो आप कभी भी इस गाइड को दोबारा खोल सकते हैं।
                </span>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold px-4" data-dismiss="modal">
                    समझ आ गया (Got it!)
                </button>
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

    // Initialize Select2 AJAX for Item search (by Code, Barcode, or Name)
    $('#item_id').select2({
        theme: 'bootstrap4',
        placeholder: '-- Search Item by Code or Name --',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: '{{ route("reports.analytics-builder.search-items") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '', term: params.term || '' };
            },
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
        minimumInputLength: 0,
        ajax: {
            url: '{{ route("reports.analytics-builder.search-suppliers") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '', term: params.term || '' };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    // Initialize Select2 AJAX for Customer search
    $('#customer_id').select2({
        theme: 'bootstrap4',
        placeholder: '-- Search Customer by Name or Mobile --',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: '{{ route("reports.analytics-builder.search-customers") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '', term: params.term || '' };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    // Calculate exact dates for all presets (Today, Yesterday, Week, Last 7D, Month, Last Month, 30D, FY, All Time)
    function getPresetDates(preset) {
        let now = new Date();
        function fmt(d) {
            let year = d.getFullYear();
            let month = String(d.getMonth() + 1).padStart(2, '0');
            let day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        if (preset === 'today') {
            let s = fmt(now);
            return { from: s, to: s, label: 'Today' };
        }
        if (preset === 'yesterday') {
            let y = new Date(now);
            y.setDate(y.getDate() - 1);
            let s = fmt(y);
            return { from: s, to: s, label: 'Yesterday' };
        }
        if (preset === 'this_week') {
            let day = now.getDay() || 7;
            let mon = new Date(now);
            mon.setDate(now.getDate() - day + 1);
            let sun = new Date(mon);
            sun.setDate(mon.getDate() + 6);
            return { from: fmt(mon), to: fmt(sun), label: 'This Week' };
        }
        if (preset === 'last_7_days') {
            let past = new Date(now);
            past.setDate(now.getDate() - 6);
            return { from: fmt(past), to: fmt(now), label: 'Last 7 Days' };
        }
        if (preset === 'this_month') {
            let first = new Date(now.getFullYear(), now.getMonth(), 1);
            let last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            return { from: fmt(first), to: fmt(last), label: 'This Month' };
        }
        if (preset === 'last_month') {
            let first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            let last = new Date(now.getFullYear(), now.getMonth(), 0);
            return { from: fmt(first), to: fmt(last), label: 'Last Month' };
        }
        if (preset === 'last_30_days') {
            let past = new Date(now);
            past.setDate(now.getDate() - 29);
            return { from: fmt(past), to: fmt(now), label: 'Last 30 Days' };
        }
        if (preset === 'this_fy') {
            let year = now.getFullYear();
            let m = now.getMonth() + 1;
            let startYear = (m >= 4) ? year : (year - 1);
            let endYear = startYear + 1;
            return { from: `${startYear}-04-01`, to: `${endYear}-03-31`, label: `FY ${startYear}-${String(endYear).slice(-2)}` };
        }
        if (preset === 'all_time') {
            return { from: '', to: '', label: 'All Time' };
        }
        return null;
    }

    // Apply Date Preset Function
    function applyDatePreset(preset, autoRun = true) {
        $('#date_preset').val(preset);
        $('.preset-pill').removeClass('active btn-primary btn-success btn-info btn-dark').addClass('btn-outline-secondary');
        let $activeBtn = $(`.preset-pill[data-preset="${preset}"]`);
        if ($activeBtn.length) {
            let btnClass = (preset === 'last_month') ? 'btn-success' : ((preset === 'all_time') ? 'btn-dark' : ((preset === 'this_fy') ? 'btn-info' : 'btn-primary'));
            $activeBtn.removeClass('btn-outline-secondary').addClass('active ' + btnClass);
        }

        let dates = getPresetDates(preset);
        if (dates) {
            $('#date_from').val(dates.from);
            $('#date_to').val(dates.to);
            $('#active-date-text').text(dates.label);
        } else if (preset === 'custom') {
            $('#active-date-text').text('Custom Range');
        }

        if (autoRun) {
            runLiveReport();
        }
    }

    // Preset Pill Click Listener (1-click auto run)
    $(document).on('click', '.preset-pill', function(e) {
        e.preventDefault();
        let preset = $(this).data('preset');
        applyDatePreset(preset, true);
    });

    // Quick Switch Date Buttons (In Results Card and Empty Table State)
    $(document).on('click', '.quick-switch-date', function(e) {
        e.preventDefault();
        let preset = $(this).data('preset');
        applyDatePreset(preset, true);
    });

    // Custom Date inputs change: switch preset to custom
    $('#date_from, #date_to').on('change', function() {
        $('#date_preset').val('custom');
        $('.preset-pill').removeClass('active btn-primary btn-success btn-info btn-dark').addClass('btn-outline-secondary');
        $('#active-date-text').text('Custom Range');
    });

    // Apply Button click for custom date inputs
    $('#btn-apply-dates').on('click', function(e) {
        e.preventDefault();
        $('#date_preset').val('custom');
        runLiveReport();
    });

    // Enter key in date inputs to apply
    $('#date_from, #date_to').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#date_preset').val('custom');
            runLiveReport();
        }
    });

    // Toggle filter panel
    $('#btn-toggle-filter').on('click', function() {
        $('#filter-panel').slideToggle(200, function() {
            let isVisible = $(this).is(':visible');
            $('#toggle-filter-text').text(isVisible ? 'Hide Controls' : 'Show Controls');
        });
    });

    // Group By dropdown change: update help text, sync quick pills, update badge
    $('#group_by').on('change', function() {
        let val = $(this).val();

        // Sync 1-click pills
        $('.groupby-pill').removeClass('active btn-primary').addClass('btn-outline-secondary');
        let $activePill = $(`.groupby-pill[data-group="${val}"]`);
        if ($activePill.length) {
            $activePill.removeClass('btn-outline-secondary').addClass('active btn-primary');
        }

        // Update badge text
        let selectedText = $('#group_by option:selected').text().replace(/^[^\w\s]+/, '').trim();
        $('#current-group-badge').text(selectedText || 'Dimension');

        let desc = 'Aggregates data by selected dimension.';
        if (val === 'item_supplier') {
            desc = '📦 Item ⇄ Supplier Traceability: Shows which suppliers supplied each item, inward quantity, last purchase rate, and invoices.';
            $('#m_margin').prop('checked', false);
        } else if (val === 'supplier') {
            desc = '🏭 Supplier Invoices Summary: Shows how many invoices were issued by each supplier, total purchase value, items inwarded, and average bill value.';
            $('#m_margin').prop('checked', false);
        } else if (val === 'item') {
            desc = 'Aggregates each item\'s sold quantity, total revenue, discount, and profit margin.';
        } else if (val === 'customer') {
            desc = 'Aggregates each customer\'s total purchase value, number of visits, and average order value.';
        } else if (val === 'category') {
            desc = 'Summarizes sales, revenue, and gross margins grouped by Product Category.';
        } else if (val === 'brand') {
            desc = 'Analyzes performance and brand-wise revenue across manufacturers.';
        } else if (val === 'payment_mode') {
            desc = 'Analyzes cash vs card vs UPI transactions breakdown.';
        } else if (val === 'cashier') {
            desc = 'Tracks bill count, sales total, and discount per billing staff / cashier.';
        } else if (val === 'date') {
            desc = 'Day-by-day revenue, transaction counts, and trend breakdown.';
        }
        $('#group-desc-text').text(desc);
        syncMetricChips();
    });

    // 1-Click Group By Pill Click
    $(document).on('click', '.groupby-pill', function(e) {
        e.preventDefault();
        let group = $(this).data('group');
        $('#group_by').val(group).trigger('change');
        runLiveReport();
    });

    // Metric Chip Click Handler (Interactive Toggle)
    $(document).on('click', '.metric-chip', function(e) {
        e.preventDefault();
        let $input = $(this).find('.metric-check');
        let isChecked = !$input.prop('checked');
        $input.prop('checked', isChecked).trigger('change');
        $(this).toggleClass('active', isChecked);

        // Auto-refresh report (debounced 350ms)
        clearTimeout(window.metricDebounceTimer);
        window.metricDebounceTimer = setTimeout(function() {
            runLiveReport();
        }, 350);
    });

    // Metric Bundles (Standard, Profit, All)
    $(document).on('click', '.metric-bundle-btn', function(e) {
        e.preventDefault();
        let bundle = $(this).data('bundle');
        if (bundle === 'standard') {
            $('.metric-check').prop('checked', false);
            $('#m_qty, #m_sales, #m_margin, #m_bills').prop('checked', true);
        } else if (bundle === 'profit') {
            $('.metric-check').prop('checked', false);
            $('#m_sales, #m_margin, #m_disc').prop('checked', true);
        } else if (bundle === 'all') {
            $('.metric-check').prop('checked', true);
        }
        syncMetricChips();
        runLiveReport();
    });

    function syncMetricChips() {
        $('.metric-chip').each(function() {
            let isChecked = $(this).find('.metric-check').prop('checked');
            $(this).toggleClass('active', isChecked);
        });
    }

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

        // Update active date range display badge
        if (resp.date_range_label) {
            $('#display-date-range-text').text(resp.date_range_label);
        }

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
            let activePeriodLabel = resp.date_range_label || 'the chosen period';
            $body.html(`
                <tr>
                    <td colspan="${resp.columns.length + 2}" class="text-center py-5">
                        <div class="py-2">
                            <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                            <h5 class="font-weight-bold text-dark mb-1">No transactions found for ${activePeriodLabel}</h5>
                            <p class="text-muted small mb-3">Transactions may exist in another date range. Click a shortcut below to view:</p>
                            <div>
                                <button type="button" class="btn btn-sm btn-success font-weight-bold mr-2 quick-switch-date shadow-xs" data-preset="last_month">
                                    <i class="fas fa-history mr-1"></i> View Last Month (Sep 2026)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info font-weight-bold mr-2 quick-switch-date shadow-xs" data-preset="this_fy">
                                    <i class="fas fa-calendar mr-1"></i> View This FY (2026-27)
                                </button>
                                <button type="button" class="btn btn-sm btn-dark font-weight-bold quick-switch-date shadow-xs" data-preset="all_time">
                                    <i class="fas fa-globe mr-1"></i> View All Time (Full History)
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            `);
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
                                       <i class="fas fa-search-plus mr-1"></i>${val} ${col.key === 'invoice_count' || resp.group_by === 'supplier' ? 'Invoices' : 'Bills'}
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
        if (!resp || !resp.chart || !resp.chart.labels || resp.chart.labels.length === 0) return;
        if (typeof Chart === 'undefined') return;

        try {
            let canvas = document.getElementById('analyticsChart');
            if (!canvas) return;
            let ctx = canvas.getContext('2d');
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
        } catch (chartErr) {
            console.warn('Visual chart rendering suppressed:', chartErr);
        }
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
            applyDatePreset('all_time', false);
            $('#limit').val('all');
            $('#sort_dir').val('desc');
        } else if (preset === 'single_item') {
            $('#group_by').val('item').trigger('change');
            applyDatePreset('all_time', false);
            $('#limit').val('all');
            $('#sort_dir').val('desc');
        } else if (preset === 'supplier_invoices') {
            $('#group_by').val('supplier').trigger('change');
            applyDatePreset('all_time', false);
            $('#limit').val('all');
            $('#sort_dir').val('desc');
        } else if (preset === 'top_selling') {
            $('#group_by').val('item').trigger('change');
            applyDatePreset('all_time', false);
            $('#limit').val('20');
            $('#sort_dir').val('desc');
        } else if (preset === 'slow_moving') {
            $('#group_by').val('item').trigger('change');
            applyDatePreset('all_time', false);
            $('#limit').val('20');
            $('#sort_dir').val('asc');
        } else if (preset === 'vip_customers') {
            $('#group_by').val('customer').trigger('change');
            applyDatePreset('all_time', false);
            $('#limit').val('20');
            $('#sort_dir').val('desc');
        } else if (preset === 'payment_mode') {
            $('#group_by').val('payment_mode').trigger('change');
            applyDatePreset('all_time', false);
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
            syncMetricChips();
        }

        if (filters) {
            if (filters.date_preset) {
                applyDatePreset(filters.date_preset, false);
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
        applyDatePreset('this_month', false);
        $('#m_qty, #m_sales, #m_margin, #m_bills').prop('checked', true);
        syncMetricChips();
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

        } else if (resp.group_by === 'supplier') {
            $thead.html(`
                <tr>
                    <th class="text-center" style="width: 45px;">#</th>
                    <th>Invoice Number</th>
                    <th>Invoice Date</th>
                    <th>Branch</th>
                    <th class="text-center">Total Qty</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Invoice Total (₹)</th>
                    <th class="text-center" style="width: 100px;">Action</th>
                </tr>
            `);

            if (resp.records.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No purchase invoices found for this supplier.</td></tr>');
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
                        <td class="text-center font-weight-bold">${parseFloat(r.total_qty || 0).toFixed(2)}</td>
                        <td class="text-center"><span class="badge badge-light border font-weight-bold">${r.status || 'Active'}</span></td>
                        <td class="text-right font-weight-bold text-success">${r.formatted_amount}</td>
                        <td class="text-center">
                            <a href="${r.view_url}" target="_blank" class="btn btn-xs btn-outline-primary font-weight-bold" title="Open Invoice">
                                View ↗
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
    @else
        applyDatePreset('this_month', false);
    @endif

    syncMetricChips();

    // Initial Execution
    runLiveReport();
});
</script>
@endpush
