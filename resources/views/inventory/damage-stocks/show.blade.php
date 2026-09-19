@extends('adminlte::page')

@section('title', "Damage Stock {$damageStock->damage_number}")

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark">
                <i class="fas fa-file-invoice text-danger mr-2"></i> Damage Stock: <strong>{{ $damageStock->damage_number }}</strong>
            </h1>
            <small class="text-muted">Recorded on {{ $damageStock->entry_date ? $damageStock->entry_date->format('d M Y') : '-' }}</small>
        </div>
        <div>
            <a href="{{ route('inventory.damage-stocks.edit', $damageStock) }}" class="btn btn-warning mr-1 shadow-sm">
                <i class="fas fa-pen mr-1"></i> Edit Entry
            </a>
            <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-danger shadow-sm">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold m-0 text-dark">
                <i class="fas fa-info-circle text-info mr-1"></i> Entry Details
            </h5>
        </div>
        <div class="card-body py-3">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Location / Branch:</span>
                    <strong class="text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-store-alt text-secondary mr-1"></i> {{ $damageStock->branch?->name ?? 'N/A' }}
                    </strong>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Entry Date:</span>
                    <strong class="text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-calendar-alt text-secondary mr-1"></i> {{ $damageStock->entry_date ? $damageStock->entry_date->format('d-m-Y') : '-' }}
                    </strong>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Wastage Type:</span>
                    @php
                        $badge = match($damageStock->wastage_type) {
                            'Wastage' => 'badge-warning text-dark',
                            'Damage' => 'badge-danger',
                            'Theft' => 'badge-dark',
                            default => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $badge }} px-2 py-1 font-weight-bold" style="font-size: 0.95rem;">
                        {{ $damageStock->wastage_type }}
                    </span>
                </div>
                <div class="col-md-3 col-sm-6 mb-2 text-md-right">
                    <span class="text-muted small d-block">Total Written-off Cost:</span>
                    <strong class="text-danger" style="font-size: 1.3rem;">
                        ₹{{ number_format($damageStock->total_cost, 2) }}
                    </strong>
                </div>
            </div>

            @if ($damageStock->remarks || $damageStock->message)
                <hr class="my-2">
                <div class="row text-muted small">
                    @if ($damageStock->remarks)
                        <div class="col-md-6">
                            <strong>Remarks:</strong> {{ $damageStock->remarks }}
                        </div>
                    @endif
                    @if ($damageStock->message)
                        <div class="col-md-6">
                            <strong>Message / Notes:</strong> {{ $damageStock->message }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Written-off Items Grid --}}
    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header bg-light py-2">
            <h6 class="card-title font-weight-bold m-0 text-dark">
                <i class="fas fa-boxes text-danger mr-1"></i> Written-off Items ({{ $damageStock->items->count() }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped mb-0">
                    <thead class="thead-light text-nowrap">
                        <tr class="text-center">
                            <th style="width: 45px;">S.No</th>
                            <th style="width: 130px;">Item Code</th>
                            <th class="text-left">Item Description</th>
                            <th style="width: 110px;">Exp Dt</th>
                            <th style="width: 90px;" class="text-right">Qty</th>
                            <th style="width: 110px;" class="text-right">Cost Price</th>
                            <th style="width: 110px;" class="text-right">Sell Price</th>
                            <th style="width: 110px;" class="text-right">MRP</th>
                            <th style="width: 70px;" class="text-center">GST%</th>
                            <th style="width: 110px;" class="text-right">GST TaxAmt</th>
                            <th style="width: 120px;" class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($damageStock->items as $idx => $line)
                            @php
                                $item = $line->item;
                                $displayCode = $item?->item_code ?: ($item?->ean_upc_code ?: '-');
                            @endphp
                            <tr>
                                <td class="text-center align-middle font-weight-bold text-muted">{{ $idx + 1 }}</td>
                                <td class="text-center align-middle font-weight-bold">
                                    <span class="badge badge-light border">{{ $displayCode }}</span>
                                </td>
                                <td class="align-middle text-left font-weight-bold text-dark">
                                    {{ $item?->name ?? 'Unknown Item' }}
                                </td>
                                <td class="text-center align-middle text-muted">
                                    {{ $line->exp_date ? $line->exp_date->format('d-m-Y') : '-' }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-primary">
                                    {{ number_format($line->qty, 3) }}
                                </td>
                                <td class="text-right align-middle">₹{{ number_format($line->cost_price, 2) }}</td>
                                <td class="text-right align-middle text-muted">₹{{ number_format($line->sell_price, 2) }}</td>
                                <td class="text-right align-middle text-dark">₹{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-info">{{ number_format($line->gst_percent, 0) }}%</span>
                                </td>
                                <td class="text-right align-middle text-secondary">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                                <td class="text-right align-middle font-weight-bold text-danger">₹{{ number_format($line->net_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="4" class="text-right align-middle">Totals:</td>
                            <td class="text-right align-middle text-primary" style="font-size: 1rem;">
                                {{ number_format($damageStock->total_qty, 3) }}
                            </td>
                            <td colspan="4"></td>
                            <td class="text-right align-middle text-secondary">
                                ₹{{ number_format($damageStock->items->sum('gst_tax_amount'), 2) }}
                            </td>
                            <td class="text-right align-middle text-danger font-weight-bold" style="font-size: 1.05rem;">
                                ₹{{ number_format($damageStock->total_cost, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@stop
