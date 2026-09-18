@extends('adminlte::page')

@section('title', 'Re-order / Low Stock Report')

@section('content_header')
    <h1>Re-order / Low Stock Report</h1>
@stop

@section('content')
    {{-- Filters --}}
    <div class="card card-outline card-danger mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('reports.reorder-report') }}">
                <div class="row">
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Branch</label>
                        <select name="branch_id" class="form-control form-control-sm">
                            <option value="">All Branches</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" {{ $branchId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Brand</label>
                        <select name="brand_id" class="form-control form-control-sm">
                            <option value="">All Brands</option>
                            @foreach ($brands as $id => $name)
                                <option value="{{ $id }}" {{ $brandId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Category</label>
                        <select name="category_value_id" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}" {{ $categoryValueId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Stock Status</label>
                        <select name="stock_status" class="form-control form-control-sm">
                            <option value="">All</option>
                            <option value="out" {{ $stockStatus === 'out' ? 'selected' : '' }}>Out of Stock (qty ≤ 0)</option>
                            <option value="low" {{ $stockStatus === 'low' ? 'selected' : '' }}>Low Stock (qty 1–{{ $threshold }})</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Low Stock Threshold</label>
                        <input type="number" name="threshold" class="form-control form-control-sm" value="{{ $threshold }}" min="1" max="100">
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Item name / code…">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-right">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-search mr-1"></i> Apply</button>
                        <a href="{{ route('reports.reorder-report') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        <button type="button" id="csvExport" class="btn btn-sm btn-outline-secondary ml-2"><i class="fas fa-file-csv mr-1"></i> CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-3">
        <div class="col-lg-4 col-6">
            <div class="small-box bg-danger">
                <div class="inner"><h3>{{ $kpi['out_of_stock'] }}</h3><p>Out of Stock SKUs</p></div>
                <div class="icon"><i class="fas fa-ban"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ $kpi['low_stock'] }}</h3><p>Low Stock SKUs (≤ {{ $threshold }})</p></div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-secondary">
                <div class="inner"><h3>{{ $kpi['total_skus'] }}</h3><p>Total Affected SKUs</p></div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="card card-outline card-danger">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-boxes mr-1"></i> Low Stock Items</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <button type="button" onclick="exportTableToCSV('reorderTable', 'reorder-stock-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.reorder-report" table-id="reorderTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped table-hover mb-0" id="reorderTable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th class="text-right">Current Qty</th>
                        <th class="text-right">Sell Price</th>
                        <th class="text-right">MRP</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        <tr class="{{ $row->quantity <= 0 ? 'table-danger' : 'table-warning' }}">
                            <td>{{ $i + 1 }}</td>
                            <td><small class="text-muted">{{ $row->item_code }}</small></td>
                            <td><strong>{{ $row->item_name }}</strong></td>
                            <td>{{ $row->brand_name }}</td>
                            <td>{{ $row->category_name }}</td>
                            <td>{{ $row->branch_name }}</td>
                            <td class="text-right">
                                <span class="badge {{ $row->quantity <= 0 ? 'badge-danger' : 'badge-warning' }} badge-lg">
                                    {{ $row->quantity }}
                                </span>
                            </td>
                            <td class="text-right">{{ number_format($row->sell_price, 2) }}</td>
                            <td class="text-right">{{ number_format($row->mrp, 2) }}</td>
                            <td><small>{{ $row->supplier_name ?? '—' }}</small></td>
                            <td>
                                <span class="badge {{ $row->status === 'Out of Stock' ? 'badge-danger' : 'badge-warning' }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('purchase.purchase-invoices.create') }}" class="btn btn-xs btn-outline-success" title="Create Purchase Invoice">
                                    <i class="fas fa-plus"></i> PO
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-5">
                            <i class="fas fa-check-circle text-success fa-3x mb-2 d-block"></i>
                            All items have adequate stock!
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
document.getElementById('csvExport')?.addEventListener('click', function () {
    const rows = [['#','Item Code','Item Name','Brand','Category','Branch','Current Qty','Sell Price','MRP','Supplier','Status']];
    document.querySelectorAll('#reorderTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 11) return;
        rows.push(Array.from(cells).slice(0, 11).map(c => c.innerText.trim()));
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'reorder-report.csv'; a.click();
});
</script>
@stop
