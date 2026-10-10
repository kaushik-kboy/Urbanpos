@extends('adminlte::page')

@section('title', 'Stock & Expiry Verification — Review Queue')

@section('content_header')
    <h1><i class="fas fa-clipboard-check text-primary mr-2"></i>Stock & Expiry Verification — Review Queue</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Location:</label>
                <select name="branch_id" class="form-control form-control-sm mr-3">
                    <option value="">All Locations</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) request('branch_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <label class="mr-2 font-weight-bold">Type:</label>
                <select name="type" class="form-control form-control-sm mr-3">
                    <option value="">All</option>
                    <option value="stock_count" @selected(request('type') === 'stock_count')>Stock Count</option>
                    <option value="expiry_check" @selected(request('type') === 'expiry_check')>Expiry Check</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Filter</button>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Batch</th>
                            <th>Physical Qty</th>
                            <th>System Qty</th>
                            <th>Variance</th>
                            <th>Submitted By</th>
                            <th>Submitted At</th>
                            <th class="text-right" style="width: 280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($observations['data'] as $o)
                            <tr>
                                <td>{{ $o['id'] }}</td>
                                <td>
                                    @if ($o['type'] === 'stock_count')
                                        <span class="badge badge-secondary">Stock Count</span>
                                    @else
                                        <span class="badge badge-info">Expiry Check</span>
                                    @endif
                                </td>
                                <td>{{ $o['item_name'] }} <small class="text-muted">({{ $o['item_code'] }})</small></td>
                                <td>{{ $o['branch_name'] }}</td>
                                <td>{{ $o['batch_no'] ?: '-' }}</td>
                                <td>{{ $o['physical_qty'] ?? '-' }}</td>
                                <td>{{ $o['system_qty_snapshot'] ?? '-' }}</td>
                                <td>
                                    @if ($o['variance'] === null)
                                        -
                                    @elseif ($o['variance'] > 0)
                                        <span class="text-success font-weight-bold">+{{ $o['variance'] }}</span>
                                    @elseif ($o['variance'] < 0)
                                        <span class="text-danger font-weight-bold">{{ $o['variance'] }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>{{ $o['submitted_by_name'] }}</td>
                                <td><small>{{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('d-m-Y H:i') }}</small></td>
                                <td class="text-right">
                                    <a href="{{ route('inventory.stock-verification.show', $o['id']) }}" class="btn btn-xs btn-outline-info mr-1">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>

                                    @if ($o['type'] === 'stock_count')
                                        <form action="{{ route('inventory.stock-verification.approve', $o['id']) }}" method="POST" class="d-inline" onsubmit="return promptApprove(this, {{ (float) ($o['system_qty_snapshot'] ?? 0) }})">
                                            @csrf
                                            <input type="hidden" name="approved_qty" value="">
                                            <button type="button" class="btn btn-xs btn-success" onclick="promptApprove(this.closest('form'), {{ (float) ($o['system_qty_snapshot'] ?? 0) }})"><i class="fas fa-check mr-1"></i> Approve</button>
                                        </form>
                                    @else
                                        <form action="{{ route('inventory.stock-verification.approve', $o['id']) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this expiry check? This records the review only — it will NOT change stock quantity.')">
                                            @csrf
                                            <button class="btn btn-xs btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('inventory.stock-verification.request-recount', $o['id']) }}" method="POST" class="d-inline" onsubmit="return promptRemarks(this)">
                                        @csrf
                                        <input type="hidden" name="remarks" value="">
                                        <button type="button" class="btn btn-xs btn-warning" onclick="promptRemarks(this.closest('form'))"><i class="fas fa-redo mr-1"></i> Recount</button>
                                    </form>

                                    <form action="{{ route('inventory.stock-verification.reject', $o['id']) }}" method="POST" class="d-inline" onsubmit="return promptRemarks(this)">
                                        @csrf
                                        <input type="hidden" name="remarks" value="">
                                        <button type="button" class="btn btn-xs btn-danger" onclick="promptRemarks(this.closest('form'))"><i class="fas fa-times mr-1"></i> Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No pending observations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            <small class="text-muted">
                Page {{ $observations['current_page'] ?? 1 }} of {{ $observations['last_page'] ?? 1 }}
                ({{ $observations['total'] ?? count($observations['data']) }} total)
            </small>
            @if (($observations['current_page'] ?? 1) > 1)
                <a href="{{ request()->fullUrlWithQuery(['page' => $observations['current_page'] - 1]) }}" class="btn btn-sm btn-light">&laquo; Prev</a>
            @endif
            @if (($observations['current_page'] ?? 1) < ($observations['last_page'] ?? 1))
                <a href="{{ request()->fullUrlWithQuery(['page' => $observations['current_page'] + 1]) }}" class="btn btn-sm btn-light">Next &raquo;</a>
            @endif
        </div>
    </div>
@stop

@section('js')
    <script>
        function promptRemarks(form) {
            const remarks = prompt('Remarks (required):');
            if (!remarks) { return false; }
            form.querySelector('input[name="remarks"]').value = remarks;
            form.submit();
            return false;
        }
        function promptApprove(form, systemQty) {
            const approved = prompt('Approved target quantity (system qty is ' + systemQty + '):', systemQty);
            if (approved === null || approved === '') { return false; }
            form.querySelector('input[name="approved_qty"]').value = approved;
            form.submit();
            return false;
        }
    </script>
@stop
