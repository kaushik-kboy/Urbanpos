@extends('adminlte::page')

@section('title', 'Add Purchase Order')

@section('content_header')
    <h1>Create Purchase Order</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-orders.store') }}" method="POST">
            @csrf
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
