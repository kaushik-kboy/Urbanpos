@extends('adminlte::page')

@section('title', 'Customer Loyalty Details Report')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-0"><i class="fas fa-gem text-primary mr-2"></i>Customer Loyalty Details Report</h1>
            <small class="text-muted">Comprehensive customer reward balances, points accrued, and redemption tracking</small>
        </div>
        <div>
            <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-outline-primary mr-2">
                <i class="fas fa-award mr-1"></i>Loyalty Programs
            </a>
            <a href="{{ route('master.loyalty-points.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="fas fa-coins mr-1"></i>Adjust Points
            </a>
            <button type="button" class="btn btn-success" onclick="exportTableToCSV('customer-loyalty-report.csv')">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- KPI Cards --}}
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm border">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Loyalty Customers</span>
                    <span class="info-box-number text-dark h4 mb-0">{{ number_format($totals['total_customers']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm border">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-arrow-up"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Points Earned</span>
                    <span class="info-box-number text-success h4 mb-0">{{ number_format($totals['total_earned_points'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm border">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-arrow-down"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Points Redeemed</span>
                    <span class="info-box-number text-warning h4 mb-0">{{ number_format($totals['total_redeemed_points'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm border">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Outstanding Points Balance</span>
                    <span class="info-box-number text-primary h4 mb-0">{{ number_format($totals['total_balance_points'], 2) }} <small style="font-size: 13px;">pts</small></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('finance.reports.customer-loyalty') }}" class="row align-items-center">
                <div class="col-md-4 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control" placeholder="Search customer name, phone or code…" value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach($branches as $bId => $bName)
                            <option value="{{ $bId }}" {{ $branchId == $bId ? 'selected' : '' }}>{{ $bName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                    <a href="{{ route('finance.reports.customer-loyalty') }}" class="btn btn-sm btn-default">
                        <i class="fas fa-times mr-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Table --}}
    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold mb-0">Customer Reward Balances</h5>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="finance.reports.customer-loyalty" table-id="loyalty-report-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped table-sm mb-0" id="loyalty-report-table">
                <thead class="bg-dark text-white">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Customer Name</th>
                        <th>Contact / Phone</th>
                        <th>Category</th>
                        <th class="text-center">Loyalty Status</th>
                        <th class="text-right">Total Earned (pts)</th>
                        <th class="text-right">Total Redeemed (pts)</th>
                        <th class="text-right">Current Balance (pts)</th>
                        <th>Last Activity</th>
                        <th class="text-center" style="width: 110px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td><strong class="text-primary">{{ $row['name'] }}</strong></td>
                            <td>{{ $row['phone'] ?: '-' }}</td>
                            <td><span class="badge badge-light border">{{ $row['category'] }}</span></td>
                            <td class="text-center">
                                @if($row['enable_loyalty'])
                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Active</span>
                                @else
                                    <span class="badge badge-secondary">Disabled</span>
                                @endif
                            </td>
                            <td class="text-right text-success font-weight-bold">{{ number_format($row['earned'], 2) }}</td>
                            <td class="text-right text-warning font-weight-bold">{{ number_format($row['redeemed'], 2) }}</td>
                            <td class="text-right font-weight-bold h6 mb-0 text-primary">{{ number_format($row['balance'], 2) }}</td>
                            <td><small class="text-muted">{{ $row['last_activity'] }}</small></td>
                            <td class="text-center">
                                <a href="{{ route('master.loyalty-points.index', ['customer_id' => $row['id']]) }}" class="btn btn-xs btn-outline-primary" title="Adjust Points">
                                    <i class="fas fa-coins mr-1"></i>Adjust
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-gem fa-2x mb-2 d-block text-gray"></i>
                                No customer loyalty records found matching the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@push('js')
<script>
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#loyalty-report-table tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length - 1; j++) { // exclude action column
            var text = cols[j].innerText.replace(/"/g, '""').trim();
            row.push('"' + text + '"');
        }
        csv.push(row.join(","));
    }

    var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
@endpush
