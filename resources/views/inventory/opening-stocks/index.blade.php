@extends('adminlte::page')

@section('title', 'Opening Stock')

@section('content_header')
    <h1>Opening Stock Entry</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('inventory.opening-stocks.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Opening Stock
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Entry No</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Total Qty</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($openingStocks as $entry)
                        <tr>
                            <td>{{ $entry->entry_number }}</td>
                            <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                            <td>{{ $entry->branch?->name }}</td>
                            <td>{{ $entry->total_qty }}</td>
                            <td>{{ number_format($entry->total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('inventory.opening-stocks.edit', $entry) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('inventory.opening-stocks.destroy', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this opening stock entry? Stock will be reversed.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No opening stock entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $openingStocks->links() }}</div>
    </div>
@stop
