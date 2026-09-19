@extends('adminlte::page')

@section('title', 'Goods Receipt Notes (GRN)')

@section('content_header')
    <h1>Goods Receipt Notes (GRN)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('purchase.purchase-receipt-notes.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="GRN No / Challan / Supplier / PO..." value="{{ request('search') }}">
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
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $id => $name)
                            <option value="{{ $id }}" {{ request('supplier_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
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
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="{{ route('purchase.purchase-receipt-notes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Goods Receipt Notes</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('purchase.purchase-receipt-notes.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Receipt Note
                </a>
                <x-table-column-customizer table-key="purchase.receipt-notes" table-id="receiptNotesTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0" id="receiptNotesTable">
                <thead class="thead-light">
                    <tr>
                        <th>GRN No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>Ref PO</th>
                        <th>Challan No</th>
                        <th class="text-right">Accepted Qty</th>
                        <th class="text-right">Total (₹)</th>
                        <th>Status</th>
                        <th class="text-right" style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receiptNotes as $rn)
                        <tr>
                            <td>
                                <a href="{{ route('purchase.purchase-receipt-notes.show', $rn) }}" class="font-weight-bold text-primary">
                                    {{ $rn->receipt_number }}
                                </a>
                            </td>
                            <td>{{ $rn->receipt_date->format('d-m-Y') }}</td>
                            <td>{{ $rn->supplier?->name }}</td>
                            <td>{{ $rn->branch?->name }}</td>
                            <td>
                                @if ($rn->purchaseOrder)
                                    <span class="badge badge-light border">{{ $rn->purchaseOrder->po_number }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $rn->supplier_challan_no ?: '—' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($rn->total_accepted_qty, 2) }}</td>
                            <td class="text-right font-weight-bold">₹{{ number_format($rn->total_amount, 2) }}</td>
                            <td>
                                @if ($rn->status === 'Received')
                                    <span class="badge badge-success"><i class="fas fa-box mr-1"></i> Received</span>
                                @elseif ($rn->status === 'Invoiced')
                                    <span class="badge badge-primary"><i class="fas fa-file-invoice mr-1"></i> Invoiced</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Cancelled</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('purchase.purchase-receipt-notes.show', $rn) }}" class="btn btn-xs btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('purchase.purchase-receipt-notes.print', $rn) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Slip">
                                    <i class="fas fa-print"></i>
                                </a>

                                @if ($rn->status === 'Received')
                                    <a href="{{ route('purchase.purchase-invoices.create', ['from_receipt_note' => $rn->id]) }}" class="btn btn-xs btn-success font-weight-bold" title="Generate Purchase Invoice">
                                        <i class="fas fa-file-invoice-dollar"></i> Bill
                                    </a>
                                    <form action="{{ route('purchase.purchase-receipt-notes.destroy', $rn) }}" method="POST" class="d-inline grn-cancel-form">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" class="grn-cancel-reason">
                                        <button type="button" class="btn btn-xs btn-outline-danger grn-cancel-btn" title="Cancel & Reverse Stock">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                No goods receipt notes found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($receiptNotes->hasPages())
            <div class="card-footer py-2">
                {{ $receiptNotes->links() }}
            </div>
        @endif
    </div>

    @push('js')
    <script>
        document.querySelectorAll('.grn-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const reason = prompt('Reason for cancelling this Goods Receipt Note (Inventory will be reversed):');
                if (reason === null || reason.trim() === '') {
                    return;
                }
                const form = btn.closest('.grn-cancel-form');
                form.querySelector('.grn-cancel-reason').value = reason.trim();
                form.submit();
            });
        });
    </script>
    @endpush
@stop
