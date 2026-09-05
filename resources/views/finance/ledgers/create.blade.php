@extends('adminlte::page')

@section('title', 'New Ledger')

@section('content_header')
    <h1>New Ledger</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('finance.ledgers.store') }}" method="POST">
            @csrf
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
