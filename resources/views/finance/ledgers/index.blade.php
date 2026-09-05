@extends('adminlte::page')

@section('title', 'Ledger Master')

@section('content_header')
    <h1>Ledger Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

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
