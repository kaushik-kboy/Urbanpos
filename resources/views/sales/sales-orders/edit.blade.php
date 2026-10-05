@extends('adminlte::page')

@section('title', 'Edit Sales Order')

@section('classes_body', 'sidebar-mini sidebar-collapse tx-viewport-fixed')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5">
            <i class="fas fa-edit mr-2 text-primary"></i> Edit Sales Order: <span class="text-primary">{{ $salesOrder->order_number }}</span>
        </h1>
        <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('sales.sales-orders.update', $salesOrder) }}" method="POST" id="so-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body py-1 px-3">
                <x-error-summary />
                @include('sales.sales-orders._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-so-final-total"
                total-label="Grand Total:"
                items-badge-id="so-total-items-badge"
                save-btn-id="so-main-save-btn"
                save-btn-text="Update Order"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('sales.sales-orders.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

@section('footer')
    <!-- Suppressed for 1-page layout -->
@stop

