@extends('adminlte::page')

@section('title', 'Add Sales Bill')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-file-invoice mr-1 text-primary"></i> Create Sales Bill</h1>
        <a href="{{ route('pos.terminal') }}" class="btn btn-success btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-cash-register mr-1"></i> Modern POS Terminal
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('sales.sales-bills.store') }}" method="POST" id="sales-bill-form" novalidate>
            @csrf
            <div class="card-body py-2 px-3">
                <x-error-summary />
                @include('sales.sales-bills._form')
            </div>
            <div class="card-footer py-2 px-3">
                <button type="submit" class="btn btn-primary disabled font-weight-bold px-3" disabled id="sb-main-save-btn" title="Please select a Customer and add at least 1 item">Save</button>
                <button type="button" id="btn-reset-form" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset Form</button>
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
