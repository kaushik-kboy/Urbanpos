@extends('adminlte::page')

@section('title', 'Sales Margin by Category')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-chart-bar mr-2 text-primary"></i> Sales Margin by Category</h1>
        <a href="{{ route('reports.index', ['group' => 'sales']) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
    </div>
@stop

@section('content')
    {{-- Filter --}}
    <div class="card card-outline card-primary mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('reports.sales-margin-category') }}" class="form-inline">
                <label class="mr-2 small font-weight-bold">From</label>
                <input type="date" name="from" class="form-control form-control-sm mr-3" value="{{ $from }}">
                <label class="mr-2 small font-weight-bold">To</label>
                <input type="date" name="to" class="form-control form-control-sm mr-3" value="{{ $to }}">
                <select name="branch_id" class="form-control form-control-sm mr-3">
                    <option value="">All Branches</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" {{ $branchId == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary mr-2"><i class="fas fa-search mr-1"></i> Apply</button>
                <a href="{{ route('reports.sales-margin-category') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                <button type="button" id="csvExport" class="btn btn-sm btn-outline-info ml-2"><i class="fas fa-file-csv mr-1"></i> CSV</button>
                <button type="button" class="btn btn-sm btn-outline-primary ml-2 font-weight-bold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print Report</button>
            </form>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-3">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
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
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box {{ $totals['margin_pct'] >= 20 ? 'bg-success' : ($totals['margin_pct'] >= 10 ? 'bg-warning' : 'bg-danger') }}">
                <div class="inner"><h3>{{ number_format($totals['margin_pct'], 1) }}%</h3><p>Avg Margin %</p></div>
                <div class="icon"><i class="fas fa-percentage"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Bar Chart --}}
        <div class="col-lg-6 mb-3">
            <div class="card card-outline card-primary h-100">
                <div class="card-header"><h3 class="card-title">Top 10 Categories — Gross Profit</h3></div>
                <div class="card-body">
                    <canvas id="marginChart" height="300"></canvas>
                </div>
            </div>
        </div>

        {{-- Category Table --}}
        <div class="col-lg-6 mb-3">
            <div class="card card-outline card-primary h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Category Breakdown</h3>
                    <div class="card-tools ml-auto">
                        <x-table-column-customizer table-key="reports.sales-margin-categorywise" table-id="catTable" button-class="btn btn-sm btn-light border text-secondary" />
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0" id="catTable">
                        <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>Category</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Sales ₹</th>
                                <th class="text-right">COGS ₹</th>
                                <th class="text-right">Profit ₹</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grouped as $i => $cat)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $cat->category_name }}</td>
                                    <td class="text-right">{{ number_format($cat->qty) }}</td>
                                    <td class="text-right">{{ number_format($cat->sell_total, 2) }}</td>
                                    <td class="text-right">{{ number_format($cat->cog_total, 2) }}</td>
                                    <td class="text-right {{ $cat->gross_margin < 0 ? 'text-danger' : 'text-success' }}">
                                        <strong>{{ number_format($cat->gross_margin, 2) }}</strong>
                                    </td>
                                    <td class="text-right">
                                        @php $pct = $cat->margin_pct; @endphp
                                        <span class="badge {{ $pct >= 20 ? 'badge-success' : ($pct >= 10 ? 'badge-warning' : 'badge-danger') }}">
                                            {{ number_format($pct, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No data for selected period.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($grouped->isNotEmpty())
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="3">NetTotal</td>
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
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('marginChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartLabels->values()->toArray()) !!},
            datasets: [
                {
                    label: 'Gross Profit ₹',
                    data: {!! json_encode($chartMargin->values()->toArray()) !!},
                    backgroundColor: 'rgba(40,167,69,0.7)',
                    borderColor: 'rgba(40,167,69,1)',
                    borderWidth: 1,
                },
                {
                    label: 'Total Sales ₹',
                    data: {!! json_encode($chartSales->values()->toArray()) !!},
                    backgroundColor: 'rgba(0,123,255,0.4)',
                    borderColor: 'rgba(0,123,255,1)',
                    borderWidth: 1,
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

document.getElementById('csvExport')?.addEventListener('click', function () {
    const rows = [['#','Category','Qty','Sales','COGS','Gross Profit','Margin %']];
    document.querySelectorAll('#catTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 7) return;
        rows.push(Array.from(cells).map(c => c.innerText.trim()));
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sales-margin-category-{{ $from }}-{{ $to }}.csv';
    a.click();
});
</script>
@stop
