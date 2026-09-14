@extends('adminlte::page')

@section('title', 'Till Sessions')

@section('content_header')
    <h1>Till Sessions</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('till.sessions.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-door-open"></i> Open Till
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
