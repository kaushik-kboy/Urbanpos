@extends('adminlte::page')

@section('title', 'New Voucher')

@section('content_header')
    <h1>Voucher Entry</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.vouchers.store') }}" method="POST">
            @csrf
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
