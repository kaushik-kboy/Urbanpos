@extends('adminlte::page')

@section('title', 'Items')

@section('content_header')
    <h1>Item</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.items.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Item
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Alias</th>
                        <th>Sell Price</th>
                        <th>Supplier</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->alias }}</td>
                            <td>{{ number_format($item->sell_price, 2) }}</td>
                            <td>{{ $item->supplier?->name }}</td>
                            <td>{{ $item->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.items.edit', $item) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $items->links() }}</div>
    </div>
@stop
