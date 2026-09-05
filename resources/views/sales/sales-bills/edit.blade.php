@extends('adminlte::page')

@section('title', 'Edit Sales Bill')

@section('content_header')
    <h1>Edit Sales Bill "{{ $salesBill->bill_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-bills.update', $salesBill) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('sales.sales-bills._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
