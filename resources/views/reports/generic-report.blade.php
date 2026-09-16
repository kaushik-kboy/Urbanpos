@extends('adminlte::page')

@section('title', $title ?? 'Report')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div class="mb-2 mb-md-0">
            <h1 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-chart-line text-primary mr-2"></i> {{ $title ?? 'Report' }}
            </h1>
            <p class="text-muted small mb-0 mt-1">
                {{ $subtitle ?? 'UrbanPOS SmartReport Analytics Engine &bull; Auto-synchronized with live store database' }}
            </p>
        </div>
        <div class="d-flex align-items-center">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm mr-2">
                <i class="fas fa-th-large mr-1"></i> Reports Center
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- KPI Cards if available --}}
    @if (!empty($kpis) && is_array($kpis))
        <div class="row mb-3">
            @foreach ($kpis as $kpi)
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-2">
                        <span class="info-box-icon bg-{{ $kpi['color'] ?? 'primary' }} elevation-1">
                            <i class="{{ $kpi['icon'] ?? 'fas fa-chart-bar' }}"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase font-weight-bold" style="font-size: 0.75rem;">
                                {{ $kpi['label'] }}
                            </span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.15rem;">
                                {{ $kpi['value'] }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ url()->current() }}" class="form-row align-items-end">
                @if ($hasDateFilter ?? true)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">From Date</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ $from ?? now()->subDays(60)->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">To Date</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ $to ?? now()->format('Y-m-d') }}">
                    </div>
                @endif

                @if ($hasBranchFilter ?? true)
                    <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Branch / Location</label>
                        <select name="branch_id" class="form-control form-control-sm">
                            <option value="">All Branches / Locations</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" @selected(($branchId ?? '') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="{{ ($hasDateFilter ?? true) ? 'col-md-3' : 'col-md-5' }} col-sm-6 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">Search Keywords</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, code, ref..." value="{{ $search ?? '' }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill mr-1 shadow-sm">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm shadow-sm" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Report Data Card --}}
    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-table mr-1 text-primary"></i> Data Records
                @if (isset($rows) && method_exists($rows, 'total'))
                    <span class="badge badge-light border ml-1 font-weight-normal">{{ number_format($rows->total()) }} total</span>
                @endif
            </h6>
            <div class="card-tools">
                <button type="button" class="btn btn-xs btn-outline-success mr-1 shadow-sm" onclick="window.print()">
                    <i class="fas fa-file-excel mr-1"></i> Print / Export
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0" id="reportDataTable">
                    <thead class="bg-light">
                        <tr>
                            @if (!empty($columns) && is_array($columns))
                                @foreach ($columns as $cIdx => $col)
                                    <th class="{{ $column_alignments[$cIdx] ?? 'text-left' }} py-2 text-uppercase text-secondary" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                        {{ $col }}
                                    </th>
                                @endforeach
                            @else
                                <th class="text-center">#</th>
                                <th>Particulars</th>
                                <th>Details</th>
                                <th class="text-right">Amount</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @if (isset($rows) && count($rows) > 0)
                            @foreach ($rows as $index => $row)
                                <tr>
                                    <td class="text-center font-weight-bold text-muted" style="width: 50px;">
                                        @if (method_exists($rows, 'firstItem'))
                                            {{ $rows->firstItem() + $index }}
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    @if (isset($row['cells']) && is_array($row['cells']))
                                        @foreach ($row['cells'] as $cIdx => $cell)
                                            <td class="{{ $column_alignments[$cIdx + 1] ?? 'text-left' }}">
                                                {!! $cell !!}
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ !empty($columns) ? count($columns) : 4 }}" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h5 class="font-weight-bold text-dark">No Records Found</h5>
                                    <p class="small text-muted mb-2">There are currently no transactions matching your selected criteria in this period.</p>
                                    @if ($hasDateFilter ?? true)
                                        <span class="badge badge-light border px-2 py-1">Period: {{ date('d M Y', strtotime($from ?? now())) }} - {{ date('d M Y', strtotime($to ?? now())) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-light py-2 d-flex flex-wrap justify-content-between align-items-center">
            <div class="small text-muted mb-2 mb-md-0">
                @if (isset($rows) && method_exists($rows, 'total'))
                    Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} entries
                @else
                    Report generated on {{ now()->format('d M Y, h:i A') }}
                @endif
            </div>
            <div>
                @if (isset($rows) && method_exists($rows, 'links'))
                    {{ $rows->links('pagination::bootstrap-4') }}
                @endif
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        @media print {
            .main-sidebar, .main-header, .card-header .card-tools, .card-body form, .card-footer, .breadcrumb, .btn {
                display: none !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
                background-color: white !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
@stop
