@extends('adminlte::page')

@section('title', 'Ledger Master')

@section('content_header')
    <h1>Ledger Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.ledgers.index') }}" class="row align-items-end">
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Ledger Name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Ledger Group</label>
                    <select name="ledger_group" class="form-control form-control-sm">
                        <option value="">All Groups</option>
                        @foreach ($groups as $grp)
                            <option value="{{ $grp }}" {{ request('ledger_group') == $grp ? 'selected' : '' }}>{{ $grp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('finance.ledgers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('finance.ledgers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> New Ledger
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Ledger Name</th>
                        <th>Ledger Group</th>
                        <th class="text-right">Current Balance</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledgers as $ledger)
                        <tr>
                            <td>{{ $ledger->name }}</td>
                            <td>{{ $ledger->ledger_group }}</td>
                            <td class="text-right">
                                {{ number_format(abs($ledger->current_balance), 2) }}
                                {{ $ledger->current_balance >= 0 ? 'Dr' : 'Cr' }}
                            </td>
                            <td><x-status-badge :active="$ledger->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('finance.ledgers.edit', $ledger) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('finance.ledgers.destroy', $ledger) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this ledger?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No ledgers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $ledgers->links() }}</div>
    </div>
@stop
