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
                        <th>#</th>
                        <th>Item</th>
                        <th class="text-center">Code</th>
                        <th class="text-center">Batch No</th>
                        <th class="text-center">Exp Date</th>
                        <th class="text-right">MRP (₹)</th>
                        <th class="text-right">Unit Cost (₹)</th>
                        <th class="text-right">Dispatched Qty</th>
                        <th class="text-right">Amount (₹)</th>
                        <th class="text-right">Received Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grandTotal = 0; @endphp
                    @foreach ($stockTransfer->items as $line)
                        @php
                            $unitCost = (float) $line->unit_cost;
                            if ($unitCost <= 0) {
                                $unitCost = (float) ($line->item?->cost_price ?: ($line->item?->landing_cost ?: ($line->item?->purchase_rate ?: ($line->item?->sell_price ?: 0))));
                            }
                            $lineAmount = (float) $line->qty * $unitCost;
                            $grandTotal += $lineAmount;
                            $itemCode = $line->item?->item_code ?: ($line->item?->ean_upc_code ?: '');

                            $expDate = $line->exp_date;
                            if (empty($expDate) && $line->batch_no && $line->item_id) {
                                $expDateVal = \Illuminate\Support\Facades\DB::table('stock_ledger')
                                    ->where('item_id', $line->item_id)
                                    ->where('batch_no', $line->batch_no)
                                    ->whereNotNull('exp_date')
                                    ->whereNotIn('exp_date', ['', '0000-00-00'])
                                    ->orderByDesc('id')
                                    ->value('exp_date');
                                if (empty($expDateVal)) {
                                    $expDateVal = \Illuminate\Support\Facades\DB::table('purchase_invoice_items')
                                        ->where('item_id', $line->item_id)
                                        ->where('batch_no', $line->batch_no)
                                        ->whereNotNull('exp_date')
                                        ->whereNotIn('exp_date', ['', '0000-00-00'])
                                        ->orderByDesc('id')
                                        ->value('exp_date');
                                }
                                if (!empty($expDateVal)) {
                                    try {
                                        $expDate = \Carbon\Carbon::parse($expDateVal);
                                    } catch (\Throwable) {}
                                }
                            }
                            if (empty($expDate) && $line->item_id) {
                                $itemExp = \Illuminate\Support\Facades\DB::table('purchase_invoice_items')
                                    ->where('item_id', $line->item_id)
                                    ->whereNotNull('exp_date')
                                    ->whereNotIn('exp_date', ['', '0000-00-00'])
                                    ->orderByDesc('id')
                                    ->value('exp_date');
                                if (empty($itemExp)) {
                                    $itemExp = \Illuminate\Support\Facades\DB::table('stock_ledger')
                                        ->where('item_id', $line->item_id)
                                        ->whereNotNull('exp_date')
                                        ->whereNotIn('exp_date', ['', '0000-00-00'])
                                        ->orderByDesc('id')
                                        ->value('exp_date');
                                }
                                if (!empty($itemExp)) {
                                    try {
                                        $expDate = \Carbon\Carbon::parse($itemExp);
                                    } catch (\Throwable) {}
                                }
                            }
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted font-weight-bold">{{ $loop->iteration }}</td>
                            <td class="align-middle font-weight-bold">{{ $line->item?->name }}</td>
                            <td class="text-center align-middle">
                                @if($itemCode)
                                    <span class="badge badge-secondary px-2 py-1">{{ $itemCode }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                @if($line->batch_no)
                                    <span class="badge badge-info px-2 py-1">
                                        <i class="fas fa-layer-group mr-1"></i>{{ $line->batch_no }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                @if($expDate)
                                    @php $isExpired = $expDate < now()->startOfDay(); @endphp
                                    <span class="badge {{ $isExpired ? 'badge-danger' : 'badge-secondary' }} px-2 py-1">
                                        {{ $expDate->format('d-m-Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right align-middle font-weight-bold">{{ number_format((float) ($line->mrp ?: ($line->item?->mrp ?: 0)), 2) }}</td>
                            <td class="text-right align-middle">{{ number_format($unitCost, 2) }}</td>
                            <td class="text-right align-middle font-weight-bold">{{ number_format($line->qty, 3) }}</td>
                            <td class="text-right align-middle font-weight-bold text-primary">{{ number_format($lineAmount, 2) }}</td>
                            <td class="text-right align-middle">{{ $line->received_qty !== null ? number_format($line->received_qty, 3) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="8" class="text-right">Grand Total:</td>
                        <td class="text-right text-primary">{{ number_format($grandTotal, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
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
