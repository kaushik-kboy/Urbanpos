@extends('adminlte::page')

@section('title', 'Edit Sales Return')

@section('classes_body', 'sidebar-mini sidebar-collapse tx-viewport-fixed')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-undo mr-1 text-primary"></i> Edit Sales Return: <span class="text-primary">{{ $salesReturn->return_number }}</span></h1>
        <a href="{{ route('sales.sales-returns.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Returns
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('sales.sales-returns.update', $salesReturn) }}" method="POST" id="sr-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body py-1 px-3">
                <x-error-summary />
                @include('sales.sales-returns._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-sr-final-total"
                total-label="Net Return Amount:"
                items-badge-id="sr-total-items-badge"
                save-btn-id="sr-main-save-btn"
                save-btn-text="Save Return"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('sales.sales-returns.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

@section('footer')
    <!-- Suppressed for 1-page layout -->
@stop

