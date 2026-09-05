@extends('adminlte::page')

@section('title', 'Add Purchase Invoice')

@section('content_header')
    <h1>Create Purchase Invoice</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-invoices.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-invoices._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
