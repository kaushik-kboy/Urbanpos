@extends('adminlte::page')

@section('title', 'Database Backups')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark">
                <i class="fas fa-database text-primary mr-2"></i>Database Backups
            </h1>
            <p class="text-muted small mb-0">Automated daily snapshots and on-demand database backup management.</p>
        </div>
        <div>
            <form action="{{ route('tools.backups.create') }}" method="POST" class="d-inline" onsubmit="return confirm('Generate a new database backup now?');">
                @csrf
                <button type="submit" class="btn btn-primary font-weight-bold shadow-sm">
                    <i class="fas fa-download mr-1"></i> Generate Backup Now
                </button>
            </form>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title font-weight-bold mb-0">
                <i class="fas fa-archive text-info mr-2"></i>Available Database Backups
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>Backup File Name</th>
                            <th>File Size</th>
                            <th>Created At</th>
                            <th class="text-right" style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $idx => $b)
                            <tr>
                                <td class="text-center font-weight-bold text-muted">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold text-dark">
                                    <i class="fas fa-file-archive text-warning mr-2"></i>{{ $b['filename'] }}
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1">{{ $b['size'] }}</span>
                                </td>
                                <td class="text-muted">{{ $b['date'] }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('tools.backups.download', $b['filename']) }}" class="btn btn-sm btn-success mr-1" title="Download Backup Archive">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                    <form action="{{ route('tools.backups.destroy', $b['filename']) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this backup?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Backup">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-database mb-3" style="font-size: 40px; color: #cbd5e1;"></i>
                                    <p class="font-weight-bold mb-1">No database backups found.</p>
                                    <p class="small text-muted mb-0">Click "Generate Backup Now" above to create your first database snapshot.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light text-muted small py-2">
            <i class="fas fa-shield-alt text-success mr-1"></i> Automatic retention policy: Backups older than 14 days are automatically cleaned up to save server disk space.
        </div>
    </div>
@stop
