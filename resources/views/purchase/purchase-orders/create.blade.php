@extends('adminlte::page')

@section('title', 'Add Purchase Order')

@section('classes_body', 'sidebar-mini sidebar-collapse')

@section('content_header')
    <h1>Create Purchase Order</h1>
@stop

@section('content')
    <script>
        document.body.classList.add('sidebar-collapse');
    </script>
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-orders.store') }}" method="POST" id="po-form" novalidate>
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-orders._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" id="btn-reset-form" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset Form</button>
                <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
