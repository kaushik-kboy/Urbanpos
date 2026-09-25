@extends('adminlte::page')

@section('title', 'Add Sales Bill')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 font-weight-bold text-dark">Create Sales Bill</h1>
        <a href="{{ route('pos.terminal') }}" class="btn btn-success font-weight-bold shadow-sm">
            <i class="fas fa-cash-register mr-1"></i> Launch Modern POS Terminal View
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-bills.store') }}" method="POST" id="sales-bill-form" novalidate>
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('sales.sales-bills._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset</button>
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
