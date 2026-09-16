@extends('adminlte::page')

@section('title', 'Create Purchase Return')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-undo-alt mr-2 text-warning"></i>New Purchase Return</h1>
        <a href="{{ route('purchase.purchase-returns.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <h6 class="font-weight-bold mb-1"><i class="fas fa-exclamation-circle mr-1"></i> Please correct the following errors:</h6>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm">
        <form action="{{ route('purchase.purchase-returns.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('purchase.purchase-returns._form')
            </div>
            <div class="card-footer bg-light d-flex justify-content-between">
                <a href="{{ route('purchase.purchase-returns.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4 font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Save Purchase Return
                </button>
            </div>
        </form>
    </div>
@stop
