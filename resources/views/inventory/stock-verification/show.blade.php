@extends('adminlte::page')

@section('title', 'Observation #'.$observation['id'])

@section('content_header')
    <h1><i class="fas fa-search text-primary mr-2"></i>Observation #{{ $observation['id'] }}</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Details</h3></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th style="width:220px;">Type</th><td>{{ $observation['type'] === 'stock_count' ? 'Stock Count' : 'Expiry Check' }}</td></tr>
                        <tr><th>Product</th><td>{{ $observation['item_name'] ?? '-' }} ({{ $observation['item_code'] ?? '-' }})</td></tr>
                        <tr><th>Branch</th><td>{{ $observation['branch_name'] ?? '-' }}</td></tr>
                        <tr><th>Batch No</th><td>{{ $observation['batch_no'] ?? '-' }}</td></tr>
                        @if (!empty($observation['batch_correction_applied']))
                            <tr>
                                <th>Batch Correction</th>
                                <td>
                                    <span class="badge badge-warning">Applied</span>
                                    Original batch: <strong>{{ $observation['original_batch_no'] }}</strong><br>
                                    <small class="text-muted">Reason: {{ $observation['batch_correction_reason'] }}</small>
                                </td>
                            </tr>
                        @endif
                        <tr><th>Expiry Date Observed</th><td>{{ $observation['exp_date_observed'] ?? '-' }}</td></tr>
                        <tr><th>Physical Qty</th><td>{{ $observation['physical_qty'] ?? '-' }}</td></tr>
                        @if (array_key_exists('system_qty_snapshot', $observation))
                            <tr><th>System Qty (at submission)</th><td>{{ $observation['system_qty_snapshot'] ?? '-' }}</td></tr>
                            <tr><th>Variance</th><td>{{ $observation['variance'] ?? '-' }}</td></tr>
                        @endif
                        <tr><th>Approved Qty</th><td>{{ $observation['approved_qty'] ?? '-' }}</td></tr>
                        <tr><th>Actual Adjustment</th><td>{{ $observation['actual_adjustment_qty'] ?? '-' }}</td></tr>
                        <tr><th>Remarks</th><td>{{ $observation['remarks'] ?? '-' }}</td></tr>
                        <tr><th>Status</th><td><span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $observation['status'])) }}</span></td></tr>
                        <tr><th>Submitted By</th><td>{{ $observation['submitted_by_name'] ?? '-' }}</td></tr>
                        <tr><th>Reviewed By</th><td>{{ $observation['reviewed_by_name'] ?? '-' }}</td></tr>
                        <tr><th>Review Remarks</th><td>{{ $observation['review_remarks'] ?? '-' }}</td></tr>
                        <tr><th>Submitted At</th><td>{{ \Illuminate\Support\Carbon::parse($observation['created_at'])->format('d-m-Y H:i') }}</td></tr>
                    </table>

                    @if (!empty($observation['photo_path']))
                        <h5 class="mt-3">Evidence Photo</h5>
                        <img src="{{ route('inventory.stock-verification.photo', $observation['id']) }}" class="img-thumbnail" style="max-width: 320px;" alt="Evidence photo" onerror="this.replaceWith(document.createTextNode('Photo could not be loaded.'))">
                    @endif
                </div>
            </div>

            @if ($observation['status'] === 'pending')
                <div class="card card-outline card-secondary">
                    <div class="card-body">
                        @if ($observation['type'] === 'stock_count')
                            <form action="{{ route('inventory.stock-verification.approve', $observation['id']) }}" method="POST" class="form-inline d-inline mr-2">
                                @csrf
                                <label class="mr-2">Approved Qty:</label>
                                <input type="number" step="0.001" name="approved_qty" class="form-control form-control-sm mr-2" style="width:120px" value="{{ $observation['system_qty_snapshot'] ?? '' }}" required>
                                <button class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Approve</button>
                            </form>
                        @else
                            <form action="{{ route('inventory.stock-verification.approve', $observation['id']) }}" method="POST" class="d-inline mr-2" onsubmit="return confirm('Approve this expiry check? This will NOT change stock quantity.')">
                                @csrf
                                <button class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Approve (no quantity change)</button>
                            </form>
                        @endif

                        <form action="{{ route('inventory.stock-verification.request-recount', $observation['id']) }}" method="POST" class="form-inline d-inline mr-2">
                            @csrf
                            <input type="text" name="remarks" class="form-control form-control-sm mr-2" placeholder="Remarks (required)" required>
                            <button class="btn btn-warning btn-sm"><i class="fas fa-redo mr-1"></i> Request Recount</button>
                        </form>

                        <form action="{{ route('inventory.stock-verification.reject', $observation['id']) }}" method="POST" class="form-inline d-inline">
                            @csrf
                            <input type="text" name="remarks" class="form-control form-control-sm mr-2" placeholder="Remarks (required)" required>
                            <button class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i> Reject</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card card-outline card-info">
                <div class="card-header"><h3 class="card-title">Recount History</h3></div>
                <div class="card-body p-0">
                    @if (empty($observation['parent_observation']) && empty($observation['recounts']))
                        <p class="text-muted p-3 mb-0">No recount history for this observation.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @if (!empty($observation['parent_observation']))
                                <li class="list-group-item">
                                    <small class="text-muted">Parent attempt #{{ $observation['parent_observation']['id'] }}</small><br>
                                    Physical qty: {{ $observation['parent_observation']['physical_qty'] ?? '-' }} —
                                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $observation['parent_observation']['status'])) }}</span>
                                </li>
                            @endif
                            @foreach ($observation['recounts'] ?? [] as $r)
                                <li class="list-group-item">
                                    <small class="text-muted">Recount attempt #{{ $r['id'] }}</small><br>
                                    Physical qty: {{ $r['physical_qty'] ?? '-' }} —
                                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $r['status'])) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop
