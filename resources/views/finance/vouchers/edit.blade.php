@extends('adminlte::page')

@section('title', 'Edit Voucher')

@section('content_header')
    <h1>Edit Voucher "{{ $voucher->voucher_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.vouchers.update', $voucher) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('finance.vouchers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('finance.vouchers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
