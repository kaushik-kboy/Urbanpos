@extends('adminlte::page')

@section('title', 'Sales Delivery Notes (Challans)')

@section('content_header')
    <h1>Sales Delivery Notes</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.delivery-notes.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Challan / Customer / SO / Vehicle / LR..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_from" class="form-control form-control-sm datepicker" value="{{ request('date_from') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_to" class="form-control form-control-sm datepicker" value="{{ request('date_to') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" {{ request('branch_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm">
                        <option value="">All Customers</option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}" {{ request('customer_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter mr-1"></i> Filter</button>
                    <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo mr-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Delivery Notes</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('sales.delivery-notes.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Delivery Note
                </a>
                <x-table-column-customizer table-key="sales.delivery-notes" table-id="deliveryNotesTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0" id="deliveryNotesTable">
                <thead class="thead-light">
                    <tr>
                        <th>Challan No</th>
                        <th>Dispatch Date</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Ref Order</th>
                        <th>Vehicle / Transporter</th>
                        <th class="text-right">Dispatched Qty</th>
                        <th class="text-right">Total (₹)</th>
                        <th>Status</th>
                        <th class="text-right" style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveryNotes as $dn)
                        <tr>
                            <td>
                                <a href="{{ route('sales.delivery-notes.show', $dn) }}" class="font-weight-bold text-primary">
                                    {{ $dn->delivery_number }}
                                </a>
                            </td>
                            <td>{{ $dn->delivery_date->format('d-m-Y') }}</td>
                            <td>{{ $dn->customer?->name }}</td>
                            <td>{{ $dn->branch?->name }}</td>
                            <td>
                                @if ($dn->salesOrder)
                                    <span class="badge badge-light border">{{ $dn->salesOrder->order_number }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($dn->vehicle_no || $dn->transporter_name)
                                    <span class="small font-weight-bold">{{ $dn->vehicle_no ?: '—' }}</span>
                                    @if ($dn->transporter_name)
                                        <span class="text-muted small d-block">({{ $dn->transporter_name }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right font-weight-bold">{{ number_format($dn->total_dispatched_qty, 2) }}</td>
                            <td class="text-right font-weight-bold text-success">₹{{ number_format($dn->total_amount, 2) }}</td>
                            <td>
                                @if ($dn->status === 'Dispatched')
                                    <span class="badge badge-warning text-dark"><i class="fas fa-shipping-fast mr-1"></i> Dispatched</span>
                                @elseif ($dn->status === 'Invoiced')
                                    <span class="badge badge-success"><i class="fas fa-file-invoice mr-1"></i> Invoiced</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Cancelled</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('sales.delivery-notes.show', $dn) }}" class="btn btn-xs btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('sales.delivery-notes.print', $dn) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Delivery Challan">
                                    <i class="fas fa-print"></i>
                                </a>

                                @if ($dn->status === 'Dispatched')
                                    <a href="{{ route('sales.sales-bills.create', ['from_delivery_note' => $dn->id]) }}" class="btn btn-xs btn-success font-weight-bold shadow-sm" title="Generate Sales Bill">
                                        <i class="fas fa-file-invoice-dollar"></i> Bill
                                    </a>
                                    <form action="{{ route('sales.delivery-notes.destroy', $dn) }}" method="POST" class="d-inline sdn-cancel-form">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" class="sdn-cancel-reason">
                                        <button type="button" class="btn btn-xs btn-outline-danger sdn-cancel-btn" title="Cancel & Restore Stock">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-truck-loading fa-2x mb-2 d-block"></i>
                                No Sales Delivery Notes found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($deliveryNotes->hasPages())
            <div class="card-footer py-2">
                {{ $deliveryNotes->links() }}
            </div>
        @endif
    </div>
@stop

@section('js')
<script>
$(function () {
    $('.sdn-cancel-btn').on('click', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var reason = prompt("Enter reason for cancelling this Delivery Note (stock will be restored to warehouse):");
        if (reason && reason.trim() !== '') {
            form.find('.sdn-cancel-reason').val(reason.trim());
            form.submit();
        }
    });
});
</script>
@stop
