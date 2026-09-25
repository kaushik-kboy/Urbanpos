@extends('adminlte::page')

@section('title', 'Create Sales Order')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 text-dark"><i class="fas fa-shopping-basket mr-2 text-primary"></i>Create Sales Order</h1>
        <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('sales.sales-orders.store') }}" method="POST" id="so-form" novalidate>
        @csrf
        <div class="card card-primary card-outline">
            <div class="card-body">
                @include('sales.sales-orders._form')
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-default mr-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Save Order
                </button>
            </div>
        </div>
    </form>
@stop
