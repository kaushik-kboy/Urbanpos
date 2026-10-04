@extends('adminlte::page')

@section('title', 'Create Sales Order')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-shopping-basket mr-2 text-primary"></i> Create Sales Order</h1>
        <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('sales.sales-orders.store') }}" method="POST" id="so-form" novalidate>
            @csrf
            <div class="card-body py-2 px-3">
                <x-error-summary />
                @include('sales.sales-orders._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-so-final-total"
                items-badge-id="so-total-items-badge"
                save-btn-id="so-main-save-btn"
                save-btn-text="Save Order"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('sales.sales-orders.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

