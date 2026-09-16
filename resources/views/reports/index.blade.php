@extends('adminlte::page')

@section('title', 'Reports Center')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-chart-pie mr-2 text-primary"></i> Reports Center</h1>
        <div>
            <a href="{{ route('reports.index') }}" class="btn btn-sm {{ !request('group') ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
            <a href="{{ route('reports.index', ['group' => 'sales']) }}" class="btn btn-sm {{ request('group') === 'sales' ? 'btn-primary' : 'btn-outline-secondary' }}">Sales</a>
            <a href="{{ route('reports.index', ['group' => 'purchase']) }}" class="btn btn-sm {{ request('group') === 'purchase' ? 'btn-primary' : 'btn-outline-secondary' }}">Purchase</a>
            <a href="{{ route('reports.index', ['group' => 'inventory']) }}" class="btn btn-sm {{ request('group') === 'inventory' ? 'btn-primary' : 'btn-outline-secondary' }}">Inventory</a>
            <a href="{{ route('reports.index', ['group' => 'masters']) }}" class="btn btn-sm {{ request('group') === 'masters' ? 'btn-primary' : 'btn-outline-secondary' }}">Masters</a>
            <a href="{{ route('reports.index', ['group' => 'audit']) }}" class="btn btn-sm {{ request('group') === 'audit' ? 'btn-primary' : 'btn-outline-secondary' }}">Audit</a>
            <a href="{{ route('reports.index', ['group' => 'finance']) }}" class="btn btn-sm {{ request('group') === 'finance' ? 'btn-primary' : 'btn-outline-secondary' }}">Finance</a>
        </div>
    </div>
@stop

@section('content')
    @php
        $group = request('group');
    @endphp

    <div class="row">
        {{-- SALES REPORTS --}}
        @if (!$group || $group === 'sales')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-primary h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-primary"><i class="fas fa-shopping-cart mr-2"></i> Sales Reports</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.sales-summary') }}" class="font-weight-bold text-dark">Daily Sales Summary</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.billwise-sales') }}" class="font-weight-bold text-dark">Billwise Itemwise Sales Detail</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.gst-sales-summary') }}" class="font-weight-bold text-dark">GST Sales Summary</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.sales-return-summary') }}" class="font-weight-bold text-dark">Sales Return Summary</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.tender-summary') }}" class="font-weight-bold text-dark">Tender / Payment Mode Summary</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.eod') }}" class="font-weight-bold text-dark">EOD / Settlement Report</a>
                                <span class="badge badge-primary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- PURCHASE REPORTS --}}
        @if (!$group || $group === 'purchase')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-success h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-success"><i class="fas fa-truck mr-2"></i> Purchase Reports</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.purchase-detail') }}" class="font-weight-bold text-dark">Purchase Invoice Detail</a>
                                <span class="badge badge-success badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.gst-purchase-summary') }}" class="font-weight-bold text-dark">GST Purchase Summary (ITC)</a>
                                <span class="badge badge-success badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.purchase-order-summary') }}" class="font-weight-bold text-dark">Purchase Order Summary</a>
                                <span class="badge badge-success badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- INVENTORY & STOCK REPORTS --}}
        @if (!$group || $group === 'inventory')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-info h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-info"><i class="fas fa-boxes mr-2"></i> Inventory & Stock</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.current-stock') }}" class="font-weight-bold text-dark">Current Stock Branchwise</a>
                                <span class="badge badge-info badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.stock-transfer-summary') }}" class="font-weight-bold text-dark">Stock Transfer Summary</a>
                                <span class="badge badge-info badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.damage-stock-summary') }}" class="font-weight-bold text-dark">Damage / Wastage Stock Report</a>
                                <span class="badge badge-info badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- MASTERS REPORTS --}}
        @if (!$group || $group === 'masters')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-warning h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-warning"><i class="fas fa-database mr-2"></i> Masters Reports</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.item-master') }}" class="font-weight-bold text-dark">Item Master Report</a>
                                <span class="badge badge-warning badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.supplier-master') }}" class="font-weight-bold text-dark">Supplier Master Report</a>
                                <span class="badge badge-warning badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.customer-master') }}" class="font-weight-bold text-dark">Customer Master Report</a>
                                <span class="badge badge-warning badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.customer-pet-details') }}" class="font-weight-bold text-dark">Customer Pet Details</a>
                                <span class="badge badge-warning badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- AUDIT REPORTS --}}
        @if (!$group || $group === 'audit')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-danger h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-danger"><i class="fas fa-shield-alt mr-2"></i> Audit Reports</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('reports.audit-logs') }}" class="font-weight-bold text-dark">Audit Activity Log Viewer</a>
                                <span class="badge badge-danger badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- FINANCE REPORTS --}}
        @if (!$group || $group === 'finance')
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card card-outline card-secondary h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title font-weight-bold text-secondary"><i class="fas fa-book mr-2"></i> Finance & Accounts</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('finance.reports.day-book') }}" class="font-weight-bold text-dark">Day Book</a>
                                <span class="badge badge-secondary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('finance.reports.general-ledger') }}" class="font-weight-bold text-dark">General Ledger</a>
                                <span class="badge badge-secondary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('finance.reports.trial-balance') }}" class="font-weight-bold text-dark">Trial Balance</a>
                                <span class="badge badge-secondary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('finance.reports.profit-loss') }}" class="font-weight-bold text-dark">Trading - Profit & Loss Statement</a>
                                <span class="badge badge-secondary badge-pill"><i class="fas fa-arrow-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop
