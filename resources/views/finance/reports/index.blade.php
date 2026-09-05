@extends('adminlte::page')

@section('title', 'Finance Reports')

@section('content_header')
    <h1>Finance Reports</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><a href="{{ route('finance.reports.general-ledger') }}">General Ledger</a></li>
                <li class="list-group-item"><a href="{{ route('finance.reports.day-book') }}">Day Book</a></li>
                <li class="list-group-item"><a href="{{ route('finance.reports.trial-balance') }}">Trial Balance</a></li>
            </ul>
        </div>
    </div>
@stop
