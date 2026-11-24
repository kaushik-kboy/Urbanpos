@extends('adminlte::page')

@section('title', 'Edit Purchase Invoice')

@section('plugins.Sweetalert2', true)

@section('classes_body', 'sidebar-mini sidebar-collapse')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="m-0 h3 font-weight-bold text-dark text-truncate mr-2">Edit Purchase Invoice "{{ $purchaseInvoice->invoice_number }}"</h1>
        <div class="my-1">
            <a href="{{ route('purchase.purchase-invoices.index', request()->query() ?: session('purchase_invoices_filters', [])) }}" class="btn btn-secondary btn-sm font-weight-bold mr-2">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
            <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $purchaseInvoice->id, 'format' => '50x38_2up']) }}" target="_blank" class="btn btn-warning btn-sm font-weight-bold shadow-sm mr-2">
                <i class="fas fa-barcode mr-1"></i> Print Stickers (TSC TE244)
            </a>
            <a href="{{ route('purchase.purchase-invoices.print', $purchaseInvoice) }}" target="_blank" class="btn btn-outline-secondary btn-sm font-weight-bold">
                <i class="fas fa-print mr-1"></i> Print Invoice
            </a>
        </div>
    </div>
@stop

@section('content')
    <script>
        document.body.classList.add('sidebar-collapse');
    </script>
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-invoices.update', $purchaseInvoice) }}" method="POST" id="pinv-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-invoices._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-pinv-final-total"
                items-badge-id="pinv-total-items-badge"
                save-btn-id="pinv-main-save-btn"
                save-btn-text="Update Invoice"
                cancel-route="{{ route('purchase.purchase-invoices.index', request()->query() ?: session('purchase_invoices_filters', [])) }}"
                reset-btn-id="btn-reset-form"
            >
                <x-slot:customLeft>
                    <div id="final-amount-match-badge" class="d-inline-block ml-2"></div>
                </x-slot:customLeft>
                <x-slot:extraActions>
                    <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $purchaseInvoice->id, 'format' => '50x38_2up']) }}" target="_blank" class="btn btn-warning btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-barcode mr-1"></i> Print Stickers
                    </a>
                </x-slot:extraActions>
            </x-transaction-rich-footer>
        </form>
    </div>
@stop
