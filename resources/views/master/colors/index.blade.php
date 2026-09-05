@extends('adminlte::page')

@section('title', 'Colors')

@section('content_header')
    <h1>Color Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.colors.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Color
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
                    @forelse ($colors as $color)
                        <tr>
                            <td>{{ $color->name }}</td>
                            <td><x-status-badge :active="$color->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.colors.edit', $color) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.colors.destroy', $color) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this color?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No colors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $colors->links() }}</div>
    </div>
@stop
