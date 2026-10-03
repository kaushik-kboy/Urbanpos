@extends('adminlte::page')

@section('title', 'Add Sales Bill')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-file-invoice mr-1 text-primary"></i> Create Sales Bill</h1>
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
            <div class="card-footer sb-rich-footer py-2 px-3" style="border-top: 2px solid #dee2e6;">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                    {{-- Left: Live Items Count --}}
                    <div id="sb-total-items-badge" class="d-inline-block">
                        <span class="badge badge-secondary px-3 py-2 font-weight-bold" style="font-size: 0.95rem; line-height: 1.4;">0 Items</span>
                    </div>

                    {{-- Right: Final Bill Total --}}
                    <div class="d-flex align-items-baseline">
                        <span class="text-muted font-weight-bold mr-2" style="font-size: 0.92rem; text-transform: uppercase; letter-spacing: 0.5px;">FINAL TOTAL:</span>
                        <span class="text-success font-weight-bold" style="font-size: 1.55rem; line-height: 1;">₹<span id="display-sb-final-total">0.00</span></span>
                    </div>
                </div>

                {{-- Hidden buttons still needed for fallback/accessibility --}}
                <button type="button" id="btn-reset-form" class="btn btn-warning btn-sm font-weight-bold btn-reset-form d-none">
                    <i class="fas fa-undo mr-1"></i> Reset Form
                </button>
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold d-none" id="btn-cancel-bill">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm d-none" id="sb-main-save-btn" title="Save Bill">
                    <i class="fas fa-check-circle mr-1"></i> Save Bill
                </button>
            </div>
        </form>
    </div>
@stop

@section('footer')
    <!-- Sales Bill Footer Suppressed -->
@stop
