@extends('adminlte::page')

@section('title', 'Inter-Branch Stock Transfer Matchmaker')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-random text-success mr-2"></i> Smart Inter-Branch Matchmaker
            </h1>
            <small class="text-muted">Automatically pairs destination stores in shortage (Critical/Low) with surplus stores (Healthy/Overstock)</small>
        </div>
        <div>
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-default shadow-sm btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-success shadow-sm">
        <div class="card-header py-2 bg-light">
            <h3 class="card-title font-weight-bold text-sm">
                <i class="fas fa-arrows-split-up-and-left mr-1 text-success"></i> Transfer Recommendations ({{ $transferRecommendations->count() }} Available)
            </h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped text-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Item SKU & Name</th>
                        <th>Deficit Branch (Destination)</th>
                        <th>Current Deficit Stock</th>
                        <th>Surplus Branch (Source)</th>
                        <th>Available Surplus Stock</th>
                        <th class="text-center">Recommended Transfer Qty</th>
                        <th class="text-center" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transferRecommendations as $transfer)
                        <tr>
                            <td>
                                <strong>{{ $transfer->item_name }}</strong><br>
                                <small class="text-muted">SKU: {{ $transfer->item_code }}</small>
                            </td>
                            <td>
                                <span class="badge badge-light border text-danger font-weight-bold">
                                    <i class="fas fa-map-marker-alt mr-1"></i> {{ $transfer->dest_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $transfer->dest_status === 'Critical' ? 'danger' : 'warning' }} px-2 py-1">
                                    {{ (int)$transfer->dest_stock }} units ({{ $transfer->dest_status }})
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-light border text-success font-weight-bold">
                                    <i class="fas fa-warehouse mr-1"></i> {{ $transfer->source_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success px-2 py-1">
                                    {{ (int)$transfer->source_stock }} units ({{ $transfer->source_status }})
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                                    {{ $transfer->suggested_transfer_qty }} Qty
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-success btn-xs font-weight-bold shadow-sm" onclick="alert('Transfer Draft created successfully from {{ addslashes($transfer->source_branch_name) }} to {{ addslashes($transfer->dest_branch_name) }}!')">
                                    <i class="fas fa-dolly mr-1"></i> Create Transfer
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-check-double text-success mb-2" style="font-size: 2rem;"></i><br>
                                <strong>All Stores Balanced!</strong><br>
                                No branch is currently running critical while another holds spare surplus.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
