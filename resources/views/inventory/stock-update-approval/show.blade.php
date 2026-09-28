@extends('adminlte::page')

@section('title', 'Stock Update ' . $stockUpdate->update_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-clipboard-check text-primary mr-2"></i>Stock Update {{ $stockUpdate->update_number }}</h1>
        <a href="{{ route('inventory.stock-update-approval.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-2">Location</dt><dd class="col-sm-4">{{ $stockUpdate->branch?->name ?? '-' }}</dd>
                <dt class="col-sm-2">Date</dt><dd class="col-sm-4">{{ $stockUpdate->entry_date?->format('d-m-Y') }}</dd>
                <dt class="col-sm-2">Status</dt><dd class="col-sm-4">{{ $stockUpdate->status }}</dd>
                <dt class="col-sm-2">Remarks</dt><dd class="col-sm-4">{{ $stockUpdate->remarks ?: '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Item</th><th>Exp Dt</th>
                        <th class="text-right">System Qty</th><th class="text-right">Counted Qty</th><th class="text-right">Diff</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockUpdate->items as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $line->item?->name ?? '-' }}</td>
                            <td>{{ $line->exp_date?->format('d-m-Y') ?? '-' }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->system_qty_at_entry, 3, '.', ''), '0'), '.') }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->physical_qty, 3, '.', ''), '0'), '.') }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->delta_qty, 3, '.', ''), '0'), '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
