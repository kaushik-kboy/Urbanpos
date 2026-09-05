@extends('adminlte::page')

@section('title', 'Current Stock Branchwise')

@section('content_header')
    <h1>Current Stock Branchwise</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <label class="mr-2">Location</label>
                <select name="branch_id" class="form-control form-control-sm mr-3">
                    <option value="">All Location</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Item</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Cost Price</th>
                        <th class="text-right">Selling</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">Value on Cost</th>
                        <th class="text-right">Value on Selling</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->branch?->name }}</td>
                            <td>{{ $row->item?->name }}</td>
                            <td class="text-right">{{ $row->quantity }}</td>
                            <td class="text-right">{{ number_format($row->item?->cost_price ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->item?->sell_price ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->item?->mrp ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->quantity * ($row->item?->cost_price ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format($row->quantity * ($row->item?->sell_price ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No stock on hand.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">NetTotal</td>
                            <td class="text-right">{{ number_format($rows->sum(fn ($r) => $r->quantity * ($r->item?->cost_price ?? 0)), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum(fn ($r) => $r->quantity * ($r->item?->sell_price ?? 0)), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
