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
            <div class="card-footer sb-rich-footer py-2 px-3 d-flex justify-content-between align-items-center flex-wrap">
                {{-- Left: Live Items Count, Final Bill Total & Keyboard Shortcuts --}}
                <div class="d-flex align-items-center flex-wrap">
                    <div id="sb-total-items-badge" class="d-inline-block mr-3">
                        <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">0 Items</span>
                    </div>
                    <div class="d-flex align-items-baseline mr-3">
                        <span class="text-muted font-weight-bold mr-1" style="font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.5px;">Final Total:</span>
                        <span class="text-success font-weight-bold" style="font-size: 1.35rem; line-height: 1;">₹<span id="display-sb-final-total">0.00</span></span>
                    </div>
                    <div class="d-none d-lg-flex align-items-center text-muted pl-2 border-left" style="font-size: 0.78rem;">
                        <span class="mr-2"><kbd class="bg-white text-dark border px-1 shadow-xs">F2</kbd> Search Item</span>
                        <span class="mr-2"><kbd class="bg-white text-dark border px-1 shadow-xs">Tab</kbd> Next Field</span>
                        <span><kbd class="bg-white text-dark border px-1 shadow-xs">Enter</kbd> Confirm</span>
                    </div>
                </div>

                {{-- Right: Actions (Reset, Cancel, Save) --}}
                <div class="d-flex align-items-center">
                    <button type="button" id="btn-reset-form" class="btn btn-warning btn-sm font-weight-bold btn-reset-form mr-2 shadow-xs">
                        <i class="fas fa-undo mr-1"></i> Reset Form
                    </button>
                    <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2 shadow-xs">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm disabled" disabled id="sb-main-save-btn" title="Please select a Customer and add at least 1 item">
                        <i class="fas fa-check-circle mr-1"></i> Save Bill
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop
