@extends('adminlte::page')

@section('title', 'Stock Transfer ' . $stockTransfer->transfer_number)

@section('content_header')
    <h1>Stock Transfer {{ $stockTransfer->transfer_number }}</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Date:</strong> {{ $stockTransfer->transfer_date->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>From:</strong> {{ $stockTransfer->fromBranch?->name }}</div>
                <div class="col-md-3"><strong>To:</strong> {{ $stockTransfer->toBranch?->name }}</div>
                <div class="col-md-3"><strong>Status:</strong> {{ $stockTransfer->status }}</div>
            </div>
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Item</th>
                        <th>Exp Date</th>
                        <th class="text-right">Dispatched Qty</th>
                        <th class="text-right">Received Qty</th>
                        <th class="text-right">Unit Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockTransfer->items as $line)
                        <tr>
                            <td>{{ $line->item?->name }}</td>
                            <td>{{ optional($line->exp_date)->format('d-m-Y') ?: '-' }}</td>
                            <td class="text-right">{{ $line->qty }}</td>
                            <td class="text-right">{{ $line->received_qty ?? '-' }}</td>
                            <td class="text-right">{{ number_format($line->unit_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($stockTransfer->remarks)
                <p class="text-muted mb-0"><strong>Remarks:</strong> {{ $stockTransfer->remarks }}</p>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('inventory.stock-transfers.index') }}" class="btn btn-default">Back</a>
            <a href="{{ route('inventory.stock-transfers.print', $stockTransfer) }}" target="_blank" class="btn btn-primary">
                <i class="fas fa-print mr-1"></i> Print Transfer Note
            </a>
        </div>
    </div>
@stop
