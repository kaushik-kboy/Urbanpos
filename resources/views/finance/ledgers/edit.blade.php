@extends('adminlte::page')

@section('title', 'Edit Ledger')

@section('content_header')
    <h1>Edit Ledger "{{ $ledger->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.ledgers.update', $ledger) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('finance.ledgers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('finance.ledgers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
