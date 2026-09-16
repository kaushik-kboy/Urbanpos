@extends('adminlte::page')

@section('title', 'Billwise Outstanding Aging Report')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 text-dark">
            <i class="fas fa-hourglass-half mr-2 text-warning"></i>Billwise Outstanding Aging Report
        </h1>
        <a href="{{ route('finance.reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Reports Center
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <h3 class="card-title text-muted text-sm"><i class="fas fa-filter mr-1"></i> Filter Aging Parameters</h3>
        </div>
        <div class="card-body py-2">
            <form method="GET" action="{{ route('finance.reports.outstanding-aging') }}" class="row align-items-end">
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Party Type</label>
                    <select name="party_type" class="form-control form-control-sm">
                        <option value="Customer" @selected($partyType === 'Customer')>Customers (Sundry Debtors - Receivables)</option>
                        <option value="Supplier" @selected($partyType === 'Supplier')>Suppliers (Sundry Creditors - Payables)</option>
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">As Of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}">
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm select2">
                        <option value="">All Branches</option>
                        @foreach($branches as $id => $name)
                            <option value="{{ $id }}" @selected($branchId == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i> Generate Report</button>
                    <button type="button" class="btn btn-outline-dark btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row">
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-primary"><i class="fas fa-coins"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total {{ $partyType === 'Customer' ? 'Receivable' : 'Payable' }}</span>
                    <span class="info-box-number text-lg font-weight-bold">₹{{ number_format($totals['total'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">0–30 Days (Current)</span>
                    <span class="info-box-number text-lg font-weight-bold text-success">₹{{ number_format($totals['b0_30'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning"><i class="fas fa-clock text-white"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">31–60 Days</span>
                    <span class="info-box-number text-lg font-weight-bold text-warning">₹{{ number_format($totals['b31_60'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">60+ Days Overdue</span>
                    <span class="info-box-number text-lg font-weight-bold text-danger">₹{{ number_format($totals['b61_90'] + $totals['b90_plus'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header py-2">
            <h5 class="card-title font-weight-bold mb-0">
                <i class="fas fa-table mr-1"></i>
                {{ $partyType === 'Customer' ? 'Customer Outstanding Debtors' : 'Supplier Outstanding Creditors' }} (As of {{ \Carbon\Carbon::parse($asOfDate)->format('d-M-Y') }})
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th>Party Name</th>
                            <th>Phone</th>
                            <th class="text-center">Pending Bills</th>
                            <th class="text-right text-success">0–30 Days</th>
                            <th class="text-right text-warning">31–60 Days</th>
                            <th class="text-right text-orange">61–90 Days</th>
                            <th class="text-right text-danger">> 90 Days</th>
                            <th class="text-right font-weight-bold text-primary">Total Balance Due</th>
                            <th class="text-center" style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $idx => $r)
                            <tr>
                                <td class="text-center font-weight-bold align-middle">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold align-middle">{{ $r['party_name'] }}</td>
                                <td class="align-middle text-muted">{{ $r['phone'] }}</td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-light border">{{ $r['bill_count'] }}</span>
                                </td>
                                <td class="text-right align-middle text-success">
                                    {{ $r['bucket_0_30'] > 0 ? '₹'.number_format($r['bucket_0_30'], 2) : '-' }}
                                </td>
                                <td class="text-right align-middle text-warning font-weight-bold">
                                    {{ $r['bucket_31_60'] > 0 ? '₹'.number_format($r['bucket_31_60'], 2) : '-' }}
                                </td>
                                <td class="text-right align-middle text-danger">
                                    {{ $r['bucket_61_90'] > 0 ? '₹'.number_format($r['bucket_61_90'], 2) : '-' }}
                                </td>
                                <td class="text-right align-middle text-danger font-weight-bold">
                                    {{ $r['bucket_90_plus'] > 0 ? '₹'.number_format($r['bucket_90_plus'], 2) : '-' }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-primary text-md">
                                    ₹{{ number_format($r['total_due'], 2) }}
                                </td>
                                <td class="text-center align-middle text-nowrap">
                                    <a href="{{ route('finance.settlements.create', ['type' => $partyType]) }}" class="btn btn-xs btn-success" title="Settle Credit">
                                        <i class="fas fa-hand-holding-usd mr-1"></i> Settle
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle text-success mr-1"></i> No outstanding credit balances found for {{ $partyType }}s!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows) > 0)
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="4" class="text-right align-middle">Totals:</td>
                                <td class="text-right align-middle text-success">₹{{ number_format($totals['b0_30'], 2) }}</td>
                                <td class="text-right align-middle text-warning">₹{{ number_format($totals['b31_60'], 2) }}</td>
                                <td class="text-right align-middle text-danger">₹{{ number_format($totals['b61_90'], 2) }}</td>
                                <td class="text-right align-middle text-danger">₹{{ number_format($totals['b90_plus'], 2) }}</td>
                                <td class="text-right align-middle text-primary text-lg font-weight-bold">₹{{ number_format($totals['total'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@stop
