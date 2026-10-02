@extends('adminlte::page')

@section('title', 'Edit Sales Bill')

@section('content_header')
    <h1>Edit Sales Bill "{{ $salesBill->bill_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-bills.update', $salesBill) }}" method="POST" id="sales-bill-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('sales.sales-bills._form')
            </div>
            <div class="card-footer sb-rich-footer py-2 px-3 d-flex justify-content-between align-items-center flex-wrap">
                {{-- Left: Live Items Count & Final Bill Total --}}
                <div class="d-flex align-items-center flex-wrap">
                    <div id="sb-total-items-badge" class="d-inline-block mr-3">
                        <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">0 Items</span>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <span class="text-muted font-weight-bold mr-1" style="font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.5px;">Final Total:</span>
                        <span class="text-success font-weight-bold" style="font-size: 1.35rem; line-height: 1;">₹<span id="display-sb-final-total">0.00</span></span>
                    </div>
                </div>

                {{-- Right: Actions (Reset, Cancel, Save) --}}
                <div class="d-flex align-items-center">
                    <button type="button" id="btn-reset-form" class="btn btn-warning btn-sm font-weight-bold btn-reset-form mr-2 shadow-xs">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2 shadow-xs">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm" id="sb-main-save-btn">
                        <i class="fas fa-check-circle mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop

@section('footer')
    <!-- Sales Bill Footer Suppressed -->
@stop
