@extends('adminlte::page')

@section('title', $title ?? 'Module')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <i class="{{ $icon ?? 'fas fa-layer-group' }} text-primary mr-2"></i>
            {{ $title ?? 'Module' }}
        </h1>
        <div>
            @if (!empty($newButton))
                <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="alert('{{ $newButton }} form')">
                    <i class="fas fa-plus mr-1"></i> {{ $newButton }}
                </button>
            @endif
        </div>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div class="form-inline">
                <label class="mr-2 font-weight-bold">Location:</label>
                <select class="form-control form-control-sm mr-3">
                    <option value="2">URBANPETS SERVICES PRIVATE LIMITED (HO)</option>
                    <option value="3">URBAN PETS / MOTERA (Branch)</option>
                </select>

                <input type="text" class="form-control form-control-sm mr-2" placeholder="Search...">
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
            <div class="card-tools">
                <span class="badge badge-info">{{ $section ?? 'TruePOS' }}</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-white">
                        <tr>
                            <th style="width: 60px;">#</th>
                            @foreach ($columns ?? ['Code / Ref No', 'Date', 'Description', 'Location', 'Status', 'Total'] as $col)
                                <th>{{ $col }}</th>
                            @endforeach
                            <th class="text-right" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="{{ count($columns ?? ['Code / Ref No', 'Date', 'Description', 'Location', 'Status', 'Total']) + 2 }}" class="text-center text-muted py-5">
                                <i class="{{ $icon ?? 'fas fa-folder-open' }} fa-3x mb-3 text-muted d-block opacity-50"></i>
                                <h5>No {{ $title ?? 'records' }} found for this location.</h5>
                                <p class="small text-muted mb-3">Click below to create a new entry or adjust your search filters.</p>
                                @if (!empty($newButton))
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="alert('{{ $newButton }}')">
                                        <i class="fas fa-plus mr-1"></i> {{ $newButton }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing 0 of 0 entries</small>
            <div>
                <button type="button" class="btn btn-xs btn-outline-secondary mr-1" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="alert('Export to Excel')">
                    <i class="fas fa-file-excel mr-1"></i> Export
                </button>
            </div>
        </div>
    </div>
@stop
