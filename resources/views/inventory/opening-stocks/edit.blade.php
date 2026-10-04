@extends('adminlte::page')

@section('title', 'Edit Opening Stock')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Edit Opening Stock "{{ $openingStock->entry_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.opening-stocks.update', $openingStock) }}" method="POST" id="opening-stock-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('inventory.opening-stocks._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-os-final-total"
                items-badge-id="os-total-items-badge"
                save-btn-id="os-main-save-btn"
                save-btn-text="Update Opening Stock"
                cancel-route="{{ route('inventory.opening-stocks.index') }}"
            />
        </form>
    </div>
@stop
