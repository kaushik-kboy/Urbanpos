@extends('adminlte::page')

@section('title', 'Voucher Entry')

@section('content_header')
    <h1>Voucher Entry</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.vouchers.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Voucher No / Narration..." value="{{ request('search') }}">
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
                    <label class="small font-weight-bold mb-1">Type</label>
                    <select name="voucher_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($manualTypes as $type)
                            <option value="{{ $type }}" {{ request('voucher_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-receipt mr-1"></i> Vouchers</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('finance.vouchers.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> New Voucher
                </a>
                <x-table-column-customizer table-key="finance.vouchers" table-id="vouchersTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0" id="vouchersTable">
                <thead>
                    <tr>
                        <th>Voucher No</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Narration</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vouchers as $voucher)
                        <tr>
                            <td>{{ $voucher->voucher_number }}</td>
                            <td>{{ $voucher->voucher_type }}</td>
                            <td>{{ $voucher->voucher_date->format('d-m-Y') }}</td>
                            <td>{{ $voucher->branch?->name }}</td>
                            <td>{{ $voucher->narration }}</td>
                            <td class="text-right">{{ number_format($voucher->total_debit, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('finance.vouchers.edit', $voucher) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('finance.vouchers.destroy', $voucher) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No vouchers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $vouchers->links() }}</div>
    </div>
@stop
