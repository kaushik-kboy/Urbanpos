@extends('adminlte::page')

@section('title', 'Create Sales Quotation')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-file-signature mr-2 text-primary"></i> Create Sales Quotation</h1>
        <a href="{{ route('sales.sales-quotations.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Quotations
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline mb-0">
        <form action="{{ route('sales.sales-quotations.store') }}" method="POST" id="sq-form" novalidate>
            @csrf
            <div class="card-body py-2 px-3">
                <x-error-summary />
                @include('sales.sales-quotations._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-sq-final-total"
                items-badge-id="sq-total-items-badge"
                save-btn-id="sq-main-save-btn"
                save-btn-text="Save Quotation"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('sales.sales-quotations.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

