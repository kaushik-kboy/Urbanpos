@extends('adminlte::page')

@section('title', 'Sales Item Margin Report')

@section('content_header')
    <h1>Sales Item Margin Report</h1>
@stop

@section('content')
    {{-- Filter Panel --}}
    <div class="card card-outline card-success mb-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filters</h3></div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.sales-margin-itemwise') }}" id="filterForm">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">From Date</label>
                            <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">To Date</label>
                            <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">Branch</label>
                            <select name="branch_id" class="form-control form-control-sm">
                                <option value="">All Branches</option>
                                @foreach ($branches as $id => $name)
                                    <option value="{{ $id }}" {{ $branchId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">Brand</label>
                            <select name="brand_id" class="form-control form-control-sm">
                                <option value="">All Brands</option>
                                @foreach ($brands as $id => $name)
                                    <option value="{{ $id }}" {{ $brandId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">Category</label>
                            <select name="category_value_id" class="form-control form-control-sm">
                                <option value="">All Categories</option>
                                @foreach ($categories as $id => $name)
                                    <option value="{{ $id }}" {{ $categoryValueId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-1">
                            <label class="small font-weight-bold">Customer</label>
                            <select name="customer_id" class="form-control form-control-sm">
                                <option value="">All Customers</option>
                                @foreach ($customers as $id => $name)
                                    <option value="{{ $id }}" {{ $customerId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search item name / code…" value="{{ $search }}">
                    </div>
                    <div class="col-md-8 text-right">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-search mr-1"></i> Apply</button>
                        <a href="{{ route('reports.sales-margin-itemwise') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        <button type="button" id="csvExport" class="btn btn-sm btn-outline-info ml-2">
                            <i class="fas fa-file-csv mr-1"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary ml-2 font-weight-bold" onclick="window.print()">
                            <i class="fas fa-print mr-1"></i> Print Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI Summary Cards --}}
    @if ($lines->isNotEmpty())
    <div class="row mb-3">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner"><h3>{{ number_format($totals['sell_total'], 2) }}</h3><p>Total Sales ₹</p></div>
                <div class="icon"><i class="fas fa-rupee-sign"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ number_format($totals['cog_total'], 2) }}</h3><p>Total COGS ₹</p></div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner"><h3>{{ number_format($totals['gross_margin'], 2) }}</h3><p>Gross Profit ₹</p></div>
                <div class="icon"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box {{ $totals['margin_pct'] >= 20 ? 'bg-success' : ($totals['margin_pct'] >= 10 ? 'bg-warning' : 'bg-danger') }}">
                <div class="inner"><h3>{{ number_format($totals['margin_pct'], 1) }}%</h3><p>Avg Gross Margin %</p></div>
                <div class="icon"><i class="fas fa-percentage"></i></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Data Table --}}
    <div class="card card-outline card-success">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-table mr-1"></i> Itemwise Detail
                <span class="badge badge-secondary ml-2">{{ $lines->count() }} lines</span>
            </h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <x-table-column-customizer table-key="reports.sales-margin-itemwise" table-id="marginTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0" id="marginTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Bill No</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Item</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Sell ₹/unit</th>
                            <th class="text-right">Cost ₹/unit</th>
                            <th class="text-right">Sell Total</th>
                            <th class="text-right">COGS</th>
                            <th class="text-right">Gross Profit</th>
                            <th class="text-right">Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lines as $line)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($line->bill_date)->format('d-m-Y') }}</td>
                                <td><small>{{ $line->bill_number }}</small></td>
                                <td>{{ $line->customer_name }}</td>
                                <td>{{ $line->branch_name }}</td>
                                <td>
                                    <strong>{{ $line->item_name }}</strong>
                                    @if ($line->item_code)<br><small class="text-muted">{{ $line->item_code }}</small>@endif
                                </td>
                                <td>{{ $line->brand_name }}</td>
                                <td>{{ $line->category_name }}</td>
                                <td class="text-right">{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format($line->sell_price, 2) }}</td>
                                <td class="text-right">{{ number_format($line->cost_at_sale, 2) }}</td>
                                <td class="text-right">{{ number_format($line->sell_total, 2) }}</td>
                                <td class="text-right">{{ number_format($line->cog_total, 2) }}</td>
                                <td class="text-right {{ $line->gross_margin < 0 ? 'text-danger' : 'text-success' }}">
                                    <strong>{{ number_format($line->gross_margin, 2) }}</strong>
                                </td>
                                <td class="text-right">
                                    @php $pct = $line->margin_pct; @endphp
                                    <span class="badge {{ $pct >= 20 ? 'badge-success' : ($pct >= 10 ? 'badge-warning' : 'badge-danger') }}">
                                        {{ number_format($pct, 1) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="text-center text-muted py-4">No sales data found for the selected period.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($lines->isNotEmpty())
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="10">NetTotal</td>
                            <td class="text-right">{{ number_format($totals['sell_total'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['cog_total'], 2) }}</td>
                            <td class="text-right {{ $totals['gross_margin'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($totals['gross_margin'], 2) }}
                            </td>
                            <td class="text-right">
                                <span class="badge {{ $totals['margin_pct'] >= 20 ? 'badge-success' : ($totals['margin_pct'] >= 10 ? 'badge-warning' : 'badge-danger') }}">
                                    {{ number_format($totals['margin_pct'], 1) }}%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
document.getElementById('csvExport')?.addEventListener('click', function () {
    const rows = [['Date','Bill No','Customer','Branch','Item','Item Code','Brand','Category','Qty','Sell Price','Cost at Sale','Sell Total','COGS','Gross Profit','Margin %']];
    document.querySelectorAll('#marginTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 14) return;
        rows.push([
            cells[0].innerText.trim(), cells[1].innerText.trim(), cells[2].innerText.trim(),
            cells[3].innerText.trim(), cells[4].innerText.trim().split('\n')[0],
            cells[4].innerText.trim().split('\n')[1] || '',
            cells[5].innerText.trim(), cells[6].innerText.trim(),
            cells[7].innerText.trim(), cells[8].innerText.trim(), cells[9].innerText.trim(),
            cells[10].innerText.trim(), cells[11].innerText.trim(),
            cells[12].innerText.trim(), cells[13].innerText.trim()
        ]);
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sales-margin-itemwise-{{ $from }}-{{ $to }}.csv';
    a.click();
});
</script>
@stop
