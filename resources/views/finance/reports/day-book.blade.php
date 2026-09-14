@extends('adminlte::page')

@section('title', 'Day Book')

@section('content_header')
    <h1>Day Book</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" action="{{ route('finance.reports.day-book') }}" class="row align-items-end mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Voucher No, Ledger, Narration...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Location</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Voucher Type</label>
                    <select name="voucher_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($voucherTypes as $vt)
                            <option value="{{ $vt }}" {{ request('voucher_type') == $vt ? 'selected' : '' }}>{{ $vt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('finance.reports.day-book') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>

            @forelse ($entries as $entry)
                <div class="mb-3 border rounded p-2">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $entry->voucher_date->format('d-m-Y') }} — {{ $entry->voucher_number }} ({{ $entry->voucher_type }})</strong>
                        <span>{{ $entry->branch?->name }}</span>
                    </div>
                    <table class="table table-sm mb-1 mt-2">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entry->lines as $line)
                                <tr>
                                    <td>{{ $line->ledger?->name }}</td>
                                    <td class="text-right">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                    <td class="text-right">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($entry->narration)
                        <div class="text-muted small">{{ $entry->narration }}</div>
                    @endif
                </div>
            @empty
                <p class="text-center text-muted py-3">No transactions in this period.</p>
            @endforelse
        </div>
    </div>
@stop
