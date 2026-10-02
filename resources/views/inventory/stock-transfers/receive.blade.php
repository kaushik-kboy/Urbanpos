@extends('adminlte::page')

@section('title', 'Receive Stock Transfer ' . $stockTransfer->transfer_number)

@section('content_header')
    <h1>Receive Transfer {{ $stockTransfer->transfer_number }}</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-transfers.receive', $stockTransfer) }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                <div class="row mb-3">
                    <div class="col-md-4"><strong>From:</strong> {{ $stockTransfer->fromBranch?->name }}</div>
                    <div class="col-md-4"><strong>To:</strong> {{ $stockTransfer->toBranch?->name }}</div>
                    <div class="col-md-4"><strong>Date:</strong> {{ $stockTransfer->transfer_date->format('d-m-Y') }}</div>
                </div>
                <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-center">Batch No</th>
                            <th class="text-center">Exp Date</th>
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
                                $lineAmount = $line->qty * $line->unit_cost;
                                $grandTotal += $lineAmount;
                            @endphp
                            <tr>
                                <td class="text-center align-middle text-muted font-weight-bold">{{ $loop->iteration }}</td>
                                <td class="align-middle font-weight-bold">
                                    {{ $line->item?->name }}
                                    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $line->id }}">
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
                                    @if($line->exp_date)
                                        @php
                                            $isExpired = $line->exp_date < now()->startOfDay();
                                        @endphp
                                        <span class="badge {{ $isExpired ? 'badge-danger' : 'badge-secondary' }} px-2 py-1">
                                            {{ $line->exp_date->format('d-m-Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right align-middle">{{ number_format($line->unit_cost, 2) }}</td>
                                <td class="text-right align-middle font-weight-bold">{{ number_format($line->qty, 3) }}</td>
                                <td class="text-right align-middle font-weight-bold text-primary">{{ number_format($lineAmount, 2) }}</td>
                                <td style="max-width: 150px;" class="align-middle">
                                    <input type="number" step="0.001" min="0" max="{{ $line->qty }}"
                                           name="items[{{ $loop->index }}][received_qty]"
                                           value="{{ $line->qty }}"
                                           class="form-control form-control-sm text-right font-weight-bold" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="6" class="text-right">Grand Total:</td>
                            <td class="text-right text-primary">{{ number_format($grandTotal, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                @if($stockTransfer->remarks)
                    <div class="alert alert-light border small py-2 px-3 mb-3">
                        <strong><i class="fas fa-info-circle text-info mr-1"></i> Outward Remarks:</strong> {{ $stockTransfer->remarks }}
                    </div>
                @endif
                <p class="text-muted small mb-3">
                    If the received quantity is less than dispatched, the shortfall is recorded as "lost in transit" —
                    only the actually-received quantity is added to this branch's stock.
                </p>

                <div class="form-group mb-0">
                    <label for="receive_remarks" class="font-weight-bold small text-dark">
                        <i class="fas fa-comment-dots mr-1 text-primary"></i> Receipt / Inward Remarks (Optional):
                    </label>
                    <textarea name="remarks" id="receive_remarks" rows="2" class="form-control" placeholder="Enter remarks on receipt, package condition, damage, or discrepancy notes..."></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Confirm Receipt</button>
                <a href="{{ route('inventory.stock-transfers.pending-receipt') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
