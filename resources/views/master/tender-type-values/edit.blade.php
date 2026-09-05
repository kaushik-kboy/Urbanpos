@extends('adminlte::page')

@section('title', 'Edit Tender Type Value')

@section('content_header')
    <h1>Edit Tender Type Value "{{ $tenderTypeValue->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.tender-type-values.update', $tenderTypeValue) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.tender-type-values._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.tender-type-values.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
