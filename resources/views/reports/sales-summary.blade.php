@extends('adminlte::page')

@section('title', 'Daily Sales Summary')

@section('content_header')
    <h1>Daily Sales Summary [Store Wise]</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-chart-bar mr-1"></i> Summary</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="btn btn-sm btn-success mr-2 font-weight-bold shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                <button type="button" onclick="exportTableToCSV('sales-summary-table', 'sales-summary-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.sales-summary" table-id="sales-summary-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <label class="mr-2 font-weight-bold">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">
                <label class="mr-2 font-weight-bold">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">
                <label class="mr-2 font-weight-bold">Location</label>
                <select name="branch_id" class="form-control form-control-sm mr-3" {{ count($branches) <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
                    @if(count($branches) > 1)
                        <option value="all" {{ (!request('branch_id') || request('branch_id') === 'all') ? 'selected' : '' }}>All Locations</option>
                    @endif
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <label class="mr-2 font-weight-bold"><i class="fas fa-wallet mr-1 text-primary"></i> Payment Mode</label>
                <select name="payment_mode" class="form-control form-control-sm mr-3">
                    <option value="all" {{ (!request('payment_mode') || request('payment_mode') === 'all') ? 'selected' : '' }}>All Modes</option>
                    @if(isset($availablePaymentModes))
                        @foreach ($availablePaymentModes as $pm)
                            <option value="{{ $pm }}" @selected(request('payment_mode') === $pm)>{{ $pm }}</option>
                        @endforeach
                    @else
                        <option value="Cash" @selected(request('payment_mode') === 'Cash')>Cash</option>
                        <option value="Card" @selected(request('payment_mode') === 'Card')>Card</option>
                        <option value="UPI" @selected(request('payment_mode') === 'UPI')>UPI</option>
                        <option value="Split" @selected(request('payment_mode') === 'Split')>Split</option>
                    @endif
                </select>
                <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" name="export" value="excel" class="btn btn-success btn-sm font-weight-bold mr-1 shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
                <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print Report</button>
            </form>

            <table id="sales-summary-table" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Payment Mode</th>
                        <th class="text-right">Total Bills</th>
                        <th class="text-right">Bill Amount</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">GST</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->bill_date)->format('d-m-Y') }}</td>
                            <td>{{ $branches[$row->branch_id] ?? '' }}</td>
                            <td>
                                @if(!empty($row->payment_mode) && str_contains($row->payment_mode, 'Split'))
                                    <span class="badge badge-warning text-dark font-weight-bold px-2 py-1">
                                        <i class="fas fa-layer-group mr-1"></i> {{ $row->payment_mode }}
                                    </span>
                                @elseif(!empty($row->payment_mode) && $row->payment_mode === 'Cash')
                                    <span class="badge badge-success font-weight-bold px-2 py-1">
                                        <i class="fas fa-money-bill-wave mr-1"></i> Cash
                                    </span>
                                @elseif(!empty($row->payment_mode) && $row->payment_mode === 'Card')
                                    <span class="badge badge-info font-weight-bold px-2 py-1">
                                        <i class="fas fa-credit-card mr-1"></i> Card
                                    </span>
                                @elseif(!empty($row->payment_mode) && $row->payment_mode === 'UPI')
                                    <span class="badge badge-primary font-weight-bold px-2 py-1">
                                        <i class="fas fa-qrcode mr-1"></i> UPI
                                    </span>
                                @else
                                    <span class="badge badge-secondary font-weight-bold px-2 py-1">
                                        {{ $row->payment_mode ?? 'All' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ $row->bill_count }}</td>
                            <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($row->total_disc, 2) }}</td>
                            <td class="text-right">{{ number_format($row->total_gst, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="3">NetTotal</td>
                            <td class="text-right">{{ $rows->sum('bill_count') }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_amount'), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_disc'), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_gst'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
