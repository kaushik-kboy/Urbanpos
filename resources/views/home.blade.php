@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($todaySales, 2) }}</h3>
                    <p>Today's Sales</p>
                </div>
                <div class="icon"><i class="fas fa-cash-register"></i></div>
                <a href="{{ route('reports.sales-summary') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($monthSales, 2) }}</h3>
                    <p>This Month Sales</p>
                </div>
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <a href="{{ route('reports.sales-summary') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($monthPurchase, 2) }}</h3>
                    <p>This Month Purchase</p>
                </div>
                <div class="icon"><i class="fas fa-truck-loading"></i></div>
                <a href="{{ route('reports.purchase-detail') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ number_format($monthReturns, 2) }}</h3>
                    <p>This Month Returns</p>
                </div>
                <div class="icon"><i class="fas fa-undo"></i></div>
                <a href="{{ route('reports.sales-return-summary') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ number_format($stockValue, 2) }}</h3>
                    <p>Current Stock Value (at cost)</p>
                </div>
                <div class="icon"><i class="fas fa-warehouse"></i></div>
                <a href="{{ route('reports.current-stock') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-dark">
                <div class="inner">
                    <h3>{{ $lowStockItems }}</h3>
                    <p>Items Out of Stock</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <a href="{{ route('reports.current-stock') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalCustomers }}</h3>
                    <p>Total Customers</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
                <a href="{{ route('reports.customer-master') }}" class="small-box-footer">View Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $totalItems }}</h3>
                    <p>Active Items</p>
                </div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
                <a href="{{ route('master.items.index') }}" class="small-box-footer">View Items <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">Recent Sales Bills</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentBills as $bill)
                        <tr>
                            <td>{{ $bill->bill_number }}</td>
                            <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                            <td>{{ $bill->customer?->name }}</td>
                            <td>{{ $bill->branch?->name }}</td>
                            <td class="text-right">{{ number_format($bill->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No sales bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
