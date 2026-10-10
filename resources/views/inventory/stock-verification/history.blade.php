@extends('adminlte::page')

@section('title', 'Stock & Expiry Verification — History & Audit')

@section('content_header')
    <h1><i class="fas fa-history text-primary mr-2"></i>Stock & Expiry Verification — History & Audit</h1>
@stop

@section('content')
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

                <label class="mr-2 font-weight-bold">Status:</label>
                <select name="status" class="form-control form-control-sm mr-3">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="recount_requested" @selected(request('status') === 'recount_requested')>Recount Requested</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>

                <label class="mr-2 font-weight-bold">From:</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm mr-3">
                <label class="mr-2 font-weight-bold">To:</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm mr-3">

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
                            <th>Physical Qty</th>
                            <th>Variance</th>
                            <th>Status</th>
                            <th>Submitted By</th>
                            <th>Reviewed By</th>
                            <th>Submitted At</th>
                            <th class="text-right">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($observations['data'] as $o)
                            <tr>
                                <td>{{ $o['id'] }}</td>
                                <td>{{ $o['type'] === 'stock_count' ? 'Stock Count' : 'Expiry Check' }}</td>
                                <td>{{ $o['item_name'] }} <small class="text-muted">({{ $o['item_code'] }})</small></td>
                                <td>{{ $o['branch_name'] }}</td>
                                <td>{{ $o['physical_qty'] ?? '-' }}</td>
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
                                <td>
                                    @php $badgeMap = ['pending' => 'warning', 'approved' => 'success', 'recount_requested' => 'info', 'rejected' => 'danger']; @endphp
                                    <span class="badge badge-{{ $badgeMap[$o['status']] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $o['status'])) }}</span>
                                </td>
                                <td>{{ $o['submitted_by_name'] }}</td>
                                <td>{{ $o['reviewed_by_name'] ?? '-' }}</td>
                                <td><small>{{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('d-m-Y H:i') }}</small></td>
                                <td class="text-right">
                                    <a href="{{ route('inventory.stock-verification.show', $o['id']) }}" class="btn btn-xs btn-outline-info">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No observations match these filters.</td></tr>
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
