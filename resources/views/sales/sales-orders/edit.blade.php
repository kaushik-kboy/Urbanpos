@extends('adminlte::page')

@section('title', 'Edit Sales Order')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 text-dark">
            <i class="fas fa-edit mr-2 text-primary"></i>Edit Sales Order: <span class="text-primary">{{ $salesOrder->order_number }}</span>
        </h1>
        <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('sales.sales-orders.update', $salesOrder) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card card-primary card-outline">
            <div class="card-body">
                @include('sales.sales-orders._form')
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-default mr-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Update Order
                </button>
            </div>
        </div>
    </form>
@stop
