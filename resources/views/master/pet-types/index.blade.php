@extends('adminlte::page')

@section('title', 'Pet Types')

@section('content_header')
    <h1>Pet Types</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.pet-types.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Pet Type
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($petTypes as $petType)
                        <tr>
                            <td>{{ $petType->name }}</td>
                            <td><x-status-badge :active="$petType->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.pet-types.edit', $petType) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.pet-types.destroy', $petType) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this pet type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No pet types yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $petTypes->links() }}</div>
    </div>
@stop
