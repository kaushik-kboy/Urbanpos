@extends('adminlte::page')

@section('title', 'Till Sessions')

@section('content_header')
    <h1>Till Sessions</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('till.sessions.index') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Register</label>
                    <select name="register_id" class="form-control form-control-sm">
                        <option value="">All Registers</option>
                        @foreach ($registers as $reg)
                            <option value="{{ $reg->id }}" {{ request('register_id') == $reg->id ? 'selected' : '' }}>
                                {{ $reg->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">User</label>
                    <select name="user_id" class="form-control form-control-sm">
                        <option value="">All Users</option>
                        @foreach ($users as $usr)
                            <option value="{{ $usr->id }}" {{ request('user_id') == $usr->id ? 'selected' : '' }}>
                                {{ $usr->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="Open" {{ request('status') === 'Open' ? 'selected' : '' }}>Open</option>
                        <option value="Closed" {{ request('status') === 'Closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="{{ route('till.sessions.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-cash-register mr-1"></i> Till Sessions</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('till.sessions.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-door-open"></i> Open Till
                </a>
                <x-table-column-customizer table-key="till.sessions" table-id="tillSessionsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0" id="tillSessionsTable">
                <thead>
                    <tr>
                        <th>Register</th>
                        <th>Branch</th>
                        <th>Opened By</th>
                        <th>Opened At</th>
                        <th>Status</th>
                        <th>Variance</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tillSessions as $session)
                        <tr>
                            <td>{{ $session->register->name }}</td>
                            <td>{{ $session->branch->name }}</td>
                            <td>{{ $session->user->name }}</td>
                            <td>{{ $session->opened_at->format('d-m-Y H:i') }}</td>
                            <td>
                                @if ($session->isOpen())
                                    <span class="badge badge-success">Open</span>
                                @else
                                    <span class="badge badge-secondary">Closed</span>
                                @endif
                            </td>
                            <td>
                                @if (! is_null($session->variance))
                                    <span class="{{ (float) $session->variance == 0 ? 'text-muted' : ((float) $session->variance < 0 ? 'text-danger' : 'text-success') }}">
                                        {{ number_format($session->variance, 2) }}
                                    </span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('till.sessions.show', $session) }}" class="btn btn-xs btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No till sessions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $tillSessions->links() }}
        </div>
    </div>
@stop
