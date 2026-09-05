@extends('adminlte::page')

@section('title', 'Edit Purchase Order')

@section('content_header')
    <h1>Edit Purchase Order "{{ $purchaseOrder->po_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-orders.update', $purchaseOrder) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-orders._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
