@extends('adminlte::page')

@section('title', 'Edit Loyalty Program')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-0"><i class="fas fa-edit text-primary mr-2"></i>Edit Loyalty Program</h1>
            <small class="text-muted">Modify parameters for <strong>{{ $program->name }}</strong></small>
        </div>
        <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-default">
            <i class="fas fa-arrow-left mr-1"></i>Back to Programs
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm">
        <form action="{{ route('master.loyalty-programs.update', $program) }}" method="POST">
            @csrf
            @method('PUT')
            @include('master.loyalty-programs._form')
        </form>
    </div>
@stop
