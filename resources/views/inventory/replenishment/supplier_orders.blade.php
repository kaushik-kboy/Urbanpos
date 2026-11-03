@extends('adminlte::page')

@section('title', 'Supplier PO Recommendations')

@push('css')
<style>
body, .content-wrapper {
    color: #0f172a !important;
}

.page-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    font-size: 1.6rem;
}
.page-subheading {
    color: #1e293b !important;
    font-weight: 600;
    font-size: 0.85rem;
}

.modern-vendor-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.modern-vendor-card:hover {
    transform: translateY(-2px);
    border-color: #0f172a;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
}
.modern-vendor-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-vendor-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}
.btn-action-outline {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    color: #0f172a !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-outline:hover {
    background: #0f172a;
    color: #ffffff !important;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="page-heading m-0">
                <i class="fas fa-truck text-primary mr-2"></i> Supplier-wise Order Recommendations
            </h1>
            <div class="page-subheading mt-1">
                Items needing reorder grouped by vendor with estimated procurement value
            </div>
        </div>
        <div>
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-outline shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        @forelse($supplierOrders as $group)
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="modern-vendor-card">
                    <div class="modern-vendor-header">
                        <h3 class="modern-vendor-title">
                            <i class="fas fa-building text-primary mr-1"></i> {{ $group->supplier_name }}
                        </h3>
                        <span class="badge badge-dark px-2 py-1 font-weight-bold" style="border-radius: 6px;">{{ $group->items_count }} Items</span>
                    </div>
                    <div class="p-3 d-flex flex-column justify-content-between flex-grow-1">
                        <div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="font-weight-bold text-dark text-xs uppercase">Total Units to Order:</span>
                                <span class="font-weight-bold text-dark">{{ number_format($group->total_units) }} units</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="font-weight-bold text-dark text-xs uppercase">Estimated Order Total:</span>
                                <span class="font-weight-bold text-primary" style="font-size: 1.2rem;">₹ {{ number_format($group->estimated_value, 2) }}</span>
                            </div>

                            <div class="p-2 rounded bg-light border mb-3">
                                <span class="font-weight-bold text-dark text-xs d-block mb-1 text-uppercase">Top Items in this Order:</span>
                                <ul class="list-unstyled mb-0" style="font-size: 0.82rem;">
                                    @foreach($group->items->take(4) as $metric)
                                        <li class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="font-weight-bold text-dark text-truncate mr-2" style="max-width: 190px;">{{ $metric->item->name ?? 'SKU #' . $metric->item_id }}</span>
                                            <span class="badge badge-danger font-weight-bold px-2 py-1" style="border-radius: 4px;">{{ $metric->suggested_qty }} Qty</span>
                                        </li>
                                    @endforeach
                                    @if($group->items_count > 4)
                                        <li class="font-weight-bold text-dark text-center pt-2" style="font-size: 0.75rem;">
                                            + {{ $group->items_count - 4 }} more items
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </div>

                        <a href="{{ route('inventory.replenishment.index', ['supplier_id' => $group->supplier_id]) }}" class="btn btn-action-dark btn-block shadow-sm">
                            <i class="fas fa-file-invoice mr-1 text-primary"></i> Review & Create Order
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="modern-vendor-card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-clipboard-check text-success mb-3" style="font-size: 3rem;"></i><br>
                        <h4 class="font-weight-bold text-dark">No Purchase Orders Currently Required</h4>
                        <p class="font-weight-bold text-dark text-sm">All store inventory levels are currently above reorder thresholds.</p>
                        <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-dark font-weight-bold">
                            Go to Stock Analysis Studio
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@stop
