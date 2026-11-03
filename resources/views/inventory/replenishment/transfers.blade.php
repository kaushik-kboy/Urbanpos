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
    <div id="transferAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert">
        <i class="fas fa-check-circle mr-1"></i> <span id="transferAlertMessage"></span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

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
                        <th class="text-center" style="width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transferRecommendations as $transfer)
                        <tr id="transfer-row-{{ $transfer->item_id }}">
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
                                <button type="button" class="btn btn-success btn-xs font-weight-bold shadow-sm" onclick="dispatchDirectTransfer({{ $transfer->item_id }}, {{ $transfer->source_branch_id }}, {{ $transfer->dest_branch_id }}, {{ $transfer->suggested_transfer_qty }}, '{{ addslashes($transfer->item_name) }}')">
                                    <i class="fas fa-dolly mr-1"></i> 1-Click Dispatch
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

@section('js')
<script>
    function dispatchDirectTransfer(itemId, sourceBranchId, destBranchId, qty, itemName) {
        if (!confirm(`Dispatch ${qty} units of ${itemName} from source to deficit branch?`)) {
            return;
        }

        $.ajax({
            url: "{{ route('inventory.replenishment.create-transfer') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                from_branch_id: sourceBranchId,
                to_branch_id: destBranchId,
                items: [{ item_id: itemId, qty: qty }]
            },
            success: function(res) {
                $('#transferAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-dark" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Transfer Document</a>`);
                $('#transferAlert').removeClass('d-none');
                $('#transfer-row-' + itemId).fadeOut(500);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.');
            }
        });
    }
</script>
@stop
