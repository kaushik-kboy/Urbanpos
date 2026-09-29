@extends('adminlte::page')

@section('title', 'Add Purchase Invoice')

@section('classes_body', 'sidebar-mini sidebar-collapse')

@section('content_header')
    <h1>Create Purchase Invoice</h1>
@stop

@section('content')
    <script>
        document.body.classList.add('sidebar-collapse');
    </script>
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-invoices.store') }}" method="POST" id="pinv-form" novalidate>
            <input type="hidden" name="posting_key" value="{{ old('posting_key', (string) \Illuminate\Support\Str::uuid()) }}">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-invoices._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" id="btn-reset-form" class="btn btn-warning btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset Form</button>
                <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
