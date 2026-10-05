@extends('adminlte::page')

@section('title', 'Edit Voucher')

@section('content_header')
    <h1>Edit Voucher "{{ $voucher->voucher_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.vouchers.update', $voucher) }}" method="POST" id="voucher-form">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('finance.vouchers._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-voucher-total"
                total-label="Voucher Total:"
                items-badge-id="voucher-total-lines-badge"
                save-btn-id="voucher-main-save-btn"
                save-btn-text="Update Voucher"
                cancel-route="{{ route('finance.vouchers.index') }}"
            />
        </form>
    </div>
@stop
