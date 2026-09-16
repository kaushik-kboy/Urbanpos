@extends('adminlte::page')

@section('title', 'Finance Reports')

@section('content_header')
    <h1>Finance Reports</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><a href="{{ route('finance.reports.general-ledger') }}"><i class="fas fa-book mr-2 text-primary"></i> General Ledger</a></li>
                <li class="list-group-item"><a href="{{ route('finance.reports.day-book') }}"><i class="fas fa-calendar-day mr-2 text-info"></i> Day Book</a></li>
                <li class="list-group-item"><a href="{{ route('finance.reports.trial-balance') }}"><i class="fas fa-balance-scale mr-2 text-warning"></i> Trial Balance</a></li>
                <li class="list-group-item"><a href="{{ route('finance.reports.profit-loss') }}"><i class="fas fa-chart-line mr-2 text-success"></i> Trading - Profit & Loss Statement</a></li>
            </ul>
        </div>
    </div>
@stop
