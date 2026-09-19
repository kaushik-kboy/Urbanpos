@extends('adminlte::page')

@section('title', 'Credit Settlements')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 text-dark"><i class="fas fa-hand-holding-usd mr-2 text-success"></i>Billwise Credit Settlements</h1>
        <div>
            <a href="{{ route('finance.settlements.create', ['type' => 'Customer']) }}" class="btn btn-success btn-sm shadow-sm font-weight-bold mr-1">
                <i class="fas fa-plus mr-1"></i> Settle Customer Credit
            </a>
            <a href="{{ route('finance.settlements.create', ['type' => 'Supplier']) }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-plus mr-1"></i> Settle Supplier Payment
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <h3 class="card-title text-muted text-sm"><i class="fas fa-filter mr-1"></i> Filter Settlements</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body py-2">
            <form method="GET" action="{{ route('finance.settlements.index') }}" class="row align-items-end">
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Search Settlement / Party / Ref</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number, party, ref..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Settlement Type</label>
                    <select name="settlement_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        <option value="Customer" @selected(request('settlement_type') === 'Customer')>Customer (Receipt)</option>
                        <option value="Supplier" @selected(request('settlement_type') === 'Supplier')>Supplier (Payment)</option>
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Date From</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_from" class="form-control form-control-sm datepicker" value="{{ request('date_from') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Date To</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_to" class="form-control form-control-sm datepicker" value="{{ request('date_to') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-1 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="Active" @selected(request('status') === 'Active')>Active</option>
                        <option value="Cancelled" @selected(request('status') === 'Cancelled')>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i> Filter</button>
                    <a href="{{ route('finance.settlements.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Settlements History</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="finance.settlements" table-id="settlementsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="settlementsTable">
                <thead>
                    <tr>
                        <th>Settlement No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Party Name</th>
                        <th>Payment Mode</th>
                        <th>Ledger Account</th>
                        <th class="text-right">Amount Settled</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlements as $settlement)
                        <tr>
                            <td class="font-weight-bold">
                                <a href="{{ route('finance.settlements.show', $settlement) }}">{{ $settlement->settlement_number }}</a>
                            </td>
                            <td>{{ $settlement->settlement_date?->format('d-m-Y') }}</td>
                            <td>
                                @if($settlement->settlement_type === 'Customer')
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-arrow-down mr-1"></i> Customer Receipt</span>
                                @else
                                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-arrow-up mr-1"></i> Supplier Payment</span>
                                @endif
                            </td>
                            <td class="font-weight-bold">
                                {{ $settlement->settlement_type === 'Customer' ? $settlement->customer?->name : $settlement->supplier?->name }}
                            </td>
                            <td>
                                <span class="badge badge-light border">{{ $settlement->payment_mode }}</span>
                                @if($settlement->reference_no)
                                    <small class="text-muted d-block">Ref: {{ $settlement->reference_no }}</small>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $settlement->bankLedger?->name }}</td>
                            <td class="text-right font-weight-bold {{ $settlement->settlement_type === 'Customer' ? 'text-success' : 'text-primary' }}">
                                ₹{{ number_format($settlement->total_amount, 2) }}
                                @if((float)$settlement->discount_amount > 0)
                                    <small class="text-danger d-block">Disc: ₹{{ number_format($settlement->discount_amount, 2) }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $settlement->status === 'Active' ? 'badge-success' : 'badge-danger' }} px-2 py-1">
                                    {{ $settlement->status }}
                                </span>
                            </td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('finance.settlements.show', $settlement) }}" class="btn btn-xs btn-outline-info mr-1" title="View Voucher / Receipt">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                @if($settlement->status === 'Active')
                                    <form action="{{ route('finance.settlements.destroy', $settlement) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this settlement? Accounting voucher will be reversed.')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" value="User cancelled settlement">
                                        <button class="btn btn-xs btn-outline-danger" title="Cancel & Reverse Voucher"><i class="fas fa-ban"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No credit settlements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($settlements->hasPages())
            <div class="card-footer clearfix">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>
@stop
