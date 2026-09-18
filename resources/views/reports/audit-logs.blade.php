@extends('adminlte::page')

@section('title', 'Audit Activity Logs')

@section('content_header')
    <h1>Audit Activity Logs</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.audit-logs') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Reason, Entity, IP...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">User</label>
                    <select name="user_id" class="form-control form-control-sm">
                        <option value="">All Users</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" {{ request('user_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.audit-logs') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0">Audit Logs</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <button type="button" onclick="exportTableToCSV('auditLogsTable', 'audit-logs-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.audit-logs" table-id="auditLogsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="auditLogsTable">
                <thead class="thead-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Entity ID</th>
                        <th>Reason / Description</th>
                        <th>IP Address</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d-m-Y H:i:s') }}</td>
                            <td><strong>{{ $log->user?->name ?? 'System' }}</strong></td>
                            <td>
                                <span class="badge badge-{{ in_array($log->action, ['cancel', 'delete', 'destroy']) ? 'danger' : (in_array($log->action, ['approve', 'create']) ? 'success' : 'info') }}">
                                    {{ strtoupper($log->action) }}
                                </span>
                            </td>
                            <td><code>{{ class_basename($log->auditable_type) }}</code></td>
                            <td>{{ $log->auditable_id }}</td>
                            <td>{{ $log->reason ?: '-' }}</td>
                            <td><small class="text-muted">{{ $log->ip_address ?: '-' }}</small></td>
                            <td>
                                @if ($log->old_values || $log->new_values)
                                    <button type="button" class="btn btn-xs btn-outline-info" data-toggle="collapse" data-target="#diff-{{ $log->id }}">
                                        View Diff
                                    </button>
                                    <div id="diff-{{ $log->id }}" class="collapse mt-2">
                                        <pre class="bg-dark p-2 text-white small rounded mb-0" style="max-height: 150px; overflow-y: auto;">Old: {{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}
New: {{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No audit logs found in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $logs->total() }} audit records</span>
            {{ $logs->links() }}
        </div>
    </div>
@stop
