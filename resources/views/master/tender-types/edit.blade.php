@extends('adminlte::page')

@section('title', 'Edit Tender Type')

@section('content_header')
    <h1>Edit Tender Type "{{ $tenderType->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.tender-types.update', $tenderType) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.tender-types._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.tender-types.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
