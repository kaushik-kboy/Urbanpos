@extends('adminlte::page')

@section('title', $title ?? 'Report')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 font-weight-bold">
                <i class="fas fa-chart-bar text-primary mr-2"></i> {{ $title ?? 'Report' }}
            </h1>
            <ol class="breadcrumb mt-1 bg-transparent p-0 small">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports Center</a></li>
                <li class="breadcrumb-item active">{{ $title ?? 'Report' }}</li>
            </ol>
        </div>
        <div>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Reports Center
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm ml-1" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print Report
            </button>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ url()->current() }}" class="form-row align-items-end">
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">From Date</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $from ?? now()->startOfMonth()->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">To Date</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $to ?? now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1">Branch / Location</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected(($branchId ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill mr-1">
                        <i class="fas fa-filter mr-1"></i> Apply Filter
                    </button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-table mr-1 text-info"></i> {{ $title ?? 'Report' }} Data
            </h6>
            <div class="card-tools">
                <button type="button" class="btn btn-xs btn-outline-success mr-1" onclick="alert('Export to Excel initiated.')">
                    <i class="fas fa-file-excel mr-1"></i> Excel
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="alert('Export to PDF initiated.')">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead class="bg-white">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Date / Ref No</th>
                            <th>Item / Particulars</th>
                            <th>Category / Brand</th>
                            <th>Branch / Location</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Rate (₹)</th>
                            <th class="text-right">Total Amount (₹)</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-file-invoice fa-3x mb-3 text-secondary d-block opacity-50"></i>
                                <h5 class="font-weight-bold">No records found for the selected period</h5>
                                <p class="small text-muted mb-2">There are currently no transactions matching your selected date range and branch criteria.</p>
                                <span class="badge badge-light border px-2 py-1">Period: {{ $from ?? now()->startOfMonth()->format('d M Y') }} - {{ $to ?? now()->format('d M Y') }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted">Showing 0 of 0 entries</span>
            <span class="small text-muted">UrbanPOS Analytics Engine &bull; Auto-synchronized</span>
        </div>
    </div>
@stop
