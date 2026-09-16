@extends('adminlte::page')

@section('title', 'Quotation & Order Summary')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-file-alt mr-2 text-info"></i> Quotation & Order Summary</h1>
        <a href="{{ route('reports.index', ['group' => 'sales']) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
    </div>
@stop

@section('content')
    {{-- Filters --}}
    <div class="card card-outline card-info mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('reports.quotation-order-summary') }}">
                <div class="row">
                    <div class="col-md-2">
                        <label class="small font-weight-bold">From</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">To</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                    </div>
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
                        <label class="small font-weight-bold">Type</label>
                        <select name="type" class="form-control form-control-sm">
                            <option value="">All</option>
                            <option value="quotation" {{ $type === 'quotation' ? 'selected' : '' }}>Quotation</option>
                            <option value="order" {{ $type === 'order' ? 'selected' : '' }}>Order</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Status</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small font-weight-bold">Customer</label>
                        <select name="customer_id" class="form-control form-control-sm">
                            <option value="">All Customers</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ $customerId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-right">
                        <button type="submit" class="btn btn-sm btn-info"><i class="fas fa-search mr-1"></i> Apply</button>
                        <a href="{{ route('reports.quotation-order-summary') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        <button type="button" id="csvExport" class="btn btn-sm btn-outline-secondary ml-2"><i class="fas fa-file-csv mr-1"></i> CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="row mb-3">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3>{{ $summary['total_quotations'] }}</h3><p>Quotations</p></div>
                <div class="icon"><i class="fas fa-file-invoice"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner"><h3>{{ $summary['total_orders'] }}</h3><p>Sales Orders</p></div>
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ $summary['open'] }}</h3><p>Open / Pending</p></div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner"><h3>{{ $summary['converted'] }}</h3><p>Converted to Bill</p></div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="card card-outline card-info">
        <div class="card-body p-0">
            <table class="table table-sm table-striped table-hover mb-0" id="qoTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Type</th>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Valid Until / Delivery</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th class="text-right">Items</th>
                        <th class="text-right">Total ₹</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <span class="badge {{ $row->type === 'Quotation' ? 'badge-info' : 'badge-primary' }}">
                                    {{ $row->type }}
                                </span>
                            </td>
                            <td><strong>{{ $row->number }}</strong></td>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                            <td>{{ $row->valid_until ? \Carbon\Carbon::parse($row->valid_until)->format('d-m-Y') : '—' }}</td>
                            <td>{{ $row->customer }}</td>
                            <td>{{ $row->branch }}</td>
                            <td class="text-right">{{ $row->items_count }}</td>
                            <td class="text-right">{{ number_format($row->total, 2) }}</td>
                            <td>
                                @php
                                    $badgeMap = ['Draft' => 'secondary', 'Confirmed' => 'primary', 'Converted' => 'success', 'Cancelled' => 'danger'];
                                    $badge = $badgeMap[$row->status] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $badge }}">{{ $row->status }}</span>
                            </td>
                            <td>
                                <a href="{{ $row->show_url }}" class="btn btn-xs btn-outline-secondary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if ($row->converted_bill_id)
                                    <a href="{{ route('sales.sales-bills.show', $row->converted_bill_id) }}" class="btn btn-xs btn-outline-success ml-1" title="View Bill">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No quotations or orders found.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="7">Total ({{ $rows->count() }} records)</td>
                        <td class="text-right">{{ number_format($rows->sum('total'), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
document.getElementById('csvExport')?.addEventListener('click', function () {
    const rows = [['Type','Number','Date','Valid Until','Customer','Branch','Items','Total','Status']];
    document.querySelectorAll('#qoTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 9) return;
        rows.push([
            cells[0].innerText.trim(), cells[1].innerText.trim(), cells[2].innerText.trim(),
            cells[3].innerText.trim(), cells[4].innerText.trim(), cells[5].innerText.trim(),
            cells[6].innerText.trim(), cells[7].innerText.trim(), cells[8].innerText.trim()
        ]);
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'quotation-order-summary-{{ $from }}-{{ $to }}.csv'; a.click();
});
</script>
@stop
