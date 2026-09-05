@extends('adminlte::page')

@section('title', 'Brands')

@section('content_header')
    <h1>Brands</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.brands.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Brand
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Prefix</th>
                        <th>Alias Code</th>
                        <th>Status</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->prefix }}</td>
                            <td>{{ $brand->alias_code }}</td>
                            <td><x-status-badge :active="$brand->status" /></td>
                            <td>{{ $brand->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.brands.edit', $brand) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.brands.destroy', $brand) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this brand?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No brands yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $brands->links() }}</div>
    </div>
@stop
