@extends('adminlte::page')

@section('title', 'New Stock Transfer')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Dispatch Stock Transfer</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-transfers.store') }}" method="POST" id="transfer-form">
            @csrf
            <input type="hidden" name="posting_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="card-body">
                <x-error-summary />
                @include('inventory.stock-transfers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Dispatch</button>
                <a href="{{ route('inventory.stock-transfers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
