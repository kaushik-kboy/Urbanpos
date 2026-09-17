@extends('adminlte::page')

@section('title', 'Damage / Wastage Stock Report')

@section('content_header')
    <h1>Damage / Wastage Stock Report</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.damage-stock-summary') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Entry Number...">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.damage-stock-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0">Damaged / Wastage Stocks</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="reports.damage-stock-summary" table-id="damageStockTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="damageStockTable">
                <thead class="thead-light">
                    <tr>
                        <th>Entry Number</th>
                        <th>Entry Date</th>
                        <th>Branch</th>
                        <th>Wastage Type</th>
                        <th class="text-right">Total Damaged Qty</th>
                        <th class="text-right">Total Loss Cost</th>
                        <th>Remarks</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($damageStocks as $ds)
                        <tr>
                            <td><strong>{{ $ds->damage_number }}</strong></td>
                            <td>{{ $ds->entry_date->format('d-m-Y') }}</td>
                            <td>{{ $ds->branch?->name }}</td>
                            <td>{{ ucfirst($ds->wastage_type ?: 'Damage') }}</td>
                            <td class="text-right font-weight-bold text-danger">{{ number_format($ds->total_qty, 2) }}</td>
                            <td class="text-right font-weight-bold text-danger">{{ number_format($ds->total_cost, 2) }}</td>
                            <td>{{ $ds->remarks ?: '-' }}</td>
                            <td class="text-center">
                                <span class="badge badge-{{ $ds->status === 'Posted' ? 'success' : 'secondary' }}">
                                    {{ $ds->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No damage stock entries in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $damageStocks->total() }} entries</span>
            {{ $damageStocks->links() }}
        </div>
    </div>
@stop
