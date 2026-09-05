@extends('adminlte::page')

@section('title', 'Stock Update')

@section('content_header')
    <h1>Stock Update Entry</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('inventory.stock-updates.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Stock Update
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Update No</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Lines</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockUpdates as $entry)
                        <tr>
                            <td>{{ $entry->update_number }}</td>
                            <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                            <td>{{ $entry->branch?->name }}</td>
                            <td>{{ $entry->items_count ?? $entry->items()->count() }}</td>
                            <td class="text-right">
                                <a href="{{ route('inventory.stock-updates.edit', $entry) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('inventory.stock-updates.destroy', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this stock update? The physical-count adjustment will be reversed.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No stock updates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stockUpdates->links() }}</div>
    </div>
@stop
