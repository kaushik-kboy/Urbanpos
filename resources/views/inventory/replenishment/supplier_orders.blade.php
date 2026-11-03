@extends('adminlte::page')

@section('title', 'Supplier PO Recommendations')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-truck text-primary mr-2"></i> Supplier-wise Order Recommendations
            </h1>
            <small class="text-muted">Items needing reorder grouped by vendor with estimated procurement value</small>
        </div>
        <div>
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-default shadow-sm btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        @forelse($supplierOrders as $group)
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card card-outline card-primary shadow-sm h-100">
                    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                        <h3 class="card-title font-weight-bold text-sm">
                            <i class="fas fa-building text-primary mr-1"></i> {{ $group->supplier_name }}
                        </h3>
                        <span class="badge badge-primary font-weight-bold">{{ $group->items_count }} Items</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted text-xs">Total Units to Order:</span>
                            <span class="font-weight-bold text-dark">{{ number_format($group->total_units) }} units</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted text-xs">Estimated Order Total:</span>
                            <span class="font-weight-bold text-success" style="font-size: 1.1rem;">₹ {{ number_format($group->estimated_value, 2) }}</span>
                        </div>

                        <hr class="my-2">
                        <small class="text-muted font-weight-bold text-uppercase d-block mb-2" style="font-size: 0.7rem;">Top Items in this PO:</small>
                        <ul class="list-unstyled mb-3" style="font-size: 0.82rem;">
                            @foreach($group->items->take(3) as $metric)
                                <li class="d-flex justify-content-between py-1 border-bottom">
                                    <span class="text-truncate mr-2" style="max-width: 180px;">{{ $metric->item->name ?? 'SKU #' . $metric->item_id }}</span>
                                    <span class="badge badge-light border font-weight-bold text-danger">{{ $metric->suggested_qty }} Qty</span>
                                </li>
                            @endforeach
                            @if($group->items_count > 3)
                                <li class="text-muted text-center pt-1" style="font-size: 0.75rem;">
                                    + {{ $group->items_count - 3 }} more items
                                </li>
                            @endif
                        </ul>

                        <button type="button" class="btn btn-primary btn-block btn-sm font-weight-bold shadow-sm" onclick="alert('1-Click PO Draft Generated for {{ addslashes($group->supplier_name) }}!')">
                            <i class="fas fa-file-invoice mr-1"></i> 1-Click PO Draft
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-box-check text-success mb-2" style="font-size: 2.5rem;"></i><br>
                        <h5>No Purchase Orders Currently Required</h5>
                        <p class="text-muted text-sm">All store inventory levels are currently above reorder thresholds.</p>
                        <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-primary btn-sm">
                            Go to Stock Analysis
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@stop
