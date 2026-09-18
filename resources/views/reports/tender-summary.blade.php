@extends('adminlte::page')

@section('title', 'Tender / Payment Mode Summary')

@section('content_header')
    <h1>Tender / Payment Mode Summary</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.tender-summary') }}" class="row align-items-end">
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
                    <a href="{{ route('reports.tender-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6 col-12">
            <div class="info-box bg-gradient-success">
                <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Collections</span>
                    <span class="info-box-number" style="font-size: 1.6rem;">₹{{ number_format($totalCollected, 2) }}</span>
                    <span class="progress-description">Across {{ $rows->sum('count') }} tender transactions</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0">Tender Collections</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <x-table-column-customizer table-key="reports.tender-summary" table-id="tenderSummaryTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="tenderSummaryTable">
                <thead class="thead-light">
                    <tr>
                        <th>Tender Mode / Name</th>
                        <th>Type Category</th>
                        <th class="text-right">Transaction Count</th>
                        <th class="text-right">Total Amount (₹)</th>
                        <th class="text-right">% of Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $pct = $totalCollected > 0 ? ($row->total_amount / $totalCollected) * 100 : 0;
                        @endphp
                        <tr>
                            <td><strong>{{ $row->tender_name }}</strong></td>
                            <td><span class="badge badge-info">{{ $row->type }}</span></td>
                            <td class="text-right">{{ number_format($row->count) }}</td>
                            <td class="text-right font-weight-bold text-success">{{ number_format($row->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No tender payments recorded in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="2">Net Total</td>
                            <td class="text-right">{{ number_format($rows->sum('count')) }}</td>
                            <td class="text-right text-success" style="font-size: 1.1rem;">₹{{ number_format($totalCollected, 2) }}</td>
                            <td class="text-right">100.0%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
