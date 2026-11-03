@extends('adminlte::page')

@section('title', 'Inter-Branch Stock Transfer Matchmaker')

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

.modern-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.modern-card-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-card-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.modern-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.modern-table thead th {
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 0.72rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.06em !important;
    text-transform: uppercase !important;
    padding: 0.85rem 1rem !important;
    border: none !important;
    white-space: nowrap;
}
.modern-table tbody tr {
    transition: background-color 0.1s ease;
}
.modern-table tbody tr:nth-of-type(even) {
    background-color: #f8fafc;
}
.modern-table tbody tr:hover {
    background-color: #e2e8f0 !important;
}
.modern-table tbody td {
    padding: 0.85rem 1rem !important;
    vertical-align: middle !important;
    border-top: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    font-size: 0.85rem;
}

.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
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
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
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
                <i class="fas fa-random text-success mr-2"></i> Smart Inter-Branch Matchmaker
            </h1>
            <div class="page-subheading mt-1">
                Pairs destination stores in shortage (Critical / Low) with surplus stores (Healthy / Overstock)
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
    <div id="transferAlert" class="alert alert-dark alert-dismissible fade show d-none border-dark shadow-sm mb-3 font-weight-bold" style="background:#0f172a; color:#ffffff; border-radius:10px;" role="alert">
        <i class="fas fa-check-circle mr-1 text-success"></i> <span id="transferAlertMessage"></span>
        <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modern-card">
        <div class="modern-card-header">
            <h3 class="modern-card-title">
                <i class="fas fa-arrows-split-up-and-left mr-2 text-success"></i> Transfer Recommendations
            </h3>
            <span class="badge badge-dark px-3 py-2 font-weight-bold" style="border-radius: 6px;">
                {{ $transferRecommendations->count() }} Opportunities Found
            </span>
        </div>
        <div class="p-0 table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Item SKU & Name</th>
                        <th>Deficit Branch (Destination)</th>
                        <th>Current Deficit Stock</th>
                        <th>Surplus Branch (Source)</th>
                        <th>Available Surplus Stock</th>
                        <th class="text-center">Recommended Transfer Qty</th>
                        <th class="text-center" style="width: 170px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transferRecommendations as $transfer)
                        <tr id="transfer-row-{{ $transfer->item_id }}-{{ $transfer->source_branch_id }}-{{ $transfer->dest_branch_id }}">
                            <td>
                                <strong class="text-dark" style="font-size: 0.9rem;">{{ $transfer->item_name }}</strong><br>
                                <span class="badge badge-dark px-2 py-1 font-weight-bold text-xs mt-1" style="border-radius: 4px;">SKU: {{ $transfer->item_code }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light border border-dark text-danger font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <i class="fas fa-map-marker-alt mr-1"></i> {{ $transfer->dest_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $transfer->dest_status === 'Critical' ? 'danger' : 'warning' }} px-2 py-1 font-weight-bold">
                                    {{ (int)$transfer->dest_stock }} units ({{ $transfer->dest_status }})
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-light border border-dark text-success font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <i class="fas fa-warehouse mr-1"></i> {{ $transfer->source_branch_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success px-2 py-1 font-weight-bold">
                                    {{ (int)$transfer->source_stock }} units ({{ $transfer->source_status }})
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-dark px-3 py-2 font-weight-bold text-white shadow-sm" style="font-size: 0.95rem; border-radius: 6px;">
                                    {{ $transfer->suggested_transfer_qty }} Qty
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-action-dark btn-sm shadow-sm" onclick="createTransfer(this, {{ $transfer->item_id }}, {{ $transfer->source_branch_id }}, {{ $transfer->dest_branch_id }}, {{ $transfer->suggested_transfer_qty }}, '{{ addslashes($transfer->item_name) }}')">
                                    <i class="fas fa-dolly mr-1 text-success"></i> Create Transfer
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-dark font-weight-bold">
                                <i class="fas fa-check-circle text-success mb-2" style="font-size: 2.5rem;"></i><br>
                                <span style="font-size: 1rem;">No Inter-Branch Transfer Match Opportunities Needed</span><br>
                                <small class="text-dark">All stores have balanced inventory across active SKUs.</small>
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
    function createTransfer(buttonEl, itemId, fromBranchId, toBranchId, qty, itemName) {
        if (!confirm(`Dispatch ${qty} units of "${itemName}" from source store to deficit store?`)) {
            return;
        }

        const $btn = $(buttonEl);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating...');

        $.ajax({
            url: "{{ route('inventory.replenishment.create-transfer') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                from_branch_id: fromBranchId,
                to_branch_id: toBranchId,
                items: [{ item_id: itemId, qty: qty }]
            },
            success: function(res) {
                $('#transferAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-white" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Transfer #${res.transfer_number}</a>`);
                $('#transferAlert').removeClass('d-none');
                $btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.';
                alert(msg);
                $btn.prop('disabled', false).html('<i class="fas fa-dolly mr-1 text-success"></i> Create Transfer');
            }
        });
    }
</script>
@stop
