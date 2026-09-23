@extends('adminlte::page')

@section('title', 'Executive Dashboard & Analytics')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-chart-pie mr-2 text-primary"></i> Executive Dashboard
            </h1>
            <small class="text-muted">Real-time store metrics, visual analytics, and fast-action command center</small>
        </div>
        <div class="d-flex align-items-center mt-2 mt-md-0">
            <button type="button" class="btn btn-outline-dark btn-sm shadow-sm font-weight-bold mr-2" data-toggle="modal" data-target="#dashboardCustomizerModal" id="btnOpenDashboardCustomizer">
                <i class="fas fa-sliders-h mr-1 text-primary"></i> Customize Dashboard
            </button>
            <span class="badge badge-primary px-3 py-2 shadow-sm font-weight-normal">
                <i class="far fa-calendar-alt mr-1"></i> {{ now()->format('d M Y') }}
            </span>
        </div>
    </div>

    <div class="mt-4 mb-2">
        <div class="position-relative">
            <div class="input-group input-group-lg shadow-sm">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0 text-primary">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <input type="text" id="dashboardSearchInput" class="form-control border-left-0 pl-0" placeholder="Search for modules, reports, or settings..." autocomplete="off">
            </div>

            <!-- Search Results Dropdown -->
            <div id="dashboardSearchResults" class="dropdown-menu w-100 shadow-lg mt-1 rounded" style="display: none; max-height: 350px; overflow-y: auto; position: absolute; z-index: 1000;">
                <!-- Results injected via JS -->
            </div>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Fast Action Command Center (Dynamic Auto-Fill Grid) --}}
    <div class="card card-outline card-secondary shadow-sm mb-4">
        <div class="card-header py-2 bg-light">
            <h3 class="card-title text-sm font-weight-bold text-uppercase text-secondary mb-0">
                <i class="fas fa-bolt mr-1 text-warning"></i> Fast Action Command Center
            </h3>
        </div>
        <div class="card-body py-3">
            <div class="dashboard-shortcut-grid">
                @forelse($activeShortcuts as $shortcut)
                    @php
                        $btnClass = match($shortcut['color'] ?? 'primary') {
                            'primary' => 'btn-primary',
                            'success' => 'btn-success',
                            'outline-primary' => 'btn-outline-primary',
                            'outline-info' => 'btn-outline-info',
                            'outline-success' => 'btn-outline-success',
                            'outline-danger' => 'btn-outline-danger',
                            'outline-warning' => 'btn-outline-warning text-dark',
                            'outline-secondary' => 'btn-outline-secondary',
                            'outline-purple' => 'btn-outline-purple text-dark border-secondary',
                            'outline-dark' => 'btn-outline-dark',
                            default => 'btn-outline-primary',
                        };
                    @endphp
                    <a href="{{ route($shortcut['route']) }}" class="btn {{ $btnClass }} shadow-sm shortcut-tile position-relative">
                        <i class="{{ $shortcut['icon'] }} fa-lg mb-1"></i>
                        <span class="font-weight-bold shortcut-title">{{ $shortcut['title'] }}</span>
                        @if(isset($shortcut['badge_value']) && $shortcut['badge_value'] > 0)
                            <span class="badge badge-danger position-absolute" style="top: 6px; right: 6px; font-size: 10px; border-radius: 10px; padding: 2px 6px;">
                                {{ $shortcut['badge_value'] }}
                            </span>
                        @endif
                    </a>
                @empty
                    <div class="text-center py-4 text-muted w-100">
                        <i class="fas fa-info-circle mr-1"></i> No shortcuts currently active. Click 
                        <button type="button" class="btn btn-xs btn-primary ml-1 font-weight-bold" data-toggle="modal" data-target="#dashboardCustomizerModal">
                            Customize Dashboard
                        </button> to add fast-action buttons.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Executive KPI Metrics --}}
    @if(($activeWidgets['kpi_sales'] ?? true) || ($activeWidgets['kpi_stock'] ?? true))
    <div class="row">
        @if($activeWidgets['kpi_sales'] ?? true)
        <div class="col-xl-3 col-md-6 col-12 mb-3">
            <div class="card bg-gradient-primary text-white shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-xs font-weight-bold text-white-50">Today's Sales</span>
                            <h2 class="font-weight-bold mb-0">₹{{ number_format($todaySales, 2) }}</h2>
                            <small class="text-white-50">
                                <i class="fas fa-receipt mr-1"></i> {{ $todayBillsCount }} {{ Str::plural('Bill', $todayBillsCount) }} Today
                            </small>
                        </div>
                        <div class="bg-white rounded-circle p-3 text-primary shadow-sm">
                            <i class="fas fa-cash-register fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-1 bg-black-10 border-0">
                    <a href="{{ route('reports.sales-summary') }}" class="text-white text-xs font-weight-bold d-flex justify-content-between align-items-center">
                        <span>View Sales Summary</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3">
            <div class="card bg-gradient-success text-white shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-xs font-weight-bold text-white-50">Month to Date (MTD)</span>
                            <h2 class="font-weight-bold mb-0">₹{{ number_format($monthSales, 2) }}</h2>
                            <small class="text-white-50">
                                @if ($salesGrowthPct >= 0)
                                    <i class="fas fa-arrow-up text-white"></i> +{{ $salesGrowthPct }}% vs prev month
                                @else
                                    <i class="fas fa-arrow-down text-white"></i> {{ $salesGrowthPct }}% vs prev month
                                @endif
                            </small>
                        </div>
                        <div class="bg-white rounded-circle p-3 text-success shadow-sm">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-1 bg-black-10 border-0">
                    <a href="{{ route('reports.sales-margin-itemwise') }}" class="text-white text-xs font-weight-bold d-flex justify-content-between align-items-center">
                        <span>View Margin Analysis</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3">
            <div class="card bg-gradient-info text-white shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-xs font-weight-bold text-white-50">Gross Margin (MTD)</span>
                            <h2 class="font-weight-bold mb-0">₹{{ number_format($monthProfit, 2) }}</h2>
                            <small class="text-white-50">
                                <i class="fas fa-percentage mr-1"></i>
                                {{ $monthSales > 0 ? number_format(($monthProfit / $monthSales) * 100, 1) : '0' }}% gross margin rate
                            </small>
                        </div>
                        <div class="bg-white rounded-circle p-3 text-info shadow-sm">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-1 bg-black-10 border-0">
                    <a href="{{ route('reports.sales-margin-category') }}" class="text-white text-xs font-weight-bold d-flex justify-content-between align-items-center">
                        <span>Category Margin Breakdown</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif

        @if($activeWidgets['kpi_stock'] ?? true)
        <div class="col-xl-3 col-md-6 col-12 mb-3">
            <div class="card bg-gradient-warning text-dark shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-xs font-weight-bold text-black-50">Stock Valuation (at Cost)</span>
                            <h2 class="font-weight-bold mb-0">₹{{ number_format($stockValue, 2) }}</h2>
                            <small class="text-black-50">
                                <i class="fas fa-box-open mr-1"></i> {{ $lowStockItems }} low stock, {{ $outOfStockItems }} out of stock
                            </small>
                        </div>
                        <div class="bg-white rounded-circle p-3 text-warning shadow-sm">
                            <i class="fas fa-warehouse fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-1 bg-black-10 border-0">
                    <a href="{{ route('reports.current-stock') }}" class="text-dark text-xs font-weight-bold d-flex justify-content-between align-items-center">
                        <span>Current Stock Ledger</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Charts Row 1: 30-Day Trend & Category Breakdown --}}
    @if(($activeWidgets['revenue_trend'] ?? true) || ($activeWidgets['category_share'] ?? true))
    <div class="row">
        @if($activeWidgets['revenue_trend'] ?? true)
        <div class="{{ ($activeWidgets['category_share'] ?? true) ? 'col-lg-8' : 'col-12' }} mb-4">
            <div class="card card-outline card-primary shadow-sm h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-chart-area text-primary mr-2"></i> 30-Day Sales & Revenue Trend
                    </h3>
                    <span class="text-muted text-xs">Daily gross turnover</span>
                </div>
                <div class="card-body pt-0">
                    <div style="position: relative; height: 310px;">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($activeWidgets['category_share'] ?? true)
        <div class="{{ ($activeWidgets['revenue_trend'] ?? true) ? 'col-lg-4' : 'col-12' }} mb-4">
            <div class="card card-outline card-info shadow-sm h-100">
                <div class="card-header border-0">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-chart-pie text-info mr-2"></i> Sales by Category (MTD)
                    </h3>
                </div>
                <div class="card-body pt-0">
                    @if (empty($categoryLabels))
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-tags fa-3x mb-2 text-light"></i>
                            <p class="mb-0">No categorized sales recorded this month.</p>
                        </div>
                    @else
                        <div style="position: relative; height: 260px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Charts Row 2: Top Items & Today's Hourly Velocity --}}
    @if($activeWidgets['top_items'] ?? true)
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card card-outline card-success shadow-sm h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-trophy text-warning mr-2"></i> Top 10 Fast-Moving Products
                    </h3>
                    <span class="text-muted text-xs">By Units Sold (Last 30 Days)</span>
                </div>
                <div class="card-body pt-0">
                    @if (empty($topItemLabels))
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-box fa-3x mb-2 text-light"></i>
                            <p class="mb-0">No item movement recorded in this period.</p>
                        </div>
                    @else
                        <div style="position: relative; height: 280px;">
                            <canvas id="topItemsChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card card-outline card-warning shadow-sm h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-clock text-warning mr-2"></i> Today's Hourly Sales Velocity
                    </h3>
                    <span class="text-muted text-xs">Peak Shopping Hours</span>
                </div>
                <div class="card-body pt-0">
                    <div style="position: relative; height: 280px;">
                        <canvas id="hourlyVelocityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Operational Row 3: Active Till Sessions & Recent Transactions --}}
    <div class="row">
        {{-- Active Till Session Monitor --}}
        <div class="{{ ($activeWidgets['recent_bills'] ?? true) ? 'col-lg-5' : 'col-12' }} mb-4">
            <div class="card card-outline card-secondary shadow-sm h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-cash-register text-success mr-2"></i> Active Till Shifts
                    </h3>
                    <a href="{{ route('reports.eod') }}" class="btn btn-xs btn-outline-primary">
                        EOD Summary <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 text-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>Register</th>
                                    <th>Cashier</th>
                                    <th>Opened At</th>
                                    <th class="text-right">Opening Cash</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activeTills as $till)
                                    <tr>
                                        <td class="font-weight-bold">
                                            {{ $till->register?->name ?? 'Register #' . $till->register_id }}
                                            <br>
                                            <small class="text-muted">{{ $till->branch?->name }}</small>
                                        </td>
                                        <td>{{ $till->user?->name ?? 'Cashier' }}</td>
                                        <td>{{ $till->opened_at ? $till->opened_at->format('h:i A') : '-' }}</td>
                                        <td class="text-right font-weight-bold">₹{{ number_format($till->opening_cash, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-dot-circle mr-1"></i> Open</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-lock fa-2x mb-2 text-light d-block"></i>
                                            No registers currently open for this scope.
                                            <div class="mt-2">
                                                <a href="{{ route('sales.sales-bills.create') }}" class="btn btn-xs btn-outline-primary">Go to POS Counter</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Sales Bills & Open Quotations --}}
        @if($activeWidgets['recent_bills'] ?? true)
        <div class="col-lg-7 mb-4">
            <div class="card card-outline card-primary shadow-sm h-100">
                <div class="card-header border-0 p-2">
                    <ul class="nav nav-pills p-0">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" href="#recent-bills-tab" data-toggle="tab">
                                <i class="fas fa-receipt mr-1"></i> Recent Sales Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" href="#open-quotes-tab" data-toggle="tab">
                                <i class="fas fa-file-invoice mr-1"></i> Open Quotations ({{ $openQuotationsCount }})
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        {{-- Recent Sales Bills --}}
                        <div class="tab-pane active" id="recent-bills-tab">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 text-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Bill No</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th class="text-right">Amount (₹)</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentBills as $bill)
                                            <tr>
                                                <td class="font-weight-bold text-primary">
                                                    <a href="{{ route('sales.sales-bills.show', $bill) }}">{{ $bill->bill_number }}</a>
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($bill->bill_date)->format('d M') }}</td>
                                                <td>{{ $bill->customer?->name ?? 'Walk-in Customer' }}</td>
                                                <td class="text-right font-weight-bold">₹{{ number_format($bill->total, 2) }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('sales.sales-bills.receipt', $bill) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Thermal Print">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <a href="{{ route('sales.sales-bills.show', $bill) }}" class="btn btn-xs btn-outline-primary" title="View Bill">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">No sales bills created yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Open Quotations --}}
                        <div class="tab-pane" id="open-quotes-tab">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 text-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Quotation No</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th class="text-right">Amount (₹)</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentQuotations as $quote)
                                            <tr>
                                                <td class="font-weight-bold text-info">
                                                    <a href="{{ route('sales.sales-quotations.show', $quote) }}">{{ $quote->quotation_number }}</a>
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($quote->quotation_date)->format('d M') }}</td>
                                                <td>{{ $quote->customer?->name ?? 'Customer' }}</td>
                                                <td class="text-right font-weight-bold">₹{{ number_format($quote->total, 2) }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('sales.sales-bills.create', ['from_quotation' => $quote->id]) }}" class="btn btn-xs btn-success font-weight-bold" title="Convert to Sales Bill">
                                                        <i class="fas fa-arrow-right mr-1"></i> Convert
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">No open quotations pending.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Dashboard Personalization Modal --}}
    @include('components.dashboard-customizer-modal')
@stop

@section('css')
<style>
.bg-black-10 {
    background-color: rgba(0, 0, 0, 0.1);
}
.text-white-50 {
    color: rgba(255, 255, 255, 0.75) !important;
}
.text-black-50 {
    color: rgba(0, 0, 0, 0.6) !important;
}

/* Dynamic Responsive Auto-Fill Grid (Layout-Safe) */
.dashboard-shortcut-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
    gap: 12px;
    width: 100%;
}
.shortcut-tile {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 84px;
    border-radius: 8px;
    text-align: center;
    padding: 10px 8px;
    text-decoration: none !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.shortcut-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.12) !important;
}
.shortcut-tile .shortcut-title {
    font-size: 13px;
    line-height: 1.25;
    margin-top: 4px;
}
/* Dashboard Search Keyboard Selection */
#dashboardSearchResults .dropdown-item.is-selected {
    background-color: #1769E8 !important;
    color: #ffffff !important;
}
#dashboardSearchResults .dropdown-item.is-selected .text-dark,
#dashboardSearchResults .dropdown-item.is-selected .text-muted {
    color: rgba(255, 255, 255, 0.9) !important;
}
#dashboardSearchResults .dropdown-item.is-selected .icon-container {
    background-color: rgba(255, 255, 255, 0.2) !important;
}
#dashboardSearchResults .dropdown-item.is-selected .icon-container i {
    color: #ffffff !important;
}
@media (max-width: 576px) {
    .dashboard-shortcut-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
}
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dashboard Search Logic
    const searchInput = document.getElementById('dashboardSearchInput');
    const searchResults = document.getElementById('dashboardSearchResults');

    if (searchInput && searchResults) {
        const links = [];
        let activeIndex = -1;

        function updateSelection(items) {
            items.forEach((item, idx) => {
                if (idx === activeIndex) {
                    item.classList.add('is-selected');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('is-selected');
                }
            });
        }

        document.querySelectorAll('.nav-sidebar .nav-item > .nav-link').forEach(link => {
            const url = link.getAttribute('href');
            if (!url || url === '#' || url === 'javascript:void(0)') return;

            const textEl = link.querySelector('p') || link;
            let text = textEl.textContent.trim();
            const badge = link.querySelector('.badge');
            if (badge) {
                text = text.replace(badge.textContent.trim(), '').trim();
            }

            const iconEl = link.querySelector('i.nav-icon');
            const iconClass = iconEl ? iconEl.className : 'far fa-circle text-muted';

            if (text && url) {
                links.push({ text: text, url: url, icon: iconClass });
            }
        });

        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            searchResults.innerHTML = '';
            activeIndex = -1;

            if (query.length === 0) {
                searchResults.style.display = 'none';
                return;
            }

            const filtered = links.filter(link => link.text.toLowerCase().includes(query));

            if (filtered.length > 0) {
                filtered.forEach((link, idx) => {
                    const a = document.createElement('a');
                    a.className = 'dropdown-item d-flex align-items-center py-2 border-bottom';
                    a.href = link.url;
                    a.setAttribute('data-index', idx);
                    a.innerHTML = `
                        <div class="icon-container rounded bg-light mr-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="${link.icon} text-primary" style="font-size: 1.1rem;"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <span class="font-weight-bold text-dark">${link.text}</span>
                            <small class="text-muted">${link.url.replace(window.location.origin, '')}</small>
                        </div>
                    `;
                    a.addEventListener('mouseenter', function() {
                        activeIndex = idx;
                        const items = searchResults.querySelectorAll('.dropdown-item');
                        updateSelection(items);
                    });
                    searchResults.appendChild(a);
                });
            } else {
                searchResults.innerHTML = `
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-search fa-3x mb-3 text-light"></i>
                        <p class="mb-0 font-weight-bold">No results found for "${query}"</p>
                        <small>Try a different keyword</small>
                    </div>`;
            }

            searchResults.style.display = 'block';
        });

        searchInput.addEventListener('keydown', function(e) {
            const items = searchResults.querySelectorAll('.dropdown-item');
            if (!items || items.length === 0 || searchResults.style.display === 'none') {
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (activeIndex < items.length - 1) {
                    activeIndex++;
                } else {
                    activeIndex = 0; // Wrap around to top
                }
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (activeIndex > 0) {
                    activeIndex--;
                } else {
                    activeIndex = items.length - 1; // Wrap around to bottom
                }
                updateSelection(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && activeIndex < items.length) {
                    items[activeIndex].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
            } else if (e.key === 'Escape') {
                searchResults.style.display = 'none';
                activeIndex = -1;
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
                activeIndex = -1;
            }
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length > 0) {
                searchResults.style.display = 'block';
            }
        });
    }

    // UI 2.0 Font & Color Defaults
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, sans-serif";
    Chart.defaults.color = '#74839B';

    // 1. 30-Day Sales & Revenue Trend Chart
    const trendCtx = document.getElementById('salesTrendChart');
    if (trendCtx) {
        const trendLabels = {!! json_encode($trendLabels) !!};
        const trendRevenue = {!! json_encode($trendRevenue) !!};
        const trendBills = {!! json_encode($trendBills) !!};

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Daily Revenue (₹)',
                        data: trendRevenue,
                        borderColor: '#1769E8', // UI 2.0 Primary Blue
                        backgroundColor: 'rgba(23, 105, 232, 0.12)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#1769E8',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Bills Count',
                        data: trendBills,
                        borderColor: '#078B87', // UI 2.0 Teal
                        backgroundColor: 'transparent',
                        borderWidth: 1.8,
                        borderDash: [4, 4],
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#078B87',
                        tension: 0.2,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (context.datasetIndex === 0) {
                                    return 'Revenue: ₹' + Number(context.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                }
                                return 'Bills: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, maxTicksLimit: 12 }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            font: { size: 10 },
                            callback: function (val) {
                                return '₹' + (val >= 1000 ? (val / 1000).toFixed(0) + 'k' : val);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 10 }, precision: 0 }
                    }
                }
            }
        });
    }

    // 2. Sales by Category Doughnut Chart
    const catCtx = document.getElementById('categoryChart');
    if (catCtx) {
        const catLabels = {!! json_encode($categoryLabels) !!};
        const catAmounts = {!! json_encode($categoryAmounts) !!};

        if (catLabels.length > 0) {
            new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catAmounts,
                        backgroundColor: [
                            '#1769E8', '#078B87', '#F28C28', '#6C3BE8', '#168447', '#74839B'
                        ],
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 11 }, color: '#0B1F52' }
                        },
                        tooltip: {
                            backgroundColor: '#0B1F52',
                            callbacks: {
                                label: function (ctx) {
                                    const val = Number(ctx.raw);
                                    return ' ' + ctx.label + ': ₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // 3. Top 10 Fast-Moving Products Horizontal Bar Chart
    const topCtx = document.getElementById('topItemsChart');
    if (topCtx) {
        const itemLabels = {!! json_encode($topItemLabels) !!};
        const itemQty = {!! json_encode($topItemQty) !!};
        const itemRev = {!! json_encode($topItemRevenue) !!};

        if (itemLabels.length > 0) {
            new Chart(topCtx, {
                type: 'bar',
                data: {
                    labels: itemLabels,
                    datasets: [{
                        label: 'Units Sold',
                        data: itemQty,
                        backgroundColor: 'rgba(23, 105, 232, 0.8)',
                        borderColor: '#1769E8',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0B1F52',
                            callbacks: {
                                afterLabel: function (ctx) {
                                    const idx = ctx.dataIndex;
                                    return 'Revenue: ₹' + Number(itemRev[idx]).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: '#EEF3FA' },
                            ticks: { font: { size: 10 }, precision: 0 }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, color: '#0B1F52' }
                        }
                    }
                }
            });
        }
    }

    // 4. Today's Hourly Sales Velocity Bar Chart
    const hourlyCtx = document.getElementById('hourlyVelocityChart');
    if (hourlyCtx) {
        const hourlyLabels = {!! json_encode($hourlyLabels) !!};
        const hourlyRevenue = {!! json_encode($hourlyRevenue) !!};
        const hourlyBills = {!! json_encode($hourlyBills) !!};

        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Hourly Sales (₹)',
                    data: hourlyRevenue,
                    backgroundColor: 'rgba(7, 139, 135, 0.8)',
                    borderColor: '#078B87',
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0B1F52',
                        callbacks: {
                            label: function (ctx) {
                                return 'Sales: ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            },
                            afterLabel: function (ctx) {
                                return 'Bills: ' + hourlyBills[ctx.dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 10 },
                            callback: function (val) {
                                return '₹' + (val >= 1000 ? (val / 1000).toFixed(0) + 'k' : val);
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@stop
