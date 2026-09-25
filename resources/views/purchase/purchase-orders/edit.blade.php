@extends('adminlte::page')

@section('title', 'Edit Purchase Order')

@section('content_header')
    <h1>Edit Purchase Order "{{ $purchaseOrder->po_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-orders.update', $purchaseOrder) }}" method="POST" id="po-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-orders._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset</button>
                <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
