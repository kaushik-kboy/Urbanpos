@extends('adminlte::page')

@section('title', 'Loyalty Programs')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-0"><i class="fas fa-award text-warning mr-2"></i>Loyalty Program Master</h1>
            <small class="text-muted">Configure customer reward rules, earning slabs, and redemption rates</small>
        </div>
        <div>
            <a href="{{ route('master.loyalty-points.index') }}" class="btn btn-outline-primary mr-2">
                <i class="fas fa-coins mr-1"></i>Points Update
            </a>
            <a href="{{ route('master.loyalty-programs.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle mr-1"></i>New Loyalty Program
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-light py-2">
            <form method="GET" action="{{ route('master.loyalty-programs.index') }}" class="form-inline">
                <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Search program name…" value="{{ request('search') }}">
                <select name="status" class="form-control form-control-sm mr-2">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-sm btn-default"><i class="fas fa-times mr-1"></i>Reset</a>
            </form>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-dark text-white">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Program Name</th>
                        <th>Validity Period</th>
                        <th>Calculation Basis</th>
                        <th class="text-right">Points / ₹100</th>
                        <th class="text-right">Min Redeem Pts</th>
                        <th class="text-right">Point Value (₹)</th>
                        <th class="text-center">Custom Slabs</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($programs as $index => $program)
                        <tr>
                            <td>{{ $programs->firstItem() + $index }}</td>
                            <td>
                                <strong class="text-primary">{{ $program->name }}</strong>
                                @if($program->roundoff)
                                    <span class="badge badge-light border text-muted ml-1" title="Rounded to whole points">Rounded</span>
                                @endif
                            </td>
                            <td>
                                <small class="d-block text-dark font-weight-bold">{{ $program->start_date->format('d M Y') }}</small>
                                <small class="text-muted">to {{ $program->end_date ? $program->end_date->format('d M Y') : 'Ongoing (No expiry)' }}</small>
                            </td>
                            <td><span class="badge badge-info">{{ $program->based_on }}</span></td>
                            <td class="text-right font-weight-bold">{{ number_format($program->points_per_hundred, 2) }} pts</td>
                            <td class="text-right">{{ $program->min_points_redeem }} pts</td>
                            <td class="text-right font-weight-bold text-success">₹{{ number_format($program->amount_per_point, 2) }}</td>
                            <td class="text-center">
                                @if($program->rules->count() > 0)
                                    <span class="badge badge-primary">{{ $program->rules->count() }} slabs</span>
                                @else
                                    <span class="badge badge-secondary">Default %</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($program->status)
                                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('master.loyalty-programs.edit', $program) }}" class="btn btn-xs btn-outline-primary mr-1" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                @if($program->status)
                                    <form action="{{ route('master.loyalty-programs.destroy', $program) }}" method="POST" class="d-inline" onsubmit="return confirm('Deactivate this program?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Deactivate">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-award fa-2x mb-2 d-block text-gray"></i>
                                No loyalty programs configured yet. Click <strong>New Loyalty Program</strong> to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($programs->hasPages())
            <div class="card-footer py-2">
                {{ $programs->links() }}
            </div>
        @endif
    </div>
@stop
