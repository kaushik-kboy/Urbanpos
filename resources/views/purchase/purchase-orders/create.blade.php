@extends('adminlte::page')

@section('title', 'Add Purchase Order')

@section('classes_body', 'sidebar-mini sidebar-collapse')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-file-invoice text-primary mr-1"></i> Create Purchase Order</h1>
        <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>
@stop

@section('content')
    <script>
        document.body.classList.add('sidebar-collapse');
    </script>
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('purchase.purchase-orders.store') }}" method="POST" id="po-form" novalidate>
            @csrf
            <div class="card-body py-2 px-3">
                <x-error-summary />
                @include('purchase.purchase-orders._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-po-final-total"
                items-badge-id="po-total-items-badge"
                save-btn-id="po-main-save-btn"
                save-btn-text="Save PO"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('purchase.purchase-orders.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

