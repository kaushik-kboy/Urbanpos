@extends('adminlte::page')

@section('title', 'Stock Transfer ' . $stockTransfer->transfer_number)

@section('content_header')
    <h1>Stock Transfer {{ $stockTransfer->transfer_number }}</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-1"></i> Stock Transfer Details</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer
                    table-key="inventory.stock-transfers.show"
                    table-id="st-show-items-table"
                    button-class="btn btn-sm btn-outline-secondary"
                    title="Customize Table Columns"
                />
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-3"><strong>Date:</strong> {{ $stockTransfer->transfer_date->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>From:</strong> <span class="badge badge-light border border-dark text-dark font-weight-bold px-2 py-1"><i class="fas fa-warehouse text-success mr-1"></i>{{ $stockTransfer->fromBranch?->name }}</span></div>
                <div class="col-md-3"><strong>To:</strong> <span class="badge badge-light border border-dark text-dark font-weight-bold px-2 py-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i>{{ $stockTransfer->toBranch?->name }}</span></div>
                <div class="col-md-3">
                    <strong>Status:</strong>
                    @if($stockTransfer->status === 'Received')
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Received</span>
                    @elseif($stockTransfer->status === 'Dispatched')
                        <span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-truck mr-1"></i>Dispatched (In-Transit)</span>
                    @else
                        <span class="badge badge-secondary px-2 py-1">{{ $stockTransfer->status }}</span>
                    @endif
                </div>
            </div>
            <div class="row mb-3 pb-2 border-bottom">
                <div class="col-md-3">
                    <strong>Dispatched By:</strong>
                    <span class="badge badge-info px-2 py-1">
                        <i class="fas fa-user-check mr-1"></i>{{ $stockTransfer->creator?->name ?: 'Staff' }}
                    </span>
                    @if($stockTransfer->dispatched_at)
                        <div class="text-muted text-xs mt-1">{{ \Carbon\Carbon::parse($stockTransfer->dispatched_at)->format('d-m-Y h:i A') }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <strong>Received By:</strong>
                    @if($stockTransfer->receiver)
                        <span class="badge badge-success px-2 py-1">
                            <i class="fas fa-user-check mr-1"></i>{{ $stockTransfer->receiver->name }}
                        </span>
                        @if($stockTransfer->received_at)
                            <div class="text-muted text-xs mt-1">{{ \Carbon\Carbon::parse($stockTransfer->received_at)->format('d-m-Y h:i A') }}</div>
                        @endif
                    @elseif($stockTransfer->status === 'Received')
                        <span class="badge badge-success px-2 py-1">Staff</span>
                    @else
                        <span class="badge badge-light border text-muted px-2 py-1">
                            <i class="fas fa-hourglass-half mr-1"></i>Awaiting Receipt
                        </span>
                    @endif
                </div>
                @if($stockTransfer->remarks)
                    <div class="col-md-6">
                        <strong>Remarks:</strong> <span class="text-muted">{{ $stockTransfer->remarks }}</span>
                    </div>
                @endif
            </div>
            <table class="table table-sm table-bordered" id="st-show-items-table">
                <thead class="thead-light">
                    <tr>
                        <th data-col-key="seq">#</th>
                        <th data-col-key="item">Item</th>
                        <th class="text-center" data-col-key="code">Code</th>
                        <th class="text-center" data-col-key="batch_no">Batch No</th>
                        <th class="text-center" data-col-key="exp_date">Exp Date</th>
                        <th class="text-right" data-col-key="mrp">MRP (₹)</th>
                        <th class="text-right" data-col-key="unit_cost">Unit Cost (₹)</th>
                        <th class="text-right" data-col-key="qty">Dispatched Qty</th>
                        <th class="text-right" data-col-key="amount">Amount (₹)</th>
                        <th class="text-right" data-col-key="received_qty">Received Qty</th>
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
                            <td class="text-center align-middle text-muted font-weight-bold" data-col-key="seq">{{ $loop->iteration }}</td>
                            <td class="align-middle font-weight-bold" data-col-key="item">{{ $line->item?->name }}</td>
                            <td class="text-center align-middle" data-col-key="code">
                                @if($itemCode)
                                    <span class="badge badge-secondary px-2 py-1">{{ $itemCode }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" data-col-key="batch_no">
                                @if($line->batch_no)
                                    <span class="badge badge-info px-2 py-1">
                                        <i class="fas fa-layer-group mr-1"></i>{{ $line->batch_no }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" data-col-key="exp_date">
                                @if($expDate)
                                    @php $isExpired = $expDate < now()->startOfDay(); @endphp
                                    <span class="badge {{ $isExpired ? 'badge-danger' : 'badge-secondary' }} px-2 py-1">
                                        {{ $expDate->format('d-m-Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right align-middle font-weight-bold" data-col-key="mrp">{{ number_format((float) ($line->mrp ?: ($line->item?->mrp ?: 0)), 2) }}</td>
                            <td class="text-right align-middle" data-col-key="unit_cost">{{ number_format($unitCost, 2) }}</td>
                            <td class="text-right align-middle font-weight-bold" data-col-key="qty">
                                {{ (float)$line->qty == (int)$line->qty ? number_format($line->qty, 0) : number_format($line->qty, 3) }}
                                @if(!empty($line->requested_qty) && (float)$line->requested_qty != (float)$line->qty)
                                    <br><small class="text-muted" title="Recommended Deficit Qty">(Req: {{ (float)$line->requested_qty == (int)$line->requested_qty ? number_format($line->requested_qty, 0) : number_format($line->requested_qty, 3) }})</small>
                                @endif
                            </td>
                            <td class="text-right align-middle font-weight-bold text-primary" data-col-key="amount">{{ number_format($lineAmount, 2) }}</td>
                            <td class="text-right align-middle" data-col-key="received_qty">{{ $line->received_qty !== null ? ((float)$line->received_qty == (int)$line->received_qty ? number_format($line->received_qty, 0) : number_format($line->received_qty, 3)) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold" data-customizer-ignore="true">
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
