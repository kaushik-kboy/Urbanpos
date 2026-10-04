@extends('adminlte::page')

@section('title', 'New Stock Transfer')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Dispatch Stock Transfer</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-transfers.store') }}" method="POST" id="transfer-form" novalidate>
            @csrf
            <input type="hidden" name="posting_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="card-body">
                <x-error-summary />
                @include('inventory.stock-transfers._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-st-total-cost"
                items-badge-id="st-total-items-badge"
                save-btn-id="st-main-save-btn"
                save-btn-text="Dispatch Transfer"
                cancel-route="{{ route('inventory.stock-transfers.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop
