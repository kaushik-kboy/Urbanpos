@extends('adminlte::page')

@section('title', 'Edit Purchase Invoice')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="m-0">Edit Purchase Invoice "{{ $purchaseInvoice->invoice_number }}"</h1>
        <div class="my-1">
            <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $purchaseInvoice->id, 'format' => '50x25_2up']) }}" target="_blank" class="btn btn-warning font-weight-bold shadow-sm mr-2">
                <i class="fas fa-barcode mr-1"></i> Print Stickers (TSC TE244)
            </a>
            <a href="{{ route('purchase.purchase-invoices.print', $purchaseInvoice) }}" target="_blank" class="btn btn-outline-secondary font-weight-bold">
                <i class="fas fa-print mr-1"></i> Print Invoice
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-invoices.update', $purchaseInvoice) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-invoices._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset</button>
                <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-default">Cancel</a>
                <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $purchaseInvoice->id, 'format' => '50x25_2up']) }}" target="_blank" class="btn btn-warning float-right font-weight-bold shadow-sm">
                    <i class="fas fa-barcode mr-1"></i> Print Stickers (TSC TE244)
                </a>
            </div>
        </form>
    </div>
@stop
