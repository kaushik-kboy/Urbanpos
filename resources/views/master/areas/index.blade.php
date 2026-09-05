@extends('adminlte::page')

@section('title', 'Areas')

@section('content_header')
    <h1>Area</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.areas.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Area
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Branch</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td>{{ $area->name }}</td>
                            <td>{{ $area->branch?->name ?? 'GLOBAL' }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.areas.edit', $area) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.areas.destroy', $area) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this area?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No areas yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $areas->links() }}</div>
    </div>
@stop
