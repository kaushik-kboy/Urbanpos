@extends('adminlte::page')

@section('title', 'Edit Stock Update')

@section('content_header')
    <h1>Edit Stock Update "{{ $stockUpdate->update_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-updates.update', $stockUpdate) }}" method="POST" id="stock-update-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('inventory.stock-updates._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-su-total-qty"
                total-label="Total Physical Qty:"
                items-badge-id="su-total-items-badge"
                save-btn-id="su-main-save-btn"
                save-btn-text="Update Stock Update"
                cancel-route="{{ route('inventory.stock-updates.index') }}"
            />
        </form>
    </div>
@stop
