@extends('adminlte::page')

@section('title', 'Reports')

@section('content_header')
    <h1>Reports</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Sales</h3></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="{{ route('reports.sales-summary') }}">Daily Sales Summary</a></li>
                        <li class="list-group-item"><a href="{{ route('reports.billwise-sales') }}">Billwise Itemwise Sales Detail</a></li>
                        <li class="list-group-item"><a href="{{ route('reports.gst-sales-summary') }}">GST Sales Summary</a></li>
                        <li class="list-group-item"><a href="{{ route('reports.sales-return-summary') }}">Sales Return Summary</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Purchase & Stock</h3></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="{{ route('reports.purchase-detail') }}">Purchase Detail</a></li>
                        <li class="list-group-item"><a href="{{ route('reports.current-stock') }}">Current Stock Branchwise</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Masters / CRM</h3></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="{{ route('reports.customer-master') }}">Customer Master</a></li>
                        <li class="list-group-item"><a href="{{ route('reports.customer-pet-details') }}">Customer Pet Details</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@stop
