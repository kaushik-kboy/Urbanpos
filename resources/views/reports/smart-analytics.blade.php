@extends('adminlte::page')

@section('title', 'Smart Item & Customer 360° Analytics')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-chart-line text-primary mr-2"></i> Smart Item & Customer 360° Analytics
            </h1>
            <small class="text-muted">Instant sales drilldown for single items, individual customers, and fast/slow-moving ranking</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Reports Hub
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline card-outline-tabs shadow-sm mb-4">
        <div class="card-header p-0 border-bottom-0 bg-light">
            <ul class="nav nav-tabs font-weight-bold" id="analyticsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active px-4 py-3 text-dark" id="tab-item-link" data-toggle="pill" href="#tab-item" role="tab">
                        <i class="fas fa-box-open mr-1 text-primary"></i> Single Item 360° Drilldown
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 text-dark" id="tab-customer-link" data-toggle="pill" href="#tab-customer" role="tab">
                        <i class="fas fa-user-check mr-1 text-success"></i> Single Customer 360°
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 text-dark" id="tab-ranking-link" data-toggle="pill" href="#tab-ranking" role="tab">
                        <i class="fas fa-trophy mr-1 text-warning"></i> Top & Slow-Moving Ranking
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="analyticsTabsContent">

                {{-- ========================================================================= --}}
                {{-- TAB 1: SINGLE ITEM 360° DRILLDOWN --}}
                {{-- ========================================================================= --}}
                <div class="tab-pane fade show active" id="tab-item" role="tabpanel">
                    {{-- Item Filter Bar --}}
                    <div class="card card-outline card-secondary shadow-none border bg-light mb-4">
                        <div class="card-body p-3">
                            <form id="item-filter-form">
                                <div class="row align-items-end">
                                    <div class="col-lg-4 col-md-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">
                                            <i class="fas fa-barcode text-primary mr-1"></i> Select Item / Product <span class="text-danger">*</span>
                                        </label>
                                        <select id="item-select" class="form-control select2" style="width: 100%;" required>
                                            @if($initialItem)
                                                <option value="{{ $initialItem->id }}" selected>
                                                    [{{ $initialItem->item_code }}] {{ $initialItem->name }}
                                                </option>
                                            @else
                                                <option value="">-- Search by Item Name or Code --</option>
                                            @endif
                                        </select>
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">Branch</label>
                                        <select id="item-branch" class="form-control form-control-sm">
                                            <option value="">All Branches</option>
                                            @foreach($branches as $bId => $bName)
                                                <option value="{{ $bId }}" @selected($defaultBranchId == $bId)>{{ $bName }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">From Date</label>
                                        <input type="date" id="item-from-date" class="form-control form-control-sm" value="{{ $fromDate }}">
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">To Date</label>
                                        <input type="date" id="item-to-date" class="form-control form-control-sm" value="{{ $toDate }}">
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6">
                                        <button type="submit" class="btn btn-primary btn-sm btn-block font-weight-bold shadow-sm" id="btn-load-item">
                                            <i class="fas fa-search mr-1"></i> Analyze Item
                                        </button>
                                    </div>
                                </div>

                                {{-- Date Presets --}}
                                <div class="mt-2 d-flex flex-wrap align-items-center">
                                    <span class="small font-weight-bold text-muted mr-2">Quick Presets:</span>
                                    <div class="btn-group btn-group-xs" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="item" data-preset="today">Today</button>
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="item" data-preset="yesterday">Yesterday</button>
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="item" data-preset="this_week">This Week</button>
                                        <button type="button" class="btn btn-primary btn-xs preset-btn active" data-target="item" data-preset="this_month">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="item" data-preset="last_month">Last Month</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Empty Placeholder --}}
                    <div id="item-empty-state" class="text-center py-5 {{ $initialItem ? 'd-none' : '' }}">
                        <div class="mb-3 text-muted">
                            <i class="fas fa-search-dollar fa-4x text-gray-300"></i>
                        </div>
                        <h5 class="font-weight-bold text-secondary">No Product Selected</h5>
                        <p class="text-muted small max-w-md mx-auto">
                            Please select any product from the search box above to view total sold quantity this month, sales revenue, gross margins, and bill-by-bill sales history.
                        </p>
                    </div>

                    {{-- Item Analytics Results Container --}}
                    <div id="item-results" class="{{ $initialItem ? '' : 'd-none' }}">
                        {{-- Product Overview Card --}}
                        <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center mb-4 shadow-sm">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white p-3 rounded mr-3">
                                    <i class="fas fa-tag fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="font-weight-bold text-dark mb-1" id="res-item-name">Loading...</h4>
                                    <div class="text-muted small">
                                        <span class="mr-3"><strong>Item Code:</strong> <span id="res-item-code">-</span></span>
                                        <span class="mr-3"><strong>Brand:</strong> <span id="res-item-brand">-</span></span>
                                        <span class="mr-3"><strong>Selling Price:</strong> <span id="res-item-price">-</span></span>
                                        <span><strong>MRP:</strong> <span id="res-item-mrp">-</span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 mt-md-0">
                                <button type="button" class="btn btn-outline-success btn-sm font-weight-bold mr-1" id="btn-export-item-csv">
                                    <i class="fas fa-file-excel mr-1"></i> Export to Excel / CSV
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                                    <i class="fas fa-print mr-1"></i> Print
                                </button>
                            </div>
                        </div>

                        {{-- 4 KPI Cards --}}
                        <div class="row mb-4">
                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-primary shadow-sm h-100 mb-0">
                                    <div class="card-body p-3 text-center">
                                        <span class="text-muted text-uppercase small font-weight-bold">Total Sold Qty</span>
                                        <h2 class="font-weight-bold text-primary my-2" id="kpi-item-qty">0</h2>
                                        <small class="text-muted font-weight-bold" id="kpi-item-bills-count">Across 0 bills</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-success shadow-sm h-100 mb-0">
                                    <div class="card-body p-3 text-center">
                                        <span class="text-muted text-uppercase small font-weight-bold">Total Sales Revenue</span>
                                        <h2 class="font-weight-bold text-success my-2" id="kpi-item-revenue">₹0.00</h2>
                                        <small class="text-muted font-weight-bold">Discounts: <span id="kpi-item-discount" class="text-danger">₹0.00</span></small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-info shadow-sm h-100 mb-0">
                                    <div class="card-body p-3 text-center">
                                        <span class="text-muted text-uppercase small font-weight-bold">Gross Profit & Margin</span>
                                        <h2 class="font-weight-bold text-info my-2" id="kpi-item-profit">₹0.00</h2>
                                        <small class="text-muted font-weight-bold">Margin: <span id="kpi-item-margin" class="badge badge-info">0%</span></small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-warning shadow-sm h-100 mb-0">
                                    <div class="card-body p-3 text-center">
                                        <span class="text-muted text-uppercase small font-weight-bold">Current In-Stock</span>
                                        <h2 class="font-weight-bold text-warning my-2" id="kpi-item-stock">0</h2>
                                        <small class="text-muted font-weight-bold">Available in selected branch</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Daily Sales Trend Chart --}}
                        <div class="card card-outline card-secondary mb-4 shadow-sm">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                                <h6 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-chart-bar text-primary mr-1"></i> Daily Sales Trend (Selected Period)
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div id="chart-container" style="min-height: 220px; display: flex; align-items: flex-end; gap: 8px; overflow-x: auto; padding-top: 20px;">
                                    {{-- Rendered dynamically --}}
                                </div>
                            </div>
                        </div>

                        {{-- Detailed Bill Breakdown Table --}}
                        <div class="card card-outline card-primary shadow-sm">
                            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                                <h6 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-list-alt text-primary mr-1"></i> Bill-by-Bill Breakdown (Sales Invoices)
                                </h6>
                                <span class="badge badge-light border" id="item-bills-count-badge">0 entries</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm table-striped mb-0" id="item-bills-table">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Bill Number</th>
                                                <th>Date & Time</th>
                                                <th>Customer</th>
                                                <th class="text-right">Qty Sold</th>
                                                <th class="text-right">Selling Rate</th>
                                                <th class="text-right">Discount (₹)</th>
                                                <th class="text-right">Net Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody id="item-bills-tbody">
                                            {{-- Populated via AJAX --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ========================================================================= --}}
                {{-- TAB 2: SINGLE CUSTOMER 360° --}}
                {{-- ========================================================================= --}}
                <div class="tab-pane fade" id="tab-customer" role="tabpanel">
                    {{-- Customer Filter Bar --}}
                    <div class="card card-outline card-secondary shadow-none border bg-light mb-4">
                        <div class="card-body p-3">
                            <form id="customer-filter-form">
                                <div class="row align-items-end">
                                    <div class="col-lg-4 col-md-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">
                                            <i class="fas fa-user-tag text-success mr-1"></i> Select Customer <span class="text-danger">*</span>
                                        </label>
                                        <select id="customer-select" class="form-control select2" style="width: 100%;" required>
                                            @if($initialCustomer)
                                                <option value="{{ $initialCustomer->id }}" selected>
                                                    {{ $initialCustomer->name }} ({{ $initialCustomer->mobile }})
                                                </option>
                                            @else
                                                <option value="">-- Search Customer by Name or Mobile --</option>
                                            @endif
                                        </select>
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">Branch</label>
                                        <select id="customer-branch" class="form-control form-control-sm">
                                            <option value="">All Branches</option>
                                            @foreach($branches as $bId => $bName)
                                                <option value="{{ $bId }}" @selected($defaultBranchId == $bId)>{{ $bName }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">From Date</label>
                                        <input type="date" id="customer-from-date" class="form-control form-control-sm" value="{{ $fromDate }}">
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                                        <label class="font-weight-bold small text-dark mb-1">To Date</label>
                                        <input type="date" id="customer-to-date" class="form-control form-control-sm" value="{{ $toDate }}">
                                    </div>

                                    <div class="col-lg-2 col-md-3 col-sm-6">
                                        <button type="submit" class="btn btn-success btn-sm btn-block font-weight-bold shadow-sm" id="btn-load-customer">
                                            <i class="fas fa-search mr-1"></i> Analyze Customer
                                        </button>
                                    </div>
                                </div>

                                {{-- Date Presets --}}
                                <div class="mt-2 d-flex flex-wrap align-items-center">
                                    <span class="small font-weight-bold text-muted mr-2">Quick Presets:</span>
                                    <div class="btn-group btn-group-xs" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="customer" data-preset="today">Today</button>
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="customer" data-preset="this_week">This Week</button>
                                        <button type="button" class="btn btn-success btn-xs preset-btn active" data-target="customer" data-preset="this_month">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-xs preset-btn" data-target="customer" data-preset="last_month">Last Month</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Empty Placeholder --}}
                    <div id="customer-empty-state" class="text-center py-5 {{ $initialCustomer ? 'd-none' : '' }}">
                        <div class="mb-3 text-muted">
                            <i class="fas fa-user-clock fa-4x text-gray-300"></i>
                        </div>
                        <h5 class="font-weight-bold text-secondary">No Customer Selected</h5>
                        <p class="text-muted small max-w-md mx-auto">
                            Select any customer to inspect their overall bill count, total spending, outstanding balance, and most frequently purchased products.
                        </p>
                    </div>

                    {{-- Customer Results --}}
                    <div id="customer-results" class="{{ $initialCustomer ? '' : 'd-none' }}">
                        <div class="row mb-4">
                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-success shadow-sm text-center p-3 h-100 mb-0">
                                    <span class="text-muted text-uppercase small font-weight-bold">Total Invoices</span>
                                    <h2 class="font-weight-bold text-success my-2" id="kpi-cust-bills">0</h2>
                                    <small class="text-muted">In selected period</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-primary shadow-sm text-center p-3 h-100 mb-0">
                                    <span class="text-muted text-uppercase small font-weight-bold">Total Spend</span>
                                    <h2 class="font-weight-bold text-primary my-2" id="kpi-cust-spend">₹0.00</h2>
                                    <small class="text-muted">Net sales across all visits</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-info shadow-sm text-center p-3 h-100 mb-0">
                                    <span class="text-muted text-uppercase small font-weight-bold">Average Bill Value</span>
                                    <h2 class="font-weight-bold text-info my-2" id="kpi-cust-aov">₹0.00</h2>
                                    <small class="text-muted">Average spend per visit</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-3">
                                <div class="card card-outline card-danger shadow-sm text-center p-3 h-100 mb-0">
                                    <span class="text-muted text-uppercase small font-weight-bold">Current Ledger Balance</span>
                                    <h2 class="font-weight-bold text-danger my-2" id="kpi-cust-balance">₹0.00</h2>
                                    <small class="text-muted">Sundry debtor balance</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            {{-- Top Purchased Items --}}
                            <div class="col-lg-4 mb-4">
                                <div class="card card-outline card-secondary shadow-sm h-100">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="card-title font-weight-bold mb-0 text-dark">
                                            <i class="fas fa-heart text-danger mr-1"></i> Favorite Products Purchased
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush" id="cust-top-items-list">
                                            {{-- Dynamically populated --}}
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {{-- Invoices Table --}}
                            <div class="col-lg-8 mb-4">
                                <div class="card card-outline card-success shadow-sm h-100">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="card-title font-weight-bold mb-0 text-dark">
                                            <i class="fas fa-file-invoice text-success mr-1"></i> Invoices History
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm table-striped mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Bill No</th>
                                                        <th>Date</th>
                                                        <th class="text-right">Total Qty</th>
                                                        <th class="text-right">Total (₹)</th>
                                                        <th class="text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cust-bills-tbody">
                                                    {{-- Populated via AJAX --}}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ========================================================================= --}}
                {{-- TAB 3: TOP & SLOW MOVING RANKING --}}
                {{-- ========================================================================= --}}
                <div class="tab-pane fade" id="tab-ranking" role="tabpanel">
                    <div class="card card-outline card-secondary shadow-none border bg-light mb-4">
                        <div class="card-body p-3">
                            <form id="ranking-filter-form">
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-2 mb-md-0">
                                        <label class="font-weight-bold small text-dark mb-1">Ranking Mode</label>
                                        <select id="ranking-type" class="form-control form-control-sm font-weight-bold">
                                            <option value="top_selling" selected>🚀 Top Selling Items (Fast Moving)</option>
                                            <option value="slow_moving">🐢 Slow Moving / Dead Stock (Zero Sales)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2 mb-2 mb-md-0">
                                        <label class="font-weight-bold small text-dark mb-1">Rank By</label>
                                        <select id="ranking-metric" class="form-control form-control-sm">
                                            <option value="qty" selected>By Quantity Sold</option>
                                            <option value="revenue">By Total Revenue (₹)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2 mb-2 mb-md-0">
                                        <label class="font-weight-bold small text-dark mb-1">Show Count</label>
                                        <select id="ranking-limit" class="form-control form-control-sm">
                                            <option value="10" selected>Top 10</option>
                                            <option value="25">Top 25</option>
                                            <option value="50">Top 50</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-2 mb-md-0">
                                        <label class="font-weight-bold small text-dark mb-1">Branch</label>
                                        <select id="ranking-branch" class="form-control form-control-sm">
                                            <option value="">All Branches</option>
                                            @foreach($branches as $bId => $bName)
                                                <option value="{{ $bId }}" @selected($defaultBranchId == $bId)>{{ $bName }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold shadow-sm" id="btn-load-ranking">
                                            <i class="fas fa-sync-alt mr-1"></i> Generate Rank
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card card-outline card-warning shadow-sm">
                        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                            <h6 class="card-title font-weight-bold mb-0 text-dark" id="ranking-table-title">
                                <i class="fas fa-medal text-warning mr-1"></i> Top Selling Items Ranking
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped table-sm mb-0" id="ranking-table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th>Item Code</th>
                                            <th>Product Name</th>
                                            <th class="text-right">Unit Price</th>
                                            <th class="text-right" id="th-rank-metric">Total Qty Sold</th>
                                            <th class="text-right">Total Revenue (₹)</th>
                                            <th style="width: 120px;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ranking-tbody">
                                        <tr><td colspan="7" class="text-center py-4 text-muted">Click "Generate Rank" to load data</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    // 1. Initialize Item Select2 with AJAX search
    $('#item-select').select2({
        theme: 'bootstrap4',
        placeholder: 'Search product by name, code or barcode...',
        allowClear: true,
        ajax: {
            url: '{{ route("reports.smart-analytics.search-items") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: '[' + (item.item_code || 'NO-CODE') + '] ' + item.name + ' - ₹' + parseFloat(item.sell_price || 0).toFixed(2)
                        };
                    })
                };
            },
            cache: true
        }
    });

    // 2. Initialize Customer Select2 with AJAX search
    $('#customer-select').select2({
        theme: 'bootstrap4',
        placeholder: 'Search customer by name or mobile...',
        allowClear: true,
        ajax: {
            url: '{{ route("reports.smart-analytics.search-customers") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(c) {
                        return {
                            id: c.id,
                            text: c.name + (c.mobile ? ' (' + c.mobile + ')' : '')
                        };
                    })
                };
            },
            cache: true
        }
    });

    // Quick Date Presets Handler
    $('.preset-btn').on('click', function(e) {
        e.preventDefault();
        let target = $(this).data('target');
        let preset = $(this).data('preset');

        $(this).siblings().removeClass('btn-primary btn-success active').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass(target === 'item' ? 'btn-primary active' : 'btn-success active');

        let today = new Date();
        let fromDate = '', toDate = '';

        function fmt(d) {
            let month = '' + (d.getMonth() + 1), day = '' + d.getDate(), year = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        }

        if (preset === 'today') {
            fromDate = toDate = fmt(today);
        } else if (preset === 'yesterday') {
            let y = new Date(today);
            y.setDate(today.getDate() - 1);
            fromDate = toDate = fmt(y);
        } else if (preset === 'this_week') {
            let curr = new Date(today);
            let first = curr.getDate() - curr.getDay() + (curr.getDay() === 0 ? -6 : 1);
            let firstDay = new Date(curr.setDate(first));
            fromDate = fmt(firstDay);
            toDate = fmt(today);
        } else if (preset === 'this_month') {
            let firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            fromDate = fmt(firstDay);
            toDate = fmt(today);
        } else if (preset === 'last_month') {
            let firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            let lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
            fromDate = fmt(firstDay);
            toDate = fmt(lastDay);
        }

        $('#' + target + '-from-date').val(fromDate);
        $('#' + target + '-to-date').val(toDate);
    });

    // Load Single Item Analytics
    function fetchItemAnalytics() {
        let itemId = $('#item-select').val();
        if (!itemId) {
            alert('Please select an item first.');
            return;
        }

        let $btn = $('#btn-load-item');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading...');

        $.ajax({
            url: '{{ route("reports.smart-analytics.item") }}',
            method: 'GET',
            data: {
                item_id: itemId,
                branch_id: $('#item-branch').val(),
                from_date: $('#item-from-date').val(),
                to_date: $('#item-to-date').val()
            },
            success: function(resp) {
                $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Analyze Item');
                renderItemAnalytics(resp);
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Analyze Item');
                alert('Failed to load item analytics. Please check parameters.');
            }
        });
    }

    $('#item-filter-form').on('submit', function(e) {
        e.preventDefault();
        fetchItemAnalytics();
    });

    function renderItemAnalytics(data) {
        $('#item-empty-state').addClass('d-none');
        $('#item-results').removeClass('d-none');

        // Product Header
        $('#res-item-name').text(data.item.name);
        $('#res-item-code').text(data.item.item_code || 'N/A');
        $('#res-item-brand').text(data.item.brand);
        $('#res-item-price').text('₹' + parseFloat(data.item.sell_price).toFixed(2));
        $('#res-item-mrp').text('₹' + parseFloat(data.item.mrp).toFixed(2));

        // KPIs
        $('#kpi-item-qty').text(parseFloat(data.summary.total_qty).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 2}));
        $('#kpi-item-bills-count').text('Across ' + data.summary.bills_count + ' invoices');
        $('#kpi-item-revenue').text('₹' + parseFloat(data.summary.total_net_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#kpi-item-discount').text('₹' + parseFloat(data.summary.total_discount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#kpi-item-profit').text('₹' + parseFloat(data.summary.gross_profit).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#kpi-item-margin').text(data.summary.margin_percent + '% margin');
        $('#kpi-item-stock').text(parseFloat(data.summary.current_stock).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 2}));

        // Render Bar Trend Chart
        let $chart = $('#chart-container').empty();
        if (data.daily_trend.length === 0) {
            $chart.html('<div class="w-100 text-center text-muted py-4">No sales recorded for this item in the selected period.</div>');
        } else {
            let maxQty = Math.max(...data.daily_trend.map(d => parseFloat(d.qty)), 1);
            data.daily_trend.forEach(function(d) {
                let heightPct = Math.max((parseFloat(d.qty) / maxQty) * 160, 15);
                let bar = $(`
                    <div style="flex: 1; min-width: 38px; text-align: center;" title="Date: ${d.date} | Qty: ${d.qty} | ₹${parseFloat(d.amount).toFixed(2)}">
                        <div class="small font-weight-bold text-primary mb-1">${parseFloat(d.qty).toFixed(0)}</div>
                        <div style="height: ${heightPct}px; background: linear-gradient(180deg, #007bff 0%, #0056b3 100%); border-radius: 4px 4px 0 0; width: 80%; margin: 0 auto; transition: height 0.3s;"></div>
                        <div class="small text-muted mt-1" style="font-size: 10px; transform: rotate(-35deg); transform-origin: left top; white-space: nowrap;">${d.date.slice(5)}</div>
                    </div>
                `);
                $chart.append(bar);
            });
        }

        // Bill Breakdown
        let $tbody = $('#item-bills-tbody').empty();
        $('#item-bills-count-badge').text(data.bills.length + ' entries');

        if (data.bills.length === 0) {
            $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No bill records found for this period.</td></tr>');
        } else {
            data.bills.forEach(function(b) {
                let dt = new Date(b.bill_date);
                let formattedDate = dt.toLocaleDateString('en-GB') + ' ' + dt.toLocaleTimeString('en-IN', {hour: '2-digit', minute:'2-digit'});
                $tbody.append(`
                    <tr>
                        <td class="font-weight-bold text-primary">${b.bill_number}</td>
                        <td class="text-muted small">${formattedDate}</td>
                        <td>${b.customer_name || '<span class="text-muted">Walk-in Customer</span>'}</td>
                        <td class="text-right font-weight-bold">${parseFloat(b.qty).toFixed(2)}</td>
                        <td class="text-right">₹${parseFloat(b.sell_price).toFixed(2)}</td>
                        <td class="text-right text-danger">₹${parseFloat(b.disc_amount).toFixed(2)}</td>
                        <td class="text-right font-weight-bold text-success">₹${parseFloat(b.net_amount).toFixed(2)}</td>
                    </tr>
                `);
            });
        }
    }

    // Export Item CSV
    $('#btn-export-item-csv').on('click', function(e) {
        e.preventDefault();
        let itemId = $('#item-select').val();
        if (!itemId) return;
        let url = '{{ route("reports.smart-analytics.export-item") }}' +
            '?item_id=' + itemId +
            '&branch_id=' + $('#item-branch').val() +
            '&from_date=' + $('#item-from-date').val() +
            '&to_date=' + $('#item-to-date').val();
        window.location.href = url;
    });

    // Customer Analytics
    $('#customer-filter-form').on('submit', function(e) {
        e.preventDefault();
        let custId = $('#customer-select').val();
        if (!custId) {
            alert('Please select a customer.');
            return;
        }

        let $btn = $('#btn-load-customer');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading...');

        $.ajax({
            url: '{{ route("reports.smart-analytics.customer") }}',
            method: 'GET',
            data: {
                customer_id: custId,
                branch_id: $('#customer-branch').val(),
                from_date: $('#customer-from-date').val(),
                to_date: $('#customer-to-date').val()
            },
            success: function(resp) {
                $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Analyze Customer');
                $('#customer-empty-state').addClass('d-none');
                $('#customer-results').removeClass('d-none');

                $('#kpi-cust-bills').text(resp.summary.total_bills);
                $('#kpi-cust-spend').text('₹' + parseFloat(resp.summary.total_spend).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                $('#kpi-cust-aov').text('₹' + parseFloat(resp.summary.avg_bill_value).toFixed(2));
                $('#kpi-cust-balance').text('₹' + parseFloat(resp.customer.credit_balance).toFixed(2));

                // Favorite items
                let $favList = $('#cust-top-items-list').empty();
                if (resp.top_items.length === 0) {
                    $favList.append('<li class="list-group-item text-muted text-center py-3">No purchases in this period.</li>');
                } else {
                    resp.top_items.forEach(function(item) {
                        $favList.append(`
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark">${item.name}</strong>
                                    <div class="small text-muted">₹${parseFloat(item.total_amount).toFixed(2)} total</div>
                                </div>
                                <span class="badge badge-primary badge-pill">${parseFloat(item.total_qty).toFixed(0)} pcs</span>
                            </li>
                        `);
                    });
                }

                // Invoices
                let $custTbody = $('#cust-bills-tbody').empty();
                if (resp.recent_bills.length === 0) {
                    $custTbody.append('<tr><td colspan="5" class="text-center py-4 text-muted">No invoices found.</td></tr>');
                } else {
                    resp.recent_bills.forEach(function(b) {
                        $custTbody.append(`
                            <tr>
                                <td class="font-weight-bold text-primary">${b.bill_number}</td>
                                <td>${b.bill_date.slice(0, 10)}</td>
                                <td class="text-right">${b.total_qty}</td>
                                <td class="text-right font-weight-bold text-success">₹${parseFloat(b.total).toFixed(2)}</td>
                                <td class="text-center"><span class="badge badge-success">${b.status}</span></td>
                            </tr>
                        `);
                    });
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Analyze Customer');
                alert('Failed to load customer analytics.');
            }
        });
    });

    // Ranking Handler
    $('#ranking-filter-form').on('submit', function(e) {
        e.preventDefault();
        let type = $('#ranking-type').val();
        let metric = $('#ranking-metric').val();
        let limit = $('#ranking-limit').val();
        let branchId = $('#ranking-branch').val();

        let $btn = $('#btn-load-ranking');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating...');

        $('#ranking-table-title').html(type === 'slow_moving'
            ? '<i class="fas fa-hourglass-half text-danger mr-1"></i> Slow Moving & Dead Stock (Zero Sales)'
            : '<i class="fas fa-medal text-warning mr-1"></i> Top Selling Items Ranking'
        );

        $('#th-rank-metric').text(type === 'slow_moving' ? 'Current Stock' : (metric === 'revenue' ? 'Revenue (₹)' : 'Total Qty Sold'));

        $.ajax({
            url: '{{ route("reports.smart-analytics.ranking") }}',
            method: 'GET',
            data: {
                type: type,
                metric: metric,
                limit: limit,
                branch_id: branchId
            },
            success: function(resp) {
                $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> Generate Rank');
                let $tbody = $('#ranking-tbody').empty();

                if (resp.items.length === 0) {
                    $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No ranking data available for criteria.</td></tr>');
                    return;
                }

                resp.items.forEach(function(item, idx) {
                    let metricDisplay = type === 'slow_moving'
                        ? `<span class="badge badge-warning">${parseFloat(item.current_stock).toFixed(0)} pcs in stock</span>`
                        : (metric === 'revenue'
                            ? `<span class="font-weight-bold text-success">₹${parseFloat(item.total_revenue).toFixed(2)}</span>`
                            : `<span class="badge badge-primary">${parseFloat(item.total_sold_qty).toFixed(0)} pcs</span>`);

                    $tbody.append(`
                        <tr>
                            <td class="text-center font-weight-bold">${idx + 1}</td>
                            <td><span class="badge badge-light border">${item.item_code || 'N/A'}</span></td>
                            <td class="font-weight-bold text-dark">${item.name}</td>
                            <td class="text-right">₹${parseFloat(item.sell_price).toFixed(2)}</td>
                            <td class="text-right">${metricDisplay}</td>
                            <td class="text-right font-weight-bold text-success">₹${parseFloat(item.total_revenue || 0).toFixed(2)}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-primary btn-drilldown font-weight-bold" data-id="${item.id}" data-text="[${item.item_code}] ${item.name}">
                                    <i class="fas fa-chart-line mr-1"></i> 360° View
                                </button>
                            </td>
                        </tr>
                    `);
                });
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> Generate Rank');
                alert('Failed to load ranking analytics.');
            }
        });
    });

    // 1-Click Drilldown from Ranking to Single Item Tab
    $(document).on('click', '.btn-drilldown', function(e) {
        e.preventDefault();
        let itemId = $(this).data('id');
        let itemText = $(this).data('text');

        let newOption = new Option(itemText, itemId, true, true);
        $('#item-select').empty().append(newOption).trigger('change');

        $('#tab-item-link').tab('show');
        fetchItemAnalytics();
    });

    // Auto-load if initial item passed
    @if($initialItem)
        fetchItemAnalytics();
    @endif
});
</script>
@endpush
