@extends('adminlte::page')

@section('title', 'New Voucher')

@section('content_header')
    <h1>Voucher Entry</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.vouchers.store') }}" method="POST" id="voucher-form">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('finance.vouchers._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-voucher-total"
                total-label="Voucher Total:"
                items-badge-id="voucher-total-lines-badge"
                save-btn-id="voucher-main-save-btn"
                save-btn-text="Save Voucher"
                cancel-route="{{ route('finance.vouchers.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop
