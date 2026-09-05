@extends('adminlte::page')

@section('title', 'Damage Stock')

@section('content_header')
    <h1>Damage Stock Entry</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('inventory.damage-stocks.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> New Damage Stock
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Damage No</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Total Qty</th>
                        <th>Total Cost</th>
                        <th>Wastage Type</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($damageStocks as $entry)
                        <tr>
                            <td>{{ $entry->damage_number }}</td>
                            <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                            <td>{{ $entry->branch?->name }}</td>
                            <td>{{ $entry->total_qty }}</td>
                            <td>{{ number_format($entry->total_cost, 2) }}</td>
                            <td>{{ $entry->wastage_type }}</td>
                            <td class="text-right">
                                <a href="{{ route('inventory.damage-stocks.edit', $entry) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('inventory.damage-stocks.destroy', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this damage stock entry? Stock will be restored.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No damage stock entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $damageStocks->links() }}</div>
    </div>
@stop
