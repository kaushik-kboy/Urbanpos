@extends('adminlte::page')

@section('title', 'Purchase Returns')

@section('content_header')
    <h1>Purchase Returns</h1>
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
            <form method="GET" action="{{ route('purchase.purchase-returns.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Return No / Invoice No / Debit Note / Supplier" value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected(request('branch_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $id => $name)
                            <option value="{{ $id }}" @selected(request('supplier_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block" title="Apply Filter">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-list mr-1 text-primary"></i> Purchase Returns List
            </h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('purchase.purchase-returns.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Purchase Return
                </a>
                <x-table-column-customizer table-key="purchase.purchase-returns" table-id="purchaseReturnsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="purchaseReturnsTable">
                <thead class="bg-light">
                    <tr>
                        <th>Return No</th>
                        <th>Return Date</th>
                        <th>Supplier</th>
                        <th>Ref Invoice</th>
                        <th>Debit Note No</th>
                        <th>Branch</th>
                        <th class="text-right">Tax (₹)</th>
                        <th class="text-right">Total (₹)</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseReturns as $return)
                        <tr>
                            <td class="font-weight-bold">
                                <a href="{{ route('purchase.purchase-returns.show', $return) }}">{{ $return->return_number }}</a>
                            </td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>{{ $return->supplier?->name }}</td>
                            <td>{{ $return->purchaseInvoice?->invoice_number ?? '—' }}</td>
                            <td>{{ $return->supplier_debit_note_no ?? '—' }}</td>
                            <td>{{ $return->branch?->name }}</td>
                            <td class="text-right text-muted">₹{{ number_format($return->total_gst, 2) }}</td>
                            <td class="text-right font-weight-bold text-success">₹{{ number_format($return->total, 2) }}</td>
                            <td>
                                @if ($return->status === 'Posted')
                                    <span class="badge badge-success">Posted</span>
                                @elseif ($return->status === 'Cancelled')
                                    <span class="badge badge-danger">Cancelled</span>
                                @else
                                    <span class="badge badge-secondary">{{ $return->status }}</span>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('purchase.purchase-returns.show', $return) }}" class="btn btn-xs btn-outline-info mr-1" title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="{{ route('purchase.purchase-returns.print', $return) }}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Print Return Note">
                                    <i class="fas fa-print"></i> Print
                                </a>
                                @if ($return->status !== 'Cancelled')
                                    <form action="{{ route('purchase.purchase-returns.destroy', $return) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this purchase return? Stock will be restored and accounting journal reversed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Cancel & Reverse Stock">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-2 d-block text-gray"></i>
                                No purchase returns found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchaseReturns->hasPages())
            <div class="card-footer py-2">
                {{ $purchaseReturns->links() }}
            </div>
        @endif
    </div>
@stop
