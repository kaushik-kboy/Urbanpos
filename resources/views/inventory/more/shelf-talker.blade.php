@extends('adminlte::page')

@section('title', 'Shelf Talker')

@section('content_header')
    <h1><i class="fas fa-sticky-note text-primary mr-2"></i>Shelf Talker (Shelf-Edge Labels)</h1>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold mb-0">Shelf Price Tags & Edge Labels</h5>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print Shelf Talkers
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="row" id="shelf-talker-grid">
                @foreach ($items as $item)
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card border-dark shadow-sm h-100" style="border: 2px solid #333 !important;">
                            <div class="card-header bg-dark text-white text-center py-1 font-weight-bold" style="font-size: 0.85rem;">
                                {{ config('app.name', 'URBAN PETS') }}
                            </div>
                            <div class="card-body text-center p-2">
                                <h6 class="font-weight-bold mb-1" style="min-height: 38px;">{{ $item->name }}</h6>
                                <p class="text-muted small mb-1">{{ $item->brand?->name ?: 'Standard' }}</p>
                                <div class="my-2">
                                    <span class="text-muted" style="text-decoration: line-through; font-size: 0.9rem;">
                                        MRP: ₹{{ number_format($item->mrp, 2) }}
                                    </span>
                                    <h4 class="font-weight-bold text-success mb-0">
                                        OUR: ₹{{ number_format($item->sell_price, 2) }}
                                    </h4>
                                </div>
                                <code class="small text-dark">{{ $item->ean_upc_code ?: 'SKU-'.$item->id }}</code>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@stop
