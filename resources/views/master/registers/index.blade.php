@extends('adminlte::page')

@section('title', 'Registers')

@section('content_header')
    <h1>Register</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.registers.import')" :sample-route="route('master.registers.import-sample')" title="Register" />
            <a href="{{ route('master.registers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Register
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Register Name</th>
                        <th>Branch Name</th>
                        <th>Product Type</th>
                        <th>Created Date</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registers as $register)
                        <tr>
                            <td>{{ $register->name }}</td>
                            <td>{{ $register->branch?->name }}</td>
                            <td>{{ $register->product_type }}</td>
                            <td>{{ $register->created_at->format('d-m-Y') }}</td>
                            <td>{{ $register->status }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.registers.edit', $register) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.registers.destroy', $register) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this register?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No registers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$registers->perPage()" />
            {{ $registers->appends(request()->query())->links() }}
        </div>
    </div>
@stop
