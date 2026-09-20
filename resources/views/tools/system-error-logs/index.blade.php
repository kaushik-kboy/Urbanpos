@extends('adminlte::page')

@section('title', 'System Error & Exception Logs')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-bug text-danger mr-2"></i> System Error & Exception Hub
            </h1>
            <p class="text-muted small mb-0">
                Track, inspect, and debug application errors, breaks, and exceptions module-wise & date-wise in real-time.
            </p>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="{{ route('tools.system-error-logs.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm mr-2 shadow-sm font-weight-bold">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm shadow-sm font-weight-bold" data-toggle="modal" data-target="#clearOldModal">
                <i class="fas fa-trash-alt mr-1"></i> Clean Old Logs
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Top Metrics KPI Cards --}}
    <div class="row">
        <div class="col-lg-3 col-6 mb-3">
            <div class="small-box bg-gradient-danger shadow-sm">
                <div class="inner">
                    <h3>{{ number_format($totalErrors) }}</h3>
                    <p class="font-weight-bold">Total Errors Logged</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6 mb-3">
            <div class="small-box bg-gradient-warning shadow-sm">
                <div class="inner">
                    <h3>{{ number_format($unresolvedCount) }}</h3>
                    <p class="font-weight-bold">Unresolved Exceptions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6 mb-3">
            <div class="small-box bg-gradient-info shadow-sm">
                <div class="inner">
                    <h3>{{ number_format($todayCount) }}</h3>
                    <p class="font-weight-bold">Errors Today</p>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6 mb-3">
            <div class="small-box bg-gradient-secondary shadow-sm">
                <div class="inner">
                    <h3>{{ count($moduleStats) }}</h3>
                    <p class="font-weight-bold">Affected Modules</p>
                </div>
                <div class="icon">
                    <i class="fas fa-cubes"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Module Breakdown Quick Filter Badges --}}
    @if($moduleStats->isNotEmpty())
        <div class="card card-outline card-secondary shadow-sm mb-3">
            <div class="card-header py-2">
                <h3 class="card-title text-sm font-weight-bold">
                    <i class="fas fa-layer-group text-primary mr-1"></i> Module Breakdown (Click to Filter)
                </h3>
            </div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <a href="{{ route('tools.system-error-logs.index', array_merge(request()->except('module', 'page'), ['module' => 'all'])) }}"
                       class="badge p-2 {{ empty($selectedModule) || $selectedModule === 'all' ? 'badge-dark shadow-sm' : 'badge-light border' }}">
                        All Modules ({{ $totalErrors }})
                    </a>
                    @foreach($moduleStats as $stat)
                        <a href="{{ route('tools.system-error-logs.index', array_merge(request()->except('module', 'page'), ['module' => $stat->module])) }}"
                           class="badge p-2 {{ $selectedModule === $stat->module ? 'badge-primary shadow-sm' : 'badge-light border' }}">
                            {{ $stat->module }}: <strong class="text-danger">{{ $stat->total }}</strong>
                            @if($stat->unresolved > 0)
                                <span class="badge badge-warning text-dark ml-1">{{ $stat->unresolved }} pending</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-header py-2">
            <h3 class="card-title text-sm font-weight-bold">
                <i class="fas fa-filter text-primary mr-1"></i> Filters & Search
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body py-3">
            <form action="{{ route('tools.system-error-logs.index') }}" method="GET" id="filterForm">
                <input type="hidden" name="date_preset" id="date_preset" value="{{ $datePreset }}">

                <div class="row align-items-end">
                    {{-- Module Filter --}}
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Module</label>
                        <select name="module" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="all" {{ empty($selectedModule) || $selectedModule === 'all' ? 'selected' : '' }}>-- All Modules --</option>
                            @foreach($availableModules as $mod)
                                <option value="{{ $mod }}" {{ $selectedModule === $mod ? 'selected' : '' }}>{{ $mod }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Status</label>
                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>-- All Statuses --</option>
                            <option value="Unresolved" {{ $selectedStatus === 'Unresolved' ? 'selected' : '' }}>Unresolved</option>
                            <option value="Resolved" {{ $selectedStatus === 'Resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="Ignored" {{ $selectedStatus === 'Ignored' ? 'selected' : '' }}>Ignored</option>
                        </select>
                    </div>

                    {{-- Date Presets --}}
                    <div class="col-md-4 col-sm-12 mb-2">
                        <label class="small font-weight-bold mb-1">Date Quick Preset</label>
                        <div class="btn-group btn-group-sm d-flex" role="group">
                            <button type="button" class="btn {{ $datePreset === 'today' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="applyDatePreset('today')">Today</button>
                            <button type="button" class="btn {{ $datePreset === 'yesterday' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="applyDatePreset('yesterday')">Yesterday</button>
                            <button type="button" class="btn {{ $datePreset === 'last_7_days' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="applyDatePreset('last_7_days')">Last 7 Days</button>
                            <button type="button" class="btn {{ $datePreset === 'this_month' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="applyDatePreset('this_month')">This Month</button>
                        </div>
                    </div>

                    {{-- Custom From/To Dates --}}
                    <div class="col-md-3 col-sm-12 mb-2">
                        <label class="small font-weight-bold mb-1">Custom Date Range</label>
                        <div class="input-group input-group-sm">
                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}" placeholder="From">
                            <div class="input-group-append input-group-prepend">
                                <span class="input-group-text">to</span>
                            </div>
                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}" placeholder="To">
                        </div>
                    </div>
                </div>

                <div class="row align-items-center mt-2">
                    <div class="col-md-9 col-sm-8 mb-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Search by error message, exception type, user name, URL, or file name...">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-4 mb-2 text-right">
                        <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold">
                            <i class="fas fa-filter mr-1"></i> Apply
                        </button>
                        <a href="{{ route('tools.system-error-logs.index') }}" class="btn btn-default btn-sm ml-1">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Error Logs Table --}}
    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title text-sm font-weight-bold mb-0">
                <i class="fas fa-list-alt mr-1"></i> Logged Errors ({{ $logs->total() }})
            </h3>
            <span class="badge badge-light border">Showing {{ $logs->firstItem() ?? 0 }} - {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</span>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0 text-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th style="width: 140px;">Module</th>
                        <th style="width: 160px;">Error Type</th>
                        <th>Error Message & Path</th>
                        <th style="width: 160px;">User & Branch</th>
                        <th style="width: 150px;">Date & Time</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 130px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $moduleColor = match($log->module) {
                                'SalesBill', 'Sales' => 'badge-info',
                                'PurchaseReceiptNote', 'Purchase' => 'badge-primary',
                                'Inventory' => 'badge-secondary',
                                'GST' => 'badge-warning text-dark',
                                'POSTerminal' => 'badge-dark',
                                'Master' => 'badge-success',
                                'Finance' => 'badge-teal',
                                default => 'badge-secondary'
                            };
                        @endphp
                        <tr>
                            <td><strong class="text-muted">#{{ $log->id }}</strong></td>
                            <td>
                                <span class="badge {{ $moduleColor }} font-weight-bold px-2 py-1">
                                    {{ $log->module }}
                                </span>
                                <div class="text-xs text-muted mt-1">
                                    <span class="badge badge-light border">{{ $log->method }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center flex-wrap">
                                    <span class="font-weight-bold text-danger mr-1">{{ $log->error_type ?? 'Exception' }}</span>
                                    @if(($log->occurrence_count ?? 1) > 1)
                                        <span class="badge badge-warning text-dark font-weight-bold shadow-xs" title="Occurred {{ $log->occurrence_count }} times">
                                            <i class="fas fa-fire text-danger mr-1"></i>x{{ $log->occurrence_count }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark text-break" style="max-height: 48px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    {{ $log->message }}
                                </div>
                                <div class="text-xs text-muted mt-1 text-truncate" style="max-width: 500px;" title="{{ $log->file }} : Line {{ $log->line }}">
                                    <i class="fas fa-file-code text-secondary mr-1"></i> {{ $log->file }} : <strong>L{{ $log->line }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">
                                    <i class="fas fa-user text-muted mr-1"></i> {{ $log->user_name ?? 'System / Guest' }}
                                </div>
                                <div class="text-xs text-muted">
                                    <i class="fas fa-store text-muted mr-1"></i> {{ $log->branch ? $log->branch->name : 'All Branches' }}
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $log->created_at ? $log->created_at->format('d M Y') : 'N/A' }}</div>
                                <div class="text-xs text-muted">{{ $log->created_at ? $log->created_at->format('h:i:s A') : '' }}</div>
                                @if(($log->occurrence_count ?? 1) > 1 && $log->last_seen_at)
                                    <div class="text-xs text-warning font-weight-bold mt-1" title="Most recent occurrence">
                                        <i class="fas fa-history mr-1"></i>Last: {{ $log->last_seen_at->format('d M, h:i A') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'Resolved')
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Resolved</span>
                                @elseif($log->status === 'Ignored')
                                    <span class="badge badge-secondary px-2 py-1">Ignored</span>
                                @else
                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i> Unresolved</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-info btn-xs shadow-sm" onclick="inspectError({{ $log->id }})" title="View Details">
                                    <i class="fas fa-eye"></i> Inspect
                                </button>
                                @if($log->status === 'Unresolved')
                                    <button type="button" class="btn btn-success btn-xs shadow-sm ml-1" onclick="resolveError({{ $log->id }})" title="Mark as Resolved">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="mb-2"><i class="fas fa-check-double fa-3x text-success"></i></div>
                                <h5 class="font-weight-bold mb-1">No System Errors Found!</h5>
                                <p class="small text-muted mb-0">Great job! There are no exceptions logged for the selected filter criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer py-2 d-flex justify-content-end">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Error Inspection Modal --}}
    <div class="modal fade" id="inspectModal" tabindex="-1" role="dialog" aria-labelledby="inspectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title font-weight-bold" id="inspectModalLabel">
                        <i class="fas fa-bug text-danger mr-2"></i> Error Inspection: <span id="modal-error-id"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    {{-- Nav Tabs --}}
                    <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="tab-overview-btn" data-toggle="tab" href="#tab-overview" role="tab">
                                <i class="fas fa-info-circle mr-1"></i> Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-payload-btn" data-toggle="tab" href="#tab-payload" role="tab">
                                <i class="fas fa-code mr-1"></i> Request Payload (Input)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-trace-btn" data-toggle="tab" href="#tab-trace" role="tab">
                                <i class="fas fa-terminal mr-1"></i> Stack Trace
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content p-3">
                        {{-- Tab 1: Overview --}}
                        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                            <div class="alert alert-danger mb-3">
                                <h6 class="font-weight-bold mb-1" id="modal-error-type"></h6>
                                <p class="mb-0 text-monospace text-sm" id="modal-error-message"></p>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <table class="table table-sm table-bordered mb-0">
                                        <tr>
                                            <th class="bg-light" style="width: 140px;">Module</th>
                                            <td><span id="modal-module" class="badge badge-primary px-2"></span></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">URL & Method</th>
                                            <td><span id="modal-method" class="badge badge-secondary mr-1"></span> <code id="modal-url" class="text-break"></code></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">File & Line</th>
                                            <td><code id="modal-file" class="text-break"></code> : <strong id="modal-line"></strong></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Timestamp</th>
                                            <td id="modal-timestamp"></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Occurrences</th>
                                            <td>
                                                <span id="modal-occurrences" class="badge badge-secondary px-2">1 occurrence</span>
                                                <span class="text-xs text-muted ml-2" id="modal-last-seen-text"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <table class="table table-sm table-bordered mb-0">
                                        <tr>
                                            <th class="bg-light" style="width: 140px;">User</th>
                                            <td id="modal-user"></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Branch</th>
                                            <td id="modal-branch"></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">IP & Agent</th>
                                            <td><span id="modal-ip"></span> <small class="text-muted d-block text-truncate" style="max-width: 300px;" id="modal-agent"></small></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light">Status</th>
                                            <td id="modal-status"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Tab 2: Request Payload --}}
                        <div class="tab-pane fade" id="tab-payload" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Sanitized form parameters submitted when the error occurred:</span>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="copyToClipboard('modal-payload-content')">
                                    <i class="fas fa-copy mr-1"></i> Copy JSON
                                </button>
                            </div>
                            <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height: 400px; overflow-y: auto; font-size: 12px;" id="modal-payload-content"></pre>
                        </div>

                        {{-- Tab 3: Stack Trace --}}
                        <div class="tab-pane fade" id="tab-trace" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Execution stack trace for technical diagnostics:</span>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="copyToClipboard('modal-trace-content')">
                                    <i class="fas fa-copy mr-1"></i> Copy Trace
                                </button>
                            </div>
                            <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height: 400px; overflow-y: auto; font-size: 11px; white-space: pre-wrap;" id="modal-trace-content"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <div id="modal-actions"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Clean Old Logs Modal --}}
    <div class="modal fade" id="clearOldModal" tabindex="-1" role="dialog" aria-labelledby="clearOldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form action="{{ route('tools.system-error-logs.clear-old') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-danger text-white py-2">
                        <h5 class="modal-title font-weight-bold" id="clearOldModalLabel">
                            <i class="fas fa-trash-alt mr-2"></i> Clean Resolved Error Logs
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="mb-3">Select the age of <strong>Resolved</strong> error records you would like to purge from the database to save space:</p>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Purge Resolved Logs Older Than:</label>
                            <select name="days" class="form-control">
                                <option value="7">7 Days</option>
                                <option value="15">15 Days</option>
                                <option value="30" selected>30 Days (Recommended)</option>
                                <option value="60">60 Days</option>
                                <option value="90">90 Days</option>
                            </select>
                        </div>
                        <small class="text-danger mt-2 d-block">Note: Unresolved errors will NOT be deleted.</small>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                            <i class="fas fa-trash-alt mr-1"></i> Confirm Clean
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    function applyDatePreset(preset) {
        document.getElementById('date_preset').value = preset;
        document.getElementById('from_date').value = '';
        document.getElementById('to_date').value = '';
        document.getElementById('filterForm').submit();
    }

    function inspectError(id) {
        // Show modal with loading state
        $('#modal-error-id').text('#' + id);
        $('#modal-error-type').text('Loading details...');
        $('#modal-error-message').text('');
        $('#modal-payload-content').text('Loading...');
        $('#modal-trace-content').text('Loading...');
        $('#inspectModal').modal('show');

        // Fetch details via JSON
        fetch("{{ url('tools/system-error-logs') }}/" + id, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.log) {
                const log = data.log;
                $('#modal-error-type').text(log.error_type || 'Exception');
                $('#modal-error-message').text(log.message || 'No message provided');
                $('#modal-module').text(log.module);
                $('#modal-method').text(log.method);
                $('#modal-url').text(log.url);
                $('#modal-file').text(log.file || 'Unknown');
                $('#modal-line').text(log.line || '-');
                $('#modal-timestamp').text(log.created_at);
                const count = log.occurrence_count || 1;
                $('#modal-occurrences')
                    .attr('class', count > 1 ? 'badge badge-warning text-dark font-weight-bold px-2' : 'badge badge-secondary px-2')
                    .text(count + ' occurrence' + (count > 1 ? 's' : ''));
                if (count > 1 && log.last_seen_at) {
                    $('#modal-last-seen-text').html('<i class="fas fa-history mr-1"></i>Last seen: <strong>' + log.last_seen_at + '</strong>');
                } else {
                    $('#modal-last-seen-text').text('');
                }
                $('#modal-user').text(log.user_name);
                $('#modal-branch').text(log.branch_name);
                $('#modal-ip').text(log.ip_address);
                $('#modal-agent').text(log.user_agent);

                // Status badge
                if (log.status === 'Resolved') {
                    $('#modal-status').html('<span class="badge badge-success">Resolved (' + (log.resolved_at || '') + ' by ' + (log.resolver || 'Admin') + ')</span>');
                    $('#modal-actions').html('');
                } else {
                    $('#modal-status').html('<span class="badge badge-danger">Unresolved</span>');
                    $('#modal-actions').html(`
                        <button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="resolveError(${log.id})">
                            <i class="fas fa-check mr-1"></i> Mark as Resolved
                        </button>
                    `);
                }

                // Payload & Trace
                $('#modal-payload-content').text(log.request_data || 'No request payload recorded (e.g. GET request or empty form).');
                $('#modal-trace-content').text(log.stack_trace || 'No stack trace available.');
            }
        })
        .catch(err => {
            $('#modal-error-message').text('Failed to load error details: ' + err);
        });
    }

    function resolveError(id) {
        if (!confirm('Mark error #' + id + ' as resolved?')) return;

        fetch("{{ url('tools/system-error-logs') }}/" + id + "/resolve", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Could not resolve error.');
            }
        })
        .catch(err => {
            alert('Error updating status: ' + err);
        });
    }

    function copyToClipboard(elementId) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Copied to clipboard!');
        });
    }
</script>
@stop
